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
        AND child_event.action IN ('Delegated to Unit', 'Further Delegated to Staff', 'Received by Unit', 'Received by Staff', 'Completed by Unit', 'Completed by Staff', 'Resubmitted by Staff')
        AND (
            child_event.action <> 'Delegated to Unit'
            OR EXISTS (
                SELECT 1
                FROM departments copied_target_department
                WHERE copied_target_department.id = child_event.to_department_id
                AND copied_target_department.office_level = 'unit'
            )
        )
        AND NOT EXISTS (
            SELECT 1
            FROM department_action_slip_events existing
            WHERE existing.slip_id = child.parent_slip_id
            AND existing.action = child_event.action
            AND existing.actor_user_id = child_event.actor_user_id
            AND (existing.to_user_id <=> child_event.to_user_id)
            AND (existing.to_department_id <=> child_event.to_department_id)
            AND existing.created_at = child_event.created_at
        )
    ");

    $pdo->exec("
        UPDATE department_action_slips parent
        SET parent.status = 'For Action',
            parent.completed_at = NULL,
            parent.completed_by = NULL
        WHERE parent.status = 'Completed'
        AND EXISTS (
            SELECT 1
            FROM department_action_slip_assignments assignment
            WHERE assignment.slip_id = parent.id
            AND assignment.assignment_type = 'staff'
            AND assignment.status NOT IN ('Completed', 'Cancelled')
        )
    ");
};
