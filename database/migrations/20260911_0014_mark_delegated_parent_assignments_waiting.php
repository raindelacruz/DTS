<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        UPDATE department_action_slip_assignments division_assignment
        SET division_assignment.status = 'For Action'
        WHERE division_assignment.assignment_type = 'division'
        AND division_assignment.status = 'Received'
        AND EXISTS (
            SELECT 1
            FROM department_action_slip_assignments unit_assignment
            JOIN departments unit_department ON unit_department.id = unit_assignment.department_id
            WHERE unit_assignment.slip_id = division_assignment.slip_id
            AND unit_assignment.assignment_type = 'unit'
            AND unit_department.parent_id = division_assignment.department_id
            AND unit_assignment.status NOT IN ('Completed', 'Confirmed', 'Cancelled')
        )
    ");

    $pdo->exec("
        UPDATE department_action_slip_assignments unit_assignment
        SET unit_assignment.status = 'For Action'
        WHERE unit_assignment.assignment_type = 'unit'
        AND unit_assignment.status = 'Received'
        AND EXISTS (
            SELECT 1
            FROM department_action_slip_assignments staff_assignment
            WHERE staff_assignment.slip_id = unit_assignment.slip_id
            AND staff_assignment.assignment_type = 'staff'
            AND staff_assignment.department_id = unit_assignment.department_id
            AND staff_assignment.status NOT IN ('Completed', 'Confirmed', 'Cancelled')
        )
    ");
};
