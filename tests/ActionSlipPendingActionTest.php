<?php

require_once dirname(__DIR__) . '/app/lib/ActionSlipPendingAction.php';

function pending_action_fixture(): array
{
    $departments = [
        ['id' => 1, 'parent_id' => null, 'office_level' => 'department'],
        ['id' => 2, 'parent_id' => 1, 'office_level' => 'division'],
        ['id' => 3, 'parent_id' => 1, 'office_level' => 'division'],
        ['id' => 4, 'parent_id' => 2, 'office_level' => 'unit'],
        ['id' => 5, 'parent_id' => 3, 'office_level' => 'unit'],
    ];
    $users = [];
    foreach ([
        [10, 'Gary', 'L.', 'Riparip', 2, 'manager'],
        [11, 'Maria Luisa', '', 'Quiroz', 3, 'manager'],
        [12, 'Rainier', '', 'Dela Cruz', 4, 'manager'],
        [13, 'Boots', '', '', 2, 'staff'],
        [14, 'Roel', '', '', 5, 'manager'],
        [15, 'Department', '', 'Manager', 1, 'manager'],
        [16, 'Unit', '', 'Staff', 4, 'staff'],
    ] as [$id, $first, $middle, $last, $office, $role]) {
        $users[] = ['id' => $id, 'firstname' => $first, 'middle_initial' => $middle,
            'lastname' => $last, 'department_id' => $office, 'role' => $role, 'status' => 'active'];
    }
    return [$users, $departments];
}

function pending_assignment($type, $department, $status, $user = null): array
{
    return ['assignment_type' => $type, 'department_id' => $department, 'status' => $status, 'user_id' => $user];
}

function assert_pending_names($expected, $assignments, $users, $departments, $status = 'For Action'): void
{
    $actual = ActionSlipPendingAction::names(['status' => $status], $assignments, $users, $departments);
    sort($expected);
    sort($actual);
    test_assert_same($expected, $actual);
}

return [
    'pending names follow parallel divisions and individual completion reviews' => function ($root) {
        [$users, $departments] = pending_action_fixture();
        $assignments = [pending_assignment('division', 2, 'Released'), pending_assignment('division', 3, 'Released')];
        assert_pending_names(['Gary L. Riparip', 'Maria Luisa Quiroz'], $assignments, $users, $departments);
        $assignments[0]['status'] = 'Received';
        assert_pending_names(['Gary L. Riparip', 'Maria Luisa Quiroz'], $assignments, $users, $departments);
        $assignments[0]['status'] = 'For Action';
        $assignments[] = pending_assignment('unit', 4, 'Delegated', 12);
        $assignments[] = pending_assignment('staff', 2, 'Delegated', 13);
        assert_pending_names(['Maria Luisa Quiroz', 'Rainier Dela Cruz', 'Boots'], $assignments, $users, $departments);
        $assignments[2]['status'] = 'Received';
        $assignments[3]['status'] = 'For Action';
        assert_pending_names(['Maria Luisa Quiroz', 'Rainier Dela Cruz', 'Boots'], $assignments, $users, $departments);
        $assignments[2]['status'] = 'Completed';
        assert_pending_names(['Maria Luisa Quiroz', 'Gary L. Riparip', 'Boots'], $assignments, $users, $departments);
        $assignments[3]['status'] = 'Completed';
        assert_pending_names(['Maria Luisa Quiroz', 'Gary L. Riparip'], $assignments, $users, $departments);
        // Gary confirms Rainier first; Boots still requires Gary's review.
        $assignments[2]['status'] = 'Confirmed';
        assert_pending_names(['Maria Luisa Quiroz', 'Gary L. Riparip'], $assignments, $users, $departments);
        $assignments[3]['status'] = 'Confirmed';
        $assignments[0]['status'] = 'Completed';
        $assignments[1]['status'] = 'For Action';
        $assignments[] = pending_assignment('unit', 5, 'Delegated', 14);
        assert_pending_names(['Roel'], $assignments, $users, $departments);
        $assignments[4]['status'] = 'Received';
        assert_pending_names(['Roel'], $assignments, $users, $departments);
        $assignments[4]['status'] = 'Completed';
        assert_pending_names(['Maria Luisa Quiroz'], $assignments, $users, $departments);
        $assignments[4]['status'] = 'Confirmed';
        $assignments[1]['status'] = 'Completed';
        assert_pending_names([], $assignments, $users, $departments, 'Completed');
    },
    'pending names handle unit staff returns and confirmation before division review' => function ($root) {
        [$users, $departments] = pending_action_fixture();
        $assignments = [pending_assignment('division', 2, 'For Action'),
            pending_assignment('unit', 4, 'For Action', 12), pending_assignment('staff', 4, 'For Action', 16)];
        assert_pending_names(['Unit Staff'], $assignments, $users, $departments);
        $assignments[2]['status'] = 'Completed';
        assert_pending_names(['Rainier Dela Cruz'], $assignments, $users, $departments);
        $assignments[2]['status'] = 'Returned';
        assert_pending_names(['Unit Staff'], $assignments, $users, $departments, 'Returned');
        $assignments[2]['status'] = 'Confirmed';
        $assignments[1]['status'] = 'Completed';
        assert_pending_names(['Gary L. Riparip'], $assignments, $users, $departments);
    },
    'completed slip badge does not hide staff output awaiting manager review' => function ($root) {
        [$users, $departments] = pending_action_fixture();
        assert_pending_names(['Gary L. Riparip'], [pending_assignment('staff', 2, 'Completed', 13)], $users, $departments, 'Completed');
        assert_pending_names([], [pending_assignment('staff', 2, 'Confirmed', 13)], $users, $departments, 'Completed');
    },
    'department manager returns after delegated branch is resolved' => function ($root) {
        [$users, $departments] = pending_action_fixture();
        $assignments = [pending_assignment('department', 1, 'Received'), pending_assignment('division', 2, 'Received')];
        assert_pending_names(['Gary L. Riparip'], $assignments, $users, $departments);
        $assignments[1]['status'] = 'Completed';
        assert_pending_names(['Department Manager'], $assignments, $users, $departments);
        $assignments[0]['status'] = 'Completed';
        assert_pending_names([], $assignments, $users, $departments, 'Completed');
    },
    'office recipients show all active managers and named unit recipient stays specific' => function ($root) {
        [$users, $departments] = pending_action_fixture();
        $users[] = ['id' => 20, 'firstname' => 'Second', 'middle_initial' => '', 'lastname' => 'Manager',
            'department_id' => 2, 'role' => 'manager', 'status' => 'active'];
        $users[] = ['id' => 21, 'firstname' => 'Other', 'middle_initial' => '', 'lastname' => 'Head',
            'department_id' => 4, 'role' => 'manager', 'status' => 'active'];
        assert_pending_names(['Gary L. Riparip', 'Second Manager'], [pending_assignment('division', 2, 'Received')], $users, $departments);
        assert_pending_names(['Rainier Dela Cruz'], [pending_assignment('unit', 4, 'Received', 12)], $users, $departments);
        $users[2]['status'] = 'inactive';
        assert_pending_names(['Responsible person unavailable'], [pending_assignment('unit', 4, 'Received', 12)], $users, $departments);
    },
    'legacy recipients cancelled branches and empty states are handled' => function ($root) {
        [$users, $departments] = pending_action_fixture();
        $slip = ['status' => 'Received', 'current_division_id' => 2];
        test_assert_same(['Gary L. Riparip'], ActionSlipPendingAction::names($slip, [], $users, $departments));
        assert_pending_names([], [pending_assignment('staff', 2, 'Cancelled', 13)], $users, $departments);
        assert_pending_names([], [pending_assignment('staff', 2, 'Received', 13)], $users, $departments, 'Cancelled');
        assert_pending_names(['Responsible person unavailable'], [pending_assignment('staff', 2, 'Received', 99)], $users, $departments);
        test_assert_same('No pending action', ActionSlipPendingAction::label([]));
    },
    'pending name enrichment batches existing rows without changing stored data' => function ($root) {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            test_skip('Requires SQLite for an isolated in-memory query check.');
        }
        require_once $root . '/app/models/DepartmentActionSlip.php';
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER, firstname TEXT, middle_initial TEXT, lastname TEXT, department_id INTEGER, role TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE departments (id INTEGER, parent_id INTEGER, office_level TEXT)');
        $pdo->exec('CREATE TABLE department_action_slip_assignments (id INTEGER, slip_id INTEGER, assignment_type TEXT, department_id INTEGER, user_id INTEGER, status TEXT, assigned_at TEXT)');
        [$users, $departments] = pending_action_fixture();
        $insertUser = $pdo->prepare('INSERT INTO users VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($users as $user) {
            $insertUser->execute(array_values($user));
        }
        $insertOffice = $pdo->prepare('INSERT INTO departments VALUES (?, ?, ?)');
        foreach ($departments as $department) {
            $insertOffice->execute(array_values($department));
        }
        $pdo->exec("INSERT INTO department_action_slip_assignments VALUES (1, 100, 'division', 2, NULL, 'Received', '2026-11-19'), (2, 101, 'staff', 2, 13, 'For Action', '2026-11-19'), (3, 102, 'staff', 2, 99, 'For Action', '2026-11-19')");
        $snapshot = $pdo->query('SELECT * FROM department_action_slip_assignments')->fetchAll(PDO::FETCH_ASSOC);
        $reflection = new ReflectionClass(DepartmentActionSlip::class);
        $model = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($model, $pdo);
        $rows = $model->withPendingActionNames([
            ['id' => 100, 'status' => 'Received'], ['id' => 101, 'status' => 'For Action'],
            ['id' => 103, 'status' => 'Completed'],
        ]);
        test_assert_same(['Gary L. Riparip', 'Boots', 'No pending action'], array_column($rows, 'pending_action_by'));
        test_assert_same([100, 101, 103], array_column($rows, 'id'), 'Only supplied visible slips should be enriched.');
        test_assert_same([], $model->withPendingActionNames([]));
        test_assert_same($snapshot, $pdo->query('SELECT * FROM department_action_slip_assignments')->fetchAll(PDO::FETCH_ASSOC));
    },
];
