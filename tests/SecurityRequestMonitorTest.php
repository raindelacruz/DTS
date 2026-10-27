<?php

class UnavailableMonitorPDO extends PDO
{
    public bool $activeTransaction = false;
    public bool $rolledBack = false;
    public function __construct() {}
    public function inTransaction(): bool { return $this->activeTransaction; }
    public function beginTransaction(): bool { throw new PDOException('Sensitive diagnostic must not be logged', 42); }
    public function rollBack(): bool { $this->rolledBack = true; return true; }
}

return [
    'monitoring failure is explicit and does not expose exception detail' => function ($root) {
        require_once $root . '/app/lib/SecurityRequestMonitor.php';
        require_once $root . '/app/lib/ClientRequestContext.php';
        $messages = [];
        $pdo = new UnavailableMonitorPDO();
        $monitor = new SecurityRequestMonitor($pdo, SecurityRequestMonitor::defaultPolicies(), 'test-key',
            function ($message) use (&$messages) { $messages[] = $message; });
        $result = $monitor->observe('login', 'private-identifier', ClientRequestContext::resolve(['REMOTE_ADDR' => '203.0.113.7'], []));
        test_assert_same('unavailable', $result['status']);
        test_assert_same(false, $result['blocked'], 'Observation must not block a user on counter failure.');
        test_assert_same(1, count($messages));
        test_assert(strpos(json_encode($messages), 'Sensitive diagnostic') === false);
        test_assert(strpos(json_encode($messages), 'private-identifier') === false);
    },
    'monitoring does not roll back an existing caller transaction' => function ($root) {
        require_once $root . '/app/lib/SecurityRequestMonitor.php';
        require_once $root . '/app/lib/ClientRequestContext.php';
        $pdo = new UnavailableMonitorPDO();
        $pdo->activeTransaction = true;
        $monitor = new SecurityRequestMonitor($pdo, SecurityRequestMonitor::defaultPolicies(), 'test-key', static function () {});
        $result = $monitor->observe('mfa', 'user:11', ClientRequestContext::resolve(['REMOTE_ADDR' => '203.0.113.7'], []));
        test_assert_same('unavailable', $result['status']);
        test_assert_same(false, $pdo->rolledBack);
    },
    'invalid monitoring policies cannot silently become unlimited' => function ($root) {
        require_once $root . '/app/lib/SecurityRequestMonitor.php';
        $policies = SecurityRequestMonitor::defaultPolicies();
        $policies['mfa']['subject'] = 0;
        try {
            new SecurityRequestMonitor(new UnavailableMonitorPDO(), $policies, 'test-key');
        } catch (InvalidArgumentException $e) {
            return;
        }
        throw new RuntimeException('Invalid policy must be rejected.');
    },
];
