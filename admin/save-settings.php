<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['setting_key'], $input['setting_value'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$settingKey = trim($input['setting_key']);
$settingValue = trim($input['setting_value']);
if ($settingKey === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing setting key']);
    exit;
}

$query = 'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
$result = db_query($query, 'ss', [$settingKey, $settingValue]);
if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Setting saved successfully']);
