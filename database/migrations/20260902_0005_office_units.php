<?php

return static function (PDO $pdo): void {
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $statement = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :schema_name
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $statement->execute(['schema_name' => DB_NAME, 'table_name' => $table, 'column_name' => $column]);
        return (int) $statement->fetchColumn() > 0;
    };

    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $statement = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :schema_name
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
        ");
        $statement->execute(['schema_name' => DB_NAME, 'table_name' => $table, 'index_name' => $index]);
        return (int) $statement->fetchColumn() > 0;
    };

    if (!$columnExists('departments', 'office_level')) {
        $pdo->exec("ALTER TABLE departments ADD COLUMN office_level VARCHAR(20) NOT NULL DEFAULT 'department' AFTER parent_id");
    }

    if (!$columnExists('departments', 'can_release')) {
        $pdo->exec("ALTER TABLE departments ADD COLUMN can_release TINYINT(1) NOT NULL DEFAULT 1 AFTER office_level");
    }

    $pdo->exec("UPDATE departments SET office_level = 'department', can_release = 1 WHERE parent_id IS NULL AND (office_level = '' OR office_level IS NULL OR office_level = 'department')");
    $pdo->exec("UPDATE departments SET office_level = 'division', can_release = 1 WHERE parent_id IS NOT NULL AND (office_level = '' OR office_level IS NULL OR office_level = 'department')");
    $pdo->exec("UPDATE departments SET can_release = 0 WHERE office_level = 'unit'");

    if (!$indexExists('departments', 'idx_departments_office_level')) {
        $pdo->exec('ALTER TABLE departments ADD KEY idx_departments_office_level (office_level)');
    }

    if (!$columnExists('document_assignments', 'parent_assignment_id')) {
        $pdo->exec('ALTER TABLE document_assignments ADD COLUMN parent_assignment_id INT(11) DEFAULT NULL AFTER document_id');
    }

    if (!$indexExists('document_assignments', 'idx_document_assignments_parent')) {
        $pdo->exec('ALTER TABLE document_assignments ADD KEY idx_document_assignments_parent (parent_assignment_id)');
    }

    $pdo->exec("ALTER TABLE document_assignments MODIFY assignment_type ENUM('INTERNAL','UNIT') NOT NULL DEFAULT 'INTERNAL'");
};
