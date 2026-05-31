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

$sent = generateAndSendOTP($fullMobile);
if ($sent) {
    echo json_encode(['success' => true, 'mobile' => $fullMobile]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to send SMS. Please try again.']);
}
