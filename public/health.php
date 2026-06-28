<?php

declare(strict_types=1);

$startedAt = microtime(true);
$root = dirname(__DIR__);

require_once $root . '/app/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$checks = [];

$record = function (string $name, string $status, array $details = []) use (&$checks): void {
    $checks[$name] = array_merge(['status' => $status], $details);
};

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]
    );
    $pdo->query('SELECT 1');
    $record('database', 'ok');

    $migrationCount = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    $record('migrations', $migrationCount > 0 ? 'ok' : 'warn', ['applied' => $migrationCount]);
} catch (Throwable $e) {
    $record('database', 'fail', ['message' => 'Database connection or migration check failed.']);
}

$logDir = $root . '/storage/logs';
$uploadDir = UPLOAD_ROOT;
$record('logs_writable', is_dir($logDir) && is_writable($logDir) ? 'ok' : 'fail');
$record('uploads_writable', is_dir($uploadDir) && is_writable($uploadDir) ? 'ok' : 'fail');

$freeBytes = @disk_free_space($root);
$minFreeBytes = max(1, (int) (getenv('HEALTH_MIN_FREE_MB') ?: 1024)) * 1024 * 1024;
$record('disk_free', ($freeBytes !== false && $freeBytes >= $minFreeBytes) ? 'ok' : 'warn', [
    'free_mb' => $freeBytes === false ? null : round($freeBytes / 1024 / 1024, 1),
    'minimum_mb' => (int) ($minFreeBytes / 1024 / 1024),
]);

$scanStatus = REQUIRE_MALWARE_SCAN && MALWARE_SCAN_COMMAND !== '' ? 'ok' : (APP_ENV === 'production' ? 'fail' : 'warn');
$record('malware_scanning', $scanStatus);

$failed = array_filter($checks, fn ($check) => ($check['status'] ?? '') === 'fail');
$warnings = array_filter($checks, fn ($check) => ($check['status'] ?? '') === 'warn');
$overall = !empty($failed) ? 'fail' : (!empty($warnings) ? 'warn' : 'ok');

http_response_code($overall === 'fail' ? 503 : 200);
echo json_encode([
    'status' => $overall,
    'environment' => APP_ENV,
    'checked_at' => gmdate('c'),
    'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
