<?php

class ValidationException extends Exception
{
    private $errors;

    public function __construct($message = 'Please correct the highlighted fields.', $errors = [], $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = is_array($errors) ? $errors : [];
    }

    public function getErrors()
    {
        return $this->errors;
    }
}

class AuthorizationException extends RuntimeException {}
class NotFoundException extends RuntimeException {}

function isTrustedProxyRequest()
{
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $remoteAddress !== '' && in_array($remoteAddress, TRUSTED_PROXIES, true);
}

function clientIpAddress()
{
    if (isTrustedProxyRequest() && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $candidate = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : '0.0.0.0';
}

function isSecureRequest()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return isTrustedProxyRequest() && strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https';
}

function validatePasswordPolicy($password)
{
    $errors = [];
    if (strlen((string) $password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (strlen((string) $password) > 128) {
        $errors[] = 'Password must not exceed 128 characters.';
    }
    return $errors;
}

function encryptSensitiveValue($plaintext)
{
    $key = hash('sha256', APP_KEY, true);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt((string) $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Sensitive value encryption failed.');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

function decryptSensitiveValue($encoded)
{
    $payload = base64_decode((string) $encoded, true);
    if ($payload === false || strlen($payload) < 29) {
        return '';
    }
    $iv = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', hash('sha256', APP_KEY, true), OPENSSL_RAW_DATA, $iv, $tag);
    return $plaintext === false ? '' : $plaintext;
}

function securityAudit($eventType, $actorUserId = null, $targetUserId = null, $metadata = [])
{
    try {
        (new SecurityService())->audit($eventType, $actorUserId, $targetUserId, $metadata);
    } catch (Throwable $e) {
        appLog('error', 'Security audit write failed', ['event_type' => $eventType, 'message' => $e->getMessage()]);
    }
}

function scanUploadedFileOrFail($path)
{
    if (!REQUIRE_MALWARE_SCAN && MALWARE_SCAN_COMMAND === '') {
        return;
    }
    if (MALWARE_SCAN_COMMAND === '') {
        throw new RuntimeException('Malware scanning is required but not configured.');
    }
    if (!function_exists('exec')) {
        throw new RuntimeException('Malware scanning cannot run because process execution is disabled.');
    }
    $command = MALWARE_SCAN_COMMAND . ' ' . escapeshellarg($path) . ' 2>&1';
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new ValidationException('The attachment failed the security scan.');
    }
}

function ensureUploadCapacityOrFail($incomingBytes)
{
    $usedBytes = 0;
    if (is_dir(UPLOAD_ROOT)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_ROOT, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $usedBytes += $file->getSize();
            }
        }
    }
    $quotaBytes = UPLOAD_STORAGE_QUOTA_MB * 1024 * 1024;
    $freeBytes = @disk_free_space(dirname(UPLOAD_ROOT));
    if ($usedBytes + (int) $incomingBytes > $quotaBytes || ($freeBytes !== false && $freeBytes < (int) $incomingBytes + 50 * 1024 * 1024)) {
        throw new ValidationException('Attachment storage capacity has been reached. Contact an administrator.');
    }
}

function clearAuthenticatedSession()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        expireCookieOnKnownPaths(session_name());
    }
    session_destroy();
    session_start();
}

function cookiePathVariants()
{
    $paths = [APP_COOKIE_PATH, '/'];
    $urlPath = trim((string) (parse_url(URLROOT, PHP_URL_PATH) ?: ''), '/');
    if ($urlPath !== '') {
        $segments = explode('/', $urlPath);
        $accumulator = '';
        foreach ($segments as $segment) {
            $accumulator .= '/' . trim($segment, '/');
            $paths[] = $accumulator;
            $paths[] = $accumulator . '/';
        }
    }

    $normalized = [];
    foreach ($paths as $path) {
        $path = '/' . trim((string) $path, '/');
        $path = $path === '/' ? '/' : $path;
        $normalized[$path] = $path;
    }

    return array_values($normalized);
}

function expireCookieOnKnownPaths($name)
{
    $params = session_get_cookie_params();
    $options = [
        'expires' => time() - 42000,
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    $domain = $params['domain'] ?? '';
    if ($domain !== '') {
        $options['domain'] = $domain;
    }

    foreach (cookiePathVariants() as $path) {
        setcookie((string) $name, '', $options + ['path' => $path]);
    }
}

function expireCookieOnLegacyPaths($name)
{
    $currentPath = '/' . trim(APP_COOKIE_PATH, '/');
    $currentPath = $currentPath === '/' ? '/' : $currentPath;
    foreach (cookiePathVariants() as $path) {
        if ($path === $currentPath) {
            continue;
        }
        setcookie((string) $name, '', [
            'expires' => time() - 42000,
            'path' => $path,
            'secure' => isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

function refreshAuthenticatedSession()
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $db = new Database();
    $now = time();
    if (empty($_SESSION['authenticated_at']) || !array_key_exists('session_version', $_SESSION)) {
        clearAuthenticatedSession();
        return false;
    }
    if (!empty($_SESSION['authenticated_at']) && $now - (int) $_SESSION['authenticated_at'] > SESSION_ABSOLUTE_TIMEOUT_SECONDS) {
        clearAuthenticatedSession();
        return false;
    }
    if (!empty($_SESSION['last_activity_at']) && $now - (int) $_SESSION['last_activity_at'] > SESSION_IDLE_TIMEOUT_SECONDS) {
        clearAuthenticatedSession();
        return false;
    }

    $db->query("SELECT id, firstname, lastname, email, department_id, role, status, session_version, mfa_enabled FROM users WHERE id = :id LIMIT 1");
    $db->bind(':id', (int) $_SESSION['user_id']);
    $user = $db->single();

    if (!$user || ($user->status ?? 'inactive') !== 'active' || (int) ($user->session_version ?? 0) !== (int) ($_SESSION['session_version'] ?? 0)) {
        clearAuthenticatedSession();
        return false;
    }

    $_SESSION['department_id'] = (int) $user->department_id;
    $_SESSION['role'] = (string) $user->role;
    $_SESSION['fullname'] = trim((string) $user->firstname . ' ' . (string) $user->lastname);
    $_SESSION['email'] = (string) ($user->email ?? '');
    $_SESSION['last_activity_at'] = $now;
    if ($_SESSION['role'] === 'admin' && empty($user->mfa_enabled)) {
        redirect('/auth/mfaSetup', 303);
    }
    if (!empty($user->mfa_enabled) && empty($_SESSION['mfa_verified'])) {
        redirect('/auth/mfa', 303);
    }
    return true;
}

function requireLogin()
{
    if (!refreshAuthenticatedSession()) {
        flash('error', 'Please sign in with an active account to continue.', 'error');
        redirect('/auth/login', 303);
    }
}

function requireRole($role)
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== $role) {
        throw new AuthorizationException('Access denied.');
    }
}

function allowRoles($roles = [])
{
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        throw new AuthorizationException('Access denied.');
    }
}

function appLog($level, $message, $context = [])
{
    $logDir = dirname(__DIR__) . '/storage/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $line = sprintf(
        "[%s] %s: %s%s",
        date('Y-m-d H:i:s'),
        strtoupper((string) $level),
        $message,
        !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
    ) . PHP_EOL;

    if (!@error_log($line, 3, $logDir . '/app.log')) {
        error_log(trim($line));
    }
}

function reportException(Throwable $exception, $context = [])
{
    $context['exception'] = [
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ];

    appLog('error', 'Unhandled application exception', $context);
}

function buildUrl($path = '')
{
    $path = trim((string) $path);

    if ($path === '' || $path === '/') {
        return URLROOT;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return URLROOT . '/' . ltrim($path, '/');
}

function isSafeRedirectTarget($target)
{
    $target = trim((string) $target);

    if ($target === '') {
        return false;
    }

    if (strpos($target, URLROOT . '/') === 0 || $target === URLROOT) {
        return true;
    }

    return strpos($target, '/') === 0 && strpos($target, '//') !== 0;
}

function redirect($path = '/', $statusCode = 302)
{
    header('Location: ' . buildUrl($path), true, (int) $statusCode);
    exit;
}

function safeRedirect($target, $fallback = '/', $statusCode = 302)
{
    if (isSafeRedirectTarget($target)) {
        header('Location: ' . $target, true, (int) $statusCode);
        exit;
    }

    redirect($fallback, $statusCode);
}

function flash($key, $message, $type = 'success')
{
    $_SESSION['_flash'][$key] = [
        'message' => $message,
        'type' => $type
    ];
}

function pullFlash($key)
{
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function pullAllFlash()
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function storeFormState($key, $values = [], $errors = [], $message = '')
{
    $_SESSION['_form_state'][$key] = [
        'values' => is_array($values) ? $values : [],
        'errors' => is_array($errors) ? $errors : [],
        'message' => (string) $message
    ];
}

function pullFormState($key, $defaults = [])
{
    $state = $_SESSION['_form_state'][$key] ?? null;
    unset($_SESSION['_form_state'][$key]);

    $values = $defaults;
    $errors = [];
    $message = '';

    if (is_array($state)) {
        $values = array_merge($defaults, $state['values'] ?? []);
        $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
        $message = (string) ($state['message'] ?? '');
    }

    return [
        'values' => $values,
        'errors' => $errors,
        'message' => $message
    ];
}

function ensureCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function base64UrlEncode($value)
{
    return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
}

function base64UrlDecode($value)
{
    $value = (string) $value;
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function csrfClientBinding()
{
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    return hash('sha256', clientIpAddress() . '|' . $userAgent);
}

function signedCsrfToken($context = 'default')
{
    $payload = json_encode([
        'context' => (string) $context,
        'issued_at' => time(),
        'nonce' => bin2hex(random_bytes(16))
    ], JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new RuntimeException('CSRF token generation failed.');
    }

    $encodedPayload = base64UrlEncode($payload);
    $signature = hash_hmac('sha256', $encodedPayload . '|' . csrfClientBinding(), APP_KEY);

    return 'v2.' . $encodedPayload . '.' . $signature;
}

function validateSignedCsrfToken($token, $context = 'default')
{
    $parts = explode('.', (string) $token);
    if (count($parts) !== 3 || $parts[0] !== 'v2') {
        return false;
    }

    [$version, $encodedPayload, $signature] = $parts;
    $expectedSignature = hash_hmac('sha256', $encodedPayload . '|' . csrfClientBinding(), APP_KEY);
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }

    $decodedPayload = base64UrlDecode($encodedPayload);
    if ($decodedPayload === false) {
        return false;
    }

    $payload = json_decode($decodedPayload, true);
    if (!is_array($payload)) {
        return false;
    }

    $issuedAt = (int) ($payload['issued_at'] ?? 0);
    if ($issuedAt <= 0 || $issuedAt > time() + 60 || time() - $issuedAt > 7200) {
        return false;
    }

    return hash_equals((string) ($payload['context'] ?? ''), (string) $context);
}

function csrfToken($context = 'default')
{
    ensureCsrfToken();
    return signedCsrfToken($context);
}

function csrfInput($context = 'default')
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken($context), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrfToken($token, $context = 'default')
{
    if (validateSignedCsrfToken($token, $context)) {
        return true;
    }

    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateCsrfOrFail($context = 'default')
{
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', $context)) {
        throw new ValidationException('Your session expired. Please try again.', [
            '_global' => 'Your session expired. Please submit the form again.'
        ]);
    }
}

function requirePost()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new ValidationException('Invalid request method.');
    }
}

function paginationRequest($perPage = 10, $maxPerPage = 10)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min($maxPerPage, (int) ($_GET['per_page'] ?? $perPage)));
    return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
}

function paginationMeta($total, $page, $perPage)
{
    $pages = max(1, (int) ceil((int) $total / max(1, (int) $perPage)));
    return ['total' => (int) $total, 'page' => min((int) $page, $pages), 'per_page' => (int) $perPage, 'pages' => $pages];
}

function renderServerPagination($meta)
{
    if (empty($meta) || ($meta['pages'] ?? 1) <= 1) {
        return;
    }
    $page = (int) $meta['page'];
    $pages = (int) $meta['pages'];
    $query = $_GET;
    echo '<nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Pagination">';
    echo '<span class="text-muted small">Page ' . $page . ' of ' . $pages . ' · ' . (int) $meta['total'] . ' records</span><span class="d-flex gap-2">';
    foreach ([['Previous', $page - 1, $page > 1], ['Next', $page + 1, $page < $pages]] as [$label, $target, $enabled]) {
        $query['page'] = $target;
        $url = htmlspecialchars('?' . http_build_query($query), ENT_QUOTES, 'UTF-8');
        echo $enabled ? '<a class="btn btn-sm btn-outline-secondary" href="' . $url . '">' . $label . '</a>' : '<span class="btn btn-sm btn-outline-secondary disabled">' . $label . '</span>';
    }
    echo '</span></nav>';
}


