<?php

return [
    'database migrations are present in target database' => function ($root) {
        require_once $root . '/app/init.php';

        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $count = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        test_assert($count >= 2, 'Expected the P0/P1 migrations to be recorded.');

        $fkCount = (int) $pdo->query("
            SELECT COUNT(*)
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
        ")->fetchColumn();
        test_assert($fkCount >= 40, 'Expected production foreign keys to exist.');
    },

    'security audit log is immutable' => function ($root) {
        require_once $root . '/app/init.php';

        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $id = (int) $pdo->query('SELECT id FROM security_audit_log ORDER BY id DESC LIMIT 1')->fetchColumn();
        if ($id <= 0) {
            test_skip('No audit rows exist yet.');
        }

        try {
            $stmt = $pdo->prepare("UPDATE security_audit_log SET event_type = event_type WHERE id = :id");
            $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            test_assert(stripos($e->getMessage(), 'immutable') !== false, 'Audit update should be blocked by immutable trigger.');
            return;
        }

        throw new RuntimeException('Audit log update unexpectedly succeeded.');
    },
];
