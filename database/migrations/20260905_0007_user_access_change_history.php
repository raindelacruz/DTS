<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_access_change_history (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            changed_by_user_id INT(11) NOT NULL,
            old_role VARCHAR(40) DEFAULT NULL,
            new_role VARCHAR(40) DEFAULT NULL,
            old_department_id INT(11) DEFAULT NULL,
            new_department_id INT(11) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_access_history_user (user_id),
            KEY idx_access_history_changed_by (changed_by_user_id),
            KEY idx_access_history_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
};
