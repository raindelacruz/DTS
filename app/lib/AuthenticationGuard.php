<?php

/** Serializes credential checks by subject; no IP-wide access denial. */
final class AuthenticationGuard
{
    private PDO $pdo;
    private string $key;

    public function __construct(PDO $pdo, string $key)
    {
        if ($key === '') { throw new InvalidArgumentException('Missing guard key.'); }
        $this->pdo = $pdo;
        $this->key = $key;
    }

    public static function enabled(): bool
    {
        $mode = getenv('AUTH_GUARD_MODE') ?: 'off';
        if (!in_array($mode, ['off', 'enforce'], true)) {
            throw new RuntimeException('Invalid authentication guard mode.');
        }
        return $mode === 'enforce';
    }

    public static function configured(): self
    {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 2]);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 2');
        return new self($pdo, APP_KEY);
    }

    public static function startChallenge(array &$session, int $now): void
    {
        $session['mfa_challenge'] = ['started' => $now, 'attempts' => 0,
            'user' => (int) $session['user_id'], 'version' => (int) $session['session_version']];
        $session['mfa_verified'] = false;
        unset($session['mfa_setup_secret']);
    }

    public static function challengeValid(array $session, int $user, int $version, int $now): bool
    {
        $c = $session['mfa_challenge'] ?? [];
        return !empty($c) && ($c['user'] ?? 0) === $user && ($c['version'] ?? -1) === $version
            && ($session['session_version'] ?? -1) === $version && ($c['started'] ?? 0) <= $now
            && $now - ($c['started'] ?? 0) < 300 && ($c['attempts'] ?? 5) < 5;
    }

    /** Callback returns false for failure, user object for login, or matched TOTP step for MFA. */
    public function check(string $action, string $subject, callable $verify, string $epoch = ''): array
    {
        if (!in_array($action, ['login', 'mfa'], true) || $this->pdo->inTransaction()) {
            throw new LogicException('Invalid guard context.');
        }
        $hash = hash_hmac('sha256', $action . '|' . strtolower(trim($subject)), $this->key);
        try {
            $this->pdo->beginTransaction();
            $q = $this->pdo->prepare('INSERT INTO authentication_guard (subject_hash) VALUES (?) ON DUPLICATE KEY UPDATE subject_hash = VALUES(subject_hash)');
            $q->execute([$hash]);
            $q = $this->pdo->prepare('SELECT *, UNIX_TIMESTAMP() AS clock FROM authentication_guard WHERE subject_hash = ? FOR UPDATE');
            $q->execute([$hash]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            $now = (int) $row['clock'];
            if ((int) $row['retry_at'] > $now) {
                $this->pdo->commit();
                return ['status' => 'limited', 'retry' => (int) $row['retry_at'] - $now];
            }
            $value = $verify($now);
            $epochHash = hash_hmac('sha256', $epoch, $this->key);
            if ($action === 'mfa' && $value !== false && $row['otp_epoch'] === $epochHash
                && (int) $value <= (int) $row['otp_step']) {
                $value = false;
            }
            $failures = $now - (int) $row['window_start'] >= 900 ? 0 : (int) $row['failures'];
            $start = $failures === 0 ? $now : (int) $row['window_start'];
            $delay = 0;
            if ($value === false) {
                $failures++;
                $delay = $action === 'login' ? ($failures >= 5 ? 900 : 0)
                    : ($failures >= 5 ? 300 : 2 ** ($failures - 1));
            } else { $failures = 0; $start = $now; }
            $q = $this->pdo->prepare('UPDATE authentication_guard SET failures=?, window_start=?, retry_at=?, otp_epoch=?, otp_step=? WHERE subject_hash=?');
            $q->execute([$failures, $start, $now + $delay,
                $action === 'mfa' && $value !== false ? $epochHash : $row['otp_epoch'],
                $action === 'mfa' && $value !== false ? (int) $value : $row['otp_step'], $hash]);
            $this->pdo->commit();
            if ($value === false && $failures === 5 && function_exists('appLog')) {
                appLog('warning', 'Authentication failure threshold reached', ['action' => $action, 'subject_hash' => $hash, 'retry_seconds' => $delay]);
            }
            return ['status' => $value === false ? 'invalid' : 'accepted', 'retry' => $delay, 'value' => $value];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            if (function_exists('appLog')) { appLog('error', 'Authentication guard unavailable', ['action' => $action, 'exception_class' => get_class($e)]); }
            throw new RuntimeException('Sign-in protection is temporarily unavailable. Please try again later.', 0, $e);
        }
    }
}
