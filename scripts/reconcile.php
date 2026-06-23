<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/app/config/config.php';

$format = in_array('--json', $argv, true) ? 'json' : 'text';
$overdueDays = 0;
$stuckDays = 7;
foreach ($argv as $arg) {
    if (preg_match('/^--stuck-days=(\d+)$/', $arg, $m)) {
        $stuckDays = max(1, (int) $m[1]);
    }
    if (preg_match('/^--overdue-days=(\d+)$/', $arg, $m)) {
        $overdueDays = max(0, (int) $m[1]);
    }
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :table
    ");
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :table
        AND COLUMN_NAME = :column
    ");
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function run_report(PDO $pdo, string $name, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return [
        'name' => $name,
        'count' => $stmt->rowCount(),
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

$reports = [];

if (table_exists($pdo, 'documents')) {
    $documentNumberExpr = column_exists($pdo, 'documents', 'tracking_number')
        ? 'd.tracking_number'
        : "CONCAT(COALESCE(d.prefix, ''), '-', LPAD(COALESCE(d.sequence_number, 0), 3, '0'))";
    $documentUpdatedExpr = column_exists($pdo, 'documents', 'updated_at')
        ? 'd.updated_at'
        : 'COALESCE(d.received_at, d.released_at, d.created_at)';
    $duplicateNumberExpr = column_exists($pdo, 'documents', 'tracking_number')
        ? 'tracking_number'
        : "CONCAT(COALESCE(prefix, ''), '-', LPAD(COALESCE(sequence_number, 0), 3, '0'))";

    $reports[] = run_report($pdo, 'stuck_documents', "
        SELECT d.id, {$documentNumberExpr} AS document_number, d.title, d.status, {$documentUpdatedExpr} AS last_activity_at
        FROM documents d
        WHERE d.status IN ('Released','Received','Returned','Re-released')
        AND {$documentUpdatedExpr} < DATE_SUB(NOW(), INTERVAL :days DAY)
        ORDER BY {$documentUpdatedExpr} ASC
        LIMIT 100
    ", ['days' => $stuckDays]);

    $reports[] = run_report($pdo, 'duplicate_document_tracking_numbers', "
        SELECT {$duplicateNumberExpr} AS document_number, COUNT(*) AS duplicates
        FROM documents
        GROUP BY {$duplicateNumberExpr}
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC, document_number ASC
        LIMIT 100
    ");
}

if (table_exists($pdo, 'department_action_slips')) {
    $reports[] = run_report($pdo, 'overdue_action_slips', "
        SELECT id, slip_number, subject, status, deadline, current_department_id, assigned_staff_id
        FROM department_action_slips
        WHERE status NOT IN ('Completed','Cancelled')
        AND deadline IS NOT NULL
        AND deadline < DATE_SUB(CURDATE(), INTERVAL :days DAY)
        ORDER BY deadline ASC
        LIMIT 100
    ", ['days' => $overdueDays]);

    $reports[] = run_report($pdo, 'duplicate_action_slip_numbers', "
        SELECT slip_number, COUNT(*) AS duplicates
        FROM department_action_slips
        GROUP BY slip_number
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC, slip_number ASC
        LIMIT 100
    ");
}

if (table_exists($pdo, 'document_returns')) {
    $reports[] = run_report($pdo, 'open_document_returns', "
        SELECT r.id, r.document_id, r.return_reason, r.status, r.returned_at
        FROM document_returns r
        WHERE r.status = 'Open'
        ORDER BY r.returned_at ASC
        LIMIT 100
    ");
}

if (table_exists($pdo, 'document_assignments')) {
    $reports[] = run_report($pdo, 'orphaned_document_assignments', "
        SELECT a.id, a.document_id, a.assigned_to_user_id, a.status
        FROM document_assignments a
        LEFT JOIN documents d ON d.id = a.document_id
        LEFT JOIN users u ON u.id = a.assigned_to_user_id
        WHERE d.id IS NULL OR u.id IS NULL OR u.status <> 'active'
        ORDER BY a.id ASC
        LIMIT 100
    ");
}

if (table_exists($pdo, 'department_action_slip_events')) {
    $reports[] = run_report($pdo, 'orphaned_action_slip_events', "
        SELECT e.id, e.slip_id, e.action, e.actor_user_id
        FROM department_action_slip_events e
        LEFT JOIN department_action_slips s ON s.id = e.slip_id
        LEFT JOIN users u ON u.id = e.actor_user_id
        WHERE s.id IS NULL OR u.id IS NULL
        ORDER BY e.id ASC
        LIMIT 100
    ");
}

if ($format === 'json') {
    echo json_encode([
        'generated_at' => gmdate('c'),
        'database' => DB_NAME,
        'reports' => $reports,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

echo 'DTS reconciliation report generated at ' . date('c') . PHP_EOL . PHP_EOL;
foreach ($reports as $report) {
    echo strtoupper($report['name']) . ' (' . count($report['rows']) . ')' . PHP_EOL;
    if (empty($report['rows'])) {
        echo "  No records found.\n\n";
        continue;
    }
    foreach ($report['rows'] as $row) {
        echo '  - ' . json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    echo PHP_EOL;
}
