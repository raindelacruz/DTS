<?php

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

define('APPROOT', __DIR__);

require_once APPROOT . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/auth_helper.php';

require_once APPROOT . '/core/App.php';
require_once APPROOT . '/core/Controller.php';
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/lib/SecurityService.php';
require_once APPROOT . '/lib/TotpService.php';
require_once APPROOT . '/lib/QrCodeService.php';
