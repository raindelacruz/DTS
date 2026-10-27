<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$name = getenv('SECURITY_TEST_DB_NAME');
if (!in_array($name, ['dts_security_test_phase3','dts_security_test_phase4','dts_security_test_phase5'], true)) { exit(2); }
require_once dirname(__DIR__, 2) . '/app/init.php';
$pdo = new PDO('mysql:host=' . getenv('SECURITY_TEST_DB_HOST') . ';port=' . getenv('SECURITY_TEST_DB_PORT') . ';dbname=' . $name,
    getenv('SECURITY_TEST_DB_USER'), getenv('SECURITY_TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$service = new PasswordResetProtection($pdo, 'fixture-key');
switch ($argv[1] ?? '') {
    case 'request': echo $service->request('concurrent@example.test', 'encryptSensitiveValue'); break;
    case 'complete': echo $service->complete(getenv('RESET_TEST_TOKEN'), 'Updated-Password-42!') === null ? 'invalid' : 'completed'; break;
    case 'deliver': echo $service->deliverOne('decryptSensitiveValue', static function () { usleep(30000); return true; }); break;
    default: exit(2);
}
