<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$mobile = trim($_GET['mobile'] ?? '');
if (!$mobile) {
    echo json_encode(['success' => true, 'patients' => []]);
    exit;
}

$digits = preg_replace('/\D+/', '', $mobile);
if (strlen($digits) === 10)                                $digits = '63' . $digits;
if (strlen($digits) === 11 && str_starts_with($digits,'0')) $digits = '63' . substr($digits, 1);
$mobile = '+' . $digits;

$patients = [];
$res = db_query(
    'SELECT id, name, dob, sex, company, address, created_at
     FROM patients WHERE mobile = ? ORDER BY created_at ASC',
    's', [$mobile]
);
if ($res) while ($r = $res->fetch_assoc()) $patients[] = $r;

echo json_encode(['success' => true, 'patients' => $patients, 'mobile' => $mobile]);
