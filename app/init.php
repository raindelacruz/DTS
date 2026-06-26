<?php

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

require_once 'config/config.php';
require_once dirname(__DIR__) . '/helpers/auth_helper.php';

require_once 'core/App.php';
require_once 'core/Controller.php';
require_once 'core/Database.php';
require_once 'lib/SecurityService.php';
require_once 'lib/TotpService.php';
require_once 'lib/QrCodeService.php';
