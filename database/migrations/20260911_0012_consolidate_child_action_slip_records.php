<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        INSERT INTO department_action_slip_events (
            slip_id,
            action,
            actor_user_id,
            actor_department_id,
            from_department_id,
            to_department_id,
            from_user_id,
            to_user_id,
            old_status,
            new_status,
            remarks,
            attachment,
            created_at
        )
        SELECT
            child.parent_slip_id,
            child_event.action,
            child_event.actor_user_id,
            child_event.actor_department_id,
            child_event.from_department_id,
            child_event.to_department_id,
            child_event.from_user_id,
            child_event.to_user_id,
            child_event.old_status,
            child_event.new_status,
            child_event.remarks,
            child_event.attachment,
            child_event.created_at
        FROM department_action_slips child
        JOIN department_action_slip_events child_event ON child_event.slip_id = child.id
        WHERE child.parent_slip_id IS NOT NULL
        AND child_event.action <> 'Created'
        AND NOT EXISTS (
            SELECT 1
            FROM department_action_slip_events existing
            WHERE existing.slip_id = child.parent_slip_id
            AND existing.action = child_event.action
            AND existing.actor_user_id = child_event.actor_user_id
            AND (existing.actor_department_id <=> child_event.actor_department_id)
            AND (existing.to_user_id <=> child_event.to_user_id)
            AND (existing.to_department_id <=> child_event.to_department_id)
            AND existing.created_at = child_event.created_at
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
            child.parent_slip_id,
            'division',
            child.receiving_division_id,
            NULL,
            release_event.actor_user_id,
            release_event.actor_department_id,
            CASE WHEN receive_event.id IS NOT NULL THEN 'Received' ELSE child.status END,
            release_event.remarks,
            release_event.created_at,
            receive_event.created_at
        FROM department_action_slips child
        JOIN department_action_slip_events release_event
            ON release_event.slip_id = child.id
            AND release_event.action IN ('Released to Division', 'Delegated to Division')
        LEFT JOIN department_action_slip_events receive_event
            ON receive_event.slip_id = child.id
            AND receive_event.action = 'Received by Division'
            AND receive_event.actor_department_id = child.receiving_division_id
        WHERE child.parent_slip_id IS NOT NULL
        AND child.receiving_division_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1
            FROM department_action_slip_assignments existing
            WHERE existing.slip_id = child.parent_slip_id
            AND existing.assignment_type = 'division'
            AND existing.department_id = child.receiving_division_id
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
            child_assignment.assignment_type,
            child_assignment.department_id,
            child_assignment.user_id,
            child_assignment.assigned_by,
            child_assignment.assigned_by_department_id,
            child_assignment.status,
            child_assignment.remarks,
            child_assignment.assigned_at,
            child_assignment.received_at,
            child_assignment.completed_at,
            child_assignment.completed_by,
            child_assignment.completion_attachment
        FROM department_action_slips child
        JOIN department_action_slip_assignments child_assignment ON child_assignment.slip_id = child.id
        WHERE child.parent_slip_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1
            FROM department_action_slip_assignments existing
            WHERE existing.slip_id = child.parent_slip_id
            AND existing.assignment_type = child_assignment.assignment_type
            AND (existing.department_id <=> child_assignment.department_id)
            AND (existing.user_id <=> child_assignment.user_id)
        )
    ");
};
