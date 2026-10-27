<?php
return static function(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS security_operational_alerts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        alert_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        rule_name VARCHAR(64) NOT NULL, severity VARCHAR(16) NOT NULL,
        subject VARCHAR(64) NOT NULL, event_count INT UNSIGNED NOT NULL,
        window_start BIGINT NOT NULL, first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        metadata TEXT NOT NULL, INDEX recent(last_seen), INDEX rule_window(rule_name,window_start)
    ) ENGINE=InnoDB");
};
