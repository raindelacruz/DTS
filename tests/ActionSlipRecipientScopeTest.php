<?php

return [
    'direct unit staff assignments remain independent and cannot be duplicated by unit heads' => function ($root) {
        require_once $root . '/app/init.php';
        require_once APPROOT . '/models/DepartmentActionSlip.php';
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            test_skip('Requires PDO SQLite for isolated assignment queries.');
        }
        // Exercise real assignment SQL in memory, adapting only MySQL-specific syntax.
        // Production uses FOR UPDATE; the existing delegation tests check transaction guards.
        $db = new class extends PDO {
            public function __construct() {
                parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $this->sqliteCreateFunction('CONCAT', static fn (...$parts) => implode('', $parts));
            }
            public function prepare(string $query, array $options = []): PDOStatement|false {
                return parent::prepare(str_replace(['FOR UPDATE', '<=>', 'NOW()'], ['', 'IS', 'CURRENT_TIMESTAMP'], $query), $options);
            }
        };
        $db->exec(<<<'SQL'
CREATE TABLE departments (id INTEGER PRIMARY KEY, parent_id INTEGER, office_level TEXT, division_name TEXT);
CREATE TABLE users (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, middle_initial TEXT, email TEXT, department_id INTEGER, role TEXT, status TEXT);
CREATE TABLE department_action_slips (id INTEGER PRIMARY KEY, slip_number TEXT, status TEXT,
 current_department_id INTEGER, receiving_department_id INTEGER, current_division_id INTEGER, current_unit_id INTEGER,
 assigned_staff_id INTEGER, completed_at TEXT, completed_by INTEGER, closed_at TEXT, closed_by INTEGER);
CREATE TABLE department_action_slip_assignments (id INTEGER PRIMARY KEY AUTOINCREMENT, slip_id INTEGER,
 assignment_type TEXT, department_id INTEGER, user_id INTEGER, assigned_by INTEGER, assigned_by_department_id INTEGER,
 status TEXT, remarks TEXT, assigned_at TEXT DEFAULT CURRENT_TIMESTAMP, received_at TEXT, completed_at TEXT,
 completed_by INTEGER, completion_attachment TEXT);
CREATE TABLE department_action_slip_events (id INTEGER PRIMARY KEY AUTOINCREMENT, slip_id INTEGER, action TEXT,
 actor_user_id INTEGER, actor_department_id INTEGER, from_department_id INTEGER, to_department_id INTEGER,
 from_user_id INTEGER, to_user_id INTEGER, old_status TEXT, new_status TEXT, remarks TEXT, attachment TEXT,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP);
INSERT INTO departments VALUES (10,NULL,'department','CPMSD'),(12,10,'division','ICTSD'),(85,12,'unit','SDU'),(99,10,'division','Other Division');
INSERT INTO users VALUES (15,'Gary','Riparip',NULL,'gary@example.test',12,'manager','active'),
 (11,'Rainier','Dela Cruz',NULL,'rainier@example.test',85,'manager','active'),
 (37,'Christian','Abrera',NULL,'christian@example.test',85,'staff','active'),
 (13,'Paulo','Jacinto',NULL,'paulo@example.test',85,'staff','active'),
 (14,'Eric','Aguirre',NULL,'eric@example.test',12,'staff','active'),
 (38,'Inactive','Staff',NULL,'inactive@example.test',85,'staff','inactive'),
 (90,'Other','Staff',NULL,'other@example.test',99,'staff','active');
INSERT INTO department_action_slips (id,slip_number,status,current_department_id,receiving_department_id,current_division_id)
 VALUES (1,'Fixture-1','For Action',10,10,12),(2,'Fixture-2','For Action',10,10,12),(3,'Fixture-3','For Action',10,10,12);
INSERT INTO department_action_slip_assignments (slip_id,assignment_type,department_id,assigned_by,assigned_by_department_id,status)
 VALUES (1,'division',12,15,12,'For Action'),(2,'division',12,15,12,'For Action'),(3,'division',12,15,12,'For Action');
SQL);
        $model = new class($db) extends DepartmentActionSlip {
            private $fixture;
            public function __construct($db) { $this->fixture = $db; }
            public function findById($id) {
                $stmt = $this->fixture->prepare('SELECT * FROM department_action_slips WHERE id = :id');
                $stmt->execute(['id' => $id]);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        };
        (new ReflectionClass(DepartmentActionSlip::class))->getProperty('db')->setValue($model, $db);
        $expectRejected = static function ($callback, $message) {
            $rejected = false;
            try { $callback(); } catch (RuntimeException $error) { $rejected = true; }
            test_assert($rejected, $message);
        };
        $ids = array_map('intval', array_column($model->getActiveStaffInDivisionHierarchy(12, 1), 'id'));
        test_assert(in_array(37, $ids) && in_array(14, $ids), 'Direct recipients must include unit staff and division staff.');
        test_assert(!in_array(90, $ids) && !in_array(38, $ids) && !in_array(11, $ids), 'Outsiders, inactive staff and unit heads must not appear as individual staff.');
        $model->delegateDivisionRecipients(1, [11], [37,14], 15, 12, 'Shared task');
        $christian = $model->findStaffAssignmentForUser(1, 37);
        test_assert_same(12, (int) $christian['department_id'], 'Direct Christian assignment belongs to Gary review, not SDU review.');
        test_assert_same(15, (int) $christian['assigned_by']);
        test_assert_same('ICTSD', $christian['reviewing_department_name']);
        $unitCandidates = array_map('intval', array_column($model->getEligibleStaffByDepartment(1, 85), 'id'));
        test_assert(!in_array(37, $unitCandidates) && in_array(13, $unitCandidates));
        $model->receiveByUnit(1, 11, 85);
        $before = (int) $db->query('SELECT COUNT(*) FROM department_action_slip_events')->fetchColumn();
        $expectRejected(fn () => $model->delegateStaffRecipients(1, [13,37], 11, 85), 'The entire unit batch must reject an already assigned Christian.');
        test_assert_same(null, $model->findStaffAssignmentForUser(1, 13), 'Rejected batch must not partially assign Paulo.');
        test_assert_same($before, (int) $db->query('SELECT COUNT(*) FROM department_action_slip_events')->fetchColumn());
        $expectRejected(fn () => $model->delegateToStaff(1, 37, 11, 85), 'Direct model calls must also block duplicate assignment.');
        $model->delegateStaffRecipients(1, [13], 11, 85);
        $model->startStaffAction(1, 37, 85);
        $model->completeByStaff(1, 37, 85, 'Direct completion', 'direct-output.pdf');
        test_assert(!$model->hasCompletedStaffAssignmentsForDepartment(1, 85), 'SDU must not count Christian direct completion as its own.');
        test_assert($model->hasCompletedStaffAssignmentsForDepartment(1, 12));
        $expectRejected(fn () => $model->confirmByUnitManager(1, 11, 85, '', $christian['id']), 'SDU cannot confirm Christian direct completion.');
        $expectRejected(fn () => $model->returnStaffCompletion(1, 11, 85, 'Wrong reviewer', $christian['id']), 'SDU cannot return Christian direct completion.');
        $model->returnStaffCompletion(1, 15, 12, 'Revise direct output', $christian['id']);
        $model->completeByStaff(1, 37, 85, 'Revised direct output', 'revised-output.pdf');
        $model->confirmByDivisionManager(1, 15, 12, '', $christian['id'], 'review.pdf', 'staff');
        test_assert_same('For Action', $model->findDivisionAssignmentForDepartment(1, 12)['status']);
        $paulo = $model->findStaffAssignmentForUser(1, 13);
        test_assert_same(85, (int) $paulo['department_id']);
        $model->startStaffAction(1, 13, 85);
        $model->completeByStaff(1, 13, 85, 'SDU work', 'sdu-output.pdf');
        $model->confirmByUnitManager(1, 11, 85, '', $paulo['id']);
        $unit = $model->findUnitAssignmentForDepartment(1, 85);
        test_assert_same('Completed', $unit['status']);
        $model->confirmByDivisionManager(1, 15, 12, '', $unit['id']);
        test_assert_same('For Action', $model->findDivisionAssignmentForDepartment(1, 12)['status'], 'Eric is still pending.');
        $model->startStaffAction(1, 14, 12);
        $model->completeByStaff(1, 14, 12);
        $eric = $model->findStaffAssignmentForUser(1, 14);
        $model->confirmByDivisionManager(1, 15, 12, '', $eric['id'], null, 'staff');
        test_assert_same('Completed', $model->findById(1)['status'], 'All independently reviewed assignments must finish the slip.');
        test_assert_same('revised-output.pdf', $model->findStaffAssignmentForUser(1, 37)['completion_attachment']);
        test_assert_same('sdu-output.pdf', $model->findStaffAssignmentForUser(1, 13)['completion_attachment']);
        $model->delegateDivisionRecipients(2, [11], [], 15, 12);
        $model->receiveByUnit(2, 11, 85);
        test_assert(in_array(37, array_map('intval', array_column($model->getEligibleStaffByDepartment(2, 85), 'id'))), 'Christian remains eligible when only SDU was selected.');
        $model->delegateStaffRecipients(2, [37], 11, 85);
        test_assert_same(85, (int) $model->findStaffAssignmentForUser(2, 37)['department_id']);
        foreach ([90,38,11] as $invalidUser) {
            $expectRejected(fn () => $model->delegateDivisionRecipients(3, [11], [$invalidUser], 15, 12), 'Invalid direct recipients must reject the entire division batch.');
            test_assert_same(null, $model->findUnitAssignmentForDepartment(3, 85));
        }
    }
];
