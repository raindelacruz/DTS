<?php
if (PHP_SAPI!=='cli' || !in_array(getenv('SECURITY_TEST_DB_NAME'),['dts_security_test_phase4','dts_security_test_phase5'],true)) { exit(2); }
require_once dirname(__DIR__,2).'/app/init.php';
$pdo=new PDO('mysql:host='.getenv('SECURITY_TEST_DB_HOST').';port='.getenv('SECURITY_TEST_DB_PORT').';dbname='.getenv('SECURITY_TEST_DB_NAME'),
    getenv('SECURITY_TEST_DB_USER'),getenv('SECURITY_TEST_DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$result=(new RegistrationProtection($pdo,'fixture-key'))->register(
    ['id_number'=>'Race-Employee','email'=>'race@example.test','firstname'=>'Race','lastname'=>'Employee','department_id'=>2],
    'Fixture-Password-42!',getenv('REGISTRATION_TEST_CODE'));
echo $result['status'];
