<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401); echo json_encode(['success'=>false]); exit;
}

$mobile = trim($_GET['mobile'] ?? '');
if (!$mobile) { echo json_encode(['success'=>true,'logs'=>[]]); exit; }

$logs = [];
$res  = db_query(
    'SELECT * FROM sms_logs WHERE patient_mobile = ? ORDER BY created_at DESC LIMIT 50',
    's', [$mobile]
);
if ($res) while ($r = $res->fetch_assoc()) $logs[] = $r;

echo json_encode(['success' => true, 'logs' => $logs]);
