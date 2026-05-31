<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/sms.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$mobile = preg_replace('/\D+/', '', $input['mobile'] ?? '');

if (strlen($mobile) !== 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid 10-digit mobile number.']);
    exit;
}

$fullMobile = '+63' . $mobile;

$lockKey = 'otp_sent_at_' . $mobile;
if (!empty($_SESSION[$lockKey]) && (time() - $_SESSION[$lockKey]) < 60) {
    $wait = 60 - (time() - $_SESSION[$lockKey]);
    echo json_encode(['success' => false, 'message' => "Please wait {$wait}s before requesting a new code."]);
    exit;
}

$sent = generateAndSendOTP($fullMobile);
if ($sent) {
    $_SESSION[$lockKey] = time();
    echo json_encode(['success' => true, 'mobile' => $fullMobile]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to send SMS. Please try again.']);
}
