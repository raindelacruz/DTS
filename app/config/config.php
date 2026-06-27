<?php

$appEnv = strtolower(trim((string) (getenv('APP_ENV') ?: 'development')));
define('APP_ENV', $appEnv);

$databaseConfig = [
    'host' => getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost',
    'user' => getenv('DB_USER') !== false ? getenv('DB_USER') : 'root',
    'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'name' => getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'dts_db'
];

if (APP_ENV === 'production') {
    $requiredEnvironmentVariables = ['APP_URL', 'APP_KEY', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
    foreach ($requiredEnvironmentVariables as $variable) {
        if (getenv($variable) === false || trim((string) getenv($variable)) === '') {
            throw new RuntimeException($variable . ' must be configured in production.');
        }
    }

    if (strtolower($databaseConfig['user']) === 'root') {
        throw new RuntimeException('The production database must use a least-privileged application account.');
    }
}

define('DB_HOST', $databaseConfig['host']);
define('DB_USER', $databaseConfig['user']);
define('DB_PASS', $databaseConfig['pass']);
define('DB_NAME', $databaseConfig['name']);

// Allow production to pin the canonical public URL instead of relying on
// request headers that may resolve to localhost behind a proxy or vhost.
$configuredAppUrl = getenv('APP_URL') ?: '';

if ($configuredAppUrl !== '') {
    define('URLROOT', rtrim($configuredAppUrl, '/'));
} else {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $scheme = $isHttps ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST']
        ?? $_SERVER['SERVER_NAME']
        ?? 'localhost';
    $host = trim(explode(',', $host)[0]);
    if (!preg_match('/^[A-Za-z0-9.-]+(?::\d+)?$/', $host)) {
        $host = 'localhost';
    }

    $appSubdir = getenv('APP_SUBDIR') ?: '/DTS/public';
    $appSubdir = '/' . trim($appSubdir, '/');

    define('URLROOT', $scheme . '://' . $host . $appSubdir);
}

define('SITENAME', 'NFA Document Tracking System');
define('UPLOAD_ROOT', dirname(__DIR__, 2) . '/storage/uploads');
define('LEGACY_UPLOAD_ROOT', dirname(__DIR__, 2) . '/public/uploads');
define('MAX_ATTACHMENT_SIZE_MB', max(1, (int) (getenv('MAX_ATTACHMENT_SIZE_MB') ?: 25)));
define('MAX_ATTACHMENT_SIZE_BYTES', MAX_ATTACHMENT_SIZE_MB * 1024 * 1024);

define('PASSWORD_MIN_LENGTH', max(12, (int) (getenv('PASSWORD_MIN_LENGTH') ?: 12)));
define('LOGIN_MAX_ATTEMPTS', max(3, (int) (getenv('LOGIN_MAX_ATTEMPTS') ?: 5)));
define('LOGIN_LOCKOUT_SECONDS', max(60, (int) (getenv('LOGIN_LOCKOUT_SECONDS') ?: 900)));
define('SESSION_IDLE_TIMEOUT_SECONDS', max(300, (int) (getenv('SESSION_IDLE_TIMEOUT_SECONDS') ?: 1800)));
define('SESSION_ABSOLUTE_TIMEOUT_SECONDS', max(1800, (int) (getenv('SESSION_ABSOLUTE_TIMEOUT_SECONDS') ?: 43200)));
define('MFA_REMEMBER_DEVICE_SECONDS', max(86400, (int) (getenv('MFA_REMEMBER_DEVICE_SECONDS') ?: 2592000)));
define('MFA_TRUSTED_DEVICE_COOKIE', 'dts_mfa_trusted_device');
define('TRUSTED_PROXIES', array_values(array_filter(array_map('trim', explode(',', (string) (getenv('TRUSTED_PROXIES') ?: ''))))));
define('REQUIRE_MALWARE_SCAN', filter_var(getenv('REQUIRE_MALWARE_SCAN') ?: '0', FILTER_VALIDATE_BOOLEAN));
define('MALWARE_SCAN_COMMAND', trim((string) (getenv('MALWARE_SCAN_COMMAND') ?: '')));
define('UPLOAD_STORAGE_QUOTA_MB', max(100, (int) (getenv('UPLOAD_STORAGE_QUOTA_MB') ?: 10240)));
define('PASSWORD_RESET_FROM', trim((string) (getenv('PASSWORD_RESET_FROM') ?: 'no-reply@localhost')));
define('SMTP_HOST', trim((string) (getenv('SMTP_HOST') ?: '')));
define('SMTP_PORT', max(1, (int) (getenv('SMTP_PORT') ?: 587)));
define('SMTP_USER', trim((string) (getenv('SMTP_USER') ?: '')));
define('SMTP_PASS', (string) (getenv('SMTP_PASS') ?: ''));
define('SMTP_ENCRYPTION', strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'))));
define('APP_KEY', (string) (getenv('APP_KEY') ?: 'development-only-key-change-me'));

if (APP_ENV === 'production' && (!REQUIRE_MALWARE_SCAN || MALWARE_SCAN_COMMAND === '')) {
    throw new RuntimeException('Production requires malware scanning. Set REQUIRE_MALWARE_SCAN=1 and MALWARE_SCAN_COMMAND.');
}

// Temporarily Disabled – QR Code Printing Feature
define('ENABLE_QR_PRINT', false);
