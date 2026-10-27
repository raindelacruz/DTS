<?php
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS authentication_guard (
        subject_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        failures INT UNSIGNED NOT NULL DEFAULT 0,
        window_start BIGINT NOT NULL DEFAULT 0,
        retry_at BIGINT NOT NULL DEFAULT 0,
        otp_epoch CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
        otp_step BIGINT NOT NULL DEFAULT -1
    ) ENGINE=InnoDB");
};
