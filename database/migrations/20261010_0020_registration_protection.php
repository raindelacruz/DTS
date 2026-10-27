<?php
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_invitations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        id_number VARCHAR(50) NOT NULL, email VARCHAR(150) NOT NULL, department_id INT NOT NULL,
        issued_by INT NOT NULL, issued_reason VARCHAR(500) NOT NULL,
        expires_at BIGINT NOT NULL, revoked_at BIGINT NULL, used_by INT NULL, used_at BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX expiry(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_limit_locks (
        bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        touched_at BIGINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        attempted_at BIGINT NOT NULL, INDEX bucket_time(bucket_hash,attempted_at), INDEX expiry(attempted_at)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_reviews (
        user_id INT PRIMARY KEY, invitation_id BIGINT UNSIGNED NOT NULL UNIQUE,
        decision VARCHAR(16) NOT NULL DEFAULT 'pending', reviewed_by INT NULL,
        reviewed_at BIGINT NULL, initial_approved_at BIGINT NULL, reason VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_notice_state (
        id INT PRIMARY KEY, next_notice_at BIGINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
};
