<?php
return [
    'MFA challenge binds account version expiry and attempt budget' => function ($root) {
        require_once $root . '/app/lib/AuthenticationGuard.php';
        $s = ['user_id' => 10, 'session_version' => 4, 'mfa_verified' => true, 'mfa_setup_secret' => 'old'];
        AuthenticationGuard::startChallenge($s, 1000);
        test_assert_same(false, $s['mfa_verified']);
        test_assert(!isset($s['mfa_setup_secret']));
        test_assert(AuthenticationGuard::challengeValid($s, 10, 4, 1299));
        test_assert(!AuthenticationGuard::challengeValid($s, 10, 4, 1300));
        test_assert(!AuthenticationGuard::challengeValid($s, 11, 4, 1001));
        test_assert(!AuthenticationGuard::challengeValid($s, 10, 5, 1001));
        $s['mfa_challenge']['attempts'] = 5;
        test_assert(!AuthenticationGuard::challengeValid($s, 10, 4, 1001));
    },
    'TOTP matching returns step preserves tolerance and rejects malformed codes' => function ($root) {
        require_once $root . '/app/lib/TotpService.php';
        $secret = 'JBSWY3DPEHPK3PXP';
        $method = new ReflectionMethod(TotpService::class, 'code');
        $method->setAccessible(true);
        $code = $method->invoke(null, $secret, 100);
        test_assert_same(100, TotpService::matchingStep($secret, $code, 3000));
        test_assert_same(100, TotpService::matchingStep($secret, $code, 3030));
        test_assert_same(false, TotpService::matchingStep($secret, $code, 3060));
        test_assert_same(false, TotpService::matchingStep($secret, [$code], 3000));
        test_assert_same(false, TotpService::matchingStep($secret, 'x' . $code, 3000));
    },
    'database authentication guard limits retries and rejects replay without cross-account denial' => function ($root) {
        if (getenv('RUN_SECURITY_DB_TESTS') !== '1') { test_skip('Requires isolated security test database.'); }
        $name = getenv('SECURITY_TEST_DB_NAME');
        test_assert(strpos($name, 'dts_security_test') === 0, 'Production schemas refused.');
        require_once $root . '/app/lib/AuthenticationGuard.php';
        $pdo = new PDO('mysql:host=' . getenv('SECURITY_TEST_DB_HOST') . ';port=' . getenv('SECURITY_TEST_DB_PORT') . ';dbname=' . $name,
            getenv('SECURITY_TEST_DB_USER'), getenv('SECURITY_TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $migration = require $root . '/database/migrations/20261010_0018_authentication_guard.php';
        $migration($pdo);
        $pdo->exec('DELETE FROM authentication_guard');
        $guard = new AuthenticationGuard($pdo, 'fixture-key');
        for ($i = 0; $i < 5; $i++) { test_assert_same('invalid', $guard->check('login', 'victim', static function () { return false; })['status']); }
        test_assert_same('limited', $guard->check('login', 'victim', static function () { throw new RuntimeException('Must not check blocked credentials'); })['status']);
        test_assert_same('accepted', $guard->check('login', 'other', static function () { return (object) ['id' => 2]; })['status']);
        test_assert_same('accepted', $guard->check('mfa', 'user:1', static function () { return 100; }, 'secret')['status']);
        test_assert_same('invalid', $guard->check('mfa', 'user:1', static function () { return 100; }, 'secret')['status']);
        $pdo->exec('UPDATE authentication_guard SET retry_at=0');
        test_assert_same('accepted', $guard->check('mfa', 'user:1', static function () { return 101; }, 'secret')['status']);
        $pdo->exec('UPDATE authentication_guard SET retry_at=0, window_start=0');
        test_assert_same('invalid', $guard->check('mfa', 'user:1', static function () { return 100; }, 'secret')['status'], 'Window rollover cannot erase replay history.');
        $workers = [];
        for ($i = 0; $i < 8; $i++) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, $root . '/tests/support/authentication_guard_worker.php'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $statuses = [];
        foreach ($workers as [$process, $pipes]) {
            $statuses[] = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            test_assert_same(0, proc_close($process), $errors);
        }
        test_assert_same(5, count(array_filter($statuses, static function ($s) { return $s === 'invalid'; })));
        test_assert_same(3, count(array_filter($statuses, static function ($s) { return $s === 'limited'; })));
        $pdo->exec('DROP TABLE authentication_guard');
        try { $guard->check('login', 'unavailable', static function () { throw new LogicException('Verification must not run'); }); test_assert(false); }
        catch (RuntimeException $e) { test_assert_same('Sign-in protection is temporarily unavailable. Please try again later.', $e->getMessage()); }
    },
];
