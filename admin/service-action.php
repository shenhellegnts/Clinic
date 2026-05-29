<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = trim($input['action'] ?? '');

if ($action === 'add') {
    $name       = trim($input['name']        ?? '');
    $desc       = trim($input['description'] ?? '');
    $price      = floatval($input['price']   ?? 0);
    $categoryId = intval($input['category_id'] ?? 1);
    $active     = intval($input['active']    ?? 1);

    if (!$name || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Service name and price are required.']);
        exit;
    }

    db_query(
        'INSERT INTO services (category_id, name, description, price, duration, is_basic, active) VALUES (?, ?, ?, ?, 0, 0, ?)',
        'issdi',
        [$categoryId, $name, $desc ?: null, $price, $active]
    );
    echo json_encode(['success' => true, 'id' => db_insert_id()]);
    exit;
}

if ($action === 'edit') {
    $id    = intval($input['id']          ?? 0);
    $name  = trim($input['name']          ?? '');
    $desc  = trim($input['description']   ?? '');
    $price = floatval($input['price']     ?? 0);
    $active = isset($input['active']) ? intval($input['active']) : null;

    if ($id <= 0 || !$name || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Service name and price are required.']);
        exit;
    }

    if ($active !== null) {
        db_query('UPDATE services SET name=?, description=?, price=?, active=? WHERE id=?', 'ssdii', [$name, $desc ?: null, $price, $active, $id]);
    } else {
        db_query('UPDATE services SET name=?, description=?, price=? WHERE id=?', 'ssdi', [$name, $desc ?: null, $price, $id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle') {
    $id     = intval($input['id']     ?? 0);
    $active = intval($input['active'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit; }
    db_query('UPDATE services SET active=? WHERE id=?', 'ii', [$active, $id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
