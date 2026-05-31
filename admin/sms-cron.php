<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/sms.php';

$today   = date('Y-m-d');
$message = '';

$appointments = [];
$res = db_query(
    "SELECT * FROM appointments
     WHERE DATE(preferred_date) = ?
       AND status IN ('confirmed', 'in_progress', 'waiting')
       AND queue_number IS NOT NULL
     ORDER BY CAST(queue_number AS UNSIGNED) ASC
     LIMIT 5",
    's',
    [$today]
);
if ($res) while ($r = $res->fetch_assoc()) $appointments[] = $r;

if (empty($appointments)) {
    echo "[sms-cron] No appointments to notify for {$today}.\n";
    exit;
}

$sent  = 0;
$total = count($appointments);

foreach ($appointments as $appt) {
    $msg = $appt['patient_name']
         . ' Maari na po kayong pumunta sa M.V. Masangkay Clinic dahil malapit na po ang inyong turn.'
         . ' Maaari ninyong i-check ang Live Queue Preview sa aming system.'
         . ' Maraming salamat at ingat po kayo.';

    $smsResult = sendSMS($appt['patient_mobile'], $msg);
    $ok        = $smsResult['success'];

    db_query(
        'INSERT INTO sms_logs
         (appointment_id, patient_name, patient_mobile, message, status, sms_type)
         VALUES (?, ?, ?, ?, ?, ?)',
        'isssss',
        [
            (int)$appt['id'],
            $appt['patient_name'],
            $appt['patient_mobile'],
            $msg,
            $ok ? 'sent' : 'failed',
            'called',
        ]
    );

    if ($ok) $sent++;
    echo "[sms-cron] " . ($ok ? 'SENT' : 'FAILED') . " → {$appt['patient_name']} (#" . $appt['queue_number'] . ")\n";
}

echo "[sms-cron] Done: {$sent}/{$total} SMS sent for {$today}\n";
