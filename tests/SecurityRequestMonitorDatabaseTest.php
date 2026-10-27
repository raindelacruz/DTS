<?php

return [
    'database monitor preserves atomic counts privacy retention and worker concurrency' => function ($root) {
        if (getenv('RUN_SECURITY_DB_TESTS') !== '1') {
            test_skip('Set RUN_SECURITY_DB_TESTS=1 and SECURITY_TEST_DB_* for isolated table tests.');
        }
        require_once $root . '/app/lib/SecurityRequestMonitor.php';
        require_once $root . '/app/lib/ClientRequestContext.php';
        foreach (['HOST', 'NAME', 'USER'] as $field) {
            test_assert(getenv('SECURITY_TEST_DB_' . $field) !== false, 'Explicit test connection required.');
        }
        test_assert(strpos(getenv('SECURITY_TEST_DB_NAME'), 'dts_security_test') === 0,
            'Database tests require a dedicated dts_security_test* schema; production schemas are refused.');
        $pdo = new PDO('mysql:host=' . getenv('SECURITY_TEST_DB_HOST') . ';port=' . (getenv('SECURITY_TEST_DB_PORT') ?: '3306') . ';dbname=' . getenv('SECURITY_TEST_DB_NAME') . ';charset=utf8mb4',
            getenv('SECURITY_TEST_DB_USER'), getenv('SECURITY_TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $prefix = 'security_test_' . bin2hex(random_bytes(6));
        $counter = $prefix . '_counters';
        $audit = $prefix . '_audit';
        $children = [];
        try {
            $migration = require $root . '/database/migrations/20261010_0017_security_request_monitor.php';
            $migration($pdo, $counter);
            $pdo->exec("CREATE TABLE {$audit} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_type VARCHAR(64),
                actor_user_id INT NULL, target_user_id INT NULL, ip_address VARCHAR(45),
                user_agent VARCHAR(500), metadata LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            $policies = SecurityRequestMonitor::defaultPolicies();
            $policies['login']['subject'] = 2;
            $failures = [];
            $monitor = new SecurityRequestMonitor($pdo, $policies, 'fixture-key',
                function ($message) use (&$failures) { $failures[] = $message; }, static function () {},
                ['counters' => $counter, 'audit' => $audit]);
            $client = ClientRequestContext::resolve(['REMOTE_ADDR' => '203.0.113.7'], []);
            $first = $monitor->observe('login', 'Private@Example.test', $client);
            $_SESSION = [];
            $_SERVER['HTTP_USER_AGENT'] = 'Different test browser';
            $second = $monitor->observe('login', 'private@example.test', $client);
            $third = $monitor->observe('login', 'private@example.test', $client);
            test_assert_same(1, $first['buckets'][0]['count']);
            test_assert_same(2, $second['buckets'][0]['count'], 'Browser/session changes must not reset subject counts.');
            test_assert_same(true, $third['buckets'][0]['exceeded']);
            test_assert_same(false, $third['blocked'], 'Observation thresholds must not block users.');
            $other = $monitor->observe('login', 'other-account', $client);
            test_assert_same(1, $other['buckets'][0]['count'], 'A shared-IP account must have its own subject count.');
            test_assert_same(4, $other['buckets'][1]['count']);
            $events = $pdo->query("SELECT metadata FROM {$audit}")->fetchAll(PDO::FETCH_COLUMN);
            test_assert(stripos(json_encode($events), 'private@example.test') === false, 'Subjects must only be HMAC hashes.');
            test_assert_same(1, (int) $pdo->query("SELECT COUNT(*) FROM {$audit} WHERE event_type='security_threshold_observed'")->fetchColumn());

            $countBefore = (int) $pdo->query("SELECT SUM(request_count) FROM {$counter}")->fetchColumn();
            $broken = new SecurityRequestMonitor($pdo, $policies, 'fixture-key', static function () {}, static function () {},
                ['counters' => $counter, 'audit' => $prefix . '_missing']);
            test_assert_same('unavailable', $broken->observe('login', 'private@example.test', $client)['status']);
            test_assert_same($countBefore, (int) $pdo->query("SELECT SUM(request_count) FROM {$counter}")->fetchColumn(), 'Audit failure must roll back counter increments.');

            // Real independent PHP workers, using only the named fixture tables.
            for ($worker = 0; $worker < 4; $worker++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, $root . '/tests/support/security_monitor_worker.php', $counter, $audit, '20'],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
                test_assert(is_resource($process), 'Unable to start concurrency worker.');
                fclose($pipes[0]);
                $children[] = [$process, $pipes];
            }
            foreach ($children as [$process, $pipes]) {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                test_assert_same(0, proc_close($process), 'Concurrency worker failed: ' . $stderr);
                test_assert_same('ok', $stdout);
            }
            $children = [];
            $concurrent = $pdo->query("SELECT request_count FROM {$counter} WHERE action='registration'")->fetchAll(PDO::FETCH_COLUMN);
            test_assert_same(3, count($concurrent));
            foreach ($concurrent as $count) { test_assert_same(80, (int) $count, 'No increments may be lost between workers.'); }
            test_assert_same(80, (int) $pdo->query("SELECT COUNT(*) FROM {$audit} WHERE event_type='security_request_observed' AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.action'))='registration'")->fetchColumn());

            $pdo->exec("UPDATE {$counter} SET expires_at = UNIX_TIMESTAMP() - 1 WHERE action='registration'");
            test_assert_same(2, $monitor->prune(2), 'Cleanup must obey its batch bound.');
            test_assert_same(1, $monitor->prune(2));
            test_assert((int) $pdo->query("SELECT COUNT(*) FROM {$counter} WHERE action='login'")->fetchColumn() > 0, 'Unexpired windows must remain.');
            test_assert_same([], $failures);
        } finally {
            foreach ($children as [$process, $pipes]) {
                if (is_resource($process)) { proc_terminate($process); proc_close($process); }
                foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            }
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // Only the random, validated fixture names created above are removed.
            $pdo->exec("DROP TABLE IF EXISTS {$audit}");
            $pdo->exec("DROP TABLE IF EXISTS {$counter}");
        }
    },
];
