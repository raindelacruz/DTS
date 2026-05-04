<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dts_db');

// Allow production to pin the canonical public URL instead of relying on
// request headers that may resolve to localhost behind a proxy or vhost.
$configuredAppUrl = getenv('APP_URL') ?: '';

if ($configuredAppUrl !== '') {
    define('URLROOT', rtrim($configuredAppUrl, '/'));
} else {
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || strtolower($forwardedProto) === 'https';
    $scheme = $isHttps ? 'https' : 'http';

    $host = $_SERVER['HTTP_X_FORWARDED_HOST']
        ?? $_SERVER['HTTP_HOST']
        ?? $_SERVER['SERVER_NAME']
        ?? 'localhost';
    $host = trim(explode(',', $host)[0]);

    $appSubdir = getenv('APP_SUBDIR') ?: '/DTS/public';
    $appSubdir = '/' . trim($appSubdir, '/');

    define('URLROOT', $scheme . '://' . $host . $appSubdir);
}

define('SITENAME', 'NFA Document Tracking System');
define('UPLOAD_ROOT', dirname(__DIR__, 2) . '/storage/uploads');
define('LEGACY_UPLOAD_ROOT', dirname(__DIR__, 2) . '/public/uploads');
define('MAX_ATTACHMENT_SIZE_MB', 100);
define('MAX_ATTACHMENT_SIZE_BYTES', MAX_ATTACHMENT_SIZE_MB * 1024 * 1024);

// Temporarily Disabled – QR Code Printing Feature
define('ENABLE_QR_PRINT', false);
