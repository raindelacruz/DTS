<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        DELETE FROM department_action_slip_events
        WHERE id IN (
            SELECT id
            FROM (
                SELECT copied_event.id
                FROM department_action_slip_events copied_event
                JOIN departments target_department ON target_department.id = copied_event.to_department_id
                JOIN department_action_slips parent ON parent.id = copied_event.slip_id
                JOIN department_action_slips child ON child.parent_slip_id = parent.id
                JOIN department_action_slip_events child_event
                    ON child_event.slip_id = child.id
                    AND child_event.action = copied_event.action
                    AND child_event.actor_user_id = copied_event.actor_user_id
                    AND child_event.actor_department_id = copied_event.actor_department_id
                    AND child_event.created_at = copied_event.created_at
                WHERE copied_event.action = 'Delegated to Unit'
                AND target_department.office_level <> 'unit'
            ) removable_events
        )
    ");
};
