<?php

return [
    'parallel unit and staff assignments can receive independently' => function ($root) {
        require_once $root . '/app/init.php';
        require_once APPROOT . '/controllers/ActionSlips.php';

        $model = new class {
            public $unitStatus = 'Delegated';
            public $staffStatus = 'Delegated';
            public $hasUnit = true;
            public $hasUnitStaff = false;
            public function __call($name, $args) {
                if ($name === 'findUnitAssignmentForDepartment') {
                    return $this->hasUnit && $args[1] === 85 ? ['status' => $this->unitStatus] : null;
                }
                if ($name === 'findStaffAssignmentForUser') {
                    return $args[1] === 14 ? ['status' => $this->staffStatus] : null;
                }
                if (in_array($name, ['hasUnresolvedStaffAssignmentsForDepartment', 'hasActiveStaffAssignmentsForDepartment'], true)) {
                    return $args[1] === 85 && $this->hasUnitStaff;
                }
                return str_starts_with($name, 'has') ? false : null;
            }
        };
        $departments = new class {
            public function isUnit($id) { return $id === 85; }
            public function isDivision($id) { return $id === 12; }
            public function isParentDepartment($id) { return $id === 10; }
        };
        $reflection = new ReflectionClass(ActionSlips::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('slipModel')->setValue($controller, $model);
        $reflection->getProperty('departmentModel')->setValue($controller, $departments);
        $method = $reflection->getMethod('allowedActions');
        $slip = ['id' => 1301, 'status' => 'For Action', 'receiving_level' => 'Division',
            'receiving_department_id' => 10, 'current_department_id' => 10,
            'current_division_id' => 12, 'current_unit_id' => 85, 'assigned_staff_id' => 14];
        $previousSession = $_SESSION ?? [];
        try {
            $_SESSION = ['role' => 'manager', 'department_id' => 85, 'user_id' => 11];
            test_assert($method->invoke($controller, $slip)['receive_unit'], 'SDU must receive despite Eric being assigned.');
            $model->staffStatus = 'For Action';
            test_assert($method->invoke($controller, $slip)['receive_unit'], 'Eric receiving first must not block SDU.');
            $model->unitStatus = 'Received';
            test_assert(!$method->invoke($controller, $slip)['receive_unit'], 'A received unit assignment cannot receive again.');
            $actions = $method->invoke($controller, $slip);
            test_assert($actions['delegate_staff_to_unit_staff'], 'Received SDU work can be delegated to unit staff.');
            test_assert($actions['complete_unit'], 'Received SDU work can complete independently of Eric.');
            $model->hasUnitStaff = true;
            test_assert(!$method->invoke($controller, $slip)['complete_unit'], 'Unresolved SDU staff work must block direct completion.');
            $model->hasUnitStaff = false;
            $model->unitStatus = 'Completed';
            test_assert(!$method->invoke($controller, $slip)['complete_unit'], 'Completed unit work cannot complete again.');
            $model->staffStatus = 'Delegated';
            $_SESSION = ['role' => 'staff', 'department_id' => 12, 'user_id' => 14];
            test_assert($method->invoke($controller, $slip)['start_staff'], 'SDU receiving first must not block Eric.');
            $_SESSION = ['role' => 'manager', 'department_id' => 86, 'user_id' => 99];
            test_assert(!$method->invoke($controller, $slip)['receive_unit'], 'An unrelated manager cannot receive SDU work.');
            test_assert(!$method->invoke($controller, $slip)['complete_unit'], 'An unrelated manager cannot complete SDU work.');
            $_SESSION = ['role' => 'manager', 'department_id' => 85, 'user_id' => 11];
            $model->hasUnit = false;
            $slip['status'] = 'Delegated';
            test_assert(!$method->invoke($controller, $slip)['receive_unit'], 'Legacy slips with assigned staff retain their receipt guard.');
            $slip['status'] = 'Received';
            test_assert(!$method->invoke($controller, $slip)['complete_unit'], 'Legacy completion retains the staff guard.');
            $slip['status'] = 'Delegated';
            $slip['assigned_staff_id'] = null;
            test_assert($method->invoke($controller, $slip)['receive_unit'], 'Legacy unit slips without assigned staff can still receive.');
        } finally {
            $_SESSION = $previousSession;
        }
    },
    'unit completion preserves parallel staff assignment and active rollup' => function ($root) {
        require_once $root . '/app/init.php';
        require_once APPROOT . '/models/DepartmentActionSlip.php';
        $db = new class {
            public $writes = [];
            public $active = false;
            public $unresolvedUnits = 0;
            public $unresolvedStaff = 0;
            public $delegated = false;
            public function inTransaction() { return $this->active; }
            public function beginTransaction() { test_assert(!$this->active, 'Nested transactions are not allowed.'); $this->active = true; return true; }
            public function commit() { $this->active = false; return true; }
            public function rollBack() { $this->active = false; }
            public function prepare($sql) {
                return new class($this, $sql) {
                    private $db;
                    private $sql;
                    private $params = [];
                    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
                    public function execute($params) {
                        $this->params = $params;
                        if (preg_match('/^\s*(UPDATE|INSERT)/', $this->sql)) {
                            $this->db->writes[] = ['sql' => $this->sql, 'params' => $params];
                        }
                        return true;
                    }
                    public function rowCount() { return 1; }
                    public function fetchColumn() {
                        if (str_contains($this->sql, 'FOR UPDATE')) { return 1301; }
                        if (str_contains($this->sql, "a.assignment_type = 'unit'")) { return (int) $this->db->delegated; }
                        if (str_contains($this->sql, 'status NOT IN')) {
                            return str_contains($this->sql, 'unit_assignment.') ? $this->db->unresolvedUnits : $this->db->unresolvedStaff;
                        }
                        return 0;
                    }
                    public function fetch($mode) {
                        if (str_contains($this->sql, 'AS active_count')) {
                            return ['returned_count' => 0, 'active_count' => 2, 'total_count' => 3];
                        }
                        return ['id' => $this->params['assignment_id'] ?? 1301, 'status' => 'For Action', 'assigned_staff_id' => 14,
                            'user_id' => 13, 'department_id' => 85, 'completed_at' => null, 'completed_by' => null];
                    }
                };
            }
        };
        $reflection = new ReflectionClass(DepartmentActionSlip::class);
        $model = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($model, $db);
        $model->completeByUnitManager(1301, 11, 85, 'SDU task completed');
        test_assert_same(3, count($db->writes));
        $assignment = $db->writes[0]['params'];
        test_assert_same('unit', $assignment['assignment_type']);
        test_assert_same(85, $assignment['department_id']);
        test_assert_same('Completed', $assignment['status']);
        $slip = $db->writes[1]['params'];
        test_assert(!array_key_exists('assigned_staff_id', $slip), 'Unit completion must preserve Eric in the shared staff field.');
        test_assert_same('For Action', $slip['status'], 'Other active assignments keep the slip open.');
        test_assert_same(null, $slip['completed_at']);
        test_assert_same(null, $slip['completed_by']);
        test_assert_same('Completed by Unit', $db->writes[2]['params']['action']);
        foreach ([
            ['completeByUnitManager', 85, false],
            ['completeByDepartmentManager', 10, false],
            ['confirmByDivisionManager', 12, true],
            ['confirmByUnitManager', 85, true]
        ] as [$method, $department, $confirmation]) {
            foreach ([null, 'action_slips/completions/test-output.pdf'] as $attachment) {
                $db->writes = [];
                $args = [1301, 11, $department, 'Completion remarks'];
                if ($confirmation) {
                    $args[] = 280;
                }
                $args[] = $attachment;
                $model->$method(...$args);
                $event = end($db->writes)['params'];
                test_assert_same($attachment, $event['attachment'], $method . ' must record its optional file in history.');
                test_assert_same('Completion remarks', $event['remarks']);
                if ($confirmation) {
                    test_assert(!array_key_exists('completion_attachment', $db->writes[0]['params']), 'Confirmation must preserve the recipient output.');
                } else {
                    test_assert_same($attachment, $db->writes[0]['params']['completion_attachment']);
                }
            }
        }
        foreach ([['unit', 0, 1], ['staff', 1, 0], ['staff', 0, 0]] as [$type, $pendingUnits, $pendingStaff]) {
            $db->writes = [];
            $db->unresolvedUnits = $pendingUnits;
            $db->unresolvedStaff = $pendingStaff;
            $model->confirmByDivisionManager(1301, 15, 12, 'Review complete', 280, null, $type);
            test_assert_same('Confirmed', $db->writes[0]['params']['status']);
            test_assert_same(280, $db->writes[0]['params']['id'], 'Only the selected recipient may be confirmed.');
            test_assert_same($pendingUnits || $pendingStaff ? 'For Action' : 'Completed', $db->writes[1]['params']['status'], 'Division completion must wait for both recipient types.');
            $event = end($db->writes)['params'];
            test_assert_same($type === 'staff' ? 'Staff Completion Confirmed' : 'Unit Completion Confirmed', $event['action']);
            test_assert(!array_key_exists('assigned_staff_id', $db->writes[2]['params']), 'Confirmation must preserve other assignment pointers.');
            test_assert(!$db->active, 'Confirmation must commit its transaction.');
        }
        $db->writes = [];
        $db->delegated = true;
        $blocked = false;
        try { $model->confirmByDivisionManager(1301, 15, 12, 'Premature direct completion'); }
        catch (RuntimeException $error) { $blocked = true; }
        test_assert($blocked, 'Server must reject direct completion after division delegation.');
        test_assert_same([], $db->writes, 'Rejected completion must not change any assignment.');
        test_assert(!$db->active, 'Rejected completion must roll back its transaction.');
    }
];
