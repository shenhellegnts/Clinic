<?php
date_default_timezone_set('Asia/Manila');

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

if (!defined('SKYSMS_API_KEY')) define('SKYSMS_API_KEY', getenv('SKYSMS_API_KEY') ?: '');

define('OTP_EXPIRY_SECONDS', 300);
define('OTP_LENGTH', 6);
