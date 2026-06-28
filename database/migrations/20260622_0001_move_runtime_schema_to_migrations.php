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

    $addColumn = static function (string $table, string $column, string $definition) use ($pdo, $columnExists): void {
        if (!$columnExists($table, $column)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL,
            KEY user_id (user_id),
            KEY is_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_routes (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            document_id INT(11) NOT NULL,
            from_department_id INT(11) NOT NULL,
            to_department_id INT(11) NOT NULL,
            routing_type ENUM('TO','THRU','CC','DELEGATE') NOT NULL,
            instructions TEXT NULL,
            status ENUM('Pending','Received','Returned') DEFAULT 'Pending',
            routed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            received_at DATETIME NULL,
            KEY document_id (document_id),
            KEY from_department_id (from_department_id),
            KEY to_department_id (to_department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_returns (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            document_id INT(11) NOT NULL,
            route_id INT(11) DEFAULT NULL,
            returned_by INT(11) NOT NULL,
            returned_department_id INT(11) NOT NULL,
            releasing_department_id INT(11) NOT NULL,
            return_reason VARCHAR(150) NOT NULL,
            attachment_issue VARCHAR(80) DEFAULT NULL,
            remarks TEXT NOT NULL,
            status ENUM('Open','Resolved') NOT NULL DEFAULT 'Open',
            returned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            resolved_by INT(11) DEFAULT NULL,
            KEY idx_document_returns_document (document_id),
            KEY idx_document_returns_status (status),
            KEY idx_document_returns_route (route_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_attachment_history (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            document_id INT(11) NOT NULL,
            return_id INT(11) DEFAULT NULL,
            old_filename VARCHAR(255) DEFAULT NULL,
            new_filename VARCHAR(255) NOT NULL,
            uploaded_by INT(11) NOT NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            replacement_reason TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            KEY idx_document_attachment_history_document (document_id),
            KEY idx_document_attachment_history_return (return_id),
            KEY idx_document_attachment_history_active (document_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_assignments (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            document_id INT(11) NOT NULL,
            assigned_by_user_id INT(11) NOT NULL,
            assigned_by_department_id INT(11) NOT NULL,
            assigned_to_user_id INT(11) NOT NULL,
            assigned_to_department_id INT(11) NOT NULL,
            assignment_type ENUM('INTERNAL') NOT NULL DEFAULT 'INTERNAL',
            instructions TEXT DEFAULT NULL,
            status ENUM('Pending','Received','Completed','Confirmed','Returned','Cancelled') NOT NULL DEFAULT 'Pending',
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            completed_by INT(11) DEFAULT NULL,
            completion_attachment VARCHAR(255) DEFAULT NULL,
            returned_at DATETIME DEFAULT NULL,
            return_remarks TEXT DEFAULT NULL,
            KEY idx_document_assignments_document (document_id),
            KEY idx_document_assignments_assignee (assigned_to_user_id),
            KEY idx_document_assignments_department (assigned_to_department_id),
            KEY idx_document_assignments_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS department_action_slip_sequences (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            department_id INT(11) NOT NULL,
            year INT(11) NOT NULL,
            month INT(11) NOT NULL,
            last_number INT(11) NOT NULL DEFAULT 0,
            UNIQUE KEY unique_das_sequence (department_id, year, month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS department_action_slips (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slip_number VARCHAR(100) NOT NULL,
            external_source VARCHAR(255) NOT NULL,
            date_received DATE NOT NULL,
            subject VARCHAR(255) NOT NULL,
            reference_number VARCHAR(120) DEFAULT NULL,
            receiving_level VARCHAR(20) NOT NULL DEFAULT 'Department',
            urgent TINYINT(1) NOT NULL DEFAULT 0,
            attachment VARCHAR(255) DEFAULT NULL,
            required_action TEXT NOT NULL,
            deadline DATE DEFAULT NULL,
            receiving_department_id INT(11) NOT NULL,
            receiving_division_id INT(11) DEFAULT NULL,
            current_department_id INT(11) DEFAULT NULL,
            current_division_id INT(11) DEFAULT NULL,
            assigned_staff_id INT(11) DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            created_by INT(11) NOT NULL,
            status VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            completed_by INT(11) DEFAULT NULL,
            closed_at DATETIME DEFAULT NULL,
            closed_by INT(11) DEFAULT NULL,
            UNIQUE KEY uq_department_action_slips_number (slip_number),
            KEY idx_das_receiving_department (receiving_department_id),
            KEY idx_das_current_division (current_division_id),
            KEY idx_das_assigned_staff (assigned_staff_id),
            KEY idx_das_status (status),
            KEY idx_das_date_received (date_received),
            KEY idx_das_deadline (deadline)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS department_action_slip_events (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slip_id INT(11) NOT NULL,
            action VARCHAR(120) NOT NULL,
            actor_user_id INT(11) NOT NULL,
            actor_department_id INT(11) NOT NULL,
            from_department_id INT(11) DEFAULT NULL,
            to_department_id INT(11) DEFAULT NULL,
            from_user_id INT(11) DEFAULT NULL,
            to_user_id INT(11) DEFAULT NULL,
            old_status VARCHAR(80) DEFAULT NULL,
            new_status VARCHAR(80) DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            attachment VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_das_events_slip (slip_id),
            KEY idx_das_events_actor (actor_user_id),
            KEY idx_das_events_to_department (to_department_id),
            KEY idx_das_events_to_user (to_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $addColumn('users', 'email', 'VARCHAR(150) DEFAULT NULL AFTER `lastname`');
    $addColumn('users', 'status', "VARCHAR(20) NOT NULL DEFAULT 'inactive' AFTER `role`");
    $addColumn('users', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    $addColumn('documents', 'particulars', 'TEXT DEFAULT NULL AFTER `title`');
    $addColumn('documents', 'qr_token', 'VARCHAR(64) DEFAULT NULL AFTER `attachment`');
    $addColumn('documents', 'reference_document_id', 'INT(11) DEFAULT NULL AFTER `destination_department_id`');
    if (!$indexExists('documents', 'uq_documents_qr_token')) {
        $pdo->exec('ALTER TABLE documents ADD UNIQUE KEY uq_documents_qr_token (qr_token)');
    }
    $pdo->exec("ALTER TABLE documents MODIFY status ENUM('Draft','Released','Received','Returned','Re-released','Cancelled') DEFAULT 'Draft'");
    $pdo->exec("ALTER TABLE document_routes MODIFY status ENUM('Pending','Received','Returned') DEFAULT 'Pending'");
    $pdo->exec("ALTER TABLE document_assignments MODIFY status ENUM('Pending','Received','Completed','Confirmed','Returned','Cancelled') NOT NULL DEFAULT 'Pending'");

    $addColumn('document_assignments', 'completion_attachment', 'VARCHAR(255) DEFAULT NULL AFTER `completed_by`');
    $addColumn('document_assignments', 'returned_at', 'DATETIME DEFAULT NULL AFTER `completion_attachment`');
    $addColumn('document_assignments', 'return_remarks', 'TEXT DEFAULT NULL AFTER `returned_at`');

    $addColumn('department_action_slips', 'receiving_level', "VARCHAR(20) NOT NULL DEFAULT 'Department' AFTER `reference_number`");
    $addColumn('department_action_slips', 'urgent', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `receiving_level`');
    $addColumn('department_action_slips', 'receiving_division_id', 'INT(11) DEFAULT NULL AFTER `receiving_department_id`');
    $addColumn('department_action_slips', 'current_department_id', 'INT(11) DEFAULT NULL AFTER `receiving_division_id`');
    $addColumn('department_action_slips', 'current_division_id', 'INT(11) DEFAULT NULL AFTER `current_department_id`');
    $addColumn('department_action_slips', 'remarks', 'TEXT DEFAULT NULL AFTER `assigned_staff_id`');
    $addColumn('department_action_slips', 'closed_at', 'DATETIME DEFAULT NULL AFTER `completed_by`');
    $addColumn('department_action_slips', 'closed_by', 'INT(11) DEFAULT NULL AFTER `closed_at`');

    $pdo->exec('UPDATE department_action_slips SET current_department_id = receiving_department_id WHERE current_department_id IS NULL OR current_department_id = 0');
    $pdo->exec("UPDATE department_action_slips SET current_division_id = receiving_division_id WHERE receiving_level IN ('Division', 'Staff') AND receiving_division_id IS NOT NULL AND (current_division_id IS NULL OR current_division_id = 0)");
    $pdo->exec("
        UPDATE department_action_slips
        SET status = CASE
            WHEN status = 'Created' THEN 'Draft'
            WHEN status IN ('Routed to Department', 'Reassigned') THEN 'Released'
            WHEN status IN ('Received by Department', 'Received by Receiving Department', 'Received by Division') THEN 'Received'
            WHEN status IN ('Delegated to Division', 'Delegated to Staff') THEN 'Delegated'
            WHEN status = 'For Staff Action' THEN 'For Action'
            WHEN status IN ('Staff Completed', 'Division Confirmed', 'Department Completed', 'Closed') THEN 'Completed'
            ELSE status
        END
        WHERE status NOT IN ('Draft', 'Released', 'Received', 'Delegated', 'For Action', 'Completed', 'Returned', 'Cancelled')
    ");
};
