<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$mobile = preg_replace('/\D+/', '', $input['mobile'] ?? '');
if (strlen($mobile) !== 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mobile must be 10 digits.']);
    exit;
}
$mobile = '+63' . substr($mobile, -10);
$name    = trim($input['name']    ?? '');
$dob     = trim($input['dob']     ?? '');
$sex     = trim($input['sex']     ?? 'Male');
$company = trim($input['company'] ?? '');
$address = trim($input['address'] ?? '');
$services      = $input['services'] ?? [];
$custom        = trim($input['custom_service'] ?? '');
$preferredDate = trim($input['preferred_date'] ?? '');

if ($name === '' || $dob === '' || empty($services) || $preferredDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
    exit;
}

$allowedSex = ['Female', 'Male'];
if (!in_array($sex, $allowedSex, true)) $sex = 'Male';

$services = array_filter(array_map('trim', $services));
if ($custom !== '') $services[] = $custom;
$servicesText = implode(', ', $services);

$rawFlags = $input['priority_flags'] ?? [];
$allowedFlags = ['senior', 'pregnant', 'pwd'];
$priorityFlags = implode(',', array_filter(array_map(
    fn($f) => in_array(strtolower(trim($f)), $allowedFlags, true) ? strtolower(trim($f)) : '',
    (array)$rawFlags
)));

$medicalHistoryRaw  = $input['medical_history'] ?? null;
$medicalHistoryJson = $medicalHistoryRaw ? json_encode($medicalHistoryRaw) : null;

$patient = db_row('SELECT id FROM patients WHERE mobile = ? LIMIT 1', 's', [$mobile]);
if ($patient) {
    db_query(
        'UPDATE patients SET name = ?, dob = ?, sex = ?, company = ?, address = ? WHERE id = ?',
        'sssssi',
        [$name, $dob, $sex, $company, $address, $patient['id']]
    );
    $patientId = $patient['id'];
} else {
    db_query(
        'INSERT INTO patients (name, mobile, dob, sex, company, address) VALUES (?, ?, ?, ?, ?, ?)',
        'ssssss',
        [$name, $mobile, $dob, $sex, $company, $address]
    );
    $patientId = db_insert_id();
}

$preferredDateTime = date('Y-m-d 00:00:00', strtotime($preferredDate));

$result = db_query(
    'INSERT INTO appointments (patient_id, patient_name, patient_mobile, services, preferred_date, status, priority_flags, medical_history)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    'isssssss',
    [
        $patientId,
        $name,
        $mobile,
        $servicesText,
        $preferredDateTime,
        'pending',
        $priorityFlags ?: null,
        $medicalHistoryJson,
    ]
);

if ($result === false) {
    global $mysqli;
    $dbErr = $mysqli->error ?? 'Unknown database error';
    error_log('[submit-appointment] INSERT failed: ' . $dbErr);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save appointment. (' . $dbErr . ')']);
    exit;
}

$appointmentId = db_insert_id();

echo json_encode([
    'success'        => true,
    'appointment_id' => $appointmentId,
    'status'         => 'pending',
    'patient_name'   => $name,
    'services'       => $servicesText,
    'preferred_date' => date('F j, Y', strtotime($preferredDate)),
]);
