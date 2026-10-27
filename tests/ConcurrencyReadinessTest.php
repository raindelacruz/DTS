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

    'action slip title stores manual form entry' => function ($root) {
        $controller = file_get_contents($root . '/app/controllers/ActionSlips.php');
        $model = file_get_contents($root . '/app/models/DepartmentActionSlip.php');
        $view = file_get_contents($root . '/app/views/action_slips/create.php');

        test_assert(stripos($view, 'name="title"') !== false, 'Create form must post a manual title field.');
        test_assert(stripos($controller, "'title' => trim(\$_POST['title'] ?? '')") !== false, 'Controller must read the submitted title.');
        test_assert(stripos($controller, "'title' => \$values['title']") !== false, 'Controller must pass the submitted title into creation.');
        test_assert(stripos($model, "'title' => trim((string) (\$data['title'] ?? ''))") !== false, 'Model create must save the submitted title, not the slip number.');
        test_assert(stripos($model, "SET title = slip_number") === false, 'Model must not overwrite blank/manual title state with slip numbers at runtime.');
        test_assert(stripos($model, "\$data['title'] ?? \$slipNumber") === false, 'Draft creation must not fall back to the slip number as title.');
    },

    'action slip workflow uses assignment status rollups' => function ($root) {
        $controller = file_get_contents($root . '/app/controllers/ActionSlips.php');
        $model = file_get_contents($root . '/app/models/DepartmentActionSlip.php');
        $view = file_get_contents($root . '/app/views/action_slips/view.php');

        test_assert(stripos($model, 'rollupAssignmentStatus') !== false, 'Slip status should be calculated from assignment states.');
        test_assert(stripos($model, "private const ASSIGNMENT_CONFIRMED = 'Confirmed'") !== false, 'Confirmed assignment state should hide completed work from further action.');
        test_assert(stripos($model, 'updateCompletedAssignmentsStatus') !== false, 'Completion confirmation should only touch completed assignments.');
        test_assert(stripos($model, 'private function updateAssignmentById') !== false, 'Unit completion confirmation should update a selected assignment by id.');
        test_assert(stripos($model, "updateAssignmentStatus(\$slipId, 'division', \$actorDepartmentId, null, self::STATUS_FOR_ACTION") !== false, 'Division assignment should wait after delegating to units.');
        test_assert(stripos($model, 'assignmentTypeForDepartment') !== false, 'Staff delegation should update the manager assignment level for department, division, or unit managers.');
        test_assert(stripos($model, '$this->updateAssignmentStatus($slipId, $actorAssignmentType, $actorDepartmentId, null, self::STATUS_FOR_ACTION)') !== false, 'Manager assignment should wait after delegating to staff.');
        test_assert(stripos($controller, 'hasCompletedStaffAssignmentsForDepartment') !== false, 'Unit confirmation should be scoped to the current unit.');
        test_assert(stripos($controller, 'hasCompletedUnitAssignmentsForDivision') !== false, 'Division confirmation should be scoped to the current division.');
        test_assert(stripos($controller, 'hasUnresolvedStaffAssignmentsForDepartment') !== false, 'Unit completion should wait until all staff work has been confirmed or resolved.');
        test_assert(stripos($controller, '!$hasUnresolvedStaffAssignments') !== false, 'Unit completion action should be hidden while staff confirmation is pending.');
        test_assert(stripos($controller, 'hasActiveStaffAssignmentsForDepartment') !== false, 'Staff delegation should be hidden after the slip has already been delegated to staff.');
        test_assert(stripos($controller, '!$hasActiveStaffAssignments') !== false, 'Unit manager should not be able to further delegate again while staff assignments exist.');
        test_assert(stripos($controller, '$divisionWorkStatuses') !== false, 'Division manager unit delegation and completion should share the same eligible work statuses.');
        test_assert(stripos($controller, 'DepartmentActionSlip::STATUS_FOR_ACTION') !== false, 'Division manager actions should remain available while the division assignment is For Action.');
        test_assert(stripos($controller, "in_array(\$divisionAssignmentStatus, \$divisionWorkStatuses, true)") !== false, 'Division assignment status should control delegation and completion availability.');
        test_assert(stripos($controller, '$hasPendingUnitCompletion = $isDivisionManager && $wasCompletedByUnit') !== false, 'Pending unit completions should drive division manager action visibility.');
        test_assert(stripos($controller, "'delegate_unit' => \$isDivisionManager && !\$hasPendingUnitCompletion") !== false, 'Division manager unit delegation should be hidden while unit completion is pending.');
        test_assert(stripos($controller, "'complete_division' => \$isDivisionManager && !\$hasPendingUnitCompletion") !== false, 'Division manager completion should be hidden while unit completion is pending.');
        test_assert(stripos($controller, '$canDepartmentDelegateStaff') !== false, 'Department managers should be able to release action slips to department staff.');
        test_assert(stripos($controller, '$canDivisionDelegateStaff') !== false, 'Division managers should be able to delegate action slips to division staff.');
        test_assert(stripos($controller, '$canUnitDelegateStaff') !== false, 'Unit managers should be able to delegate action slips to unit staff.');
        test_assert(stripos($controller, "'delegate_staff_to_department_staff'") !== false, 'Department staff delegation should have a distinct action context.');
        test_assert(stripos($controller, "'delegate_staff_to_division_staff'") !== false, 'Division staff delegation should have a distinct action context.');
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

    'document prefixes ignore unit-level codes' => function ($root) {
        $source = file_get_contents($root . '/app/models/Document.php');

        test_assert(stripos($source, 'resolveDocumentPrefixDepartmentId') !== false, 'Document prefix generation should resolve the effective prefix department.');
        test_assert(stripos($source, "!== 'unit'") !== false, 'Only unit departments should be remapped for document prefixes.');
        test_assert(substr_count($source, 'resolveDocumentPrefixDepartmentId((int)') >= 2, 'Both preview and create flows should normalize unit departments before sequence lookup.');
        test_assert(stripos($source, "office_level IN ('department', 'division')") !== false, 'Unit prefixes should resolve only to a department or division parent.');
    },

    'document draft creator can edit and release with division prefix' => function ($root) {
        $controller = file_get_contents($root . '/app/controllers/Documents.php');
        $view = file_get_contents($root . '/app/views/documents/view.php');

        test_assert(stripos($controller, "\$isCreator = (int) (\$document['created_by'] ?? 0) === (int) (\$_SESSION['user_id'] ?? 0)") !== false, 'Draft ownership should include the user who created the document.');
        test_assert(stripos($controller, 'if (!$isOriginOffice && !$isCreator)') !== false, 'Draft edit access should allow either origin office or creator ownership.');
        test_assert(stripos($controller, 'if (!$isCreator && !$this->departmentModel->canRelease') !== false, 'Draft creators should be allowed to release their own documents from unit offices.');
        test_assert(stripos($view, "\$document['created_by'] ?? 0") !== false, 'Draft creator should see edit and release controls.');
    },

    'unit users can release documents to parent division chief only' => function ($root) {
        $controller = file_get_contents($root . '/app/controllers/Documents.php');
        $form = file_get_contents($root . '/app/views/documents/_form.php');

        test_assert(stripos($controller, 'divisionManagerRecipientDepartmentId') !== false, 'Document controller should resolve the allowed division-chief recipient department.');
        test_assert(stripos($controller, '$this->isUnitManager() || $this->isUnitStaff()') !== false, 'Unit heads and unit staff should be included in division-chief release eligibility.');
        test_assert(stripos($controller, '$parentId > 0 && $this->isDivisionDepartment($parentId)') !== false, 'Unit users should be restricted to managers of their parent division.');
        test_assert(stripos($controller, "findActiveManagerInDepartment((int) \$managerId, \$divisionManagerDepartmentId)") !== false, 'Selected division chief must be active in the resolved division.');
        test_assert(stripos($controller, '$toDepartmentIds[] = $divisionManagerDepartmentId') !== false, 'Release to division chief should route to the resolved division department.');
        test_assert(stripos($controller, '$divisionManagerDepartmentId = $this->divisionManagerRecipientDepartmentId()') !== false, 'Notifications should recognize division-chief direct routes.');
        test_assert(stripos($form, '$canSendToDivisionManagers') !== false, 'Document form should show the division-manager picker to eligible users.');
    },

    'admin can view all documents and history' => function ($root) {
        $controller = file_get_contents($root . '/app/controllers/Documents.php');
        $model = file_get_contents($root . '/app/models/Document.php');

        test_assert(stripos($controller, "private function isAdmin()") !== false, 'Document controller should identify admin users explicitly.');
        test_assert(stripos(str_replace("\r\n", "\n", $controller), "if (\$this->isAdmin()) {\n            return [\$document, \$deptId, false];") !== false, 'Admin document view should bypass department-scoped authorization.');
        test_assert(stripos($controller, '$this->documentModel->paginateAllDocuments') !== false, 'Admin document list should use the global document paginator.');
        test_assert(stripos($controller, "\$this->isAdmin() || \$this->documentModel->canDepartmentViewDocument") !== false, 'Admin users should see referenced documents from any office.');
        test_assert(stripos($model, 'public function paginateAllDocuments') !== false, 'Document model should provide a global admin paginator.');
        test_assert(stripos($model, 'WHERE 1=1') !== false, 'Global admin paginator should not apply department visibility predicates.');
    },
];
