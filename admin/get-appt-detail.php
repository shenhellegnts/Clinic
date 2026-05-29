<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401); echo json_encode(['success'=>false]); exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false]); exit; }

$appt = db_row(
    "SELECT a.*, p.dob, p.address AS patient_address, p.sex AS patient_sex, p.company AS patient_company
     FROM appointments a
     LEFT JOIN patients p ON a.patient_id = p.id
     WHERE a.id = ? LIMIT 1",
    'i', [$id]
);

if (!$appt) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Not found']); exit; }

if ($appt['medical_history']) {
    $appt['medical_history_data'] = json_decode($appt['medical_history'], true);
} else {
    $appt['medical_history_data'] = null;
}

echo json_encode([
    'success' => true,
    'appointment' => [
        'id'              => (int)$appt['id'],
        'patient_name'    => $appt['patient_name'],
        'patient_mobile'  => $appt['patient_mobile'],
        'patient_dob'     => $appt['dob']               ?? '',
        'patient_sex'     => $appt['patient_sex']        ?? '',
        'patient_company' => $appt['patient_company']    ?? '',
        'patient_address' => $appt['patient_address']    ?? '',
        'services'        => $appt['services'],
        'preferred_date'  => $appt['preferred_date'],
        'status'          => $appt['status'],
        'queue_number'    => $appt['queue_number'],
        'priority_flags'  => $appt['priority_flags']     ?? '',
        'medical_history' => $appt['medical_history_data'],
        'created_at'      => $appt['created_at'],
    ],
]);
