<?php

return static function (PDO $pdo, string $table = 'security_request_counters'): void {
    if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $table)) {
        throw new InvalidArgumentException('Invalid counter table identifier.');
    }
    // Additive only: existing accounts, login counters and audit rows remain intact.
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (
        action VARCHAR(32) NOT NULL,
        scope VARCHAR(16) NOT NULL,
        bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        window_start BIGINT UNSIGNED NOT NULL,
        window_seconds INT UNSIGNED NOT NULL,
        request_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
        expires_at BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (action, scope, bucket_hash, window_start, window_seconds),
        KEY idx_security_counter_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
