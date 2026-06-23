<?php

return static function (PDO $pdo): void {
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute(['db' => DB_NAME, 'table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $constraintExists = static function (string $name) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :db AND CONSTRAINT_NAME = :name');
        $stmt->execute(['db' => DB_NAME, 'name' => $name]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $addColumn = static function (string $table, string $column, string $definition) use ($pdo, $columnExists): void {
        if (!$columnExists($table, $column)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };
    $addConstraint = static function (string $name, string $sql) use ($pdo, $constraintExists): void {
        if (!$constraintExists($name)) {
            $pdo->exec($sql);
        }
    };

    $addColumn('users', 'password_changed_at', 'DATETIME DEFAULT NULL');
    $addColumn('users', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0');
    $addColumn('users', 'session_version', 'INT(11) NOT NULL DEFAULT 0');
    $addColumn('users', 'mfa_secret', 'TEXT DEFAULT NULL');
    $addColumn('users', 'mfa_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
    $addColumn('users', 'last_login_at', 'DATETIME DEFAULT NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        identifier_hash CHAR(64) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        failed_attempts INT NOT NULL DEFAULT 0,
        first_failed_at DATETIME NOT NULL,
        last_failed_at DATETIME NOT NULL,
        locked_until DATETIME DEFAULT NULL,
        PRIMARY KEY (identifier_hash, ip_address),
        KEY idx_login_attempts_locked_until (locked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_password_reset_token_hash (token_hash),
        KEY idx_password_reset_user (user_id),
        KEY idx_password_reset_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS security_audit_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(100) NOT NULL,
        actor_user_id INT(11) DEFAULT NULL,
        target_user_id INT(11) DEFAULT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        metadata JSON DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_security_audit_event (event_type),
        KEY idx_security_audit_actor (actor_user_id),
        KEY idx_security_audit_target (target_user_id),
        KEY idx_security_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $triggerExists = static function (string $name) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = :db AND TRIGGER_NAME = :name');
        $stmt->execute(['db' => DB_NAME, 'name' => $name]);
        return (int) $stmt->fetchColumn() > 0;
    };
    if (!$triggerExists('security_audit_log_no_update')) {
        $pdo->exec("CREATE TRIGGER security_audit_log_no_update BEFORE UPDATE ON security_audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit records are immutable'");
    }
    if (!$triggerExists('security_audit_log_no_delete')) {
        $pdo->exec("CREATE TRIGGER security_audit_log_no_delete BEFORE DELETE ON security_audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit records are immutable'");
    }

    $orphanChecks = [
        ['document_assignments', 'document_id', 'documents', 'id'], ['document_assignments', 'assigned_by_user_id', 'users', 'id'], ['document_assignments', 'assigned_by_department_id', 'departments', 'id'], ['document_assignments', 'assigned_to_user_id', 'users', 'id'], ['document_assignments', 'assigned_to_department_id', 'departments', 'id'], ['document_assignments', 'completed_by', 'users', 'id'],
        ['document_returns', 'document_id', 'documents', 'id'], ['document_returns', 'route_id', 'document_routes', 'id'], ['document_returns', 'returned_by', 'users', 'id'], ['document_returns', 'returned_department_id', 'departments', 'id'], ['document_returns', 'releasing_department_id', 'departments', 'id'], ['document_returns', 'resolved_by', 'users', 'id'],
        ['document_attachment_history', 'document_id', 'documents', 'id'], ['document_attachment_history', 'return_id', 'document_returns', 'id'], ['document_attachment_history', 'uploaded_by', 'users', 'id'],
        ['notifications', 'user_id', 'users', 'id'], ['department_action_slip_sequences', 'department_id', 'departments', 'id'],
        ['department_action_slips', 'receiving_department_id', 'departments', 'id'], ['department_action_slips', 'receiving_division_id', 'departments', 'id'], ['department_action_slips', 'current_department_id', 'departments', 'id'], ['department_action_slips', 'current_division_id', 'departments', 'id'], ['department_action_slips', 'assigned_staff_id', 'users', 'id'], ['department_action_slips', 'created_by', 'users', 'id'], ['department_action_slips', 'completed_by', 'users', 'id'], ['department_action_slips', 'closed_by', 'users', 'id'],
        ['department_action_slip_events', 'slip_id', 'department_action_slips', 'id'], ['department_action_slip_events', 'actor_user_id', 'users', 'id'], ['department_action_slip_events', 'actor_department_id', 'departments', 'id'], ['department_action_slip_events', 'from_department_id', 'departments', 'id'], ['department_action_slip_events', 'to_department_id', 'departments', 'id'], ['department_action_slip_events', 'from_user_id', 'users', 'id'], ['department_action_slip_events', 'to_user_id', 'users', 'id']
    ];
    foreach ($orphanChecks as [$table, $column, $parent, $parentColumn]) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` c LEFT JOIN `{$parent}` p ON p.`{$parentColumn}` = c.`{$column}` WHERE c.`{$column}` IS NOT NULL AND p.`{$parentColumn}` IS NULL")->fetchColumn();
        if ($count > 0) {
            throw new RuntimeException("Cannot add constraints: {$table}.{$column} has {$count} orphaned record(s).");
        }
    }

    $addConstraint('fk_password_reset_user', 'ALTER TABLE password_reset_tokens ADD CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    $addConstraint('fk_notifications_user', 'ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    $addConstraint('fk_assignment_document', 'ALTER TABLE document_assignments ADD CONSTRAINT fk_assignment_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE');
    $addConstraint('fk_assignment_by_user', 'ALTER TABLE document_assignments ADD CONSTRAINT fk_assignment_by_user FOREIGN KEY (assigned_by_user_id) REFERENCES users(id)');
    $addConstraint('fk_assignment_by_department', 'ALTER TABLE document_assignments ADD CONSTRAINT fk_assignment_by_department FOREIGN KEY (assigned_by_department_id) REFERENCES departments(id)');
    $addConstraint('fk_assignment_to_user', 'ALTER TABLE document_assignments ADD CONSTRAINT fk_assignment_to_user FOREIGN KEY (assigned_to_user_id) REFERENCES users(id)');
    $addConstraint('fk_assignment_to_department', 'ALTER TABLE document_assignments ADD CONSTRAINT fk_assignment_to_department FOREIGN KEY (assigned_to_department_id) REFERENCES departments(id)');
    $addConstraint('fk_assignment_completed_by', 'ALTER TABLE document_assignments ADD CONSTRAINT fk_assignment_completed_by FOREIGN KEY (completed_by) REFERENCES users(id)');
    $addConstraint('fk_return_document', 'ALTER TABLE document_returns ADD CONSTRAINT fk_return_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE');
    $addConstraint('fk_return_route', 'ALTER TABLE document_returns ADD CONSTRAINT fk_return_route FOREIGN KEY (route_id) REFERENCES document_routes(id) ON DELETE SET NULL');
    $addConstraint('fk_returned_by', 'ALTER TABLE document_returns ADD CONSTRAINT fk_returned_by FOREIGN KEY (returned_by) REFERENCES users(id)');
    $addConstraint('fk_returned_department', 'ALTER TABLE document_returns ADD CONSTRAINT fk_returned_department FOREIGN KEY (returned_department_id) REFERENCES departments(id)');
    $addConstraint('fk_releasing_department', 'ALTER TABLE document_returns ADD CONSTRAINT fk_releasing_department FOREIGN KEY (releasing_department_id) REFERENCES departments(id)');
    $addConstraint('fk_return_resolved_by', 'ALTER TABLE document_returns ADD CONSTRAINT fk_return_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id)');
    $addConstraint('fk_attachment_history_document', 'ALTER TABLE document_attachment_history ADD CONSTRAINT fk_attachment_history_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE');
    $addConstraint('fk_attachment_history_return', 'ALTER TABLE document_attachment_history ADD CONSTRAINT fk_attachment_history_return FOREIGN KEY (return_id) REFERENCES document_returns(id) ON DELETE SET NULL');
    $addConstraint('fk_attachment_history_user', 'ALTER TABLE document_attachment_history ADD CONSTRAINT fk_attachment_history_user FOREIGN KEY (uploaded_by) REFERENCES users(id)');
    $addConstraint('fk_das_sequence_department', 'ALTER TABLE department_action_slip_sequences ADD CONSTRAINT fk_das_sequence_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE');
    $addConstraint('fk_das_receiving_department', 'ALTER TABLE department_action_slips ADD CONSTRAINT fk_das_receiving_department FOREIGN KEY (receiving_department_id) REFERENCES departments(id)');
    $addConstraint('fk_das_receiving_division', 'ALTER TABLE department_action_slips ADD CONSTRAINT fk_das_receiving_division FOREIGN KEY (receiving_division_id) REFERENCES departments(id)');
    $addConstraint('fk_das_current_department', 'ALTER TABLE department_action_slips ADD CONSTRAINT fk_das_current_department FOREIGN KEY (current_department_id) REFERENCES departments(id)');
    $addConstraint('fk_das_current_division', 'ALTER TABLE department_action_slips ADD CONSTRAINT fk_das_current_division FOREIGN KEY (current_division_id) REFERENCES departments(id)');
    $addConstraint('fk_das_assigned_staff', 'ALTER TABLE department_action_slips ADD CONSTRAINT fk_das_assigned_staff FOREIGN KEY (assigned_staff_id) REFERENCES users(id)');
    $addConstraint('fk_das_created_by', 'ALTER TABLE department_action_slips ADD CONSTRAINT fk_das_created_by FOREIGN KEY (created_by) REFERENCES users(id)');
    $addConstraint('fk_das_completed_by', 'ALTER TABLE department_action_slips ADD CONSTRAINT fk_das_completed_by FOREIGN KEY (completed_by) REFERENCES users(id)');
    $addConstraint('fk_das_closed_by', 'ALTER TABLE department_action_slips ADD CONSTRAINT fk_das_closed_by FOREIGN KEY (closed_by) REFERENCES users(id)');
    $addConstraint('fk_das_event_slip', 'ALTER TABLE department_action_slip_events ADD CONSTRAINT fk_das_event_slip FOREIGN KEY (slip_id) REFERENCES department_action_slips(id) ON DELETE CASCADE');
    $addConstraint('fk_das_event_actor_user', 'ALTER TABLE department_action_slip_events ADD CONSTRAINT fk_das_event_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id)');
    $addConstraint('fk_das_event_actor_department', 'ALTER TABLE department_action_slip_events ADD CONSTRAINT fk_das_event_actor_department FOREIGN KEY (actor_department_id) REFERENCES departments(id)');
    $addConstraint('fk_das_event_from_department', 'ALTER TABLE department_action_slip_events ADD CONSTRAINT fk_das_event_from_department FOREIGN KEY (from_department_id) REFERENCES departments(id)');
    $addConstraint('fk_das_event_to_department', 'ALTER TABLE department_action_slip_events ADD CONSTRAINT fk_das_event_to_department FOREIGN KEY (to_department_id) REFERENCES departments(id)');
    $addConstraint('fk_das_event_from_user', 'ALTER TABLE department_action_slip_events ADD CONSTRAINT fk_das_event_from_user FOREIGN KEY (from_user_id) REFERENCES users(id)');
    $addConstraint('fk_das_event_to_user', 'ALTER TABLE department_action_slip_events ADD CONSTRAINT fk_das_event_to_user FOREIGN KEY (to_user_id) REFERENCES users(id)');
};
