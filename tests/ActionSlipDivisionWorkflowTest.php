<?php

return [
    'division manager waits after delegation and reviews mixed recipients independently' => function ($root) {
        require_once $root . '/app/init.php';
        require_once APPROOT . '/controllers/ActionSlips.php';
        $model = new class {
            public $delegated = false;
            public $unitCompleted = false;
            public $staffCompleted = false;
            public function __call($method, $args) {
                return match ($method) {
                    'findDivisionAssignmentForDepartment' => ['status' => 'For Action'],
                    'hasDelegatedAssignmentsForDivision' => $this->delegated,
                    'hasCompletedUnitAssignmentsForDivision' => $this->unitCompleted,
                    'hasCompletedStaffAssignmentsForDepartment' => $this->staffCompleted,
                    default => str_starts_with($method, 'has') ? false : null
                };
            }
        };
        $departments = new class {
            public function isUnit($id) { return false; }
            public function isDivision($id) { return $id === 12; }
            public function isParentDepartment($id) { return false; }
        };
        $reflection = new ReflectionClass(ActionSlips::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('slipModel')->setValue($controller, $model);
        $reflection->getProperty('departmentModel')->setValue($controller, $departments);
        $allowed = $reflection->getMethod('allowedActions');
        $previousSession = $_SESSION ?? [];
        try {
            $_SESSION = ['role' => 'manager', 'department_id' => 12, 'user_id' => 15];
            $slip = ['id' => 1304, 'status' => 'For Action', 'receiving_department_id' => 10,
                'current_department_id' => 10, 'current_division_id' => 12, 'current_unit_id' => 85,
                'assigned_staff_id' => 13, 'receiving_level' => 'Division'];
            test_assert($allowed->invoke($controller, $slip)['delegate_recipients']);
            $model->delegated = true;
            test_assert_same([], array_keys(array_filter($allowed->invoke($controller, $slip))), 'Gary must have no actions while Rainier and Paulo work.');
            $model->unitCompleted = true;
            $actions = $allowed->invoke($controller, $slip);
            test_assert($actions['confirm_unit_for_division'] && $actions['return_unit_completion']);
            test_assert(!$actions['delegate_unit'] && !$actions['delegate_staff'] && !$actions['complete_division']);
            $model->staffCompleted = true;
            $actions = $allowed->invoke($controller, $slip);
            test_assert($actions['confirm_division'] && $actions['return_staff_completion'], 'Eric completion must remain reviewable alongside SDU completion.');
            $model->unitCompleted = false;
            $model->staffCompleted = false;
            $slip['status'] = 'Returned';
            test_assert_same([], array_keys(array_filter($allowed->invoke($controller, $slip))), 'Returning work must not unlock new delegation.');
        } finally {
            $_SESSION = $previousSession;
        }
    },
    'division delegation submits all recipients once and rolls back failures' => function ($root) {
        require_once $root . '/app/init.php';
        require_once APPROOT . '/models/DepartmentActionSlip.php';
        $db = new class {
            public $delegated = false;
            public $recipients = [];
            public $active = false;
            private $snapshot;
            public function beginTransaction() { $this->snapshot = [$this->delegated, $this->recipients]; $this->active = true; }
            public function commit() { $this->active = false; }
            public function inTransaction() { return $this->active; }
            public function rollBack() { [$this->delegated, $this->recipients] = $this->snapshot; $this->active = false; }
            public function prepare($sql) {
                return new class($this, $sql) {
                    private $db;
                    private $sql;
                    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
                    public function execute($params) {}
                    public function fetchColumn() { return str_contains($this->sql, 'FOR UPDATE') ? 1304 : (int) $this->db->delegated; }
                };
            }
        };
        $model = new class($db) extends DepartmentActionSlip {
            private $fixture;
            public $failStaff = false;
            public function __construct($db) { $this->fixture = $db; }
            public function findById($id) { return ['id' => $id, 'status' => 'For Action', 'current_division_id' => 12]; }
            public function findDivisionAssignmentForDepartment($id, $department) { return ['status' => 'For Action']; }
            public function findActiveManagerInChildUnit($id, $division) { return $id === 11 ? ['department_id' => 85] : null; }
            public function findActiveUserInDepartment($id, $division, $role = null) { return $id === 14 ? ['id' => 14] : null; }
            public function findActiveStaffInDivisionHierarchy($id, $division) { return in_array($id, [14, 37], true) ? ['id' => $id] : null; }
            public function findStaffAssignmentForUser($slip, $user) { return null; }
            public function delegateToUnit($id, $unit, $user, $actor, $department, $remarks = '') { $this->fixture->delegated = true; $this->fixture->recipients[] = $user; }
            public function delegateToStaff($id, $user, $actor, $department, $remarks = '') {
                if ($this->failStaff) { throw new RuntimeException('Fixture write failure'); }
                $this->fixture->delegated = true;
                $this->fixture->recipients[] = $user;
            }
        };
        (new ReflectionClass(DepartmentActionSlip::class))->getProperty('db')->setValue($model, $db);
        $model->delegateDivisionRecipients(1304, [11], [14], 15, 12);
        test_assert_same([11, 14], $db->recipients, 'Both recipient types must be delegated in the first submission.');
        $caught = false;
        try { $model->delegateDivisionRecipients(1304, [], [14], 15, 12); } catch (RuntimeException $e) { $caught = true; }
        test_assert($caught, 'Repeated requests must not add recipients after delegation.');
        test_assert_same([11, 14], $db->recipients);
        $db->delegated = false;
        $db->recipients = [];
        $model->failStaff = true;
        try { $model->delegateDivisionRecipients(1304, [11], [14], 15, 12); } catch (RuntimeException $e) {}
        test_assert_same([], $db->recipients, 'A failed multi-recipient delegation must roll back all recipients.');
        test_assert(!$db->delegated && !$db->active);
    }
];
