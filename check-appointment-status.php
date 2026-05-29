<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

$appt = db_row(
    'SELECT status, queue_number, services, preferred_date, patient_name FROM appointments WHERE id = ? LIMIT 1',
    'i',
    [$id]
);

if (!$appt) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

$response = [
    'success'      => true,
    'status'       => $appt['status'],
    'queue_number' => $appt['queue_number'],
    'services'     => $appt['services'],
    'preferred_date' => $appt['preferred_date']
        ? date('F j, Y', strtotime($appt['preferred_date']))
        : null,
];

if (in_array($appt['status'], ['confirmed', 'in_progress'], true)) {
    $ahead = db_row(
        'SELECT COUNT(*) AS cnt FROM appointments
         WHERE status = ? AND queue_number < ? AND queue_number IS NOT NULL',
        'ss',
        ['confirmed', $appt['queue_number']]
    );
    $response['people_ahead']   = $ahead['cnt'] ?? 0;
    $response['estimated_wait'] = max(0, ($ahead['cnt'] ?? 0) * 5);

    $nowServing = db_row(
        'SELECT queue_number, patient_name FROM appointments WHERE status = ? LIMIT 1',
        's',
        ['in_progress']
    );
    $response['now_serving'] = $nowServing ?: null;
}

echo json_encode($response);
