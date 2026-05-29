<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinic_db';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Database connection failed: " . htmlspecialchars($mysqli->connect_error);
    exit;
}
$mysqli->set_charset('utf8mb4');

(function () use ($mysqli) {

    $hasPriority = $mysqli->query("SHOW COLUMNS FROM `appointments` LIKE 'priority_flags'")->num_rows ?? 0;
    if (!$hasPriority) {
        $mysqli->query("ALTER TABLE `appointments`
            MODIFY COLUMN `queue_number` VARCHAR(10) NULL DEFAULT NULL");

        $mysqli->query("ALTER TABLE `appointments`
            MODIFY COLUMN `status` ENUM(
                'pending','confirmed','in_progress','done',
                'rejected','cancelled','skipped','disregarded'
            ) NOT NULL DEFAULT 'pending'");

        $mysqli->query("ALTER TABLE `appointments`
            ADD COLUMN `priority_flags` VARCHAR(100) DEFAULT NULL,
            ADD COLUMN `medical_history` MEDIUMTEXT DEFAULT NULL");
    }

    $hasNotes = $mysqli->query("SHOW COLUMNS FROM `appointments` LIKE 'review_notes'")->num_rows ?? 0;
    if (!$hasNotes) {
        $mysqli->query("ALTER TABLE `appointments` ADD COLUMN `review_notes` TEXT DEFAULT NULL");
    }

    $hasDesc = $mysqli->query("SHOW COLUMNS FROM `services` LIKE 'description'")->num_rows ?? 0;
    if (!$hasDesc) {
        $mysqli->query("ALTER TABLE `services` ADD COLUMN `description` VARCHAR(500) DEFAULT NULL AFTER `name`");
    }

    $hasAddress = $mysqli->query("SHOW COLUMNS FROM `patients` LIKE 'address'")->num_rows ?? 0;
    if (!$hasAddress) {
        $mysqli->query("ALTER TABLE `patients`
            ADD COLUMN `address` VARCHAR(255) DEFAULT NULL AFTER `company`");
    }

    $hasSmsType = $mysqli->query("SHOW COLUMNS FROM `sms_logs` LIKE 'sms_type'")->num_rows ?? 0;
    if (!$hasSmsType) {
        $mysqli->query("ALTER TABLE `sms_logs`
            ADD COLUMN `sms_type`
            ENUM('approved','rejected','called','completed','manual')
            DEFAULT 'manual' AFTER `message`");
    }

    $mysqli->query("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
        ('sms_template_approved', 'Your appointment at M.V. Masangkay Clinic has been approved. Your queue number is [Queue #]. Please wait for your turn.'),
        ('sms_template_rejected', 'Your appointment request at M.V. Masangkay Clinic has been declined based on your medical record review. Please contact us for more information.'),
        ('sms_template_called',   'Queue [Queue #] — You are now being called at M.V. Masangkay Clinic. Please proceed to the clinic now.')");

})();

function db_query($query, $types = null, $params = []) {
    global $mysqli;
    $stmt = $mysqli->prepare($query);
    if ($stmt === false) return false;
    if ($types !== null && count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) return false;
    $result = $stmt->get_result();
    return $result === false ? $stmt : $result;
}

function db_row($query, $types = null, $params = []) {
    $result = db_query($query, $types, $params);
    if ($result === false || !method_exists($result, 'fetch_assoc')) return false;
    return $result->fetch_assoc();
}

function db_escape($value) {
    global $mysqli;
    return $mysqli->real_escape_string($value);
}

function db_insert_id() {
    global $mysqli;
    return $mysqli->insert_id;
}
