<?php
// config.php - Database Configuration & Connection with Auto-Setup

define('DB_SERVER',   getenv('MYSQLHOST')     ?: 'localhost');
define('DB_USERNAME', getenv('MYSQLUSER')     ?: 'root');
define('DB_PASSWORD', getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '');
define('DB_NAME',     getenv('MYSQLDATABASE') ?: 'green_forensics');
define('DB_PORT',     getenv('MYSQLPORT')     ?: '3306');

try {
    // 1. Connect to MySQL without selecting a database first
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";port=" . DB_PORT, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create database if it does not exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 3. Re-connect to the specific database
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USERNAME, DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ============================================================
    // 4. Create USERS table (role-based)
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `full_name`  VARCHAR(150) NOT NULL,
        `email`      VARCHAR(150) NOT NULL UNIQUE,
        `password`   VARCHAR(255) NOT NULL,
        `role`       ENUM('super_admin','faculty_researcher','criminology_student','alumni_police_partner')
                     NOT NULL DEFAULT 'criminology_student',
        `status`     ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 4b. MIGRATION: Rename old 'name' column → 'full_name' if needed
    // ============================================================
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('name', $cols) && !in_array('full_name', $cols)) {
        $pdo->exec("ALTER TABLE `users` CHANGE `name` `full_name` VARCHAR(150) NOT NULL");
    }
    // 4c. MIGRATION: Add 'role' column if missing
    if (!in_array('role', $cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `role`
            ENUM('super_admin','faculty_researcher','criminology_student','alumni_police_partner')
            NOT NULL DEFAULT 'criminology_student' AFTER `password`");
        // Promote existing admin emails to super_admin
        $pdo->exec("UPDATE `users` SET `role`='super_admin'
            WHERE `email` IN ('admin@greenforensics.com','admin@greenforensics.edu.ph')");
    }
    // 4d. MIGRATION: Add 'updated_at' column if missing (old schema had none)
    if (!in_array('updated_at', $cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `updated_at`
            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
    }

    // ============================================================
    // 5. Create FINGERPRINT_TESTS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fingerprint_tests` (
        `id`                  INT AUTO_INCREMENT PRIMARY KEY,
        `student_id`          INT NOT NULL,
        `powder_type`         ENUM('eggshell','commercial') NOT NULL DEFAULT 'eggshell',
        `surface_type`        ENUM('glass','paper','wood','plastic','metal') NOT NULL,
        `fingerprint_image`   VARCHAR(255) DEFAULT NULL,
        `ridge_clarity_score` DECIMAL(5,2) DEFAULT 0.00,
        `visibility_score`    DECIMAL(5,2) DEFAULT 0.00,
        `adhesion_score`      DECIMAL(5,2) DEFAULT 0.00,
        `accuracy_score`      DECIMAL(5,2) DEFAULT 0.00,
        `status`              ENUM('pending','approved','rejected') DEFAULT 'pending',
        `submitted_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 6. Create SAFETY_CLIMATE_LOG table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `safety_climate_log` (
        `id`                INT AUTO_INCREMENT PRIMARY KEY,
        `test_id`           INT NOT NULL,
        `student_id`        INT NOT NULL,
        `temperature`       DECIMAL(5,2) DEFAULT NULL,
        `humidity`          DECIMAL(5,2) DEFAULT NULL,
        `health_feedback`   TEXT DEFAULT NULL,
        `irritation_report` TEXT DEFAULT NULL,
        `remarks`           TEXT DEFAULT NULL,
        `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`test_id`)    REFERENCES `fingerprint_tests`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 7. Create FACULTY_REMARKS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `faculty_remarks` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `test_id`    INT NOT NULL,
        `faculty_id` INT NOT NULL,
        `remarks`    TEXT NOT NULL,
        `decision`   ENUM('approved','rejected') NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`test_id`)    REFERENCES `fingerprint_tests`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`faculty_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 8. Create REPORTS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `reports` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `generated_by`  INT NOT NULL,
        `report_title`  VARCHAR(255) NOT NULL,
        `report_filter` TEXT DEFAULT NULL,
        `generated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 9. Seed default accounts using INSERT IGNORE
    //    Always runs — skips silently if email already exists.
    //    super_admin: admin123 | faculty: faculty123 | student: student123
    // ============================================================
    $defaultAccounts = [
        [
            'full_name' => 'System Administrator',
            'email'     => 'admin@greenforensics.com',
            'password'  => password_hash('admin123', PASSWORD_DEFAULT),
            'role'      => 'super_admin',
            'status'    => 'active'
        ],
        [
            'full_name' => 'System Administrator (Edu)',
            'email'     => 'admin@greenforensics.edu.ph',
            'password'  => password_hash('admin123', PASSWORD_DEFAULT),
            'role'      => 'super_admin',
            'status'    => 'active'
        ],
        [
            'full_name' => 'Dr. Maria Santos',
            'email'     => 'faculty@greenforensics.edu.ph',
            'password'  => password_hash('faculty123', PASSWORD_DEFAULT),
            'role'      => 'faculty_researcher',
            'status'    => 'active'
        ],
        [
            'full_name' => 'Juan dela Cruz',
            'email'     => 'student@greenforensics.edu.ph',
            'password'  => password_hash('student123', PASSWORD_DEFAULT),
            'role'      => 'criminology_student',
            'status'    => 'active'
        ],
    ];
    $ins = $pdo->prepare(
        "INSERT IGNORE INTO users (full_name, email, password, role, status)
         VALUES (:full_name, :email, :password, :role, :status)"
    );
    foreach ($defaultAccounts as $acc) {
        $ins->execute($acc);
    }


} catch (PDOException $e) {
    die("DATABASE ERROR: " . $e->getMessage());
}
?>