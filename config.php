<?php
// config.php - Database Configuration & Connection with Auto-Setup

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Polyfill for getallheaders() if it doesn't exist (e.g. non-Apache or cloud hosting like Railway)
if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
                // Add common casings to prevent lookup mismatch
                if (strtolower($key) === 'x-csrf-token') {
                    $headers['X-CSRF-Token'] = $value;
                    $headers['x-csrf-token'] = $value;
                }
            } elseif ($name === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($name === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }
        return $headers;
    }
}

// Define env() helper function if not exists
if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            if (isset($_ENV[$key])) {
                $val = $_ENV[$key];
            } elseif (isset($_SERVER[$key])) {
                $val = $_SERVER[$key];
            } else {
                return $default;
            }
        }
        $lowerVal = strtolower($val);
        if ($lowerVal === 'true') return true;
        if ($lowerVal === 'false') return false;
        if ($lowerVal === 'null' || $lowerVal === '(null)') return null;
        return $val;
    }
}

// Load .env file natively
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // Strip quotes
            if (preg_match('/^"(.*)"$/', $val, $matches)) {
                $val = $matches[1];
            } elseif (preg_match('/^\'(.*)\'$/', $val, $matches)) {
                $val = $matches[1];
            }
            if (getenv($key) === false) {
                putenv("$key=$val");
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Production Validation Guard
if (env('APP_ENV') === 'production') {
    $has_host = !empty(env('DB_HOST')) || !empty(env('MYSQLHOST'));
    $has_db   = !empty(env('DB_DATABASE')) || !empty(env('MYSQLDATABASE'));
    $has_user = !empty(env('DB_USERNAME')) || !empty(env('MYSQLUSER'));
    
    if (!$has_host || !$has_db || !$has_user) {
        http_response_code(500);
        die("System configuration is incomplete. Please contact the administrator.");
    }
}

require_once __DIR__ . '/auth_timeout.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Connection Settings from Environment (with Railway auto-variable fallback)
define('DB_SERVER', env('DB_HOST', env('MYSQLHOST', 'localhost')));
define('DB_USERNAME', env('DB_USERNAME', env('MYSQLUSER', 'root')));
define('DB_PASSWORD', env('DB_PASSWORD', env('MYSQLPASSWORD', '')));
define('DB_NAME', env('DB_DATABASE', env('MYSQLDATABASE', 'green_forensics')));
define('DB_PORT', env('DB_PORT', env('MYSQLPORT', '3306')));
define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));

try {
    // 1. Connect to MySQL without selecting a database first
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";port=" . DB_PORT, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create database if it does not exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 3. Re-connect to the specific database
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USERNAME,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ============================================================
    // 4. Create USERS table (role-based)
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id`                INT AUTO_INCREMENT PRIMARY KEY,
        `first_name`        VARCHAR(80) DEFAULT NULL,
        `middle_name`       VARCHAR(80) DEFAULT NULL,
        `last_name`         VARCHAR(80) DEFAULT NULL,
        `full_name`         VARCHAR(150) NOT NULL,
        `email`             VARCHAR(150) NOT NULL UNIQUE,
        `contact_number`    VARCHAR(20) DEFAULT NULL,
        `id_number`         VARCHAR(50) DEFAULT NULL,
        `department`        VARCHAR(150) DEFAULT NULL,
        `affiliation`       VARCHAR(150) DEFAULT NULL,
        `requested_role`    VARCHAR(50) DEFAULT NULL,
        `reason_for_access` TEXT DEFAULT NULL,
        `proof_of_affiliation` VARCHAR(255) DEFAULT NULL,
        `password`          VARCHAR(255) NOT NULL,
        `role`              ENUM('super_admin','faculty_researcher','criminology_student','alumni_police_partner')
                            DEFAULT NULL,
        `status`            ENUM('active','inactive','pending','rejected','suspended') DEFAULT 'pending',
        `failed_login_attempts` INT DEFAULT 0,
        `locked_until`       DATETIME NULL,
        `last_failed_login`  DATETIME NULL,
        `terms_agreed`       TINYINT(1) DEFAULT 0,
        `terms_agreed_at`    DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 4b. MIGRATIONS: Keep older local databases aligned with database.sql
    // ============================================================
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('name', $cols) && !in_array('full_name', $cols)) {
        $pdo->exec("ALTER TABLE `users` CHANGE `name` `full_name` VARCHAR(150) NOT NULL");
        $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    }

    $addUserColumn = function ($column, $definition) use ($pdo, &$cols) {
        if (!in_array($column, $cols, true)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN $definition");
            $cols[] = $column;
        }
    };

    $addUserColumn('first_name', "`first_name` VARCHAR(80) DEFAULT NULL AFTER `id`");
    $addUserColumn('middle_name', "`middle_name` VARCHAR(80) DEFAULT NULL AFTER `first_name`");
    $addUserColumn('last_name', "`last_name` VARCHAR(80) DEFAULT NULL AFTER `middle_name`");
    $addUserColumn('contact_number', "`contact_number` VARCHAR(20) DEFAULT NULL AFTER `email`");
    $addUserColumn('id_number', "`id_number` VARCHAR(50) DEFAULT NULL AFTER `contact_number`");
    $addUserColumn('department', "`department` VARCHAR(150) DEFAULT NULL AFTER `id_number`");
    $addUserColumn('affiliation', "`affiliation` VARCHAR(150) DEFAULT NULL AFTER `department`");
    $addUserColumn('requested_role', "`requested_role` VARCHAR(50) DEFAULT NULL AFTER `affiliation`");
    $addUserColumn('reason_for_access', "`reason_for_access` TEXT DEFAULT NULL AFTER `requested_role`");
    $addUserColumn('proof_of_affiliation', "`proof_of_affiliation` VARCHAR(255) DEFAULT NULL AFTER `reason_for_access`");

    if (!in_array('role', $cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `role`
            ENUM('super_admin','faculty_researcher','criminology_student','alumni_police_partner')
            DEFAULT NULL AFTER `password`");
        $cols[] = 'role';
        // Promote existing admin emails to super_admin
        $pdo->exec("UPDATE `users` SET `role`='super_admin'
            WHERE `email` IN ('admin@greenforensics.com','admin@greenforensics.edu.ph')");
    }
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role`
        ENUM('super_admin','faculty_researcher','criminology_student','alumni_police_partner')
        DEFAULT NULL");

    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `status`
            ENUM('active','inactive','pending','rejected','suspended') DEFAULT 'pending' AFTER `role`");
        $cols[] = 'status';
    }
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `status`
        ENUM('active','inactive','pending','rejected','suspended') DEFAULT 'pending'");

    if (!in_array('updated_at', $cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `updated_at`
            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
    }
    
    $addUserColumn('failed_login_attempts', "`failed_login_attempts` INT DEFAULT 0 AFTER `status`");
    $addUserColumn('locked_until', "`locked_until` DATETIME NULL AFTER `failed_login_attempts`");
    $addUserColumn('last_failed_login', "`last_failed_login` DATETIME NULL AFTER `locked_until`");
    $addUserColumn('terms_agreed', "`terms_agreed` TINYINT(1) DEFAULT 0 AFTER `last_failed_login`");
    $addUserColumn('terms_agreed_at', "`terms_agreed_at` DATETIME NULL AFTER `terms_agreed`");
    $pdo->exec("UPDATE `users`
        SET `status` = 'active'
        WHERE `email` IN (
            'admin@greenforensics.com',
            'admin@greenforensics.edu.ph',
            'faculty@greenforensics.edu.ph',
            'student@greenforensics.edu.ph'
        )");

    // ============================================================
    // 5. Create FINGERPRINT_TESTS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fingerprint_tests` (
        `id`                  INT AUTO_INCREMENT PRIMARY KEY,
        `trial_id`            VARCHAR(50) DEFAULT NULL,
        `student_id`          INT NOT NULL,
        `powder_type`         ENUM('eggshell','commercial') NOT NULL DEFAULT 'eggshell',
        `surface_type`        ENUM('glass','paper','wood','plastic','metal','ceramic','fabric') NOT NULL,
        `image_path`          VARCHAR(255) DEFAULT NULL,
        `image_label`         VARCHAR(255) DEFAULT NULL,
        `ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL,
        `visibility_score`    DECIMAL(5,2) DEFAULT NULL,
        `adhesion_score`      DECIMAL(5,2) DEFAULT NULL,
        `accuracy_score`      DECIMAL(5,2) DEFAULT NULL,
        `notes`               TEXT DEFAULT NULL,
        `status`              ENUM('pending_validation','approved','rejected','needs_revision') DEFAULT 'pending_validation',
        `submitted_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `validated_by`        INT DEFAULT NULL,
        `validated_at`        TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $testCols = $pdo->query("SHOW COLUMNS FROM `fingerprint_tests`")->fetchAll(PDO::FETCH_COLUMN);
    $addTestColumn = function ($column, $definition) use ($pdo, &$testCols) {
        if (!in_array($column, $testCols, true)) {
            $pdo->exec("ALTER TABLE `fingerprint_tests` ADD COLUMN $definition");
            $testCols[] = $column;
        }
    };

    $addTestColumn('trial_id', "`trial_id` VARCHAR(50) DEFAULT NULL AFTER `id`");
    $addTestColumn('student_id', "`student_id` INT DEFAULT NULL AFTER `trial_id`");
    $addTestColumn('powder_type', "`powder_type` ENUM('eggshell','commercial') NOT NULL DEFAULT 'eggshell' AFTER `student_id`");
    $addTestColumn('image_path', "`image_path` VARCHAR(255) DEFAULT NULL AFTER `surface_type`");
    $addTestColumn('enhanced_image_path', "`enhanced_image_path` VARCHAR(255) DEFAULT NULL AFTER `image_path`");
    $addTestColumn('image_label', "`image_label` VARCHAR(255) DEFAULT NULL AFTER `image_path`");
    $addTestColumn('image_hash', "`image_hash` VARCHAR(64) DEFAULT NULL AFTER `image_path`");

    if (in_array('created_by', $testCols, true)) {
        $pdo->exec("UPDATE `fingerprint_tests` SET `student_id` = `created_by` WHERE `student_id` IS NULL");
    }
    $pdo->exec("UPDATE `fingerprint_tests` SET `student_id` = 4 WHERE `student_id` IS NULL OR `student_id` = 0");
    try {
        $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `student_id` INT NOT NULL");
    } catch (Exception $e) {}
    $addTestColumn('ridge_clarity_score', "`ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('visibility_score', "`visibility_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('adhesion_score', "`adhesion_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('contrast_score', "`contrast_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('accuracy_score', "`accuracy_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('ai_evaluated_at', "`ai_evaluated_at` DATETIME DEFAULT NULL");
    $addTestColumn('evaluation_source', "`evaluation_source` VARCHAR(50) DEFAULT 'AI Preliminary'");
    $addTestColumn('faculty_final_score', "`faculty_final_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('ai_accuracy_score', "`ai_accuracy_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('status', "`status` VARCHAR(50) DEFAULT 'pending_validation'");
    $addTestColumn('validated_by', "`validated_by` INT DEFAULT NULL AFTER `submitted_at`");
    $addTestColumn('validated_at', "`validated_at` TIMESTAMP NULL DEFAULT NULL AFTER `validated_by`");
    $addTestColumn('faculty_accuracy_score', "`faculty_accuracy_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('faculty_ridge_clarity_score', "`faculty_ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('faculty_visibility_score', "`faculty_visibility_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('faculty_adhesion_score', "`faculty_adhesion_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('faculty_contrast_score', "`faculty_contrast_score` DECIMAL(5,2) DEFAULT NULL");
    $addTestColumn('faculty_remarks', "`faculty_remarks` TEXT DEFAULT NULL");

    // Copy legacy columns if they exist
    if (in_array('fingerprint_image', $testCols, true)) {
        $pdo->exec("UPDATE `fingerprint_tests` SET `image_path` = `fingerprint_image` WHERE `image_path` IS NULL AND `fingerprint_image` IS NOT NULL");
    }

    // Add validated_by foreign key if it does not exist
    try {
        $pdo->exec("ALTER TABLE `fingerprint_tests` ADD CONSTRAINT `fk_validated_by` FOREIGN KEY (`validated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL");
    } catch (Exception $e) {
    }

    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `visibility_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `adhesion_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `contrast_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `accuracy_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `faculty_final_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `ai_accuracy_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `faculty_accuracy_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `faculty_ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `faculty_visibility_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `faculty_adhesion_score` DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `faculty_contrast_score` DECIMAL(5,2) DEFAULT NULL");

    // Safe migration of status column
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `status` VARCHAR(50) DEFAULT 'pending_validation'");
    $pdo->exec("UPDATE `fingerprint_tests` SET `status` = 'pending_validation' WHERE `status` = 'pending'");
    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `status` 
        ENUM('pending_validation','approved','rejected','needs_revision') DEFAULT 'pending_validation'");

    $pdo->exec("ALTER TABLE `fingerprint_tests` MODIFY COLUMN `surface_type`
        ENUM('glass','paper','wood','plastic','metal','ceramic','fabric') NOT NULL");

    // Seed missing trial_ids for existing rows
    $pdo->exec("UPDATE `fingerprint_tests` SET `trial_id` = CONCAT('TR-', LPAD(id, 4, '0')) WHERE `trial_id` IS NULL OR `trial_id` = ''");

    // ============================================================
    // 6. Create or Migrate SAFETY_CLIMATE_LOG table
    // ============================================================
    $hasSafetyTable = false;
    try {
        $hasSafetyTable = $pdo->query("SELECT 1 FROM `safety_climate_log` LIMIT 1") !== false;
    } catch (Exception $e) {}

    if ($hasSafetyTable) {
        // Check if table contains data
        $rowCount = (int)$pdo->query("SELECT COUNT(*) FROM `safety_climate_log`")->fetchColumn();
        
        if ($rowCount === 0) {
            // Drop and recreate empty table
            $pdo->exec("DROP TABLE IF EXISTS `safety_climate_log`");
            $hasSafetyTable = false;
        } else {
            // Migrate existing table using ALTER
            $sclCols = $pdo->query("SHOW COLUMNS FROM `safety_climate_log`")->fetchAll(PDO::FETCH_COLUMN);
            
            // Add student_id if not present
            if (!in_array('student_id', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `student_id` INT NOT NULL");
            }
            
            // Add trial_id if not present
            if (!in_array('trial_id', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `trial_id` INT DEFAULT NULL");
                // If old test_id exists, copy test_id to trial_id
                if (in_array('test_id', $sclCols, true)) {
                    $pdo->exec("UPDATE `safety_climate_log` SET `trial_id` = `test_id` WHERE `trial_id` IS NULL");
                }
            }
            
            // Add powder_type if not present (default to empty or eggshell)
            if (!in_array('powder_type', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `powder_type` VARCHAR(100) NOT NULL DEFAULT 'eggshell'");
                // Copy from connected test if available
                $pdo->exec("UPDATE `safety_climate_log` scl 
                            JOIN `fingerprint_tests` ft ON scl.trial_id = ft.id 
                            SET scl.powder_type = ft.powder_type");
            }
            
            // Add surface_type if not present (default to empty or glass)
            if (!in_array('surface_type', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `surface_type` VARCHAR(100) NOT NULL DEFAULT 'glass'");
                // Copy from connected test if available
                $pdo->exec("UPDATE `safety_climate_log` scl 
                            JOIN `fingerprint_tests` ft ON scl.trial_id = ft.id 
                            SET scl.surface_type = ft.surface_type");
            }
            
            // Add temperature if not present, otherwise ensure type is DECIMAL(5,2)
            if (!in_array('temperature', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `temperature` DECIMAL(5,2) DEFAULT NULL");
            } else {
                try {
                    $pdo->exec("ALTER TABLE `safety_climate_log` MODIFY COLUMN `temperature` DECIMAL(5,2) DEFAULT NULL");
                } catch (Exception $e) {}
            }
            
            // Add humidity if not present, otherwise ensure type is DECIMAL(5,2)
            if (!in_array('humidity', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `humidity` DECIMAL(5,2) DEFAULT NULL");
            } else {
                try {
                    $pdo->exec("ALTER TABLE `safety_climate_log` MODIFY COLUMN `humidity` DECIMAL(5,2) DEFAULT NULL");
                } catch (Exception $e) {}
            }
            
            // Add health_feedback if not present
            if (!in_array('health_feedback', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `health_feedback` VARCHAR(255) DEFAULT NULL");
            }
            
            // Add irritation_status if not present (convert irritation_report if it exists)
            if (!in_array('irritation_status', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `irritation_status` ENUM('none','mild','moderate','severe') DEFAULT 'none'");
                if (in_array('irritation_report', $sclCols, true)) {
                    $pdo->exec("UPDATE `safety_climate_log` 
                                SET `irritation_status` = CASE 
                                    WHEN LOWER(`irritation_report`) LIKE '%severe%' THEN 'severe'
                                    WHEN LOWER(`irritation_report`) LIKE '%moderate%' THEN 'moderate'
                                    WHEN LOWER(`irritation_report`) LIKE '%mild%' THEN 'mild'
                                    ELSE 'none'
                                END");
                }
            }
            
            // Add remarks if not present
            if (!in_array('remarks', $sclCols, true)) {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD COLUMN `remarks` TEXT DEFAULT NULL");
            }
            
            // Add foreign keys constraints if possible
            try {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD CONSTRAINT `fk_scl_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE");
            } catch (Exception $e) {}
            try {
                $pdo->exec("ALTER TABLE `safety_climate_log` ADD CONSTRAINT `fk_scl_trial` FOREIGN KEY (`trial_id`) REFERENCES `fingerprint_tests`(`id`) ON DELETE SET NULL");
            } catch (Exception $e) {}
        }
    }

    if (!$hasSafetyTable) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `safety_climate_log` (
            `id`                INT AUTO_INCREMENT PRIMARY KEY,
            `student_id`        INT NOT NULL,
            `trial_id`          INT DEFAULT NULL,
            `powder_type`       VARCHAR(100) NOT NULL,
            `surface_type`      VARCHAR(100) NOT NULL,
            `temperature`       DECIMAL(5,2) DEFAULT NULL,
            `humidity`          DECIMAL(5,2) DEFAULT NULL,
            `health_feedback`   VARCHAR(255) DEFAULT NULL,
            `irritation_status` ENUM('none','mild','moderate','severe') DEFAULT 'none',
            `remarks`           TEXT DEFAULT NULL,
            `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`trial_id`)   REFERENCES `fingerprint_tests`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ============================================================
    // 7. Create STUDENT SAFETY_LOGS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `safety_logs` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `student_id`     INT NOT NULL,
        `temperature`    DECIMAL(5,2) DEFAULT NULL,
        `humidity`       DECIMAL(5,2) DEFAULT NULL,
        `ppe_worn`       VARCHAR(255) DEFAULT NULL,
        `lab_conditions` VARCHAR(50) DEFAULT NULL,
        `notes`          TEXT DEFAULT NULL,
        `logged_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 8. Create FINGERPRINT_IMAGES table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fingerprint_images` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `student_id`  INT NOT NULL,
        `filename`    VARCHAR(255) NOT NULL,
        `label`       VARCHAR(255) DEFAULT NULL,
        `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 9. Create FACULTY_REMARKS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `faculty_remarks` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `test_id`    INT NOT NULL,
        `faculty_id` INT NOT NULL,
        `remarks`    TEXT NOT NULL,
        `decision`   ENUM('approved','rejected','needs_revision') NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`test_id`)    REFERENCES `fingerprint_tests`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`faculty_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Safe migration of faculty_remarks decision column
    $pdo->exec("ALTER TABLE `faculty_remarks` MODIFY COLUMN `decision` VARCHAR(50) NOT NULL");
    $pdo->exec("ALTER TABLE `faculty_remarks` MODIFY COLUMN `decision` 
        ENUM('approved','rejected','needs_revision') NOT NULL");

    // ============================================================
    // 10. Create REPORTS table
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
    // 10e. Create FIELD_FEEDBACK table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `field_feedback` (
        `id`                    INT AUTO_INCREMENT PRIMARY KEY,
        `partner_id`            INT NOT NULL,
        `feedback_type`         VARCHAR(100) NOT NULL,
        `surface_type`          VARCHAR(50) DEFAULT NULL,
        `powder_type`           VARCHAR(50) DEFAULT NULL,
        `observation`           TEXT NOT NULL,
        `usability_rating`      INT NOT NULL,
        `suggested_improvement` TEXT DEFAULT NULL,
        `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`partner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ============================================================
    // 10b. Create ACTIVITY_LOGS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`     INT DEFAULT NULL,
        `user_email`  VARCHAR(150) NOT NULL,
        `action`      VARCHAR(100) NOT NULL,
        `details`     TEXT NOT NULL,
        `ip_address`  VARCHAR(45) DEFAULT NULL,
        `user_agent`  VARCHAR(255) DEFAULT NULL,
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 
 
    // ============================================================
    // 10c. Create ACCOUNT_UNLOCK_REQUESTS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `account_unlock_requests` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


    // ============================================================
    // 10d. Create SYSTEM_SETTINGS table
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_key`   VARCHAR(100) PRIMARY KEY,
        `setting_value` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed default settings if they do not exist
    $defaultSettings = [
        'system_name' => 'Green Forensics Evaluating System',
        'system_email' => 'admin@greenforensics.edu.ph',
        'allowed_registration_roles' => 'criminology_student,faculty_researcher,alumni_police_partner',
        'maintenance_mode' => '0',
        'max_login_attempts' => '5',
        'lockout_time' => '15'
    ];
    $checkSetting = $pdo->prepare("SELECT COUNT(*) FROM `system_settings` WHERE `setting_key` = :key");
    $insertSetting = $pdo->prepare("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES (:key, :val)");
    foreach ($defaultSettings as $key => $val) {
        $checkSetting->execute([':key' => $key]);
        if ($checkSetting->fetchColumn() == 0) {
            $insertSetting->execute([':key' => $key, ':val' => $val]);
        }
    }

    // ============================================================
    // 11. Seed default accounts using INSERT IGNORE
    //    Always runs — skips silently if email already exists.
    //    super_admin: admin123 | faculty: faculty123 | student: student123
    // ============================================================
    $defaultAccounts = [
        [
            'first_name' => 'System',
            'middle_name' => null,
            'last_name' => 'Administrator',
            'full_name' => 'System Administrator',
            'email' => 'admin@greenforensics.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'super_admin',
            'status' => 'active'
        ],
        [
            'first_name' => 'System',
            'middle_name' => null,
            'last_name' => 'Administrator',
            'full_name' => 'System Administrator (Edu)',
            'email' => 'admin@greenforensics.edu.ph',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'super_admin',
            'status' => 'active'
        ],
        [
            'first_name' => 'Maria',
            'middle_name' => null,
            'last_name' => 'Santos',
            'full_name' => 'Dr. Maria Santos',
            'email' => 'faculty@greenforensics.edu.ph',
            'password' => password_hash('faculty123', PASSWORD_DEFAULT),
            'role' => 'faculty_researcher',
            'status' => 'active'
        ],
        [
            'first_name' => 'Juan',
            'middle_name' => null,
            'last_name' => 'dela Cruz',
            'full_name' => 'Juan dela Cruz',
            'email' => 'student@greenforensics.edu.ph',
            'password' => password_hash('student123', PASSWORD_DEFAULT),
            'role' => 'criminology_student',
            'status' => 'active'
        ],
    ];
    $ins = $pdo->prepare(
        "INSERT IGNORE INTO users
            (first_name, middle_name, last_name, full_name, email, password, role, status)
         VALUES
            (:first_name, :middle_name, :last_name, :full_name, :email, :password, :role, :status)"
    );
    foreach ($defaultAccounts as $acc) {
        $ins->execute($acc);
    }


} catch (PDOException $e) {
    die("DATABASE ERROR: " . $e->getMessage());
}

// Global Inactivity Auto-Logout Tracker (5 Minutes)
register_shutdown_function(function () {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        $is_json = false;
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') !== false && stripos($header, 'application/json') !== false) {
                $is_json = true;
                break;
            }
        }

        $is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            strpos($_SERVER['SCRIPT_NAME'], 'ajax_') !== false ||
            strpos($_SERVER['SCRIPT_NAME'], 'support_chat_api.php') !== false ||
            strpos($_SERVER['SCRIPT_NAME'], 'check_registration_status.php') !== false ||
            $is_json;

        $is_admin = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ||
            (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin');

        $is_faculty = (strpos($_SERVER['SCRIPT_NAME'], '/faculty/') !== false) ||
            (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'faculty_researcher');

        if (!$is_ajax && !$is_admin && !$is_faculty) {
            $is_subdir = (strpos($_SERVER['SCRIPT_NAME'], '/faculty/') !== false ||
                strpos($_SERVER['SCRIPT_NAME'], '/student/') !== false ||
                strpos($_SERVER['SCRIPT_NAME'], '/police-partner/') !== false);
            $script_url = $is_subdir ? '../assets/js/session_timeout.js' : 'assets/js/session_timeout.js';
            ?>
            <script src="<?php echo $script_url; ?>"></script>
            <?php
        }
    }
});
?>