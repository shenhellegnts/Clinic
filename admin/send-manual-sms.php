<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/semaphore.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401); echo json_encode(['success'=>false]); exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$mobile  = trim($input['mobile']       ?? '');
$message = trim($input['message']      ?? '');
$name    = trim($input['patient_name'] ?? '');

if (!$mobile || !$message) {
    echo json_encode(['success'=>false,'message'=>'Mobile and message are required.']); exit;
}

$smsResult = sendSMS($mobile, $message);
$ok        = $smsResult['success'];

db_query(
    'INSERT INTO sms_logs (patient_name, patient_mobile, message, status, sms_type)
     VALUES (?, ?, ?, ?, ?)',
    'sssss',
    [$name ?: $mobile, $mobile, $message, $ok ? 'sent' : 'failed', 'manual']
);

echo json_encode(['success' => $ok, 'message' => $ok ? 'SMS sent.' : 'SMS failed.']);
