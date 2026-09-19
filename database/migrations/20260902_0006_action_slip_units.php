<?php

return static function (PDO $pdo): void {
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $statement = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");
        $statement->execute([
            'table_name' => $table,
            'column_name' => $column
        ]);
        return (int) $statement->fetchColumn() > 0;
    };

    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $statement = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND INDEX_NAME = :index_name
        ");
        $statement->execute([
            'table_name' => $table,
            'index_name' => $index
        ]);
        return (int) $statement->fetchColumn() > 0;
    };

    if (!$columnExists('department_action_slips', 'current_unit_id')) {
        $pdo->exec('ALTER TABLE department_action_slips ADD COLUMN current_unit_id INT(11) DEFAULT NULL AFTER current_division_id');
    }

    if (!$indexExists('department_action_slips', 'idx_das_current_unit')) {
        $pdo->exec('ALTER TABLE department_action_slips ADD KEY idx_das_current_unit (current_unit_id)');
    }
};
