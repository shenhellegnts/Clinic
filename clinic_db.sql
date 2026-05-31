-- ============================================================
--  M.V. Masangkay Clinic — Complete Database Setup
--  Import this single file in phpMyAdmin to build everything.
--  No separate migration file needed.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `clinic_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `clinic_db`;

-- ── Admins ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT          AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(100) DEFAULT 'Clinic Admin',
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Service categories ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `service_categories` (
  `id`         INT         AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(80) NOT NULL,
  `slug`       VARCHAR(80) NOT NULL UNIQUE,
  `sort_order` INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Services ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `services` (
  `id`          INT            AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT            NOT NULL,
  `name`        VARCHAR(255)   NOT NULL,
  `price`       DECIMAL(10,2)  NOT NULL,
  `duration`    INT            NOT NULL DEFAULT 0,
  `is_basic`    TINYINT(1)     NOT NULL DEFAULT 0,
  `active`      TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`  DATETIME       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Patients ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `patients` (
  `id`         INT         AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `mobile`     VARCHAR(20)  NOT NULL,
  `dob`        DATE         NOT NULL,
  `sex`        ENUM('Female','Male') NOT NULL DEFAULT 'Male',
  `company`    VARCHAR(255) DEFAULT NULL,
  `address`    VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Appointments ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `appointments` (
  `id`              INT          AUTO_INCREMENT PRIMARY KEY,
  `patient_id`      INT          NULL,
  `patient_name`    VARCHAR(100) NOT NULL,
  `patient_mobile`  VARCHAR(20)  NOT NULL,
  `services`        TEXT         NOT NULL,
  `preferred_date`  DATETIME     NOT NULL,
  `status`          ENUM(
                      'pending',
                      'confirmed',
                      'in_progress',
                      'done',
                      'rejected',
                      'cancelled',
                      'skipped',
                      'disregarded'
                    ) NOT NULL DEFAULT 'pending',
  `queue_number`    VARCHAR(10)  NULL DEFAULT NULL,
  `priority_flags`  VARCHAR(100) DEFAULT NULL,
  `medical_history` MEDIUMTEXT   DEFAULT NULL,
  `review_notes`    TEXT         DEFAULT NULL,
  `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SMS Logs ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `id`             INT          AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT          NULL,
  `patient_name`   VARCHAR(100) NOT NULL,
  `patient_mobile` VARCHAR(20)  NOT NULL,
  `message`        TEXT         NOT NULL,
  `sms_type`       ENUM('approved','rejected','called','completed','manual') DEFAULT 'manual',
  `status`         ENUM('sent','failed','pending') NOT NULL DEFAULT 'sent',
  `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Settings ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT          AUTO_INCREMENT PRIMARY KEY,
  `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT         NOT NULL,
  `updated_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--  ADMIN ACCOUNT
INSERT IGNORE INTO `admins` (`username`, `password_hash`, `full_name`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Clinic Admin');

--  SERVICE CATEGORIES
INSERT IGNORE INTO `service_categories` (`name`, `slug`, `sort_order`) VALUES
('Laboratory',   'lab',        1),
('Diagnostics',  'diagnostic', 2);

--  SERVICES  
INSERT IGNORE INTO `services` (`category_id`, `name`, `price`, `duration`, `is_basic`, `active`) VALUES
(1, 'Complete Blood Count (CBC)', 90.00,  6,  1, 1),
(1, 'Urinalysis',                 90.00,  6,  1, 1),
(1, 'Fecalysis',                  90.00,  6,  1, 1),
(2, 'Physical Examination',      300.00, 15,  1, 1),
(2, 'Chest X-Ray',               350.00, 10,  1, 1),
(1, 'Pregnancy Test',            150.00,  5,  0, 1),
(1, 'Drug Test',                 280.00, 10,  0, 1),
(2, 'ECG Examination',           400.00, 15,  0, 1),
(2, 'Ishihara Test',             150.00,  5,  0, 1),
(2, 'Audio Test',                200.00, 10,  0, 1);

--  SETTINGS
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('clinic_name',             'M.V. Masangkay Clinic'),
('clinic_subtitle',         'X-Ray & Laboratory'),
('sms_template_approved',   'Your appointment at M.V. Masangkay Clinic has been approved. Your queue number is [Queue #]. Please wait for your turn.'),
('sms_template_rejected',   'Your appointment request at M.V. Masangkay Clinic has been declined based on your medical record review. Please contact us for more information.'),
('sms_template_called',     '[Patient Name] Maari na po kayong pumunta sa M.V. Masangkay Clinic dahil malapit na po ang inyong turn. Maaari ninyong i-check ang Live Queue Preview sa aming system. Maraming salamat at ingat po kayo.');
