<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$name = getenv('SECURITY_TEST_DB_NAME');
if (strpos((string) $name, 'dts_security_test') !== 0) { exit(2); }
require_once dirname(__DIR__, 2) . '/app/lib/AuthenticationGuard.php';
$pdo = new PDO('mysql:host=' . getenv('SECURITY_TEST_DB_HOST') . ';port=' . getenv('SECURITY_TEST_DB_PORT') . ';dbname=' . $name,
    getenv('SECURITY_TEST_DB_USER'), getenv('SECURITY_TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$result = (new AuthenticationGuard($pdo, 'fixture-key'))->check('login', 'concurrent', static function () { usleep(20000); return false; });
echo $result['status'];
