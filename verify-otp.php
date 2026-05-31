<?php
session_start();
require_once __DIR__ . '/includes/sms.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$mobile = trim($input['mobile'] ?? '');
$code   = trim($input['code']   ?? '');

if (!$mobile || strlen($code) !== 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (verifyOTP($mobile, $code)) {
    $_SESSION['verified_mobile'] = $mobile;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect or expired code. Please try again.']);
}
