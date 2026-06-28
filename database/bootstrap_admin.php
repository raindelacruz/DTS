<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/app/init.php';

$options = getopt('', ['id:', 'first:', 'last:', 'email:', 'department:']);
$required = ['id', 'first', 'last', 'email', 'department'];
foreach ($required as $key) {
    if (!isset($options[$key]) || trim((string) $options[$key]) === '') {
        fwrite(STDERR, "Missing --{$key}" . PHP_EOL);
        exit(1);
    }
}

$password = (string) (getenv('BOOTSTRAP_ADMIN_PASSWORD') ?: '');
if ($password === '') {
    fwrite(STDOUT, 'Administrator password: ');
    $password = trim((string) fgets(STDIN));
}
$policyErrors = validatePasswordPolicy($password);
if (!empty($policyErrors)) {
    fwrite(STDERR, $policyErrors[0] . PHP_EOL);
    exit(1);
}

$db = new Database();
$db->query('SELECT COUNT(*) AS total FROM users WHERE role = :role');
$db->bind(':role', 'admin');
if ((int) $db->single()->total > 0) {
    fwrite(STDERR, 'An administrator already exists. Bootstrap is disabled.' . PHP_EOL);
    exit(1);
}

$db->query("INSERT INTO users (id_number, firstname, lastname, email, department_id, role, status, password, password_changed_at, must_change_password) VALUES (:id_number, :firstname, :lastname, :email, :department_id, 'admin', 'active', :password, NOW(), 0)");
$db->bind(':id_number', trim((string) $options['id']));
$db->bind(':firstname', trim((string) $options['first']));
$db->bind(':lastname', trim((string) $options['last']));
$db->bind(':email', strtolower(trim((string) $options['email'])));
$db->bind(':department_id', (int) $options['department']);
$db->bind(':password', password_hash($password, PASSWORD_DEFAULT));
$db->execute();

echo 'Administrator created. MFA enrollment is required at first login.' . PHP_EOL;
