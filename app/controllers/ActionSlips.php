<?php

require_once APPROOT . '/models/DepartmentActionSlip.php';
require_once APPROOT . '/models/Department.php';
require_once APPROOT . '/models/Notification.php';

class ActionSlips extends Controller
{
    private $slipModel;
    private $departmentModel;
    private $notificationModel;

    public function __construct()
    {
        requireLogin();

        $this->slipModel = new DepartmentActionSlip();
        $this->departmentModel = new Department();
        $this->notificationModel = new Notification();
    }

    private function isManager()
    {
        return ($_SESSION['role'] ?? '') === 'manager';
    }

    private function isAdmin()
    {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    private function currentDepartmentId()
    {
        return (int) ($_SESSION['department_id'] ?? 0);
    }

    private function currentUserId()
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function isParentDepartment($departmentId = null)
    {
        return $this->departmentModel->isParentDepartment((int) ($departmentId ?? $this->currentDepartmentId()));
    }

    private function isDivision($departmentId = null)
    {
        return $this->departmentModel->isDivision((int) ($departmentId ?? $this->currentDepartmentId()));
    }

    private function isUnit($departmentId = null)
    {
        return $this->departmentModel->isUnit((int) ($departmentId ?? $this->currentDepartmentId()));
    }

    private function getFilters()
    {
        return [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'department_id' => (int) ($_GET['department_id'] ?? 0),
            'division_id' => (int) ($_GET['division_id'] ?? 0),
            'assigned_staff_id' => (int) ($_GET['assigned_staff_id'] ?? 0)
        ];
    }

    private function formDefaults()
    {
        $currentDepartment = $this->departmentModel->getDepartmentById($this->currentDepartmentId());
        $receivingLevel = $this->isParentDepartment() ? 'Department' : 'Staff';

        return [
            'title' => '',
            'date_received' => date('Y-m-d'),
            'urgent' => 0,
            'receiving_level' => $receivingLevel,
            'receiving_department_id' => $receivingLevel === 'Staff' ? (string) ($currentDepartment['parent_id'] ?? '') : '',
            'receiving_department_ids' => [],
            'receiving_division_id' => $receivingLevel === 'Staff' ? (string) $this->currentDepartmentId() : '',
            'receiving_division_ids' => [],
            'assigned_staff_id' => '',
            'assigned_staff_ids' => [],
            'required_action' => '',
            'deadline' => '',
            'remarks' => ''
        ];
    }

    public function index()
    {
        try {
            $filters = $this->getFilters();
            $pagination = paginationRequest();
            $totalSlips = $this->slipModel->countVisible($this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? '', $filters);
            $filters['_limit'] = $pagination['per_page'];
            $filters['_offset'] = $pagination['offset'];
            $slips = $this->slipModel->getVisible($this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? '', $filters);

            $data = [
                'slips' => $slips,
                'statuses' => DepartmentActionSlip::statuses(),
                'filters' => $filters,
                'departments' => $this->departmentModel->getParentDepartments(),
                'divisions' => $this->getAllDivisions(),
                'staff' => $this->getVisibleStaff(),
                'status_counts' => $this->slipModel->countByVisibleStatus($this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? ''),
                'can_create' => $this->canCreate(),
                'pagination' => paginationMeta($totalSlips, $pagination['page'], $pagination['per_page'])
            ];

            $this->view('action_slips/index', $data);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'action_slips.index', 'user_id' => $this->currentUserId()]);
            flash('error', 'We could not load department action slips right now.', 'error');
            redirect('/dashboard', 303);
        }
    }

    private function getAllDivisions()
    {
        return array_values(array_filter($this->departmentModel->getAll(), function ($department) {
            return ($department['office_level'] ?? '') === 'division';
        }));
    }

    private function getVisibleStaff()
    {
        $currentDepartment = $this->departmentModel->getDepartmentById($this->currentDepartmentId());
        if (!$currentDepartment && !$this->isAdmin()) {
            return [];
        }

        if ($this->isAdmin()) {
            $departments = $this->departmentModel->getAll();
        } elseif (!empty($currentDepartment['parent_id'])) {
            // Division users can only filter by staff assigned to their division.
            $departments = [$currentDepartment];
        } else {
            // Parent-department users can filter by staff in the department itself
            // and in each division under it.
            $departments = array_merge(
                [$currentDepartment],
                $this->departmentModel->getChildDepartmentsForParent($this->currentDepartmentId())
            );
        }

        $staff = [];
        foreach ($departments as $department) {
            foreach ($this->slipModel->getActiveStaffByDepartment((int) $department['id']) as $user) {
                $staff[] = $user + ['department_name' => $department['division_name'] ?? ''];
            }
        }

        return $staff;
    }

    private function getCurrentDepartmentStaff()
    {
        $department = $this->departmentModel->getDepartmentById($this->currentDepartmentId());
        $staff = [];
        if (!$department) {
            return $staff;
        }

        foreach ($this->slipModel->getActiveStaffByDepartment((int) $department['id']) as $user) {
            $staff[] = $user + ['department_name' => $department['division_name'] ?? ''];
        }

        return $staff;
    }

    private function canCreate()
    {
        return $this->isManager() || ($_SESSION['role'] ?? '') === 'staff';
    }

    public function create()
    {
        try {
            if (!$this->canCreate()) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $state = pullFormState('action_slip_create', $this->formDefaults());
            $departmentId = (int) ($state['values']['receiving_department_id'] ?: $this->currentDepartmentId());
            if (($_SESSION['role'] ?? '') === 'staff') {
                $departmentId = $this->currentDepartmentId();
            }

            $data = [
                'values' => $state['values'],
                'errors' => $state['errors'],
                'message' => $state['message'],
                'departments' => $this->departmentModel->getParentDepartments(),
                'divisions' => $this->getAllDivisions(),
                'division_staff' => $this->getCurrentDepartmentStaff(),
                'is_parent_department' => $this->isParentDepartment(),
                'action_options' => DepartmentActionSlip::actionOptions(),
                'next_slip_number' => $this->slipModel->getNextSlipNumber($departmentId),
                'is_staff_draft' => ($_SESSION['role'] ?? '') === 'staff'
            ];

            $this->view('action_slips/create', $data);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'action_slips.create', 'user_id' => $this->currentUserId()]);
            flash('error', 'We could not open the action slip form right now.', 'error');
            redirect('/actionSlips', 303);
        }
    }

    public function store()
    {
        $values = [
            'title' => trim($_POST['title'] ?? ''),
            'date_received' => trim($_POST['date_received'] ?? ''),
            'urgent' => !empty($_POST['urgent']) ? 1 : 0,
            'receiving_level' => trim($_POST['receiving_level'] ?? 'Department'),
            'receiving_department_id' => (int) ($_POST['receiving_department_id'] ?? 0),
            'receiving_department_ids' => $this->postedDepartmentIds(),
            'receiving_division_id' => (int) ($_POST['receiving_division_id'] ?? 0),
            'receiving_division_ids' => $this->postedDivisionIds(),
            'assigned_staff_id' => (int) ($_POST['assigned_staff_id'] ?? 0),
            'assigned_staff_ids' => $this->postedStaffIds(),
            'required_action' => trim($_POST['required_action'] ?? ''),
            'deadline' => trim($_POST['deadline'] ?? ''),
            'remarks' => trim($_POST['remarks'] ?? '')
        ];
        if (empty($values['receiving_department_ids']) && $values['receiving_department_id'] > 0) {
            $values['receiving_department_ids'] = [$values['receiving_department_id']];
        }
        $values['receiving_department_id'] = (int) ($values['receiving_department_ids'][0] ?? $values['receiving_department_id']);
        if (empty($values['receiving_division_ids']) && $values['receiving_division_id'] > 0) {
            $values['receiving_division_ids'] = [$values['receiving_division_id']];
        }
        $values['receiving_division_id'] = (int) ($values['receiving_division_ids'][0] ?? $values['receiving_division_id']);
        if (empty($values['assigned_staff_ids']) && $values['assigned_staff_id'] > 0) {
            $values['assigned_staff_ids'] = [$values['assigned_staff_id']];
        }
        $values['assigned_staff_id'] = (int) ($values['assigned_staff_ids'][0] ?? 0);

        try {
            requirePost();
            validateCsrfOrFail();

            if (!$this->canCreate()) {
                throw new AuthorizationException('Unauthorized action.');
            }

            if (($_SESSION['role'] ?? '') === 'staff') {
                $this->storeDraft($values);
                return;
            }

            $errors = $this->validateCreateValues($values);
            if (!empty($errors)) {
                throw new ValidationException('Please correct the highlighted fields.', $errors);
            }

            $attachment = $this->handleUpload('attachment', 'action_slips');
            $createdSlipIds = [];

            if ($values['receiving_level'] === 'Staff') {
                $firstStaffId = (int) array_shift($values['assigned_staff_ids']);
                $slipValues = $values;
                $slipValues['assigned_staff_id'] = $firstStaffId;
                $slipValues['target_staff_department_id'] = $this->findStaffDepartmentInCurrentScope($firstStaffId);
                $slipValues = array_merge($slipValues, $this->hiddenCreateValues($slipValues));
                $slipId = $this->slipModel->create($slipValues + [
                    'attachment' => $attachment,
                    'created_by' => $this->currentUserId(),
                    'actor_department_id' => $this->currentDepartmentId()
                ]);
                $createdSlipIds[] = $slipId;
                $this->notificationModel->create($firstStaffId, 'Action slip released', 'An action slip was released to you.', '/actionSlips/show/' . $slipId);

                foreach ($values['assigned_staff_ids'] as $staffId) {
                    $this->slipModel->delegateToStaff($slipId, (int) $staffId, $this->currentUserId(), $this->currentDepartmentId(), $values['remarks']);
                    $this->notificationModel->create((int) $staffId, 'Action slip released', 'An action slip was released to you.', '/actionSlips/show/' . $slipId);
                }
            } elseif ($values['receiving_level'] === 'Division') {
                $firstDivisionId = (int) array_shift($values['receiving_division_ids']);
                $slipValues = $values;
                $slipValues['receiving_division_id'] = $firstDivisionId;
                $slipValues['receiving_division_ids'] = [$firstDivisionId];
                $slipValues = array_merge($slipValues, $this->hiddenCreateValues($slipValues));
                $slipId = $this->slipModel->create($slipValues + [
                    'attachment' => $attachment,
                    'created_by' => $this->currentUserId(),
                    'actor_department_id' => $this->currentDepartmentId()
                ]);
                $createdSlipIds[] = $slipId;
                $this->notificationModel->notifyDepartmentManagers([$firstDivisionId], 'Action slip released', 'A new action slip was released to your division.', '/actionSlips/show/' . $slipId, $this->currentUserId());

                foreach ($values['receiving_division_ids'] as $divisionId) {
                    $this->slipModel->delegateToDivision($slipId, (int) $divisionId, $this->currentUserId(), $this->currentDepartmentId(), $values['remarks'], DepartmentActionSlip::STATUS_RELEASED, 'Released to Division');
                    $this->notificationModel->notifyDepartmentManagers([(int) $divisionId], 'Action slip released', 'A new action slip was released to your division.', '/actionSlips/show/' . $slipId, $this->currentUserId());
                }
            } else {
                $firstDepartmentId = (int) array_shift($values['receiving_department_ids']);
                $slipValues = $values;
                $slipValues['receiving_department_id'] = $firstDepartmentId;
                $slipValues['receiving_department_ids'] = [$firstDepartmentId];
                $slipValues = array_merge($slipValues, $this->hiddenCreateValues($slipValues));
                $slipId = $this->slipModel->create($slipValues + [
                    'attachment' => $attachment,
                    'created_by' => $this->currentUserId(),
                    'actor_department_id' => $this->currentDepartmentId()
                ]);
                $createdSlipIds[] = $slipId;
                $this->notificationModel->notifyDepartmentManagers([$firstDepartmentId], 'Action slip released', 'A new action slip was released to your department.', '/actionSlips/show/' . $slipId, $this->currentUserId());

                foreach ($values['receiving_department_ids'] as $departmentId) {
                    $this->slipModel->routeToDepartment($slipId, (int) $departmentId, $this->currentUserId(), $this->currentDepartmentId(), $values['remarks'], DepartmentActionSlip::STATUS_RELEASED, 'Released to Department');
                    $this->notificationModel->notifyDepartmentManagers([(int) $departmentId], 'Action slip released', 'A new action slip was released to your department.', '/actionSlips/show/' . $slipId, $this->currentUserId());
                }
            }

            $createdCount = count($createdSlipIds);
            flash('success', $createdCount > 1 ? $createdCount . ' department action slips created successfully.' : 'Department action slip created successfully.', 'success');
            redirect($createdCount === 1 ? '/actionSlips/show/' . $createdSlipIds[0] : '/actionSlips', 303);
        } catch (ValidationException $e) {
            storeFormState('action_slip_create', $values, $e->getErrors(), $e->getMessage());
            redirect('/actionSlips/create', 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to create a department action slip.', 'error');
            redirect('/actionSlips', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'action_slips.store', 'user_id' => $this->currentUserId()]);
            storeFormState('action_slip_create', $values, [], 'We could not save the action slip right now. Please try again.');
            redirect('/actionSlips/create', 303);
        }
    }

    private function storeDraft($values)
    {
        $errors = $this->validateDraftValues($values);
        if (!empty($errors)) {
            throw new ValidationException('Please correct the highlighted fields.', $errors);
        }

        if (empty($_FILES['attachment']['name'])) {
            throw new ValidationException('Please correct the highlighted fields.', ['attachment' => 'Attachment is required.']);
        }

        $attachment = $this->handleUpload('attachment', 'action_slips');
        $currentDepartment = $this->departmentModel->getDepartmentById($this->currentDepartmentId());
        $receivingDepartmentId = (int) ($currentDepartment['parent_id'] ?? 0) > 0
            ? (int) $currentDepartment['parent_id']
            : $this->currentDepartmentId();
        $receivingDivisionId = (int) ($currentDepartment['parent_id'] ?? 0) > 0
            ? $this->currentDepartmentId()
            : null;

        $slipId = $this->slipModel->createDraft([
            'title' => $values['title'],
            'date_received' => $values['date_received'],
            'attachment' => $attachment,
            'created_by' => $this->currentUserId(),
            'actor_department_id' => $this->currentDepartmentId(),
            'receiving_department_id' => $receivingDepartmentId,
            'receiving_division_id' => $receivingDivisionId
        ]);

        $managerDepartmentId = $receivingDivisionId ?: $receivingDepartmentId;
        $this->notificationModel->notifyDepartmentManagers([$managerDepartmentId], 'Draft action slip created', 'A staff draft action slip is ready for review.', '/actionSlips/show/' . $slipId, $this->currentUserId());
        flash('success', 'Draft action slip created and sent to your manager.', 'success');
        redirect('/actionSlips/show/' . $slipId, 303);
    }

    private function postedStaffIds()
    {
        $staffIds = $_POST['assigned_staff_ids'] ?? [];
        if (!is_array($staffIds)) {
            $staffIds = [$staffIds];
        }

        return array_values(array_unique(array_filter(array_map('intval', $staffIds), function ($staffId) {
            return $staffId > 0;
        })));
    }

    private function postedDelegateStaffIds()
    {
        $staffIds = $_POST['staff_ids'] ?? ($_POST['assigned_staff_ids'] ?? []);
        if (!is_array($staffIds)) {
            $staffIds = [$staffIds];
        }

        if (empty($staffIds) && !empty($_POST['staff_id'])) {
            $staffIds = [$_POST['staff_id']];
        }

        return array_values(array_unique(array_filter(array_map('intval', $staffIds), function ($staffId) {
            return $staffId > 0;
        })));
    }

    private function postedUnitManagerIds()
    {
        $managerIds = $_POST['unit_manager_ids'] ?? [];
        if (!is_array($managerIds)) {
            $managerIds = [$managerIds];
        }

        if (empty($managerIds) && !empty($_POST['unit_manager_id'])) {
            $managerIds = [$_POST['unit_manager_id']];
        }

        return array_values(array_unique(array_filter(array_map('intval', $managerIds), function ($managerId) {
            return $managerId > 0;
        })));
    }

    private function postedDepartmentIds()
    {
        $departmentIds = $_POST['receiving_department_ids'] ?? [];
        if (!is_array($departmentIds)) {
            $departmentIds = [$departmentIds];
        }

        if (empty($departmentIds) && !empty($_POST['receiving_department_id'])) {
            $departmentIds = [$_POST['receiving_department_id']];
        }

        return array_values(array_unique(array_filter(array_map('intval', $departmentIds), function ($departmentId) {
            return $departmentId > 0;
        })));
    }

    private function postedDivisionIds()
    {
        $divisionIds = $_POST['receiving_division_ids'] ?? [];
        if (!is_array($divisionIds)) {
            $divisionIds = [$divisionIds];
        }

        if (empty($divisionIds) && !empty($_POST['receiving_division_id'])) {
            $divisionIds = [$_POST['receiving_division_id']];
        }

        return array_values(array_unique(array_filter(array_map('intval', $divisionIds), function ($divisionId) {
            return $divisionId > 0;
        })));
    }

    private function validateCreateValues($values)
    {
        $errors = [];
        if ($values['date_received'] === '') {
            $errors['date_received'] = 'Date of Action Slip is required.';
        }
        if (trim($values['title'] ?? '') === '') {
            $errors['title'] = 'Title is required.';
        }
        if (!in_array($values['receiving_level'], ['Department', 'Division', 'Staff'], true)) {
            $errors['receiving_level'] = 'Select a valid release target.';
        }

        if ($this->isManager() && $this->isParentDepartment()) {
            if ($values['receiving_level'] === 'Department') {
                $departmentIds = $values['receiving_department_ids'] ?? [];
                if (empty($departmentIds) && (int) ($values['receiving_department_id'] ?? 0) > 0) {
                    $departmentIds = [(int) $values['receiving_department_id']];
                }
                if (empty($departmentIds)) {
                    $errors['receiving_department_id'] = 'Target department is required.';
                } elseif (!$this->departmentModel->areParentDepartments($departmentIds)) {
                    $errors['receiving_department_id'] = 'Select a valid department.';
                } elseif (in_array($this->currentDepartmentId(), $departmentIds, true)) {
                    $errors['receiving_department_id'] = 'Select another department.';
                }
            } elseif ($values['receiving_level'] === 'Division') {
                $values['receiving_department_id'] = $this->currentDepartmentId();
                $divisionIds = $values['receiving_division_ids'] ?? [];
                if (empty($divisionIds) && (int) ($values['receiving_division_id'] ?? 0) > 0) {
                    $divisionIds = [(int) $values['receiving_division_id']];
                }
                if (empty($divisionIds)) {
                    $errors['receiving_division_id'] = 'Target division is required.';
                } elseif (!$this->departmentModel->areChildDepartmentsOfParent($divisionIds, $this->currentDepartmentId())) {
                    $errors['receiving_division_id'] = 'Select a division under your department.';
                }
            } elseif ($values['receiving_level'] === 'Staff') {
                $values['receiving_department_id'] = $this->currentDepartmentId();
                $values['receiving_division_id'] = 0;
                if (empty($values['assigned_staff_ids'])) {
                    $errors['assigned_staff_id'] = 'Select a staff member under your department.';
                } else {
                    foreach ($values['assigned_staff_ids'] as $staffId) {
                        if ($this->findStaffDepartmentInCurrentScope((int) $staffId) === null) {
                            $errors['assigned_staff_id'] = 'Select active staff members under your department.';
                            break;
                        }
                    }
                }
            }
        } elseif ($this->isManager() && !$this->isParentDepartment()) {
            if ($values['receiving_level'] !== 'Staff') {
                $errors['receiving_level'] = 'Division managers can release action slips to staff only.';
            }
            $currentDepartment = $this->departmentModel->getDepartmentById($this->currentDepartmentId());
            $values['receiving_department_id'] = (int) ($currentDepartment['parent_id'] ?? 0);
            $values['receiving_division_id'] = $this->currentDepartmentId();
            if (empty($values['assigned_staff_ids'])) {
                $errors['assigned_staff_id'] = 'Select a staff member under your division.';
            } else {
                foreach ($values['assigned_staff_ids'] as $staffId) {
                    if (!$this->slipModel->findActiveUserInDepartment((int) $staffId, $this->currentDepartmentId(), 'staff')) {
                        $errors['assigned_staff_id'] = 'Select active staff members under your division.';
                        break;
                    }
                }
            }
        } elseif (!$this->isAdmin()) {
            $errors['receiving_level'] = 'You are not allowed to create action slips.';
        }

        if (!in_array($values['required_action'], DepartmentActionSlip::actionOptions(), true)) {
            $errors['required_action'] = 'Select a valid action.';
        }
        if ($values['deadline'] !== '' && $values['date_received'] !== '' && $values['deadline'] < $values['date_received']) {
            $errors['deadline'] = 'Deadline cannot be earlier than the action slip date.';
        }
        return $errors;
    }

    private function findStaffDepartmentInCurrentScope($staffId)
    {
        $staffId = (int) $staffId;
        $currentDepartmentId = $this->currentDepartmentId();

        $user = $this->slipModel->findActiveUserInDepartment($staffId, $currentDepartmentId, 'staff');
        if ($user) {
            return (int) $user['department_id'];
        }

        return null;
    }

    private function validateDraftValues($values)
    {
        $errors = [];
        if ($values['date_received'] === '') {
            $errors['date_received'] = 'Date of Action Slip is required.';
        }
        if (trim($values['title'] ?? '') === '') {
            $errors['title'] = 'Title is required.';
        }
        return $errors;
    }

    private function hiddenCreateValues($values)
    {
        $currentDepartment = $this->departmentModel->getDepartmentById($this->currentDepartmentId());

        if ($values['receiving_level'] === 'Division') {
            $values['receiving_department_id'] = $this->currentDepartmentId();
            $values['release_action'] = 'Released to Division';
        } elseif ($values['receiving_level'] === 'Staff') {
            $isParentDepartment = empty($currentDepartment['parent_id']);
            $values['receiving_department_id'] = $isParentDepartment
                ? $this->currentDepartmentId()
                : (int) $currentDepartment['parent_id'];
            $targetStaffDepartmentId = (int) ($values['target_staff_department_id'] ?? 0);
            $values['receiving_division_id'] = $isParentDepartment
                ? ($targetStaffDepartmentId > 0 && $targetStaffDepartmentId !== $this->currentDepartmentId() ? $targetStaffDepartmentId : null)
                : $this->currentDepartmentId();
            $values['release_action'] = 'Released to Staff';
        } else {
            $values['release_action'] = 'Released to Department';
        }

        return [
            'external_source' => 'Internal Action Slip',
            'title' => $values['title'],
            'subject' => $values['required_action'],
            'reference_number' => null,
            'remarks' => $values['remarks'],
            'receiving_department_id' => $values['receiving_department_id'],
            'receiving_division_id' => $values['receiving_division_id'],
            'assigned_staff_id' => $values['assigned_staff_id'],
            'release_action' => $values['release_action']
        ];
    }

    public function show($id)
    {
        $slipId = (int) $id;

        try {
            if (!$this->slipModel->canUserView($slipId, $this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? '')) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $slip = $this->slipModel->findById($slipId);
            if (!$slip) {
                throw new NotFoundException('Action slip not found.');
            }

            if (!empty($slip['parent_slip_id'])) {
                $parentSlipId = (int) $slip['parent_slip_id'];
                if ($this->slipModel->canUserView($parentSlipId, $this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? '')) {
                    $parentSlip = $this->slipModel->findById($parentSlipId);
                    if ($parentSlip) {
                        $slipId = $parentSlipId;
                        $slip = $parentSlip;
                    }
                }
            }

            $currentDepartmentId = (int) ($slip['current_department_id'] ?: $slip['receiving_department_id']);
            $currentDivisionId = (int) ($slip['current_division_id'] ?: 0);
            $currentUnitId = (int) ($slip['current_unit_id'] ?? 0);
            $viewerDepartmentId = $this->currentDepartmentId();
            $viewerDivisionAssignment = $this->slipModel->findDivisionAssignmentForDepartment($slipId, $viewerDepartmentId);
            $viewDivisionId = ($this->isManager() && $this->isDivision($viewerDepartmentId) && !empty($viewerDivisionAssignment))
                ? $viewerDepartmentId
                : $currentDivisionId;
            $staffDepartmentId = $this->isManager()
                ? $viewerDepartmentId
                : ($currentUnitId > 0 ? $currentUnitId : ($viewDivisionId > 0 ? $viewDivisionId : $currentDepartmentId));
            $releasedToLabel = $slip['current_unit_name'] ?: ($slip['current_division_name'] ?: ($slip['current_department_name'] ?? '-'));
            $releasedToSubtext = $slip['assigned_staff_name'] ?? '';
            if (!empty($viewerDivisionAssignment)) {
                $viewerDepartment = $this->departmentModel->getDepartmentById($viewerDepartmentId);
                $releasedToLabel = $viewerDepartment['division_name'] ?? $releasedToLabel;
                $releasedToSubtext = '';
            }

            $data = [
                'slip' => $slip,
                'events' => $this->slipModel->getEvents($slipId),
                'departments' => $this->departmentModel->getParentDepartments(),
                'child_divisions' => $this->departmentModel->getChildDepartmentsForParent($currentDepartmentId),
                'child_units' => $viewDivisionId > 0 ? $this->departmentModel->getUnitsForDivision($viewDivisionId) : [],
                'unit_managers' => $viewDivisionId > 0 ? $this->slipModel->getActiveManagersByChildUnits($viewDivisionId) : [],
                'division_staff' => $this->isManager() && $this->isDivision($viewerDepartmentId)
                    ? $this->slipModel->getActiveStaffInDivisionHierarchy($viewerDepartmentId, $slipId)
                    : $this->slipModel->getEligibleStaffByDepartment($slipId, $staffDepartmentId),
                'staff_assignment' => $this->slipModel->findStaffAssignmentForUser($slipId, $this->currentUserId()),
                'completed_staff_assignments' => $this->slipModel->getCompletedStaffAssignmentsForDepartment($slipId, $this->currentDepartmentId()),
                'completed_unit_assignments' => $this->slipModel->getCompletedUnitAssignmentsForDivision($slipId, $this->currentDepartmentId()),
                'released_to_label' => $releasedToLabel,
                'released_to_subtext' => $releasedToSubtext,
                'division_delegated' => $this->isManager() && $this->isDivision($viewerDepartmentId)
                    && $this->slipModel->hasDelegatedAssignmentsForDivision($slipId, $viewerDepartmentId)
                    && ($this->slipModel->hasUnresolvedUnitAssignmentsForDivision($slipId, $viewerDepartmentId)
                        || $this->slipModel->hasUnresolvedStaffAssignmentsForDepartment($slipId, $viewerDepartmentId)),
                'actions' => $this->allowedActions($slip),
                'action_options' => DepartmentActionSlip::actionOptions()
            ];

            $this->view('action_slips/view', $data);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to view that department action slip.', 'error');
            redirect('/actionSlips', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Department action slip not found.', 'error');
            redirect('/actionSlips', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'action_slips.show', 'slip_id' => $slipId]);
            flash('error', 'We could not load that department action slip right now.', 'error');
            redirect('/actionSlips', 303);
        }
    }

    private function allowedActions($slip)
    {
        $status = $slip['status'] ?? '';
        $deptId = $this->currentDepartmentId();
        $currentDepartmentId = (int) ($slip['current_department_id'] ?: $slip['receiving_department_id']);
        $currentDivisionId = (int) ($slip['current_division_id'] ?? 0);
        $currentUnitId = (int) ($slip['current_unit_id'] ?? 0);
        $assignedStaffId = (int) ($slip['assigned_staff_id'] ?? 0);
        $staffAssignment = $this->slipModel->findStaffAssignmentForUser((int) $slip['id'], $this->currentUserId());
        $staffAssignmentStatus = $staffAssignment['status'] ?? '';
        $departmentAssignment = $this->slipModel->findDepartmentAssignmentForDepartment((int) $slip['id'], $deptId);
        $departmentAssignmentStatus = $departmentAssignment['status'] ?? '';
        $divisionAssignment = $this->slipModel->findDivisionAssignmentForDepartment((int) $slip['id'], $deptId);
        $divisionAssignmentStatus = $divisionAssignment['status'] ?? '';
        $unitAssignment = $this->slipModel->findUnitAssignmentForDepartment((int) $slip['id'], $deptId);
        $unitAssignmentStatus = $unitAssignment['status'] ?? '';
        $receivingLevel = $slip['receiving_level'] ?? '';
        $isDepartmentManager = $this->isManager() && $this->isParentDepartment($deptId) && ($currentDepartmentId === $deptId || !empty($departmentAssignment));
        $isDivisionManager = $this->isManager() && $this->isDivision($deptId) && ($currentDivisionId === $deptId || !empty($divisionAssignment));
        $isUnitManager = $this->isManager() && $this->isUnit($deptId) && ($currentUnitId === $deptId || !empty($unitAssignment));
        $isAssignedStaff = $assignedStaffId === $this->currentUserId() || !empty($staffAssignment);
        $isDepartmentLevelSlip = $receivingLevel === 'Department' && $currentDivisionId === 0 && $assignedStaffId === 0;
        $wasCompletedByStaff = $this->slipModel->hasCompletedStaffAssignmentsForDepartment((int) $slip['id'], $deptId);
        $wasCompletedByUnit = $this->slipModel->hasCompletedUnitAssignmentsForDivision((int) $slip['id'], $deptId);
        $hasPendingUnitCompletion = $isDivisionManager && $wasCompletedByUnit;
        $divisionDelegated = $isDivisionManager && $this->slipModel->hasDelegatedAssignmentsForDivision((int) $slip['id'], $deptId);
        $hasUnresolvedStaffAssignments = $this->slipModel->hasUnresolvedStaffAssignmentsForDepartment((int) $slip['id'], $deptId);
        $hasActiveStaffAssignments = $this->slipModel->hasActiveStaffAssignmentsForDepartment((int) $slip['id'], $deptId);
        $isReturnedToStaff = !empty($staffAssignment) && $staffAssignmentStatus === DepartmentActionSlip::STATUS_RETURNED;
        $divisionWorkStatuses = [
            DepartmentActionSlip::STATUS_RECEIVED,
            DepartmentActionSlip::STATUS_FOR_ACTION,
            DepartmentActionSlip::STATUS_RETURNED
        ];
        $managerStaffDelegationStatuses = [
            DepartmentActionSlip::STATUS_RECEIVED,
            DepartmentActionSlip::STATUS_FOR_ACTION,
            DepartmentActionSlip::STATUS_RETURNED
        ];
        $canDepartmentDelegateStaff = $isDepartmentManager && (
            (!empty($departmentAssignment) && in_array($departmentAssignmentStatus, $managerStaffDelegationStatuses, true))
            || (empty($departmentAssignment) && in_array($status, $managerStaffDelegationStatuses, true))
        );
        $canDivisionDelegateStaff = $isDivisionManager && !$divisionDelegated && (
            (!empty($divisionAssignment) && in_array($divisionAssignmentStatus, $managerStaffDelegationStatuses, true))
            || (empty($divisionAssignment) && in_array($status, $managerStaffDelegationStatuses, true))
        );
        $canUnitDelegateStaff = $isUnitManager && (
            (!empty($unitAssignment) && in_array($unitAssignmentStatus, $managerStaffDelegationStatuses, true))
            || (empty($unitAssignment) && in_array($status, $managerStaffDelegationStatuses, true))
        );
        $canDelegateStaff = !$isReturnedToStaff
            && !$hasActiveStaffAssignments
            && ($canDepartmentDelegateStaff || $canDivisionDelegateStaff || $canUnitDelegateStaff);

        return [
            'delegate_recipients' => $canDivisionDelegateStaff,
            'receive_department' => $isDepartmentManager && ($isDepartmentLevelSlip || !empty($departmentAssignment)) && (
                (!empty($departmentAssignment) && $departmentAssignmentStatus === DepartmentActionSlip::STATUS_RELEASED)
                || (empty($departmentAssignment) && $status === DepartmentActionSlip::STATUS_RELEASED)
            ),
            'finalize_draft' => $status === DepartmentActionSlip::STATUS_DRAFT && ($currentDivisionId > 0 ? $isDivisionManager : $isDepartmentManager),
            'cancel_draft' => $status === DepartmentActionSlip::STATUS_DRAFT && ($currentDivisionId > 0 ? $isDivisionManager : $isDepartmentManager),
            'route_department' => $isDepartmentManager && ($currentDivisionId === 0 || !empty($departmentAssignment)) && (
                (!empty($departmentAssignment) && in_array($departmentAssignmentStatus, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true))
                || (empty($departmentAssignment) && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true))
            ),
            'delegate_division' => $isDepartmentManager && ($currentDivisionId === 0 || !empty($departmentAssignment)) && (
                (!empty($departmentAssignment) && in_array($departmentAssignmentStatus, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true))
                || (empty($departmentAssignment) && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true))
            ),
            'complete_department' => $isDepartmentManager && ($currentDivisionId === 0 || !empty($departmentAssignment)) && (
                (!empty($departmentAssignment) && in_array($departmentAssignmentStatus, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_FOR_ACTION, DepartmentActionSlip::STATUS_RETURNED], true))
                || (empty($departmentAssignment) && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_FOR_ACTION, DepartmentActionSlip::STATUS_RETURNED], true))
            ),
            'close' => false,
            'receive_division' => $isDivisionManager && (($currentUnitId === 0 && $assignedStaffId === 0) || !empty($divisionAssignment)) && (
                (!empty($divisionAssignment) && in_array($divisionAssignmentStatus, [DepartmentActionSlip::STATUS_RELEASED, DepartmentActionSlip::STATUS_DELEGATED], true))
                || (empty($divisionAssignment) && in_array($status, [DepartmentActionSlip::STATUS_RELEASED, DepartmentActionSlip::STATUS_DELEGATED], true))
            ),
            'delegate_unit' => $isDivisionManager && !$hasPendingUnitCompletion && !$divisionDelegated && (($currentUnitId === 0 && $assignedStaffId === 0) || !empty($divisionAssignment)) && (
                (!empty($divisionAssignment) && in_array($divisionAssignmentStatus, $divisionWorkStatuses, true))
                || (empty($divisionAssignment) && in_array($status, $divisionWorkStatuses, true))
            ),
            'receive_unit' => $isUnitManager && (
                (!empty($unitAssignment) && $unitAssignmentStatus === DepartmentActionSlip::STATUS_DELEGATED)
                || (empty($unitAssignment) && $assignedStaffId === 0 && $status === DepartmentActionSlip::STATUS_DELEGATED)
            ),
            'delegate_staff' => $canDelegateStaff,
            'delegate_staff_to_department_staff' => $canDelegateStaff && $isDepartmentManager,
            'delegate_staff_to_division_staff' => $canDelegateStaff && $isDivisionManager,
            'delegate_staff_to_unit_staff' => $canDelegateStaff && $isUnitManager,
            'complete_unit' => $isUnitManager && !$hasUnresolvedStaffAssignments && (
                (!empty($unitAssignment) && in_array($unitAssignmentStatus, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true))
                || (empty($unitAssignment) && $assignedStaffId === 0 && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true))
            ),
            'complete_division' => $isDivisionManager && !$hasPendingUnitCompletion && !$divisionDelegated && (($currentUnitId === 0 && $assignedStaffId === 0) || !empty($divisionAssignment)) && (
                (!empty($divisionAssignment) && in_array($divisionAssignmentStatus, $divisionWorkStatuses, true))
                || (empty($divisionAssignment) && in_array($status, $divisionWorkStatuses, true))
            ),
            'confirm_division' => $isDivisionManager && $wasCompletedByStaff,
            'confirm_unit' => $isUnitManager && $wasCompletedByStaff,
            'confirm_unit_for_division' => $isDivisionManager && $wasCompletedByUnit,
            'return_staff_completion' => ($isDivisionManager || $isUnitManager) && $wasCompletedByStaff,
            'return_unit_completion' => $isDivisionManager && $wasCompletedByUnit,
            'start_staff' => $isAssignedStaff && (
                (!empty($staffAssignment) && in_array($staffAssignmentStatus, [DepartmentActionSlip::STATUS_RELEASED, DepartmentActionSlip::STATUS_DELEGATED, DepartmentActionSlip::STATUS_RETURNED], true))
                || (empty($staffAssignment) && in_array($status, [DepartmentActionSlip::STATUS_RELEASED, DepartmentActionSlip::STATUS_DELEGATED], true))
            ),
            'complete_staff' => $isAssignedStaff && (
                (!empty($staffAssignment) && in_array($staffAssignmentStatus, [DepartmentActionSlip::STATUS_FOR_ACTION, DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true))
                || (empty($staffAssignment) && in_array($status, [DepartmentActionSlip::STATUS_FOR_ACTION, DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true))
            ),
            'return' => ((($isDivisionManager || $isUnitManager) && $wasCompletedByStaff) || ($isDivisionManager && $wasCompletedByUnit))
        ];
    }

    public function finalizeDraft($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['finalize_draft']) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $values = [
                'title' => trim($_POST['title'] ?? ($slip['title'] ?? '')),
                'date_received' => trim($_POST['date_received'] ?? ($slip['date_received'] ?? '')),
                'urgent' => !empty($_POST['urgent']) ? 1 : 0,
                'receiving_level' => trim($_POST['receiving_level'] ?? 'Department'),
                'receiving_department_id' => (int) ($_POST['receiving_department_id'] ?? 0),
                'receiving_department_ids' => $this->postedDepartmentIds(),
                'receiving_division_id' => (int) ($_POST['receiving_division_id'] ?? 0),
                'receiving_division_ids' => $this->postedDivisionIds(),
                'assigned_staff_id' => (int) ($_POST['assigned_staff_id'] ?? 0),
                'assigned_staff_ids' => $this->postedStaffIds(),
                'required_action' => trim($_POST['required_action'] ?? ''),
                'deadline' => trim($_POST['deadline'] ?? ''),
                'remarks' => trim($_POST['remarks'] ?? '')
            ];
            if (empty($values['receiving_department_ids']) && $values['receiving_department_id'] > 0) {
                $values['receiving_department_ids'] = [$values['receiving_department_id']];
            }
            $values['receiving_department_id'] = (int) ($values['receiving_department_ids'][0] ?? $values['receiving_department_id']);
            if (empty($values['receiving_division_ids']) && $values['receiving_division_id'] > 0) {
                $values['receiving_division_ids'] = [$values['receiving_division_id']];
            }
            $values['receiving_division_id'] = (int) ($values['receiving_division_ids'][0] ?? $values['receiving_division_id']);
            if (empty($values['assigned_staff_ids']) && $values['assigned_staff_id'] > 0) {
                $values['assigned_staff_ids'] = [$values['assigned_staff_id']];
            }
            $values['assigned_staff_id'] = (int) ($values['assigned_staff_ids'][0] ?? 0);

            $errors = $this->validateCreateValues($values);
            if (!empty($errors)) {
                throw new ValidationException(reset($errors));
            }

            if ($values['receiving_level'] === 'Staff' && count($values['assigned_staff_ids']) > 1) {
                $firstStaffId = (int) array_shift($values['assigned_staff_ids']);
                $firstValues = $values;
                $firstValues['assigned_staff_id'] = $firstStaffId;
                $firstValues['assigned_staff_ids'] = [$firstStaffId];
                $firstValues['target_staff_department_id'] = $this->findStaffDepartmentInCurrentScope($firstStaffId);
                $firstValues = array_merge($firstValues, $this->hiddenCreateValues($firstValues));

                $this->slipModel->releaseDraft($slipId, $firstValues + [
                    'actor_user_id' => $this->currentUserId(),
                    'actor_department_id' => $this->currentDepartmentId()
                ]);
                $this->notificationModel->create($firstStaffId, 'Action slip released', $slip['slip_number'] . ' was released to you.', '/actionSlips/show/' . $slipId);

                foreach ($values['assigned_staff_ids'] as $staffId) {
                    $this->slipModel->delegateToStaff($slipId, (int) $staffId, $this->currentUserId(), $this->currentDepartmentId(), $values['remarks']);
                    $this->notificationModel->create((int) $staffId, 'Action slip released', $slip['slip_number'] . ' was released to you.', '/actionSlips/show/' . $slipId);
                }

                flash('success', 'Draft action slip finalized and released to selected staff.', 'success');
                return;
            }

            if ($values['receiving_level'] === 'Department' && count($values['receiving_department_ids']) > 1) {
                $firstDepartmentId = (int) array_shift($values['receiving_department_ids']);
                $firstValues = $values;
                $firstValues['receiving_department_id'] = $firstDepartmentId;
                $firstValues['receiving_department_ids'] = [$firstDepartmentId];
                $firstValues = array_merge($firstValues, $this->hiddenCreateValues($firstValues));

                $this->slipModel->releaseDraft($slipId, $firstValues + [
                    'actor_user_id' => $this->currentUserId(),
                    'actor_department_id' => $this->currentDepartmentId()
                ]);
                $this->notificationModel->notifyDepartmentManagers([$firstDepartmentId], 'Action slip released', $slip['slip_number'] . ' was released to your department.', '/actionSlips/show/' . $slipId, $this->currentUserId());

                foreach ($values['receiving_department_ids'] as $departmentId) {
                    $this->slipModel->routeToDepartment($slipId, (int) $departmentId, $this->currentUserId(), $this->currentDepartmentId(), $values['remarks'], DepartmentActionSlip::STATUS_RELEASED, 'Released to Department');
                    $this->notificationModel->notifyDepartmentManagers([(int) $departmentId], 'Action slip released', $slip['slip_number'] . ' was released to your department.', '/actionSlips/show/' . $slipId, $this->currentUserId());
                }

                flash('success', 'Draft action slip finalized and released to selected departments.', 'success');
                return;
            }

            if ($values['receiving_level'] === 'Division' && count($values['receiving_division_ids']) > 1) {
                $firstDivisionId = (int) array_shift($values['receiving_division_ids']);
                $firstValues = $values;
                $firstValues['receiving_division_id'] = $firstDivisionId;
                $firstValues['receiving_division_ids'] = [$firstDivisionId];
                $firstValues = array_merge($firstValues, $this->hiddenCreateValues($firstValues));

                $this->slipModel->releaseDraft($slipId, $firstValues + [
                    'actor_user_id' => $this->currentUserId(),
                    'actor_department_id' => $this->currentDepartmentId()
                ]);
                $this->notificationModel->notifyDepartmentManagers([$firstDivisionId], 'Action slip released', $slip['slip_number'] . ' was released to your division.', '/actionSlips/show/' . $slipId, $this->currentUserId());

                foreach ($values['receiving_division_ids'] as $divisionId) {
                    $this->slipModel->delegateToDivision($slipId, (int) $divisionId, $this->currentUserId(), $this->currentDepartmentId(), $values['remarks'], DepartmentActionSlip::STATUS_RELEASED, 'Released to Division');
                    $this->notificationModel->notifyDepartmentManagers([(int) $divisionId], 'Action slip released', $slip['slip_number'] . ' was released to your division.', '/actionSlips/show/' . $slipId, $this->currentUserId());
                }

                flash('success', 'Draft action slip finalized and released to selected divisions.', 'success');
                return;
            }

            if ($values['receiving_level'] === 'Staff') {
                $values['target_staff_department_id'] = $this->findStaffDepartmentInCurrentScope((int) $values['assigned_staff_id']);
            }
            $values = array_merge($values, $this->hiddenCreateValues($values));
            $this->slipModel->releaseDraft($slipId, $values + [
                'actor_user_id' => $this->currentUserId(),
                'actor_department_id' => $this->currentDepartmentId()
            ]);

            if ($values['receiving_level'] === 'Staff') {
                foreach ($values['assigned_staff_ids'] as $staffId) {
                    $this->notificationModel->create((int) $staffId, 'Action slip released', $slip['slip_number'] . ' was released to you.', '/actionSlips/show/' . $slipId);
                }
            } elseif ($values['receiving_level'] === 'Division') {
                $this->notificationModel->notifyDepartmentManagers([$values['receiving_division_id']], 'Action slip released', $slip['slip_number'] . ' was released to your division.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            } else {
                $this->notificationModel->notifyDepartmentManagers([$values['receiving_department_id']], 'Action slip released', $slip['slip_number'] . ' was released to your department.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            }

            flash('success', 'Draft action slip finalized and released.', 'success');
        });
    }

    public function receiveDepartment($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['receive_department']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $this->slipModel->receiveByDepartment($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            flash('success', 'Action slip received by department.', 'success');
        });
    }

    public function routeDepartment($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['route_department']) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $targetDepartmentId = (int) ($_POST['target_department_id'] ?? 0);
            if ($targetDepartmentId <= 0 || !$this->departmentModel->isParentDepartment($targetDepartmentId)) {
                throw new ValidationException('Select a valid target department.');
            }
            if ($targetDepartmentId === $this->currentDepartmentId()) {
                throw new ValidationException('Department-to-department routing cannot target the same department. Use reassignment when needed.');
            }

            $this->slipModel->routeToDepartment($slipId, $targetDepartmentId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            $this->notificationModel->notifyDepartmentManagers([$targetDepartmentId], 'Action slip routed', $slip['slip_number'] . ' was routed to your department.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            flash('success', 'Action slip routed to department.', 'success');
        });
    }

    public function delegateDivision($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['delegate_division']) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $divisionId = (int) ($_POST['division_id'] ?? 0);
            $currentDepartmentId = (int) ($slip['current_department_id'] ?: $slip['receiving_department_id']);
            if ($divisionId <= 0 || !$this->departmentModel->areChildDepartmentsOfParent([$divisionId], $currentDepartmentId)) {
                throw new ValidationException('Select a valid division under the current department.');
            }

            $this->slipModel->delegateToDivision($slipId, $divisionId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            $this->notificationModel->notifyDepartmentManagers([$divisionId], 'Action slip delegated', $slip['slip_number'] . ' was delegated to your division.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            flash('success', 'Action slip delegated to division.', 'success');
        });
    }

    public function receiveDivision($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['receive_division']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $this->slipModel->receiveByDivision($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            flash('success', 'Action slip received by division.', 'success');
        });
    }

    public function delegateRecipients($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['delegate_recipients']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $managerIds = $this->postedUnitManagerIds();
            $staffIds = $this->postedDelegateStaffIds();
            if (!$managerIds && !$staffIds) {
                throw new ValidationException('Select at least one unit head or division staff member.');
            }
            $this->slipModel->delegateDivisionRecipients($slipId, $managerIds, $staffIds,
                $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            foreach (array_merge($managerIds, $staffIds) as $recipientId) {
                $this->notificationModel->create($recipientId, 'Action slip delegated',
                    $slip['slip_number'] . ' was delegated to you.', '/actionSlips/show/' . $slipId);
            }
            flash('success', 'Action slip delegated to all selected recipients. Awaiting completion.', 'success');
        });
    }

    public function delegateUnit($id)
    {
        $this->delegateRecipients($id);
    }

    public function receiveUnit($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['receive_unit']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $this->slipModel->receiveByUnit($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            flash('success', 'Action slip received by unit.', 'success');
        });
    }

    public function delegateStaff($id)
    {
        if ($this->isManager() && $this->isDivision()) {
            $this->delegateRecipients($id);
            return;
        }
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['delegate_staff']) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $staffIds = $this->postedDelegateStaffIds();
            if (empty($staffIds)) {
                throw new ValidationException('Select at least one active staff member from your current office.');
            }

            foreach ($staffIds as $staffId) {
                if (!$this->slipModel->findActiveUserInDepartment($staffId, $this->currentDepartmentId(), 'staff')) {
                    throw new ValidationException('Select active staff members from your current office.');
                }
            }

            $remarks = trim($_POST['remarks'] ?? '');
            $this->slipModel->delegateStaffRecipients($slipId, $staffIds, $this->currentUserId(), $this->currentDepartmentId(), $remarks);
            foreach ($staffIds as $staffId) {
                $this->notificationModel->create((int) $staffId, 'Action slip assigned', $slip['slip_number'] . ' was assigned to you.', '/actionSlips/show/' . $slipId);
            }

            flash('success', 'Action slip delegated to selected staff.', 'success');
        });
    }

    public function cancelDraft($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['cancel_draft']) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $this->slipModel->cancelDraft($slipId, $this->currentUserId(), $this->currentDepartmentId());
            if ((int) ($slip['created_by'] ?? 0) !== $this->currentUserId()) {
                $this->notificationModel->create(
                    (int) $slip['created_by'],
                    'Draft action slip cancelled',
                    $slip['slip_number'] . ' was cancelled by your manager.',
                    '/actionSlips/show/' . $slipId
                );
            }
            flash('success', 'Draft action slip cancelled.', 'success');
        });
    }

    public function startStaff($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['start_staff']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $this->slipModel->startStaffAction($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            flash('success', 'Action slip moved to staff action.', 'success');
        });
    }

    public function completeStaff($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['complete_staff']) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $staffAssignment = $this->slipModel->findStaffAssignmentForUser($slipId, $this->currentUserId());
            $wasReturned = ($staffAssignment['status'] ?? '') === DepartmentActionSlip::STATUS_RETURNED
                || (($slip['status'] ?? '') === DepartmentActionSlip::STATUS_RETURNED && (int) ($slip['assigned_staff_id'] ?? 0) === $this->currentUserId());
            $attachment = $this->handleUpload('completion_attachment', 'action_slips/completions');
            $this->slipModel->completeByStaff($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''), $attachment);
            $title = $wasReturned ? 'Action slip resubmitted by staff' : 'Action slip completed by staff';
            $message = $wasReturned ? $slip['slip_number'] . ' was resubmitted by staff.' : $slip['slip_number'] . ' was marked completed by staff.';
            $reviewerDepartmentId = (int) ($staffAssignment['department_id'] ?? $this->currentDepartmentId());
            $this->notificationModel->notifyDepartmentManagers([$reviewerDepartmentId], $title, $message, '/actionSlips/show/' . $slipId, $this->currentUserId());
            flash('success', $wasReturned ? 'Action slip resubmitted to your manager.' : 'Action slip marked completed by staff.', 'success');
        });
    }

    public function confirmDivision($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            $actions = $this->allowedActions($slip);
            if (empty($actions['confirm_division']) && empty($actions['confirm_unit_for_division'])) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $completionType = $_POST['completion_type'] ?? 'unit';
            if (!in_array($completionType, ['unit', 'staff'], true)
                || empty($_POST['assignment_id'])
                || empty($actions[$completionType === 'staff' ? 'confirm_division' : 'confirm_unit_for_division'])) {
                throw new ValidationException('Select a completed assignment to confirm.');
            }
            $attachment = $this->handleUpload('completion_attachment', 'action_slips/completions');
            $this->slipModel->confirmByDivisionManager($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''), (int) $_POST['assignment_id'], $attachment, $completionType);
            $this->notificationModel->notifyDepartmentManagers([(int) ($slip['current_department_id'] ?: $slip['receiving_department_id'])], 'Action slip confirmed', $slip['slip_number'] . ' was confirmed by the division manager.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            flash('success', 'Completion confirmed by division manager.', 'success');
        });
    }

    public function confirmUnit($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['confirm_unit']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $attachment = $this->handleUpload('completion_attachment', 'action_slips/completions');
            $this->slipModel->confirmByUnitManager($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''), (int) ($_POST['assignment_id'] ?? 0), $attachment);
            flash('success', 'Staff completion confirmed. You may now mark the unit task completed.');
        });
    }

    public function completeUnit($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['complete_unit']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $attachment = $this->handleUpload('completion_attachment', 'action_slips/completions');
            $this->slipModel->completeByUnitManager($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''), $attachment);
            $divisionId = (int) ($slip['current_division_id'] ?: 0);
            if ($divisionId > 0) {
                $this->notificationModel->notifyDepartmentManagers([$divisionId], 'Action slip completed by unit', $slip['slip_number'] . ' was marked completed by the unit manager.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            }
            flash('success', 'Action slip marked completed by unit manager.');
        });
    }

    public function completeDivision($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['complete_division']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $attachment = $this->handleUpload('completion_attachment', 'action_slips/completions');
            $this->slipModel->confirmByDivisionManager($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''), null, $attachment);
            $this->notificationModel->notifyDepartmentManagers([(int) ($slip['current_department_id'] ?: $slip['receiving_department_id'])], 'Action slip completed by division', $slip['slip_number'] . ' was marked completed by the division manager.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            flash('success', 'Action slip completed by division manager.', 'success');
        });
    }

    public function completeDepartment($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['complete_department']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $attachment = $this->handleUpload('completion_attachment', 'action_slips/completions');
            $this->slipModel->completeByDepartmentManager($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''), $attachment);
            flash('success', 'Department action slip completed.', 'success');
        });
    }

    public function returnSlip($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            $actions = $this->allowedActions($slip);
            if (!$actions['return']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $remarks = trim($_POST['remarks'] ?? '');
            if ($remarks === '') {
                throw new ValidationException('Remarks are required when returning an action slip.');
            }
            $completionType = $_POST['completion_type'] ?? (!empty($actions['return_staff_completion']) ? 'staff' : 'unit');
            if ($this->isDivision() && empty($_POST['assignment_id'])) {
                throw new ValidationException('Select a completed assignment to return.');
            }
            if (!in_array($completionType, ['staff', 'unit'], true)
                || empty($actions[$completionType === 'staff' ? 'return_staff_completion' : 'return_unit_completion'])) {
                throw new AuthorizationException('Unauthorized action.');
            }
            if ($completionType === 'staff') {
                $reason = trim($_POST['return_reason'] ?? '');
                $allowedReasons = ['Did not pass standards', 'Change of instruction', 'Other'];
                if (!in_array($reason, $allowedReasons, true)) {
                    throw new ValidationException('Select a valid return reason.');
                }
                $remarks = 'Reason: ' . $reason . "\n" . $remarks;
                $staffId = $this->slipModel->returnStaffCompletion($slipId, $this->currentUserId(), $this->currentDepartmentId(), $remarks, (int) ($_POST['assignment_id'] ?? 0));
                if ($staffId > 0) {
                    $this->notificationModel->create($staffId, 'Action slip returned', $slip['slip_number'] . ' was returned for revision.', '/actionSlips/show/' . $slipId);
                }
            } elseif (!empty($actions['return_unit_completion'])) {
                $reason = trim($_POST['return_reason'] ?? '');
                $allowedReasons = ['Did not pass standards', 'Change of instruction', 'Other'];
                if (!in_array($reason, $allowedReasons, true)) {
                    throw new ValidationException('Select a valid return reason.');
                }
                $remarks = 'Reason: ' . $reason . "\n" . $remarks;
                $unitId = $this->slipModel->returnUnitCompletion($slipId, $this->currentUserId(), $this->currentDepartmentId(), $remarks, (int) ($_POST['assignment_id'] ?? 0));
                if ($unitId > 0) {
                    $this->notificationModel->notifyDepartmentManagers([$unitId], 'Action slip returned to unit', $slip['slip_number'] . ' was returned for revision.', '/actionSlips/show/' . $slipId, $this->currentUserId());
                }
            } else {
                $returnTargetDepartmentId = $this->slipModel->returnSlip($slipId, $this->currentUserId(), $this->currentDepartmentId(), $remarks);
                if ($returnTargetDepartmentId > 0) {
                    $this->notificationModel->notifyDepartmentManagers([$returnTargetDepartmentId], 'Action slip returned', $slip['slip_number'] . ' was returned for your review.', '/actionSlips/show/' . $slipId, $this->currentUserId());
                }
            }
            flash('success', 'Action slip returned.', 'success');
        });
    }

    public function reassignDepartment($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['route_department']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $targetDepartmentId = (int) ($_POST['target_department_id'] ?? 0);
            if ($targetDepartmentId <= 0 || !$this->departmentModel->isParentDepartment($targetDepartmentId)) {
                throw new ValidationException('Select a valid target department.');
            }
            $this->slipModel->routeToDepartment($slipId, $targetDepartmentId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''), DepartmentActionSlip::STATUS_RELEASED, 'Released to Department');
            $this->notificationModel->notifyDepartmentManagers([$targetDepartmentId], 'Action slip reassigned', $slip['slip_number'] . ' was reassigned to your department.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            flash('success', 'Action slip reassigned.', 'success');
        });
    }

    public function close($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['close']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $this->slipModel->closeSlip($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            flash('success', 'Action slip closed.', 'success');
        });
    }

    public function attachment($id)
    {
        $slipId = (int) $id;

        try {
            if (!$this->slipModel->canUserView($slipId, $this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? '')) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $slip = $this->slipModel->findById($slipId);
            if (!$slip || empty($slip['attachment'])) {
                throw new NotFoundException('Attachment not found.');
            }

            $this->streamStoredFile($slip['attachment']);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'action_slips.attachment', 'slip_id' => $slipId]);
            flash('error', 'The attachment could not be opened.', 'error');
            redirect('/actionSlips/show/' . $slipId, 303);
        }
    }

    public function eventAttachment($eventId)
    {
        $eventId = (int) $eventId;

        try {
            $event = $this->slipModel->findEventById($eventId);
            if (!$event || empty($event['attachment'])) {
                throw new NotFoundException('Attachment not found.');
            }

            $slipId = (int) $event['slip_id'];
            if (!$this->slipModel->canUserView($slipId, $this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? '')) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $this->streamStoredFile($event['attachment']);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'action_slips.eventAttachment', 'event_id' => $eventId]);
            flash('error', 'The attachment could not be opened.', 'error');
            redirect('/actionSlips', 303);
        }
    }

    private function handleAction($id, callable $callback)
    {
        $slipId = (int) $id;

        try {
            requirePost();
            validateCsrfOrFail();

            if (!$this->slipModel->canUserView($slipId, $this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? '')) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $slip = $this->slipModel->findById($slipId);
            if (!$slip) {
                throw new NotFoundException('Action slip not found.');
            }

            $callback($slipId, $slip);
            redirect('/actionSlips/show/' . $slipId, 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/actionSlips/show/' . $slipId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to perform that action.', 'error');
            redirect('/actionSlips', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Department action slip not found.', 'error');
            redirect('/actionSlips', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'action_slips.handleAction', 'slip_id' => $slipId]);
            flash('error', 'We could not update that department action slip right now.', 'error');
            redirect('/actionSlips/show/' . $slipId, 303);
        }
    }

    private function handleUpload($field, $subdir)
    {
        if (empty($_FILES[$field]['name'])) {
            return null;
        }

        if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException('The uploaded file could not be processed.');
        }

        if ((int) $_FILES[$field]['size'] > MAX_ATTACHMENT_SIZE_BYTES) {
            throw new ValidationException('The attachment must not exceed ' . MAX_ATTACHMENT_SIZE_MB . ' MB.');
        }

        $tmpPath = (string) ($_FILES[$field]['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath) || (int) $_FILES[$field]['size'] <= 0) {
            throw new ValidationException('The uploaded file is invalid or empty.');
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
        $originalName = basename((string) $_FILES[$field]['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new ValidationException('Upload a supported document or image file.');
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        $allowedMimeByExtension = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
            'doc' => ['application/msword', 'application/CDFV2'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/CDFV2'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']
        ];
        if (!in_array($mimeType, $allowedMimeByExtension[$extension] ?? [], true)) {
            throw new ValidationException('The attachment extension does not match its detected content type.');
        }
        if (in_array($extension, ['docx', 'xlsx'], true) && $mimeType === 'application/zip') {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('The PHP ZIP extension is required to validate Office documents.');
            }
            $archive = new ZipArchive();
            if ($archive->open($tmpPath) !== true || $archive->locateName('[Content_Types].xml') === false) {
                throw new ValidationException('The Office document is invalid.');
            }
            $archive->close();
        }

        ensureUploadCapacityOrFail((int) $_FILES[$field]['size']);
        scanUploadedFileOrFail($tmpPath);

        $uploadDir = rtrim(UPLOAD_ROOT, '/\\') . '/' . trim($subdir, '/\\');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $safeName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($tmpPath, $target)) {
            throw new RuntimeException('Unable to save uploaded file.');
        }

        return trim($subdir, '/\\') . '/' . $safeName;
    }

    private function streamStoredFile($relativePath)
    {
        $relativePath = str_replace(['..', '\\'], ['', '/'], (string) $relativePath);
        $path = rtrim(UPLOAD_ROOT, '/\\') . '/' . ltrim($relativePath, '/');
        $realPath = realpath($path);
        $realRoot = realpath(UPLOAD_ROOT);

        if (!$realPath || !$realRoot || strpos($realPath, $realRoot) !== 0 || !is_file($realPath)) {
            throw new NotFoundException('Attachment not found.');
        }

        $mimeType = function_exists('mime_content_type') ? (mime_content_type($realPath) ?: 'application/octet-stream') : 'application/octet-stream';
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($realPath));
        $disposition = ($mimeType === 'application/pdf' || strpos($mimeType, 'image/') === 0) ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($realPath) . '"');
        readfile($realPath);
        exit;
    }
}
