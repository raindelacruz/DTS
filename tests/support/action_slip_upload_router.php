<?php
// This fixture runs only in the isolated loopback test server, never against DTS data.
if (PHP_SAPI !== 'cli-server' || !getenv('ACTION_SLIP_UPLOAD_TEST_ROOT')
    || !hash_equals((string) getenv('ACTION_SLIP_UPLOAD_TEST_TOKEN'), (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit;
}
define('APPROOT', dirname(__DIR__, 2) . '/app');
define('URLROOT', '');
define('UPLOAD_ROOT', getenv('ACTION_SLIP_UPLOAD_TEST_ROOT'));
define('MAX_ATTACHMENT_SIZE_BYTES', 1024 * 1024);
define('MAX_ATTACHMENT_SIZE_MB', 1);
define('UPLOAD_STORAGE_QUOTA_MB', 10);
define('REQUIRE_MALWARE_SCAN', false);
define('MALWARE_SCAN_COMMAND', '');
define('APP_KEY', 'isolated-upload-fixture-key');
define('TRUSTED_PROXIES', []);
require_once APPROOT . '/lib/ClientRequestContext.php';
require_once dirname(__DIR__, 2) . '/helpers/auth_helper.php';
require_once APPROOT . '/core/Controller.php';
require_once APPROOT . '/controllers/ActionSlips.php';
$reflection = new ReflectionClass(ActionSlips::class);
$controller = $reflection->newInstanceWithoutConstructor();
$_SESSION = [];
$slip = ['id' => 1, 'status' => 'Returned'];
$completedStaff = ['id' => 2];
$completedUnit = ['id' => 3];
$completedStaffName = 'Fixture Staff';
$completedUnitName = 'Fixture Unit';
$source = file_get_contents(APPROOT . '/views/action_slips/view.php');
$token = rawurlencode(getenv('ACTION_SLIP_UPLOAD_TEST_TOKEN'));
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['download'])) {
    preg_match_all('~<form action="[^\r\n]+/actionSlips/(?:completeStaff|completeUnit|completeDivision|completeDepartment|confirmUnit|confirmDivision)/[^\r\n]+>.*?</form>~s', $source, $forms);
    foreach ($forms[0] as $form) {
        eval('?>' . $form);
    }
    exit;
}
if (isset($_GET['download'])) {
    $reflection->getMethod('streamStoredFile')->invoke($controller, file_get_contents(UPLOAD_ROOT . '/last-path.txt'));
    exit;
}
try {
    $attachment = $reflection->getMethod('handleUpload')->invoke($controller, 'completion_attachment', 'action_slips/completions');
    $db = new class {
        public $writes = [];
        public function inTransaction() { return false; }
        public function beginTransaction() { return true; }
        public function commit() { return true; }
        public function prepare($sql) {
            return new class($this, $sql) {
                private $db;
                private $sql;
                public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
                public function execute($params) {
                    if (preg_match('/^\s*(UPDATE|INSERT)/', $this->sql)) {
                        $this->db->writes[] = $params;
                    }
                }
                public function rowCount() { return 1; }
                public function fetch($mode) {
                    return str_contains($this->sql, 'AS active_count')
                        ? ['total_count' => 2, 'active_count' => 1, 'returned_count' => 0]
                        : ['id' => 1, 'status' => 'Returned'];
                }
            };
        }
    };
    $modelReflection = new ReflectionClass(DepartmentActionSlip::class);
    $model = $modelReflection->newInstanceWithoutConstructor();
    $modelReflection->getProperty('db')->setValue($model, $db);
    $model->completeByUnitManager(1, 2, 3, 'Fixture resubmission', $attachment);
    $event = end($db->writes);
    $event['id'] = 1;
    $start = strpos($source, "<?php if (!empty(\$event['attachment'])):");
    $end = strpos($source, '<?php endif; ?>', $start) + strlen('<?php endif; ?>');
    ob_start();
    eval('?>' . substr($source, $start, $end - $start));
    $history = ob_get_clean();
    if ($attachment !== null) {
        file_put_contents(UPLOAD_ROOT . '/last-path.txt', $attachment);
        $history = str_replace('/actionSlips/eventAttachment/1', '?download=1&token=' . $token, $history);
    }
    header('Content-Type: application/json');
    echo json_encode(['attachment' => $attachment, 'assignment_attachment' => $db->writes[0]['completion_attachment'],
        'event_attachment' => $event['attachment'], 'history' => $history, 'status' => $db->writes[1]['status']]);
} catch (Throwable $error) {
    http_response_code(422);
    echo json_encode(['error' => $error->getMessage()]);
}
