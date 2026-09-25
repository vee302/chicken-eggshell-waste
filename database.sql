-- database.sql
-- Green Forensics Evaluating System Database Schema
-- Single source of truth for the Green Forensics database.
-- Includes registration approval fields and student portal support tables.

CREATE DATABASE IF NOT EXISTS `green_forensics` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `green_forensics`;

-- ============================================================
-- USERS TABLE
-- Roles: super_admin | faculty_researcher | criminology_student | alumni_police_partner
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(80) DEFAULT NULL,
    `middle_name` VARCHAR(80) DEFAULT NULL,
    `last_name` VARCHAR(80) DEFAULT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `contact_number` VARCHAR(20) DEFAULT NULL,
    `id_number` VARCHAR(50) DEFAULT NULL,
    `department` VARCHAR(150) DEFAULT NULL,
    `affiliation` VARCHAR(150) DEFAULT NULL,
    `requested_role` VARCHAR(50) DEFAULT NULL,
    `reason_for_access` TEXT DEFAULT NULL,
    `proof_of_affiliation` VARCHAR(255) DEFAULT NULL,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin','faculty_researcher','criminology_student','alumni_police_partner') DEFAULT NULL,
    `status` ENUM('active','inactive','pending','rejected','suspended') DEFAULT 'pending',
    `failed_login_attempts` INT DEFAULT 0,
    `locked_until` DATETIME NULL,
    `last_failed_login` DATETIME NULL,
    `terms_agreed` TINYINT(1) DEFAULT 0,
    `terms_agreed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FINGERPRINT TESTS TABLE
-- Student trial submissions
-- ============================================================
CREATE TABLE IF NOT EXISTS `fingerprint_tests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `trial_id` VARCHAR(50) DEFAULT NULL,
    `student_id` INT NOT NULL,
    `powder_type` ENUM('eggshell','commercial') NOT NULL DEFAULT 'eggshell',
    `surface_type` ENUM('glass','paper','wood','plastic','metal','ceramic','fabric') NOT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `enhanced_image_path` VARCHAR(255) DEFAULT NULL,
    `image_label` VARCHAR(255) DEFAULT NULL,
    `image_hash` VARCHAR(64) DEFAULT NULL,
    `gdrive_file_id` VARCHAR(255) DEFAULT NULL,
    `ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL,
    `visibility_score` DECIMAL(5,2) DEFAULT NULL,
    `adhesion_score` DECIMAL(5,2) DEFAULT NULL,
    `contrast_score` DECIMAL(5,2) DEFAULT NULL,
    `accuracy_score` DECIMAL(5,2) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('pending_validation','approved','rejected','needs_revision') DEFAULT 'pending_validation',
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `validated_by` INT DEFAULT NULL,
    `validated_at` TIMESTAMP NULL DEFAULT NULL,
    `ai_evaluated_at` DATETIME DEFAULT NULL,
    `evaluation_source` VARCHAR(50) DEFAULT 'AI Preliminary',
    `faculty_final_score` DECIMAL(5,2) DEFAULT NULL,
    `ai_accuracy_score` DECIMAL(5,2) DEFAULT NULL,
    `faculty_accuracy_score` DECIMAL(5,2) DEFAULT NULL,
    `faculty_ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL,
    `faculty_visibility_score` DECIMAL(5,2) DEFAULT NULL,
    `faculty_adhesion_score` DECIMAL(5,2) DEFAULT NULL,
    `faculty_contrast_score` DECIMAL(5,2) DEFAULT NULL,
    `faculty_remarks` TEXT DEFAULT NULL,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`validated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SAFETY AND CLIMATE LOG TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `safety_climate_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `trial_id` INT DEFAULT NULL,
    `powder_type` VARCHAR(100) NOT NULL DEFAULT 'eggshell',
    `surface_type` VARCHAR(100) NOT NULL DEFAULT 'glass',
    `temperature` DECIMAL(5,2) DEFAULT NULL,
    `humidity` DECIMAL(5,2) DEFAULT NULL,
    `health_feedback` VARCHAR(255) DEFAULT NULL,
    `irritation_status` ENUM('none','mild','moderate','severe') DEFAULT 'none',
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`trial_id`) REFERENCES `fingerprint_tests`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STUDENT SAFETY LOGS TABLE
-- Safety entries submitted from the student portal
-- ============================================================
CREATE TABLE IF NOT EXISTS `safety_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `temperature` DECIMAL(5,2) DEFAULT NULL,
    `humidity` DECIMAL(5,2) DEFAULT NULL,
    `ppe_worn` VARCHAR(255) DEFAULT NULL,
    `lab_conditions` VARCHAR(50) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FINGERPRINT IMAGES TABLE
-- Uploaded fingerprint images from the student portal
-- ============================================================
CREATE TABLE IF NOT EXISTS `fingerprint_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `label` VARCHAR(255) DEFAULT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FACULTY REMARKS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `faculty_remarks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `test_id` INT NOT NULL,
    `faculty_id` INT NOT NULL,
    `remarks` TEXT NOT NULL,
    `decision` ENUM('approved','rejected','needs_revision') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`test_id`)    REFERENCES `fingerprint_tests`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`faculty_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- REPORTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `generated_by` INT NOT NULL,
    `report_title` VARCHAR(255) NOT NULL,
    `report_filter` TEXT DEFAULT NULL COMMENT 'JSON filter params used',
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FIELD FEEDBACK TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `field_feedback` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_id` INT NOT NULL,
    `feedback_type` VARCHAR(100) NOT NULL,
    `surface_type` VARCHAR(50) DEFAULT NULL,
    `powder_type` VARCHAR(50) DEFAULT NULL,
    `observation` TEXT NOT NULL,
    `usability_rating` INT NOT NULL,
    `suggested_improvement` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACTIVITY LOGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `user_email` VARCHAR(150) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACCOUNT UNLOCK REQUESTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `account_unlock_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `email` VARCHAR(255) NOT NULL,
    `reason` TEXT NULL,
    `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
    `requested_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `reviewed_by` INT NULL,
    `reviewed_at` DATETIME NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SMS LOGS TABLE
-- Audit logs for automated SMS notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS `sms_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_phone` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `provider` VARCHAR(50) DEFAULT 'Audit Log',
    `status` VARCHAR(20) DEFAULT 'sent',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYSTEM SETTINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER LOGIN LOGS TABLE
-- Audit logs for user security & recent login history
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_login_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `device_type` VARCHAR(50) DEFAULT 'Desktop',
    `status` ENUM('success', 'failed') DEFAULT 'success',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- No default accounts or sample research data are imported.
-- Provision the first administrator with scripts/create_admin.php.
