<?php

final class SecurityRequestMonitor
{
    private ?PDO $pdo;
    private array $policies;
    private string $key;
    private int $retentionSeconds;
    private $failureReporter;
    private $alertReporter;
    private string $counterTable;
    private string $auditTable;

    public function __construct(?PDO $pdo = null, ?array $policies = null, ?string $key = null, ?callable $failureReporter = null, ?callable $alertReporter = null, array $tables = [])
    {
        $this->pdo = $pdo;
        $this->policies = self::validatePolicies($policies ?? self::configuredPolicies());
        $this->key = $key ?? APP_KEY;
        if ($this->key === '') {
            throw new InvalidArgumentException('Monitoring requires a secret bucket key.');
        }
        $this->retentionSeconds = (defined('SECURITY_MONITOR_RETENTION_HOURS') ? SECURITY_MONITOR_RETENTION_HOURS : 48) * 3600;
        $this->failureReporter = $failureReporter ?? static function (array $context): void {
            appLog('error', 'Security request monitoring unavailable', $context);
        };
        $this->alertReporter = $alertReporter ?? static function (array $context): void {
            appLog('warning', 'Security request threshold observed', $context);
        };
        $this->counterTable = $tables['counters'] ?? 'security_request_counters';
        $this->auditTable = $tables['audit'] ?? 'security_audit_log';
        foreach ([$this->counterTable, $this->auditTable] as $table) {
            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $table)) {
                throw new InvalidArgumentException('Invalid monitoring table identifier.');
            }
        }
    }

    public static function defaultPolicies(): array
    {
        // Candidate observation thresholds only. These are not blocking limits.
        return [
            'login' => ['window_seconds' => 900, 'subject' => 10, 'ip' => 500, 'global' => 5000],
            'mfa' => ['window_seconds' => 300, 'subject' => 5, 'ip' => 500, 'global' => 5000],
            'password_reset' => ['window_seconds' => 900, 'subject' => 3, 'ip' => 200, 'global' => 2000],
            'registration' => ['window_seconds' => 900, 'subject' => 3, 'ip' => 100, 'global' => 1000],
        ];
    }

    public static function configuredPolicies(): array
    {
        $configured = defined('SECURITY_MONITOR_POLICY_JSON') ? SECURITY_MONITOR_POLICY_JSON : '';
        if ($configured === '') {
            return self::defaultPolicies();
        }
        $policies = json_decode($configured, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($policies)) {
            throw new InvalidArgumentException('Monitoring policies must be an object.');
        }
        return self::validatePolicies($policies);
    }

    private static function validatePolicies(array $policies): array
    {
        if (array_diff(array_keys(self::defaultPolicies()), array_keys($policies))
            || array_diff(array_keys($policies), array_keys(self::defaultPolicies()))) {
            throw new InvalidArgumentException('Policies must cover exactly the four authentication actions.');
        }
        foreach ($policies as $policy) {
            if (!is_array($policy) || array_keys($policy) === []) {
                throw new InvalidArgumentException('Invalid monitoring policy.');
            }
            if (array_diff(array_keys($policy), ['window_seconds', 'subject', 'ip', 'global'])) {
                throw new InvalidArgumentException('Unknown policy field.');
            }
            foreach (['window_seconds', 'subject', 'ip', 'global'] as $field) {
                if (!isset($policy[$field]) || !is_int($policy[$field]) || $policy[$field] < 1
                    || $policy[$field] > ($field === 'window_seconds' ? 86400 : 1000000)) {
                    throw new InvalidArgumentException('Invalid policy threshold or window.');
                }
            }
        }
        return $policies;
    }

    public function observe(string $action, string $subject, array $client, ?int $targetUserId = null): array
    {
        if (!isset($this->policies[$action])) {
            throw new InvalidArgumentException('Unknown monitored action.');
        }
        $ownsTransaction = false;
        try {
            $pdo = $this->connection();
            if ($pdo->inTransaction()) {
                throw new RuntimeException('Monitoring must use a dedicated connection.');
            }
            $pdo->beginTransaction();
            $ownsTransaction = true;
            $now = (int) $pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
            $policy = $this->policies[$action];
            $requestId = function_exists('securityRequestId') ? securityRequestId() : bin2hex(random_bytes(12));
            $window = $policy['window_seconds'];
            $start = intdiv($now, $window) * $window;
            $subjects = [];
            if ($subject !== '') {
                $subjects['subject'] = strtolower(trim($subject));
            }
            $subjects['ip'] = $client['ip'];
            $subjects['global'] = 'all';
            $buckets = [];
            $upsert = $pdo->prepare('INSERT INTO ' . $this->counterTable . '
                (action, scope, bucket_hash, window_start, window_seconds, request_count, expires_at)
                VALUES (:action, :scope, :hash, :start, :window, 1, :expiry)
                ON DUPLICATE KEY UPDATE request_count = LEAST(request_count + 1, 4294967295)');
            $select = $pdo->prepare('SELECT request_count FROM ' . $this->counterTable . '
                WHERE action = :action AND scope = :scope AND bucket_hash = :hash
                AND window_start = :start AND window_seconds = :window FOR UPDATE');
            foreach ($subjects as $scope => $value) {
                $params = [
                    'action' => $action, 'scope' => $scope,
                    'hash' => hash_hmac('sha256', $action . '|' . $scope . '|' . $value, $this->key),
                    'start' => $start, 'window' => $window,
                ];
                $upsert->execute($params + ['expiry' => $start + $window + $this->retentionSeconds]);
                $select->execute($params);
                $count = (int) $select->fetchColumn();
                $buckets[] = [
                    'scope' => $scope, 'bucket_hash' => $params['hash'], 'count' => $count,
                    'limit' => $policy[$scope], 'exceeded' => $count > $policy[$scope],
                ];
            }
            $metadata = [
                'request_id' => $requestId,
                'action' => $action, 'mode' => 'observe', 'window_seconds' => $window,
                'window_start' => $start, 'buckets' => $buckets,
                'peer_ip' => $client['peer_ip'], 'source' => $client['source'],
                'forwarded_status' => $client['forwarded_status'],
            ];
            $this->insertAudit($pdo, 'security_request_observed', $targetUserId, $client['ip'], $metadata);
            foreach ($buckets as $bucket) {
                if ($bucket['count'] === $bucket['limit'] + 1) {
                    $this->insertAudit($pdo, 'security_threshold_observed', $targetUserId, $client['ip'], [
                        'request_id' => $requestId,
                        'action' => $action, 'mode' => 'observe', 'scope' => $bucket['scope'],
                        'bucket_hash' => $bucket['bucket_hash'], 'limit' => $bucket['limit'],
                        'window_start' => $start, 'window_seconds' => $window,
                    ]);
                }
            }
            $pdo->commit();
            $ownsTransaction = false;
            foreach ($buckets as $bucket) {
                if ($bucket['count'] === $bucket['limit'] + 1) {
                    ($this->alertReporter)([
                        'action' => $action, 'scope' => $bucket['scope'], 'mode' => 'observe',
                        'limit' => $bucket['limit'], 'window_seconds' => $window,
                    ]);
                }
            }
            try {
                // Bounded opportunistic cleanup; CLI pruning handles idle periods.
                $this->prune(50);
            } catch (Throwable $e) {
                ($this->failureReporter)([
                    'action' => $action, 'mode' => 'observe', 'status' => 'cleanup_unavailable',
                    'exception_type' => get_class($e), 'error_code' => (string) $e->getCode(),
                ]);
            }
            return ['status' => 'observed', 'mode' => 'observe', 'blocked' => false, 'buckets' => $buckets];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Return an explicit unavailable state, never a successful limit check.
            // Later enforcing phases must reject/defer the action on this state.
            ($this->failureReporter)([
                'action' => $action, 'mode' => 'observe', 'status' => 'unavailable',
                'failure_policy' => 'alert_only_during_observation',
                'exception_type' => get_class($e), 'error_code' => (string) $e->getCode(),
            ]);
            return ['status' => 'unavailable', 'mode' => 'observe', 'blocked' => false, 'buckets' => []];
        }
    }

    public function prune(int $batchSize = 1000): int
    {
        $pdo = $this->connection();
        $batchSize = max(1, min(10000, $batchSize));
        $statement = $pdo->prepare('DELETE FROM ' . $this->counterTable . '
            WHERE expires_at < UNIX_TIMESTAMP() ORDER BY expires_at LIMIT ' . $batchSize);
        $statement->execute();
        return $statement->rowCount();
    }

    private function connection(): PDO
    {
        if (!$this->pdo) {
            $this->pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 2,
            ]);
            $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 2');
        }
        return $this->pdo;
    }

    private function insertAudit(PDO $pdo, string $event, ?int $userId, string $ip, array $metadata): void
    {
        $statement = $pdo->prepare('INSERT INTO ' . $this->auditTable . '
            (event_type, actor_user_id, target_user_id, ip_address, user_agent, metadata)
            VALUES (:event, NULL, :user, :ip, :agent, :metadata)');
        $statement->execute([
            'event' => $event, 'user' => $userId, 'ip' => $ip,
            'agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
