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

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        flash('error', 'Please sign in to continue.', 'error');
        redirect('/auth/login');
    }
}

function requireRole($role)
{
    if ($_SESSION['role'] !== $role) {
        throw new AuthorizationException('Access denied.');
    }
}

function allowRoles($roles = [])
{
    if (!in_array($_SESSION['role'], $roles)) {
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

    @error_log($line, 3, $logDir . '/app.log');
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

function csrfToken()
{
    return ensureCsrfToken();
}

function csrfInput()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateCsrfOrFail()
{
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
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


