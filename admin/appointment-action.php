<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/sms.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input         = json_decode(file_get_contents('php://input'), true);
$action        = trim($input['action'] ?? '');
$appointmentId = intval($input['appointment_id'] ?? 0);

if ($appointmentId <= 0 || !in_array($action, ['approve', 'reject', 'cancel'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$appointment = db_row(
    'SELECT a.*, p.name AS p_name, p.mobile AS p_mobile
     FROM appointments a
     LEFT JOIN patients p ON a.patient_id = p.id
     WHERE a.id = ? LIMIT 1',
    'i',
    [$appointmentId]
);
if (!$appointment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

$reviewNotes = trim($input['review_notes'] ?? '');

if ($action === 'approve') {
    if ($appointment['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Appointment is not pending approval.']);
        exit;
    }

    $apptDay  = date('Y-m-d', strtotime($appointment['preferred_date']));
    $countRow = db_row(
        "SELECT COUNT(*) AS cnt FROM appointments
         WHERE DATE(preferred_date) = ? AND queue_number IS NOT NULL",
        's',
        [$apptDay]
    );
    $nextNum     = ($countRow['cnt'] ?? 0) + 1;
    $queueNumber = str_pad($nextNum, 3, '0', STR_PAD_LEFT);

    db_query(
        "UPDATE appointments SET status = 'confirmed', queue_number = ?, review_notes = ? WHERE id = ?",
        'ssi',
        [$queueNumber, $reviewNotes ?: null, $appointmentId]
    );

    $tplRow  = db_row("SELECT setting_value FROM settings WHERE setting_key = 'sms_template_approved' LIMIT 1", null, []);
    $tpl     = $tplRow['setting_value'] ?? 'Your appointment has been approved. Queue number: [Queue #].';
    $message = str_replace(
        ['[Patient Name]', '[Queue #]'],
        [$appointment['patient_name'], '#' . $queueNumber],
        $tpl
    );
    $smsResult = sendSMS($appointment['patient_mobile'], $message);
    $smsSent   = $smsResult['success'];
    db_query(
        'INSERT INTO sms_logs (appointment_id, patient_name, patient_mobile, message, status, sms_type)
         VALUES (?, ?, ?, ?, ?, ?)',
        'isssss',
        [$appointmentId, $appointment['patient_name'], $appointment['patient_mobile'], $message, $smsSent ? 'sent' : 'failed', 'approved']
    );

    echo json_encode([
        'success'      => true,
        'status'       => 'confirmed',
        'queue_number' => $queueNumber,
    ]);
    exit;
}

if ($action === 'reject') {
    db_query("UPDATE appointments SET status = 'rejected', review_notes = ? WHERE id = ?", 'si', [$reviewNotes ?: null, $appointmentId]);

    $tplRow  = db_row("SELECT setting_value FROM settings WHERE setting_key = 'sms_template_rejected' LIMIT 1", null, []);
    $tpl     = $tplRow['setting_value'] ?? 'Your appointment request has been declined based on medical record review.';
    $message = str_replace('[Patient Name]', $appointment['patient_name'], $tpl);
    $smsResult = sendSMS($appointment['patient_mobile'], $message);
    $smsSent   = $smsResult['success'];
    db_query(
        'INSERT INTO sms_logs (appointment_id, patient_name, patient_mobile, message, status, sms_type)
         VALUES (?, ?, ?, ?, ?, ?)',
        'isssss',
        [$appointmentId, $appointment['patient_name'], $appointment['patient_mobile'], $message, $smsSent ? 'sent' : 'failed', 'rejected']
    );

    echo json_encode(['success' => true, 'status' => 'rejected']);
    exit;
}

if ($action === 'cancel') {
    db_query("UPDATE appointments SET status = 'cancelled' WHERE id = ?", 'i', [$appointmentId]);
    echo json_encode(['success' => true, 'status' => 'cancelled']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unhandled action']);
