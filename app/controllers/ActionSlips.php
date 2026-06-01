<?php

require_once '../app/models/DepartmentActionSlip.php';
require_once '../app/models/Department.php';
require_once '../app/models/Notification.php';

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

    private function getFilters()
    {
        return [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'department_id' => (int) ($_GET['department_id'] ?? 0),
            'division_id' => (int) ($_GET['division_id'] ?? 0),
            'assigned_staff_id' => (int) ($_GET['assigned_staff_id'] ?? 0),
            'date_received_from' => trim($_GET['date_received_from'] ?? ''),
            'date_received_to' => trim($_GET['date_received_to'] ?? ''),
            'deadline_from' => trim($_GET['deadline_from'] ?? ''),
            'deadline_to' => trim($_GET['deadline_to'] ?? '')
        ];
    }

    private function formDefaults()
    {
        $currentDepartment = $this->departmentModel->getDepartmentById($this->currentDepartmentId());
        $receivingLevel = $this->isParentDepartment() ? 'Department' : 'Staff';

        return [
            'date_received' => date('Y-m-d'),
            'urgent' => 0,
            'receiving_level' => $receivingLevel,
            'receiving_department_id' => $receivingLevel === 'Staff' ? (string) ($currentDepartment['parent_id'] ?? '') : '',
            'receiving_division_id' => $receivingLevel === 'Staff' ? (string) $this->currentDepartmentId() : '',
            'assigned_staff_id' => '',
            'required_action' => '',
            'deadline' => '',
            'remarks' => ''
        ];
    }

    public function index()
    {
        try {
            $filters = $this->getFilters();
            $slips = $this->slipModel->getVisible($this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? '', $filters);

            $data = [
                'slips' => $slips,
                'statuses' => DepartmentActionSlip::statuses(),
                'filters' => $filters,
                'departments' => $this->departmentModel->getParentDepartments(),
                'divisions' => $this->getAllDivisions(),
                'staff' => $this->getVisibleStaff(),
                'status_counts' => $this->slipModel->countByVisibleStatus($this->currentUserId(), $this->currentDepartmentId(), $_SESSION['role'] ?? ''),
                'can_create' => $this->canCreate()
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
            return !empty($department['parent_id']);
        }));
    }

    private function getVisibleStaff()
    {
        $staff = [];
        foreach ($this->getAllDivisions() as $division) {
            foreach ($this->slipModel->getActiveStaffByDepartment((int) $division['id']) as $user) {
                $staff[] = $user + ['department_name' => $division['division_name'] ?? ''];
            }
        }
        return $staff;
    }

    private function canCreate()
    {
        return $this->isManager();
    }

    public function create()
    {
        try {
            if (!$this->canCreate()) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $state = pullFormState('action_slip_create', $this->formDefaults());
            $departmentId = (int) ($state['values']['receiving_department_id'] ?: $this->currentDepartmentId());

            $data = [
                'values' => $state['values'],
                'errors' => $state['errors'],
                'message' => $state['message'],
                'departments' => $this->departmentModel->getParentDepartments(),
                'divisions' => $this->getAllDivisions(),
                'division_staff' => $this->isParentDepartment() ? [] : $this->slipModel->getActiveStaffByDepartment($this->currentDepartmentId()),
                'is_parent_department' => $this->isParentDepartment(),
                'action_options' => DepartmentActionSlip::actionOptions(),
                'next_slip_number' => $this->slipModel->getNextSlipNumber($departmentId)
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
            'date_received' => trim($_POST['date_received'] ?? ''),
            'urgent' => !empty($_POST['urgent']) ? 1 : 0,
            'receiving_level' => trim($_POST['receiving_level'] ?? 'Department'),
            'receiving_department_id' => (int) ($_POST['receiving_department_id'] ?? 0),
            'receiving_division_id' => (int) ($_POST['receiving_division_id'] ?? 0),
            'assigned_staff_id' => (int) ($_POST['assigned_staff_id'] ?? 0),
            'required_action' => trim($_POST['required_action'] ?? ''),
            'deadline' => trim($_POST['deadline'] ?? ''),
            'remarks' => trim($_POST['remarks'] ?? '')
        ];

        try {
            requirePost();
            validateCsrfOrFail();

            if (!$this->canCreate()) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $errors = $this->validateCreateValues($values);
            if (!empty($errors)) {
                throw new ValidationException('Please correct the highlighted fields.', $errors);
            }

            $attachment = $this->handleUpload('attachment', 'action_slips');
            $values = array_merge($values, $this->hiddenCreateValues($values));
            $slipId = $this->slipModel->create($values + [
                'attachment' => $attachment,
                'created_by' => $this->currentUserId(),
                'actor_department_id' => $this->currentDepartmentId()
            ]);

            if ($values['receiving_level'] === 'Staff') {
                $this->notificationModel->create($values['assigned_staff_id'], 'Action slip released', 'A new action slip was released to you.', '/actionSlips/show/' . $slipId);
            } elseif ($values['receiving_level'] === 'Division') {
                $this->notificationModel->notifyDepartmentManagers([$values['receiving_division_id']], 'Action slip released', 'A new action slip was released to your division.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            } else {
                $this->notificationModel->notifyDepartmentManagers([$values['receiving_department_id']], 'Action slip released', 'A new action slip was released to your department.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            }

            flash('success', 'Department action slip created successfully.', 'success');
            redirect('/actionSlips/show/' . $slipId, 303);
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

    private function validateCreateValues($values)
    {
        $errors = [];
        if ($values['date_received'] === '') {
            $errors['date_received'] = 'Date of Action Slip is required.';
        }
        if (!in_array($values['receiving_level'], ['Department', 'Division', 'Staff'], true)) {
            $errors['receiving_level'] = 'Select a valid release target.';
        }

        if ($this->isManager() && $this->isParentDepartment()) {
            if ($values['receiving_level'] === 'Department') {
                if ((int) $values['receiving_department_id'] <= 0) {
                    $errors['receiving_department_id'] = 'Target department is required.';
                } elseif (!$this->departmentModel->isParentDepartment((int) $values['receiving_department_id'])) {
                    $errors['receiving_department_id'] = 'Select a valid department.';
                } elseif ((int) $values['receiving_department_id'] === $this->currentDepartmentId()) {
                    $errors['receiving_department_id'] = 'Select another department.';
                }
            } elseif ($values['receiving_level'] === 'Division') {
                $values['receiving_department_id'] = $this->currentDepartmentId();
                if ((int) $values['receiving_division_id'] <= 0) {
                    $errors['receiving_division_id'] = 'Target division is required.';
                } elseif (!$this->departmentModel->areChildDepartmentsOfParent([(int) $values['receiving_division_id']], $this->currentDepartmentId())) {
                    $errors['receiving_division_id'] = 'Select a division under your department.';
                }
            } else {
                $errors['receiving_level'] = 'Department managers can release to departments or divisions only.';
            }
        } elseif ($this->isManager() && !$this->isParentDepartment()) {
            if ($values['receiving_level'] !== 'Staff') {
                $errors['receiving_level'] = 'Division managers can release action slips to staff only.';
            }
            $currentDepartment = $this->departmentModel->getDepartmentById($this->currentDepartmentId());
            $values['receiving_department_id'] = (int) ($currentDepartment['parent_id'] ?? 0);
            $values['receiving_division_id'] = $this->currentDepartmentId();
            if (!$this->slipModel->findActiveUserInDepartment((int) $values['assigned_staff_id'], $this->currentDepartmentId(), 'staff')) {
                $errors['assigned_staff_id'] = 'Select a staff member under your division.';
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

    private function hiddenCreateValues($values)
    {
        $currentDepartment = $this->departmentModel->getDepartmentById($this->currentDepartmentId());

        if ($values['receiving_level'] === 'Division') {
            $values['receiving_department_id'] = $this->currentDepartmentId();
            $values['release_action'] = 'Released to Division';
        } elseif ($values['receiving_level'] === 'Staff') {
            $values['receiving_department_id'] = (int) ($currentDepartment['parent_id'] ?? $this->currentDepartmentId());
            $values['receiving_division_id'] = $this->currentDepartmentId();
            $values['release_action'] = 'Released to Staff';
        } else {
            $values['release_action'] = 'Released to Department';
        }

        return [
            'external_source' => 'Internal Action Slip',
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

            $currentDepartmentId = (int) ($slip['current_department_id'] ?: $slip['receiving_department_id']);
            $currentDivisionId = (int) ($slip['current_division_id'] ?: 0);

            $data = [
                'slip' => $slip,
                'events' => $this->slipModel->getEvents($slipId),
                'departments' => $this->departmentModel->getParentDepartments(),
                'child_divisions' => $this->departmentModel->getChildDepartmentsForParent($currentDepartmentId),
                'division_staff' => $currentDivisionId > 0 ? $this->slipModel->getActiveStaffByDepartment($currentDivisionId) : [],
                'actions' => $this->allowedActions($slip)
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
        $assignedStaffId = (int) ($slip['assigned_staff_id'] ?? 0);
        $isDepartmentManager = $this->isManager() && $this->isParentDepartment($deptId) && $currentDepartmentId === $deptId;
        $isDivisionManager = $this->isManager() && !$this->isParentDepartment($deptId) && $currentDivisionId === $deptId;
        $isAssignedStaff = $assignedStaffId === $this->currentUserId();
        $wasCompletedByStaff = $status === DepartmentActionSlip::STATUS_COMPLETED
            && $assignedStaffId > 0
            && (int) ($slip['completed_by'] ?? 0) === $assignedStaffId;

        return [
            'receive_department' => $isDepartmentManager && $currentDivisionId === 0 && $status === DepartmentActionSlip::STATUS_RELEASED,
            'route_department' => $isDepartmentManager && $currentDivisionId === 0 && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true),
            'delegate_division' => $isDepartmentManager && $currentDivisionId === 0 && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true),
            'complete_department' => $isDepartmentManager && $currentDivisionId === 0 && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_FOR_ACTION, DepartmentActionSlip::STATUS_RETURNED], true),
            'close' => false,
            'receive_division' => $isDivisionManager && $assignedStaffId === 0 && in_array($status, [DepartmentActionSlip::STATUS_RELEASED, DepartmentActionSlip::STATUS_DELEGATED], true),
            'delegate_staff' => $isDivisionManager && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true),
            'complete_division' => $isDivisionManager && $assignedStaffId === 0 && in_array($status, [DepartmentActionSlip::STATUS_RECEIVED, DepartmentActionSlip::STATUS_RETURNED], true),
            'confirm_division' => $isDivisionManager && $wasCompletedByStaff,
            'start_staff' => $isAssignedStaff && in_array($status, [DepartmentActionSlip::STATUS_RELEASED, DepartmentActionSlip::STATUS_DELEGATED], true),
            'complete_staff' => $isAssignedStaff && in_array($status, [DepartmentActionSlip::STATUS_FOR_ACTION, DepartmentActionSlip::STATUS_RECEIVED], true),
            'return' => false
        ];
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

    public function delegateStaff($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['delegate_staff']) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $staffId = (int) ($_POST['staff_id'] ?? 0);
            if (!$this->slipModel->findActiveUserInDepartment($staffId, $this->currentDepartmentId(), 'staff')) {
                throw new ValidationException('Select an active staff member from the current division.');
            }

            $this->slipModel->delegateToStaff($slipId, $staffId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            $this->notificationModel->create($staffId, 'Action slip assigned', $slip['slip_number'] . ' was assigned to you.', '/actionSlips/show/' . $slipId);
            flash('success', 'Action slip delegated to staff.', 'success');
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

            $attachment = $this->handleUpload('completion_attachment', 'action_slips/completions');
            $this->slipModel->completeByStaff($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''), $attachment);
            $this->notificationModel->notifyDepartmentManagers([$this->currentDepartmentId()], 'Action slip completed by staff', $slip['slip_number'] . ' was marked completed by staff.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            flash('success', 'Action slip marked completed by staff.', 'success');
        });
    }

    public function confirmDivision($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['confirm_division']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $this->slipModel->confirmByDivisionManager($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            $this->notificationModel->notifyDepartmentManagers([(int) ($slip['current_department_id'] ?: $slip['receiving_department_id'])], 'Action slip confirmed', $slip['slip_number'] . ' was confirmed by the division manager.', '/actionSlips/show/' . $slipId, $this->currentUserId());
            flash('success', 'Completion confirmed by division manager.', 'success');
        });
    }

    public function completeDivision($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['complete_division']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $this->slipModel->confirmByDivisionManager($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
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
            $this->slipModel->completeByDepartmentManager($slipId, $this->currentUserId(), $this->currentDepartmentId(), trim($_POST['remarks'] ?? ''));
            flash('success', 'Department action slip completed.', 'success');
        });
    }

    public function returnSlip($id)
    {
        $this->handleAction($id, function ($slipId, $slip) {
            if (!$this->allowedActions($slip)['return']) {
                throw new AuthorizationException('Unauthorized action.');
            }
            $remarks = trim($_POST['remarks'] ?? '');
            if ($remarks === '') {
                throw new ValidationException('Remarks are required when returning an action slip.');
            }
            $returnTargetDepartmentId = $this->slipModel->returnSlip($slipId, $this->currentUserId(), $this->currentDepartmentId(), $remarks);
            if ($returnTargetDepartmentId > 0) {
                $this->notificationModel->notifyDepartmentManagers([$returnTargetDepartmentId], 'Action slip returned', $slip['slip_number'] . ' was returned for your review.', '/actionSlips/show/' . $slipId, $this->currentUserId());
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

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
        $originalName = basename((string) $_FILES[$field]['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new ValidationException('Upload a supported document or image file.');
        }

        $uploadDir = rtrim(UPLOAD_ROOT, '/\\') . '/' . trim($subdir, '/\\');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $safeName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
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
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($realPath));
        header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
        readfile($realPath);
        exit;
    }
}
