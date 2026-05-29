<?php
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

define('SEMAPHORE_API_KEY',    getenv('SEMAPHORE_API_KEY')  ?: '');
define('SEMAPHORE_SENDER_NAME',getenv('SEMAPHORE_SENDER')   ?: 'MVClinic');
define('SEMAPHORE_API_URL',    'https://api.semaphore.co/api/v4/messages');

define('OTP_EXPIRY_SECONDS', 300);
define('OTP_LENGTH', 6);
