<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$mobile = trim($_GET['mobile'] ?? '');
if (!$mobile) {
    echo json_encode(['success' => true, 'appointments' => []]);
    exit;
}

$digits = preg_replace('/\D+/', '', $mobile);
if (strlen($digits) === 10) $digits = '63' . $digits;
if (strlen($digits) === 11 && str_starts_with($digits, '0')) $digits = '63' . substr($digits, 1);
$mobile = '+' . $digits;

$appts  = [];
$result = db_query(
    'SELECT id, patient_name, services, preferred_date, status, queue_number, created_at
     FROM appointments
     WHERE patient_mobile = ?
     ORDER BY created_at DESC
     LIMIT 30',
    's',
    [$mobile]
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appts[] = $row;
    }
}

echo json_encode(['success' => true, 'appointments' => $appts]);
