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

$input  = json_decode(file_get_contents('php://input'), true);
$action = trim($input['action'] ?? '');
$validActions = ['call', 'done', 'skip', 'reinsert', 'priority_insert'];

if (!in_array($action, $validActions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid queue action']);
    exit;
}

function getCurrentServing() {
    $today = date('Y-m-d');
    return db_row("SELECT * FROM appointments WHERE status = 'in_progress' AND DATE(preferred_date) = ? LIMIT 1", 's', [$today]);
}

function getNextConfirmed() {
    $today = date('Y-m-d');
    return db_row(
        "SELECT * FROM appointments WHERE status = 'confirmed' AND DATE(preferred_date) = ?
         ORDER BY
           CASE WHEN priority_flags IS NOT NULL AND priority_flags != '' THEN 0 ELSE 1 END,
           created_at ASC
         LIMIT 1",
        's', [$today]
    );
}

function logSMS(int $apptId, string $name, string $mobile, string $templateKey, string $queueNum, string $type): void {
    $tplRow  = db_row("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1", 's', [$templateKey]);
    $tpl     = $tplRow['setting_value'] ?? '';
    $message = str_replace(['[Patient Name]', '[Queue #]'], [$name, '#' . $queueNum], $tpl);

    $smsResult = sendSMS($mobile, $message);
    $sent      = $smsResult['success'];

    db_query(
        'INSERT INTO sms_logs (appointment_id, patient_name, patient_mobile, message, status, sms_type)
         VALUES (?, ?, ?, ?, ?, ?)',
        'isssss',
        [$apptId, $name, $mobile, $message, $sent ? 'sent' : 'failed', $type]
    );
}

if ($action === 'call') {
    $current = getCurrentServing();
    if ($current) {
        db_query("UPDATE appointments SET status = 'done' WHERE id = ?", 'i', [$current['id']]);
    }

    $next = getNextConfirmed();
    if (!$next) {
        echo json_encode(['success' => true, 'next' => null, 'message' => 'Queue is empty.']);
        exit;
    }

    db_query("UPDATE appointments SET status = 'in_progress' WHERE id = ?", 'i', [$next['id']]);

    logSMS(
        (int)$next['id'],
        $next['patient_name'],
        $next['patient_mobile'],
        'sms_template_called',
        $next['queue_number'],
        'called'
    );

    echo json_encode(['success' => true, 'next' => $next]);
    exit;
}

if ($action === 'done') {
    $current = getCurrentServing();
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'No patient currently in service.']);
        exit;
    }
    db_query("UPDATE appointments SET status = 'done' WHERE id = ?", 'i', [$current['id']]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'skip') {
    $current = getCurrentServing();
    if (!$current) {
        $targetId = intval($input['appointment_id'] ?? 0);
        if ($targetId > 0) {
            db_query(
                "UPDATE appointments SET status = 'skipped' WHERE id = ? AND status = 'confirmed'",
                'i', [$targetId]
            );
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'No patient currently in service.']);
        exit;
    }
    db_query("UPDATE appointments SET status = 'skipped' WHERE id = ?", 'i', [$current['id']]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'reinsert') {
    $targetId = intval($input['appointment_id'] ?? 0);
    if ($targetId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'appointment_id required']);
        exit;
    }
    $result = db_query(
        "UPDATE appointments SET status = 'confirmed' WHERE id = ? AND status = 'skipped'",
        'i', [$targetId]
    );
    $affected = $GLOBALS['mysqli']->affected_rows ?? 0;
    if ($affected === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment is not in skipped state.']);
        exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'priority_insert') {
    $targetId = intval($input['appointment_id'] ?? 0);
    if ($targetId <= 0) {
        echo json_encode(['success' => false, 'message' => 'appointment_id required']);
        exit;
    }
    $today = date('Y-m-d');
    db_query(
        "UPDATE appointments SET created_at = CONCAT(?, ' 00:00:01')
         WHERE id = ? AND status = 'confirmed'",
        'si', [$today, $targetId]
    );
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unhandled action']);
