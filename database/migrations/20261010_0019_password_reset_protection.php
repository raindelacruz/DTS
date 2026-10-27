<?php
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_limits (
        bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        next_allowed BIGINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_admissions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        admitted_at BIGINT NOT NULL,
        INDEX bucket_time (bucket_hash, admitted_at), INDEX admitted_time (admitted_at)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_outbox (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL, email VARCHAR(254) NOT NULL,
        token_encrypted TEXT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'queued',
        expires_at BIGINT NOT NULL, claimed_at BIGINT NULL,
        INDEX pending (status, id), INDEX expiry (expires_at)
    ) ENGINE=InnoDB");
};
