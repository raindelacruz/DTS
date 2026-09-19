<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS department_action_slip_assignments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            slip_id INT(11) NOT NULL,
            assignment_type VARCHAR(20) NOT NULL,
            department_id INT(11) DEFAULT NULL,
            user_id INT(11) DEFAULT NULL,
            assigned_by INT(11) NOT NULL,
            assigned_by_department_id INT(11) NOT NULL,
            status VARCHAR(80) NOT NULL,
            remarks TEXT DEFAULT NULL,
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            received_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            completed_by INT(11) DEFAULT NULL,
            completion_attachment VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_das_assignments_slip (slip_id),
            KEY idx_das_assignments_department (department_id),
            KEY idx_das_assignments_user (user_id),
            KEY idx_das_assignments_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        INSERT INTO department_action_slip_assignments (
            slip_id,
            assignment_type,
            department_id,
            user_id,
            assigned_by,
            assigned_by_department_id,
            status,
            remarks,
            assigned_at,
            received_at,
            completed_at,
            completed_by,
            completion_attachment
        )
        SELECT
            e.slip_id,
            'staff',
            e.to_department_id,
            e.to_user_id,
            e.actor_user_id,
            e.actor_department_id,
            CASE
                WHEN completion.id IS NOT NULL THEN 'Completed'
                WHEN received.id IS NOT NULL THEN 'For Action'
                ELSE 'Delegated'
            END,
            e.remarks,
            e.created_at,
            received.created_at,
            completion.created_at,
            completion.actor_user_id,
            completion.attachment
        FROM department_action_slip_events e
        LEFT JOIN department_action_slip_events received
            ON received.slip_id = e.slip_id
            AND received.actor_user_id = e.to_user_id
            AND received.action = 'Received by Staff'
        LEFT JOIN department_action_slip_events completion
            ON completion.slip_id = e.slip_id
            AND completion.actor_user_id = e.to_user_id
            AND completion.action IN ('Completed by Staff', 'Resubmitted by Staff')
        WHERE e.action IN ('Further Delegated to Staff', 'Released to Staff')
        AND e.to_user_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1
            FROM department_action_slip_assignments existing
            WHERE existing.slip_id = e.slip_id
            AND existing.assignment_type = 'staff'
            AND existing.user_id = e.to_user_id
        )
    ");

    $pdo->exec("
        INSERT INTO department_action_slip_assignments (
            slip_id,
            assignment_type,
            department_id,
            user_id,
            assigned_by,
            assigned_by_department_id,
            status,
            remarks,
            assigned_at,
            received_at
        )
        SELECT
            e.slip_id,
            'unit',
            e.to_department_id,
            e.to_user_id,
            e.actor_user_id,
            e.actor_department_id,
            CASE WHEN received.id IS NOT NULL THEN 'Received' ELSE 'Delegated' END,
            e.remarks,
            e.created_at,
            received.created_at
        FROM department_action_slip_events e
        LEFT JOIN department_action_slip_events received
            ON received.slip_id = e.slip_id
            AND received.actor_department_id = e.to_department_id
            AND received.action = 'Received by Unit'
        WHERE e.action = 'Delegated to Unit'
        AND e.to_department_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1
            FROM department_action_slip_assignments existing
            WHERE existing.slip_id = e.slip_id
            AND existing.assignment_type = 'unit'
            AND existing.department_id = e.to_department_id
        )
    ");

    $pdo->exec("
        INSERT INTO department_action_slip_assignments (
            slip_id,
            assignment_type,
            department_id,
            user_id,
            assigned_by,
            assigned_by_department_id,
            status,
            remarks,
            assigned_at,
            received_at,
            completed_at,
            completed_by,
            completion_attachment
        )
        SELECT
            child.parent_slip_id,
            CASE WHEN child.assigned_staff_id IS NULL THEN 'unit' ELSE 'staff' END,
            child.current_unit_id,
            child.assigned_staff_id,
            child.created_by,
            COALESCE(child.current_unit_id, child.current_division_id, child.current_department_id),
            child.status,
            child.remarks,
            child.created_at,
            received.created_at,
            child.completed_at,
            child.completed_by,
            completion.attachment
        FROM department_action_slips child
        LEFT JOIN department_action_slip_events received
            ON received.slip_id = child.id
            AND received.action IN ('Received by Unit', 'Received by Staff')
        LEFT JOIN department_action_slip_events completion
            ON completion.slip_id = child.id
            AND completion.action IN ('Completed by Staff', 'Completed by Unit', 'Resubmitted by Staff')
        WHERE child.parent_slip_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1
            FROM department_action_slip_assignments existing
            WHERE existing.slip_id = child.parent_slip_id
            AND existing.assignment_type = CASE WHEN child.assigned_staff_id IS NULL THEN 'unit' ELSE 'staff' END
            AND (existing.department_id <=> child.current_unit_id)
            AND (existing.user_id <=> child.assigned_staff_id)
        )
    ");
};
