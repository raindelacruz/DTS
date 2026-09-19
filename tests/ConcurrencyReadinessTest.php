<?php

return [
    'action slip sequence reservation uses row locking' => function ($root) {
        $source = file_get_contents($root . '/app/models/DepartmentActionSlip.php');
        test_assert(stripos($source, 'FOR UPDATE') !== false, 'Action slip sequence reservation must use SELECT ... FOR UPDATE.');
        test_assert(stripos($source, 'beginTransaction') !== false, 'Action slip creation must reserve numbers inside a transaction.');
        test_assert(stripos($source, 'UNIQUE KEY unique_das_sequence') !== false, 'Sequence table must have a department/year/month uniqueness guard.');
    },

    'action slip fan-out records assignments on the original slip' => function ($root) {
        $controller = file_get_contents($root . '/app/controllers/ActionSlips.php');
        $model = file_get_contents($root . '/app/models/DepartmentActionSlip.php');
        $migration = file_get_contents($root . '/database/migrations/20260911_0009_action_slip_assignments.php');

        test_assert(stripos($controller, '$duplicateSlipId = $this->slipModel->create') === false, 'Delegation fan-out should not create duplicate action slips.');
        test_assert(stripos($model, 'department_action_slip_assignments') !== false, 'Action slip assignments should be stored under the original slip.');
        test_assert(stripos($migration, 'CREATE TABLE IF NOT EXISTS department_action_slip_assignments') !== false, 'A migration should add assignment tracking for existing databases.');
    },

    'action slip workflow uses assignment status rollups' => function ($root) {
        $controller = file_get_contents($root . '/app/controllers/ActionSlips.php');
        $model = file_get_contents($root . '/app/models/DepartmentActionSlip.php');
        $view = file_get_contents($root . '/app/views/action_slips/view.php');

        test_assert(stripos($model, 'rollupAssignmentStatus') !== false, 'Slip status should be calculated from assignment states.');
        test_assert(stripos($model, "private const ASSIGNMENT_CONFIRMED = 'Confirmed'") !== false, 'Confirmed assignment state should hide completed work from further action.');
        test_assert(stripos($model, 'updateCompletedAssignmentsStatus') !== false, 'Completion confirmation should only touch completed assignments.');
        test_assert(stripos($model, "updateAssignmentStatus(\$slipId, 'division', \$actorDepartmentId, null, self::STATUS_FOR_ACTION") !== false, 'Division assignment should wait after delegating to units.');
        test_assert(stripos($model, "updateAssignmentStatus(\$slipId, 'unit', \$actorDepartmentId, null, self::STATUS_FOR_ACTION") !== false, 'Unit assignment should wait after delegating to staff.');
        test_assert(stripos($controller, 'hasCompletedStaffAssignmentsForDepartment') !== false, 'Unit confirmation should be scoped to the current unit.');
        test_assert(stripos($controller, 'hasCompletedUnitAssignmentsForDivision') !== false, 'Division confirmation should be scoped to the current division.');
        test_assert(stripos($controller, 'hasUnresolvedStaffAssignmentsForDepartment') !== false, 'Unit completion should wait until all staff work has been confirmed or resolved.');
        test_assert(stripos($controller, '!$hasUnresolvedStaffAssignments') !== false, 'Unit completion action should be hidden while staff confirmation is pending.');
        test_assert(stripos($controller, 'hasActiveStaffAssignmentsForDepartment') !== false, 'Staff delegation should be hidden after the slip has already been delegated to staff.');
        test_assert(stripos($controller, '!$hasActiveStaffAssignments') !== false, 'Unit manager should not be able to further delegate again while staff assignments exist.');
        test_assert(stripos($model, '$unitStatus = $hasUnresolvedStaffAssignments ? self::STATUS_RECEIVED : self::STATUS_COMPLETED') !== false, 'Final staff confirmation should complete the unit assignment automatically.');
        test_assert(stripos($controller, '$viewDivisionId') !== false, 'Division manager dropdowns should use the viewer division assignment, not another division route on the same slip.');
        test_assert(stripos($controller, 'findDivisionAssignmentForDepartment($slipId, $viewerDepartmentId)') !== false, 'Viewer division assignment should drive division-scoped dropdowns.');
        test_assert(stripos($model, 'getCompletedUnitAssignmentsForDivision') !== false, 'Division manager confirmation should list completed unit assignments.');
        test_assert(stripos($model, 'findCompletedUnitAssignmentForDivision') !== false, 'Division manager confirmation should validate a specific completed unit assignment.');
        test_assert(stripos($view, 'Confirm Completion for <?php echo htmlspecialchars($completedUnitName); ?>') !== false, 'Division manager unit confirmation should name the unit being confirmed.');
        test_assert(stripos($view, 'Return to <?php echo htmlspecialchars($completedUnitName); ?>') !== false, 'Division manager unit return should name the unit being returned.');
        test_assert(stripos($model, "'to_department_id' => \$confirmedUnitId") !== false, 'Unit completion confirmation history should target the confirmed unit.');
        test_assert(stripos($controller, 'released_to_label') !== false, 'Action slip detail panel should support viewer-scoped Released To display.');
    },

    'action slip staff confirmations are targeted per assignment' => function ($root) {
        $controller = file_get_contents($root . '/app/controllers/ActionSlips.php');
        $model = file_get_contents($root . '/app/models/DepartmentActionSlip.php');
        $view = file_get_contents($root . '/app/views/action_slips/view.php');
        $migration = file_get_contents($root . '/database/migrations/20260911_0015_split_bulk_staff_confirmations.php');

        test_assert(stripos($model, 'findCompletedStaffAssignmentForDepartment') !== false, 'Unit manager confirmation should validate a specific completed staff assignment.');
        test_assert(stripos($model, "'to_user_id' => (int) \$assignment['user_id']") !== false, 'Staff confirmation history should identify the target staff user.');
        test_assert(stripos($controller, "\$_POST['assignment_id']") !== false, 'Staff confirmation and return requests should carry the selected assignment id.');
        test_assert(stripos($view, 'Confirm Completion for') !== false, 'The unit manager page should name the staff member being confirmed.');
        test_assert(stripos($view, 'Return to <?php echo htmlspecialchars($completedStaffName); ?>') !== false, 'The unit manager page should name the staff member being returned.');
        test_assert(stripos($migration, "event.to_user_id IS NULL") !== false, 'Existing generic confirmation events should be detected.');
        test_assert(stripos($migration, "assignment.status = 'Completed'") !== false, 'Existing bulk confirmations should restore remaining staff completions to pending confirmation.');
        test_assert(is_file($root . '/database/migrations/20260911_0016_complete_units_after_all_staff_confirmed.php'), 'Existing units with all staff confirmed should be completed by migration.');
    },

    'action slip unit prefixes avoid duplicated parent segments' => function ($root) {
        $source = file_get_contents($root . '/app/models/DepartmentActionSlip.php');
        test_assert(stripos($source, 'strcasecmp($childSegments[0], $parentSegments[count($parentSegments) - 1])') !== false, 'Child unit prefixes should collapse repeated parent segments.');
    },

    'document qr token generation handles duplicate-token races' => function ($root) {
        $source = file_get_contents($root . '/app/models/Document.php');
        test_assert(stripos($source, '1062') !== false, 'QR token generation should catch duplicate key races.');
        test_assert(stripos($source, 'uq_documents_qr_token') !== false, 'QR token column must have a unique index.');
    },
];
