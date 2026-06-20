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
    `image_label` VARCHAR(255) DEFAULT NULL,
    `ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL,
    `visibility_score` DECIMAL(5,2) DEFAULT NULL,
    `adhesion_score` DECIMAL(5,2) DEFAULT NULL,
    `contrast_score` DECIMAL(5,2) DEFAULT NULL,
    `accuracy_score` DECIMAL(5,2) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('pending_validation','approved','rejected','needs_revision') DEFAULT 'pending_validation',
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `validated_by` INT DEFAULT NULL,
    `validated_at` TIMESTAMP DEFAULT NULL,
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
    `powder_type` VARCHAR(100) NOT NULL,
    `surface_type` VARCHAR(100) NOT NULL,
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
-- SEED: Default Users
-- Passwords hashed using PASSWORD_BCRYPT via password_hash()
-- super_admin: admin123 | faculty: faculty123 | student: student123
-- ============================================================
INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `full_name`, `email`, `password`, `role`, `status`) VALUES
(1, 'System', NULL, 'Administrator', 'System Administrator', 'admin@greenforensics.com', '$2y$10$vU3vA6Kj24M75WqYFfL2aO/Qk2tQZlS66HWhFmgz4qEw3Q1c6lGxe', 'super_admin', 'active'),
(2, 'System', NULL, 'Administrator', 'System Administrator (Edu)', 'admin@greenforensics.edu.ph', '$2y$10$Cde1Vjp9ICu1HX.MbUnSXek0NUwgFr6m2VThMuTikxhTYlgn4sc.C', 'super_admin', 'active'),
(3, 'Maria', NULL, 'Santos', 'Dr. Maria Santos', 'faculty@greenforensics.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty_researcher', 'active'),
(4, 'Juan', NULL, 'dela Cruz', 'Juan dela Cruz', 'student@greenforensics.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'criminology_student', 'active')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- ============================================================
-- SEED: Sample fingerprint test submissions
-- ============================================================
INSERT INTO `fingerprint_tests` (`id`, `trial_id`, `student_id`, `powder_type`, `surface_type`, `image_path`, `ridge_clarity_score`, `visibility_score`, `adhesion_score`, `accuracy_score`, `status`, `submitted_at`, `validated_by`, `validated_at`) VALUES
(1, 'TR-0001', 4, 'eggshell', 'glass', NULL, 88.50, 91.00, 85.00, 88.17, 'pending_validation', '2026-05-28 08:30:00', NULL, NULL),
(2, 'TR-0002', 4, 'commercial', 'glass', NULL, 90.00, 92.50, 88.00, 90.17, 'approved', '2026-05-28 09:00:00', 3, '2026-05-28 10:00:00'),
(3, 'TR-0003', 4, 'eggshell', 'paper', NULL, 82.00, 79.50, 80.00, 80.50, 'pending_validation', '2026-05-29 10:00:00', NULL, NULL),
(4, 'TR-0004', 4, 'eggshell', 'wood', NULL, 75.00, 77.00, 73.00, 75.00, 'rejected', '2026-05-29 11:00:00', 3, '2026-05-29 12:00:00'),
(5, 'TR-0005', 4, 'commercial', 'plastic', NULL, 93.00, 94.00, 92.00, 93.00, 'approved', '2026-05-30 09:30:00', 3, '2026-05-30 10:00:00'),
(6, 'TR-0006', 4, 'eggshell', 'metal', NULL, 86.00, 88.00, 84.00, 86.00, 'pending_validation', '2026-05-30 10:30:00', NULL, NULL)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- ============================================================
-- SEED: Sample safety climate logs
-- ============================================================
INSERT INTO `safety_climate_log` (`id`, `student_id`, `trial_id`, `powder_type`, `surface_type`, `temperature`, `humidity`, `health_feedback`, `irritation_status`, `remarks`) VALUES
(1, 4, 1, 'eggshell', 'glass', 27.50, 65.00, 'No discomfort reported during testing.', 'none', 'Testing conditions were within safe parameters.'),
(2, 4, 2, 'commercial', 'glass', 28.00, 68.00, 'Mild dryness in throat after 30 minutes.', 'none', 'Recommended to use mask during extended testing.'),
(3, 4, 3, 'eggshell', 'paper', 26.00, 60.00, 'No issues reported.', 'none', 'Comfortable testing environment.'),
(4, 4, 4, 'eggshell', 'wood', 29.00, 72.00, 'Slight irritation in eyes noted.', 'mild', 'Goggles required for next session.'),
(5, 4, 5, 'commercial', 'plastic', 27.00, 63.00, 'No discomfort.', 'none', 'Good environmental conditions.'),
(6, 4, 6, 'eggshell', 'metal', 28.50, 70.00, 'No issues.', 'none', 'Standard testing protocol followed.')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- ============================================================
-- SEED: Sample faculty remarks
-- ============================================================
INSERT INTO `faculty_remarks` (`id`, `test_id`, `faculty_id`, `remarks`, `decision`) VALUES
(1, 2, 3, 'Clear ridge patterns visible. Eggshell powder showed comparable results to commercial grade. Approved for inclusion in final report.', 'approved'),
(2, 4, 3, 'Ridge clarity insufficient on wood surface. Recommend re-testing with finer powder particle size.', 'rejected'),
(3, 5, 3, 'Excellent results on plastic surface. Commercial powder baseline confirmed.', 'approved')
ON DUPLICATE KEY UPDATE `id`=`id`;
