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

    if (!$columnExists('department_action_slips', 'parent_slip_id')) {
        $pdo->exec('ALTER TABLE department_action_slips ADD COLUMN parent_slip_id INT(11) DEFAULT NULL AFTER receiving_division_id');
    }

    if (!$indexExists('department_action_slips', 'idx_das_parent_slip')) {
        $pdo->exec('ALTER TABLE department_action_slips ADD KEY idx_das_parent_slip (parent_slip_id)');
    }

    $pdo->exec("
        UPDATE department_action_slips child
        JOIN department_action_slip_events child_release
            ON child_release.slip_id = child.id
            AND child_release.action IN ('Released to Staff', 'Delegated to Unit')
        JOIN department_action_slip_events parent_fanout
            ON parent_fanout.action IN ('Further Delegated to Staff', 'Delegated to Unit')
            AND parent_fanout.actor_department_id = child_release.actor_department_id
            AND parent_fanout.created_at = child_release.created_at
            AND parent_fanout.slip_id <> child.id
        JOIN department_action_slips parent
            ON parent.id = parent_fanout.slip_id
            AND parent.title = child.title
            AND parent.required_action = child.required_action
            AND parent.date_received = child.date_received
        SET child.parent_slip_id = parent.id
        WHERE child.parent_slip_id IS NULL
        AND child.id > parent.id
    ");
};
