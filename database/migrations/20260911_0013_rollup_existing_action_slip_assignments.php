<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        UPDATE department_action_slip_assignments assignment
        JOIN department_action_slip_events event
            ON event.slip_id = assignment.slip_id
            AND event.action = 'Staff Completion Confirmed'
            AND event.actor_department_id = assignment.department_id
        SET assignment.status = 'Confirmed',
            assignment.completed_at = COALESCE(assignment.completed_at, event.created_at),
            assignment.completed_by = event.actor_user_id
        WHERE assignment.assignment_type = 'staff'
        AND assignment.status = 'Completed'
    ");

    $pdo->exec("
        UPDATE department_action_slip_assignments assignment
        JOIN department_action_slip_events event
            ON event.slip_id = assignment.slip_id
            AND event.action IN ('Returned by Unit Manager', 'Returned by Division Manager')
            AND event.to_user_id = assignment.user_id
            AND event.created_at >= COALESCE(assignment.completed_at, event.created_at)
        SET assignment.status = 'Returned',
            assignment.completed_at = NULL,
            assignment.completed_by = NULL,
            assignment.completion_attachment = NULL
        WHERE assignment.assignment_type = 'staff'
        AND assignment.status = 'Completed'
    ");

    $pdo->exec("
        UPDATE department_action_slip_assignments assignment
        JOIN department_action_slip_events event
            ON event.slip_id = assignment.slip_id
            AND event.action = 'Completed by Unit'
            AND event.actor_department_id = assignment.department_id
        SET assignment.status = 'Completed',
            assignment.completed_at = COALESCE(assignment.completed_at, event.created_at),
            assignment.completed_by = event.actor_user_id
        WHERE assignment.assignment_type = 'unit'
        AND assignment.status NOT IN ('Confirmed', 'Cancelled')
    ");

    $pdo->exec("
        UPDATE department_action_slip_assignments assignment
        JOIN departments unit ON unit.id = assignment.department_id
        JOIN department_action_slip_events event
            ON event.slip_id = assignment.slip_id
            AND event.action = 'Unit Completion Confirmed'
            AND event.actor_department_id = unit.parent_id
        SET assignment.status = 'Confirmed',
            assignment.completed_at = COALESCE(assignment.completed_at, event.created_at),
            assignment.completed_by = event.actor_user_id
        WHERE assignment.assignment_type = 'unit'
        AND assignment.status = 'Completed'
    ");

    $pdo->exec("
        UPDATE department_action_slip_assignments assignment
        JOIN department_action_slip_events event
            ON event.slip_id = assignment.slip_id
            AND event.action = 'Returned by Division Manager'
            AND event.to_department_id = assignment.department_id
            AND event.created_at >= COALESCE(assignment.completed_at, event.created_at)
        SET assignment.status = 'Returned',
            assignment.completed_at = NULL,
            assignment.completed_by = NULL,
            assignment.completion_attachment = NULL
        WHERE assignment.assignment_type = 'unit'
        AND assignment.status = 'Completed'
    ");

    $pdo->exec("
        UPDATE department_action_slip_assignments assignment
        JOIN department_action_slip_events event
            ON event.slip_id = assignment.slip_id
            AND event.action = 'Completed by Division'
            AND event.actor_department_id = assignment.department_id
        SET assignment.status = 'Completed',
            assignment.completed_at = COALESCE(assignment.completed_at, event.created_at),
            assignment.completed_by = event.actor_user_id
        WHERE assignment.assignment_type = 'division'
        AND assignment.status NOT IN ('Confirmed', 'Cancelled')
    ");

    $pdo->exec("
        UPDATE department_action_slip_assignments assignment
        JOIN department_action_slip_events event
            ON event.slip_id = assignment.slip_id
            AND event.action = 'Completed by Department'
            AND event.actor_department_id = assignment.department_id
        SET assignment.status = 'Completed',
            assignment.completed_at = COALESCE(assignment.completed_at, event.created_at),
            assignment.completed_by = event.actor_user_id
        WHERE assignment.assignment_type = 'department'
        AND assignment.status NOT IN ('Confirmed', 'Cancelled')
    ");
};
