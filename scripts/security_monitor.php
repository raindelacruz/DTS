<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__) . '/app/init.php';

$hours = 24;
$json = false;
$prune = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') { $json = true; }
    elseif ($arg === '--prune') { $prune = true; }
    elseif (preg_match('/^--hours=(\d+)$/D', $arg, $match) && (int) $match[1] >= 1 && (int) $match[1] <= 720) {
        $hours = (int) $match[1];
    } elseif ($arg === '--help') {
        echo "Usage: php scripts/security_monitor.php [--hours=1..720] [--json] [--prune]\n";
        echo "Read-only by default. --prune removes at most 10,000 expired monitoring counters, never audit records.\n";
        exit(0);
    } else {
        fwrite(STDERR, "Unknown option or invalid reporting window. Use --help.\n");
        exit(2);
    }
}

try {
    // Invalid policy configuration is reported as an error rather than ignored.
    $policies = SecurityRequestMonitor::configuredPolicies();
    if (!in_array(SECURITY_MONITOR_MODE, ['off', 'observe'], true)) {
        throw new RuntimeException('Invalid observation mode.');
    }
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pruned = 0;
    if ($prune) {
        $pruned = (new SecurityRequestMonitor($pdo))->prune(10000);
    }
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $clock = $pdo->query('SELECT NOW() database_time, UTC_TIMESTAMP() database_utc_time, @@system_time_zone system_timezone')->fetch(PDO::FETCH_ASSOC);
    $table = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_request_counters'")->fetchColumn();
    $run = static function (string $sql) use ($pdo, $hours): array {
        $statement = $pdo->prepare($sql);
        $statement->execute(['hours' => $hours]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    };
    $events = $run('SELECT event_type, COUNT(*) event_count FROM security_audit_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        GROUP BY event_type ORDER BY event_type');
    $observations = $run("SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.action')) action, COUNT(*) attempts
        FROM security_audit_log WHERE event_type='security_request_observed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        GROUP BY action ORDER BY action");
    $crossings = $run("SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.action')) action,
        JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.scope')) scope, COUNT(*) threshold_crossings
        FROM security_audit_log WHERE event_type='security_threshold_observed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        GROUP BY action, scope ORDER BY action, scope");
    $rejections = $run("SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.action')) action,
        JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.reason')) reason, COUNT(*) rejected_requests
        FROM security_audit_log WHERE event_type='security_request_rejected'
        AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        GROUP BY action, reason ORDER BY action, reason");
    $delivery = $run("SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.transport')) transport,
        JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.mail_accepted')) mail_accepted, COUNT(*) requests
        FROM security_audit_log WHERE event_type='password_reset_requested'
        AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        GROUP BY transport, mail_accepted ORDER BY transport, mail_accepted");
    $attribution = $run("SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.source')),'legacy_unknown') source,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.forwarded_status')),'legacy_unknown') forwarded_status,
        COUNT(*) attempts FROM security_audit_log WHERE event_type='security_request_observed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        GROUP BY source, forwarded_status ORDER BY source, forwarded_status");
    $daily = $run('SELECT DATE(created_at) day, event_type, COUNT(*) event_count FROM security_audit_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        GROUP BY day, event_type ORDER BY day, event_type');
    $counterRows = $table ? (int) $pdo->query('SELECT COUNT(*) FROM security_request_counters')->fetchColumn() : null;
    $expiredRows = $table ? (int) $pdo->query('SELECT COUNT(*) FROM security_request_counters WHERE expires_at < UNIX_TIMESTAMP()')->fetchColumn() : null;
    $pdo->rollBack();
    $result = [
        'clock' => $clock, 'hours' => $hours, 'mode' => SECURITY_MONITOR_MODE,
        'counter_table_ready' => (bool) $table, 'counter_rows' => $counterRows,
        'expired_counter_rows' => $expiredRows, 'pruned_counter_rows' => $pruned,
        'candidate_policies' => $policies, 'policy_approval' => 'Pending; no new blocking is enabled',
        'observed_attempts' => $observations, 'threshold_crossings' => $crossings,
        'rejected_requests' => $rejections,
        'reset_transport_acceptance' => $delivery, 'request_attribution' => $attribution,
        'events' => $events, 'daily_events' => $daily,
        'interpretation' => [
            'Historical activity is not verified legitimate usage and includes the incident/testing windows.',
            'login_succeeded is a legacy password-stage event; do not add it to password_accepted.',
            'authentication_completed records completed authentication; older MFA successes/device events remain separate.',
            'SMTP acceptance is not inbox delivery. Threshold crossings are observations, not rejected requests.',
            'Review application logs for monitoring-unavailable and threshold warnings; no external alerts are sent.',
        ],
    ];
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        echo 'Security monitoring: ' . SECURITY_MONITOR_MODE . '; counter table ' . ($table ? 'ready' : 'MISSING') . PHP_EOL;
        echo 'Window: ' . $hours . ' hours; database time: ' . $clock['database_time'] . PHP_EOL;
        foreach ($events as $event) { echo $event['event_type'] . ': ' . $event['event_count'] . PHP_EOL; }
        echo 'Candidate thresholds do not block requests; owner approval and a clean baseline remain pending.' . PHP_EOL;
        echo 'Use --json for action/source, delivery, threshold, and daily breakdowns.' . PHP_EOL;
    }
    exit($table ? 0 : 1);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, 'Security report unavailable; check database access, migration, and policy configuration. Error type: ' . get_class($e) . PHP_EOL);
    exit(1);
}
