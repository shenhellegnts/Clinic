<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$patient = db_row('SELECT * FROM patients WHERE id = ? LIMIT 1', 'i', [$id]);
if (!$patient) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

$appointments = [];
$apptRes = db_query(
    'SELECT id, services, preferred_date, status, queue_number, medical_history, priority_flags, review_notes, created_at
     FROM appointments WHERE patient_id = ? ORDER BY created_at DESC',
    'i', [$id]
);
if ($apptRes) {
    while ($row = $apptRes->fetch_assoc()) {
        if ($row['medical_history']) {
            $row['medical_history_data'] = json_decode($row['medical_history'], true);
        }
        $appointments[] = $row;
    }
}

echo json_encode([
    'success'      => true,
    'patient'      => $patient,
    'appointments' => $appointments,
]);
