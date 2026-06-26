<?php

return static function (PDO $pdo): void {
    $constraintExists = static function (string $name) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :db AND CONSTRAINT_NAME = :name');
        $stmt->execute(['db' => DB_NAME, 'name' => $name]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $pdo->exec("CREATE TABLE IF NOT EXISTS mfa_trusted_devices (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        user_agent_hash CHAR(64) NOT NULL,
        session_version INT(11) NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        last_used_at DATETIME DEFAULT NULL,
        revoked_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_mfa_trusted_token_hash (token_hash),
        KEY idx_mfa_trusted_user (user_id),
        KEY idx_mfa_trusted_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!$constraintExists('fk_mfa_trusted_user')) {
        $pdo->exec('ALTER TABLE mfa_trusted_devices ADD CONSTRAINT fk_mfa_trusted_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    }
};
