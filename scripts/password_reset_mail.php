<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Existing approved production environment must be supplied by the release owner.
$max = 20;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help') { echo "php scripts/password_reset_mail.php [--max=1..100] [--prune]\n"; exit; }
    if ($arg === '--prune') { continue; }
    if (!preg_match('/^--max=([0-9]+)$/D', $arg, $m) || (int) $m[1] < 1 || (int) $m[1] > 100) { fwrite(STDERR, "Invalid option.\n"); exit(2); }
    $max = (int) $m[1];
}
try {
    require dirname(__DIR__) . '/app/init.php';
    require APPROOT . '/controllers/Auth.php';
    if (!PASSWORD_RESET_ENABLED || !PasswordResetProtection::enabled()) { throw new RuntimeException('Protected reset mail delivery is disabled.'); }
    if (in_array('--prune', $argv, true)) { PasswordResetProtection::configured()->prune(); }
    $auth = new Auth();
    $counts = [];
    for ($i = 0; $i < $max; $i++) {
        $status = $auth->deliverQueuedResetMail();
        if ($status === 'empty') { break; }
        $counts[$status] = ($counts[$status] ?? 0) + 1;
    }
    echo json_encode(['processed' => $counts], JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Reset mail worker unavailable; check the approved environment and local logs.\n");
    exit(1);
}
