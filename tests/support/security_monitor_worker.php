<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__, 2) . '/app/lib/SecurityRequestMonitor.php';
require_once dirname(__DIR__, 2) . '/app/lib/ClientRequestContext.php';
[$script, $counter, $audit, $iterations] = $argv;
foreach ([$counter, $audit] as $table) {
    if (!preg_match('/^security_test_[a-f0-9]{12}_(counters|audit)$/D', $table)) {
        throw new RuntimeException('Worker must use isolated test tables.');
    }
}
if (strpos((string) getenv('SECURITY_TEST_DB_NAME'), 'dts_security_test') !== 0) {
    throw new RuntimeException('Worker requires a dedicated test schema.');
}
$pdo = new PDO('mysql:host=' . getenv('SECURITY_TEST_DB_HOST') . ';port=' . (getenv('SECURITY_TEST_DB_PORT') ?: '3306') . ';dbname=' . getenv('SECURITY_TEST_DB_NAME') . ';charset=utf8mb4',
    getenv('SECURITY_TEST_DB_USER'), getenv('SECURITY_TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$monitor = new SecurityRequestMonitor($pdo, SecurityRequestMonitor::defaultPolicies(), 'fixture-key',
    static function () {}, static function () {}, ['counters' => $counter, 'audit' => $audit]);
$client = ClientRequestContext::resolve(['REMOTE_ADDR' => '203.0.113.8'], []);
for ($i = 0; $i < (int) $iterations; $i++) {
    $result = $monitor->observe('registration', 'concurrent-subject', $client);
    if ($result['status'] !== 'observed') { exit(2); }
}
echo 'ok';
