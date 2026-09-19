<?php

return static function (PDO $pdo): void {
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :db
            AND TABLE_NAME = :table
            AND COLUMN_NAME = :column
        ');
        $stmt->execute(['db' => DB_NAME, 'table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$columnExists('department_action_slips', 'title')) {
        $pdo->exec('ALTER TABLE department_action_slips ADD COLUMN title VARCHAR(255) DEFAULT NULL AFTER slip_number');
    }

    $pdo->exec("UPDATE department_action_slips SET title = slip_number WHERE title IS NULL OR TRIM(title) = ''");
};
