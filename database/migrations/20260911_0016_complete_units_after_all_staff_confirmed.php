<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        UPDATE department_action_slip_assignments unit_assignment
        JOIN (
            SELECT staff_assignment.slip_id, staff_assignment.department_id, MAX(staff_assignment.completed_at) AS completed_at
            FROM department_action_slip_assignments staff_assignment
            WHERE staff_assignment.assignment_type = 'staff'
            AND staff_assignment.status = 'Confirmed'
            AND NOT EXISTS (
                SELECT 1
                FROM department_action_slip_assignments unresolved_staff
                WHERE unresolved_staff.slip_id = staff_assignment.slip_id
                AND unresolved_staff.department_id = staff_assignment.department_id
                AND unresolved_staff.assignment_type = 'staff'
                AND unresolved_staff.status NOT IN ('Confirmed', 'Cancelled')
            )
            GROUP BY staff_assignment.slip_id, staff_assignment.department_id
        ) ready_unit
            ON ready_unit.slip_id = unit_assignment.slip_id
            AND ready_unit.department_id = unit_assignment.department_id
        SET unit_assignment.status = 'Completed',
            unit_assignment.completed_at = COALESCE(unit_assignment.completed_at, ready_unit.completed_at, NOW()),
            unit_assignment.completed_by = COALESCE(unit_assignment.completed_by, unit_assignment.assigned_by)
        WHERE unit_assignment.assignment_type = 'unit'
        AND unit_assignment.status IN ('Received', 'For Action')
    ");
};
