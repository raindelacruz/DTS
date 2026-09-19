<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        UPDATE department_action_slip_events confirm_event
        JOIN (
            SELECT confirmed.slip_id, confirmed.department_id, MIN(confirmed.user_id) AS first_user_id
            FROM department_action_slip_assignments confirmed
            JOIN department_action_slip_events event
                ON event.slip_id = confirmed.slip_id
                AND event.action = 'Staff Completion Confirmed'
                AND event.actor_department_id = confirmed.department_id
                AND event.to_user_id IS NULL
            WHERE confirmed.assignment_type = 'staff'
            AND confirmed.status = 'Confirmed'
            GROUP BY confirmed.slip_id, confirmed.department_id
            HAVING COUNT(*) > 1
        ) first_confirmed
            ON first_confirmed.slip_id = confirm_event.slip_id
            AND first_confirmed.department_id = confirm_event.actor_department_id
        SET confirm_event.to_department_id = first_confirmed.department_id,
            confirm_event.to_user_id = first_confirmed.first_user_id
        WHERE confirm_event.action = 'Staff Completion Confirmed'
        AND confirm_event.to_user_id IS NULL
    ");

    $pdo->exec("
        UPDATE department_action_slip_assignments assignment
        JOIN (
            SELECT confirmed.slip_id, confirmed.department_id, MIN(confirmed.id) AS first_assignment_id
            FROM department_action_slip_assignments confirmed
            JOIN department_action_slip_events event
                ON event.slip_id = confirmed.slip_id
                AND event.action = 'Staff Completion Confirmed'
                AND event.actor_department_id = confirmed.department_id
            WHERE confirmed.assignment_type = 'staff'
            AND confirmed.status = 'Confirmed'
            GROUP BY confirmed.slip_id, confirmed.department_id
            HAVING COUNT(*) > 1
        ) bulk_confirmed
            ON bulk_confirmed.slip_id = assignment.slip_id
            AND bulk_confirmed.department_id = assignment.department_id
            AND assignment.id <> bulk_confirmed.first_assignment_id
        LEFT JOIN department_action_slip_events completion_event
            ON completion_event.slip_id = assignment.slip_id
            AND completion_event.actor_user_id = assignment.user_id
            AND completion_event.action IN ('Completed by Staff', 'Resubmitted by Staff')
        SET assignment.status = 'Completed',
            assignment.completed_at = COALESCE(completion_event.created_at, assignment.completed_at),
            assignment.completed_by = assignment.user_id,
            assignment.completion_attachment = COALESCE(completion_event.attachment, assignment.completion_attachment)
        WHERE assignment.assignment_type = 'staff'
        AND assignment.status = 'Confirmed'
    ");
};
