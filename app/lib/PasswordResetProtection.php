<?php

final class PasswordResetProtection
{
    private PDO $pdo;
    private string $key;

    public function __construct(PDO $pdo, string $key)
    {
        if ($key === '') { throw new InvalidArgumentException('Missing reset protection key.'); }
        $this->pdo = $pdo;
        $this->key = $key;
        $engines = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('users','password_reset_tokens')")->fetchAll(PDO::FETCH_COLUMN);
        if (count($engines) !== 2 || array_filter($engines, static function ($engine) { return strtoupper((string) $engine) !== 'INNODB'; })) {
            throw new RuntimeException('Reset protection requires transactional users and token tables.');
        }
        $engines = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('users','password_reset_tokens')")->fetchAll(PDO::FETCH_COLUMN);
        if (count($engines) !== 2 || array_filter($engines, static function ($engine) { return strtoupper((string) $engine) !== 'INNODB'; })) {
            throw new RuntimeException('Reset protection requires transactional users and token tables.');
        }
    }

    public static function enabled(): bool
    {
        $mode = getenv('PASSWORD_RESET_GUARD_MODE') ?: 'off';
        if (!in_array($mode, ['off', 'enforce'], true)) { throw new RuntimeException('Invalid reset protection mode.'); }
        return $mode === 'enforce';
    }

    public static function configured(): self
    {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 2]);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout=2');
        return new self($pdo, APP_KEY);
    }

    /** All valid addresses use the same admission path, including nonexistent/inactive accounts. */
    public function request(string $email, callable $encrypt): string
    {
        if ($this->pdo->inTransaction()) { throw new LogicException('Reset protection owns its transaction.'); }
        $email = strtolower(trim($email));
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return 'invalid'; }
        $buckets = [hash_hmac('sha256', 'global', $this->key) => 1000,
            hash_hmac('sha256', 'email|' . $email, $this->key) => 3];
        ksort($buckets);
        try {
            $this->pdo->beginTransaction();
            $now = (int) $this->pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
            foreach ($buckets as $hash => $limit) {
                $q = $this->pdo->prepare('INSERT INTO password_reset_limits (bucket_hash) VALUES (?) ON DUPLICATE KEY UPDATE bucket_hash=VALUES(bucket_hash)');
                $q->execute([$hash]);
                $q = $this->pdo->prepare('SELECT next_allowed FROM password_reset_limits WHERE bucket_hash=? FOR UPDATE');
                $q->execute([$hash]);
                $next = (int) $q->fetchColumn();
                $q = $this->pdo->prepare('SELECT COUNT(*) FROM password_reset_admissions WHERE bucket_hash=? AND admitted_at>?');
                $q->execute([$hash, $now - 3600]);
                if (($limit === 3 && $next > $now) || (int) $q->fetchColumn() >= $limit) {
                    $this->pdo->rollBack();
                    return 'limited';
                }
            }
            foreach ($buckets as $hash => $limit) {
                $q = $this->pdo->prepare('INSERT INTO password_reset_admissions (bucket_hash, admitted_at) VALUES (?, ?)');
                $q->execute([$hash, $now]);
                $q = $this->pdo->prepare('UPDATE password_reset_limits SET next_allowed=? WHERE bucket_hash=?');
                $q->execute([$now + ($limit === 3 ? 60 : 0), $hash]);
            }
            $q = $this->pdo->prepare('SELECT id, email FROM users WHERE email=? AND status=\'active\' LIMIT 1 FOR UPDATE');
            $q->execute([$email]);
            $user = $q->fetch(PDO::FETCH_ASSOC);
            // Run encryption for every admitted address to avoid a secret-dependent fast exit.
            $token = bin2hex(random_bytes(32));
            $encrypted = $encrypt($token);
            if (!is_string($encrypted) || $encrypted === '') { throw new RuntimeException('Token encryption failed.'); }
            if ($user) {
                $q = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL');
                $q->execute([$user['id']]);
                $q = $this->pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))');
                $q->execute([$user['id'], hash('sha256', $token), $now + 1800]);
                $q = $this->pdo->prepare('INSERT INTO password_reset_outbox (user_id, email, token_encrypted, expires_at) VALUES (?, ?, ?, ?)');
                $q->execute([$user['id'], $email, $encrypted, $now + 1800]);
            }
            $this->pdo->commit();
            return 'processed';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw new RuntimeException('Reset protection unavailable.', 0, $e);
        }
    }

    /** User lock precedes token lock in both issue and consume paths. */
    public function complete(string $token, string $password): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { return null; }
        if ($this->pdo->inTransaction()) { throw new LogicException('Reset protection owns its transaction.'); }
        $hash = hash('sha256', $token);
        $q = $this->pdo->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash=? LIMIT 1');
        $q->execute([$hash]);
        $id = (int) $q->fetchColumn();
        if (!$id) { return null; }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $this->pdo->beginTransaction();
            $q = $this->pdo->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            if ($q->fetchColumn() !== 'active') { $this->pdo->rollBack(); return null; }
            $q = $this->pdo->prepare('SELECT id FROM password_reset_tokens WHERE token_hash=? AND user_id=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE');
            $q->execute([$hash, $id]);
            if (!$q->fetchColumn()) { $this->pdo->rollBack(); return null; }
            $q = $this->pdo->prepare('UPDATE users SET password=?, password_changed_at=NOW(), must_change_password=0, session_version=session_version+1 WHERE id=?');
            $q->execute([$passwordHash, $id]);
            $q = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL');
            $q->execute([$id]);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw new RuntimeException('Reset completion unavailable.', 0, $e);
        }
    }

    /** At most one transport attempt; crashed sending jobs are never automatically resent. */
    public function deliverOne(callable $decrypt, callable $send): string
    {
        if ($this->pdo->inTransaction()) { throw new LogicException('Reset protection owns its transaction.'); }
        $this->pdo->beginTransaction();
        try {
            $job = $this->pdo->query("SELECT * FROM password_reset_outbox WHERE status='queued' ORDER BY id LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
            if (!$job) { $this->pdo->commit(); return 'empty'; }
            $q = $this->pdo->prepare("UPDATE password_reset_outbox SET status='sending', claimed_at=UNIX_TIMESTAMP() WHERE id=?");
            $q->execute([$job['id']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        $status = 'failed';
        try {
            $token = $decrypt($job['token_encrypted']);
            $q = $this->pdo->prepare("SELECT u.email FROM users u JOIN password_reset_tokens t ON t.user_id=u.id WHERE u.id=? AND u.status='active' AND t.token_hash=? AND t.used_at IS NULL AND t.expires_at>NOW() AND UNIX_TIMESTAMP()<? LIMIT 1");
            $q->execute([$job['user_id'], hash('sha256', $token), $job['expires_at']]);
            $email = $q->fetchColumn();
            if ($email !== false && strtolower($email) === $job['email']) {
                $status = $send($job['email'], $token) ? 'accepted' : 'failed';
            } else { $status = 'discarded'; }
        } catch (Throwable $e) {
            if (function_exists('appLog')) { appLog('error', 'Reset mail delivery failed', ['exception_class' => get_class($e)]); }
        }
        $q = $this->pdo->prepare('UPDATE password_reset_outbox SET status=?, token_encrypted=\'\' WHERE id=?');
        $q->execute([$status, $job['id']]);
        return $status;
    }

    public function prune(): void
    {
        // Expired payloads are destroyed; a crashed sender is never automatically retried.
        $this->pdo->exec("UPDATE password_reset_outbox SET token_encrypted='', status='discarded' WHERE expires_at<=UNIX_TIMESTAMP() AND status IN ('queued','sending') LIMIT 1000");
        $this->pdo->exec('DELETE FROM password_reset_admissions WHERE admitted_at<=UNIX_TIMESTAMP()-3600 LIMIT 1000');
        $this->pdo->exec('DELETE FROM password_reset_limits WHERE next_allowed<UNIX_TIMESTAMP()-7200 LIMIT 1000');
        $this->pdo->exec("DELETE FROM password_reset_outbox WHERE expires_at<UNIX_TIMESTAMP()-172800 AND status IN ('accepted','failed','discarded') LIMIT 1000");
    }
}
