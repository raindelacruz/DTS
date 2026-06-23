<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/helpers/auth_helper.php';

$incomingMb = 0;
foreach ($argv as $arg) {
    if (preg_match('/^--incoming-mb=(\d+)$/', $arg, $m)) {
        $incomingMb = (int) $m[1];
    }
}

try {
    ensureUploadCapacityOrFail($incomingMb * 1024 * 1024);
    echo "OK: upload storage capacity is within quota.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: {$e->getMessage()}\n");
    exit(2);
}
