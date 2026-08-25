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
    function env($key, $default = null)
    {
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
        if ($lowerVal === 'true')
            return true;
        if ($lowerVal === 'false')
            return false;
        if ($lowerVal === 'null' || $lowerVal === '(null)')
            return null;
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

// Set default timezone to Philippines (Asia/Manila, UTC+8)
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Manila'));


// Production Validation Guard
if (env('APP_ENV') === 'production') {
    $has_host = !empty(env('DB_HOST')) || !empty(env('MYSQLHOST')) || !empty(env('RDS_HOSTNAME'));
    $has_db   = !empty(env('DB_DATABASE')) || !empty(env('DB_NAME')) || !empty(env('MYSQLDATABASE')) || !empty(env('RDS_DB_NAME'));
    $has_user = !empty(env('DB_USERNAME')) || !empty(env('DB_USER')) || !empty(env('MYSQLUSER')) || !empty(env('RDS_USERNAME'));

    if (!$has_host || !$has_db || !$has_user) {
        http_response_code(500);
        die("System configuration is incomplete. Please contact the administrator.");
    }
}

require_once __DIR__ . '/auth_timeout.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Smart RDS Auto-Detection & Credential Enforcement
$detected_db_server = env('DB_HOST', env('MYSQLHOST', env('RDS_HOSTNAME', 'localhost')));
$is_rds_host = (strpos($detected_db_server, '.rds.amazonaws.com') !== false);

$user_val = env('DB_USER', env('RDS_USERNAME', ''));
$pass_val = env('DB_PASS', env('RDS_PASSWORD', ''));

if ($is_rds_host) {
    $db_user = !empty($user_val) ? $user_val : 'admin';
    $db_pass = !empty($pass_val) ? $pass_val : 'GreenForensics2026!';
} else {
    $db_user = env('DB_USERNAME', env('DB_USER', 'root'));
    $db_pass = env('DB_PASSWORD', env('DB_PASS', ''));
}

define('DB_SERVER', $detected_db_server);
define('DB_USERNAME', $db_user);
define('DB_PASSWORD', $db_pass);
define('DB_NAME', env('DB_DATABASE', env('DB_NAME', env('MYSQLDATABASE', env('RDS_DB_NAME', 'green_forensics')))));
define('DB_PORT', env('DB_PORT', env('MYSQLPORT', env('RDS_PORT', '3306'))));
define('GROQ_API_KEY', env('GROQ_API_KEY', ''));
define('GROQ_MODEL', env('GROQ_MODEL', 'llama-3.3-70b-versatile'));

// Google reCAPTCHA v2 Configuration
define('RECAPTCHA_SITE_KEY', env('RECAPTCHA_SITE_KEY', '6LeJW4AtAAAAAGWzBRisnnCrq6NKJBzzzV7iv6Qc'));
define('RECAPTCHA_SECRET_KEY', env('RECAPTCHA_SECRET_KEY', '6LeJW4AtAAAAAM9ntBfFDSiMMjIppuGcHh-xF1iH'));
define('RECAPTCHA_ENABLED', env('RECAPTCHA_ENABLED', true));

// Cloudflare Turnstile Configuration
define('TURNSTILE_SITE_KEY', env('TURNSTILE_SITE_KEY', '0x4AAAAAAEapwGNxjnqkVtIk'));
define('TURNSTILE_SECRET_KEY', env('TURNSTILE_SECRET_KEY', '0x4AAAAAAEapwLSoCLytAlengphotH5LUpo'));
define('TURNSTILE_ENABLED', env('TURNSTILE_ENABLED', true));

/**
 * Helper function to verify Cloudflare Turnstile response token
 * @param string $turnstile_response
 * @return bool
 */
if (!function_exists('verify_turnstile')) {
    function verify_turnstile($turnstile_response)
    {
        if (!TURNSTILE_ENABLED) {
            return true;
        }
        if (empty($turnstile_response)) {
            return false;
        }

        $secret = TURNSTILE_SECRET_KEY;
        if (empty($secret)) {
            return true;
        }

        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $post_data = http_build_query([
            'secret' => $secret,
            'response' => $turnstile_response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        $result = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $result = curl_exec($ch);
            curl_close($ch);
        }

        if ($result === false) {
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $post_data,
                    'timeout' => 10
                ]
            ];
            $context = stream_context_create($opts);
            $result = @file_get_contents($url, false, $context);
        }

        if ($result !== false) {
            $json = json_decode($result, true);
            if (isset($json['success']) && $json['success'] === true) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Helper function to verify Google reCAPTCHA v2 token
 * @param string $recaptcha_response
 * @return bool
 */
if (!function_exists('verify_recaptcha')) {
    function verify_recaptcha($recaptcha_response, $expected_action = 'register', $threshold = 0.5)
    {
        if (!RECAPTCHA_ENABLED) {
            return true;
        }
        if (empty($recaptcha_response)) {
            return false;
        }

        $secret = RECAPTCHA_SECRET_KEY;
        if (empty($secret) || $secret === 'YOUR_RECAPTCHA_SECRET_KEY_HERE') {
            return true;
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $post_data = http_build_query([
            'secret' => $secret,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        $result = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $result = curl_exec($ch);
            curl_close($ch);
        }

        if ($result === false) {
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $post_data,
                    'timeout' => 10
                ]
            ];
            $context = stream_context_create($opts);
            $result = @file_get_contents($url, false, $context);
        }

        if ($result !== false) {
            $json = json_decode($result, true);
            if (isset($json['success']) && $json['success'] === true) {
                // If v3 score is returned, check if score is above threshold
                if (isset($json['score']) && (float) $json['score'] < $threshold) {
                    error_log("reCAPTCHA v3 score too low: " . $json['score']);
                    return false;
                }
                return true;
            }
        }

        return false;
    }
}


define('APP_SCHEMA_VERSION', 4);

/**
 * Ensures the database schema is up-to-date.
 * Uses a MySQL advisory lock to prevent race conditions and deadlocks between concurrent requests.
 * Runs migrations ONLY once when schema version is outdated or tables are missing.
 *
 * @param PDO $pdo
 */
function ensure_database_schema(PDO $pdo)
{
    // Try to acquire an advisory lock for migration (timeout 10 seconds)
    $lockAcquired = false;
    try {
        $lockStmt = $pdo->query("SELECT GET_LOCK('green_forensics_migration_lock', 10)");
        $lockAcquired = ($lockStmt && (int)$lockStmt->fetchColumn() === 1);
    } catch (Exception $e) {
        $lockAcquired = false;
    }

    if (!$lockAcquired) {
        return; // Another process is running the migration or lock unavailable
    }

    try {
        // Fast-path: check if system_settings exists and schema_version is current
        $needsMigration = false;
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'system_settings'");
            if ($checkTable && $checkTable->rowCount() > 0) {
                $verStmt = $pdo->prepare("SELECT `setting_value` FROM `system_settings` WHERE `setting_key` = 'schema_version' LIMIT 1");
                $verStmt->execute();
                $currentVer = (int)$verStmt->fetchColumn();

                $checkUsers = $pdo->query("SHOW TABLES LIKE 'users'");
                if ($currentVer >= APP_SCHEMA_VERSION && $checkUsers && $checkUsers->rowCount() > 0) {
                    // Already up-to-date!
                    return;
                }
            }
            $needsMigration = true;
        } catch (Exception $e) {
            $needsMigration = true;
        }

        if (!$needsMigration) {
            return;
        }

        // ============================================================
        // 1. Create USERS table (role-based)
        // ============================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id`                   INT AUTO_INCREMENT PRIMARY KEY,
            `first_name`           VARCHAR(80) DEFAULT NULL,
            `middle_name`          VARCHAR(80) DEFAULT NULL,
            `last_name`            VARCHAR(80) DEFAULT NULL,
            `full_name`            VARCHAR(150) NOT NULL,
            `email`                VARCHAR(150) NOT NULL UNIQUE,
            `contact_number`       VARCHAR(20) DEFAULT NULL,
            `id_number`            VARCHAR(50) DEFAULT NULL,
            `department`           VARCHAR(150) DEFAULT NULL,
            `affiliation`          VARCHAR(150) DEFAULT NULL,
            `requested_role`       VARCHAR(50) DEFAULT NULL,
            `reason_for_access`    TEXT DEFAULT NULL,
            `proof_of_affiliation` VARCHAR(255) DEFAULT NULL,
            `profile_picture`      VARCHAR(255) DEFAULT NULL,
            `password`             VARCHAR(255) NOT NULL,
            `role`                 ENUM('super_admin','faculty_researcher','criminology_student','alumni_police_partner') DEFAULT NULL,
            `status`               ENUM('active','inactive','pending','rejected','suspended') DEFAULT 'pending',
            `failed_login_attempts` INT DEFAULT 0,
            `locked_until`          DATETIME NULL,
            `last_failed_login`     DATETIME NULL,
            `terms_agreed`          TINYINT(1) DEFAULT 0,
            `terms_agreed_at`       DATETIME NULL,
            `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Align columns if migrating from legacy tables
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
        $addUserColumn('profile_picture', "`profile_picture` VARCHAR(255) DEFAULT NULL AFTER `proof_of_affiliation`");
        $addUserColumn('failed_login_attempts', "`failed_login_attempts` INT DEFAULT 0 AFTER `status`");
        $addUserColumn('locked_until', "`locked_until` DATETIME NULL AFTER `failed_login_attempts`");
        $addUserColumn('last_failed_login', "`last_failed_login` DATETIME NULL AFTER `locked_until`");
        $addUserColumn('terms_agreed', "`terms_agreed` TINYINT(1) DEFAULT 0 AFTER `last_failed_login`");
        $addUserColumn('terms_agreed_at', "`terms_agreed_at` DATETIME NULL AFTER `terms_agreed`");

        // ============================================================
        // 2. Create FINGERPRINT_TESTS table
        // ============================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `fingerprint_tests` (
            `id`                          INT AUTO_INCREMENT PRIMARY KEY,
            `trial_id`                    VARCHAR(50) DEFAULT NULL,
            `student_id`                  INT NOT NULL,
            `powder_type`                 ENUM('eggshell','commercial') NOT NULL DEFAULT 'eggshell',
            `surface_type`                ENUM('glass','paper','wood','plastic','metal','ceramic','fabric') NOT NULL,
            `image_path`                  VARCHAR(255) DEFAULT NULL,
            `enhanced_image_path`         VARCHAR(255) DEFAULT NULL,
            `image_label`                 VARCHAR(255) DEFAULT NULL,
            `image_hash`                  VARCHAR(64) DEFAULT NULL,
            `gdrive_file_id`              VARCHAR(255) DEFAULT NULL,
            `ridge_clarity_score`         DECIMAL(5,2) DEFAULT NULL,
            `visibility_score`            DECIMAL(5,2) DEFAULT NULL,
            `adhesion_score`              DECIMAL(5,2) DEFAULT NULL,
            `contrast_score`              DECIMAL(5,2) DEFAULT NULL,
            `accuracy_score`              DECIMAL(5,2) DEFAULT NULL,
            `notes`                       TEXT DEFAULT NULL,
            `status`                      ENUM('pending_validation','approved','rejected','needs_revision') DEFAULT 'pending_validation',
            `submitted_at`                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `validated_by`                INT DEFAULT NULL,
            `validated_at`                TIMESTAMP NULL DEFAULT NULL,
            `ai_evaluated_at`             DATETIME DEFAULT NULL,
            `evaluation_source`           VARCHAR(50) DEFAULT 'AI Preliminary',
            `faculty_final_score`         DECIMAL(5,2) DEFAULT NULL,
            `ai_accuracy_score`           DECIMAL(5,2) DEFAULT NULL,
            `faculty_accuracy_score`      DECIMAL(5,2) DEFAULT NULL,
            `faculty_ridge_clarity_score` DECIMAL(5,2) DEFAULT NULL,
            `faculty_visibility_score`    DECIMAL(5,2) DEFAULT NULL,
            `faculty_adhesion_score`      DECIMAL(5,2) DEFAULT NULL,
            `faculty_contrast_score`      DECIMAL(5,2) DEFAULT NULL,
            `faculty_remarks`             TEXT DEFAULT NULL,
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
        $addTestColumn('gdrive_file_id', "`gdrive_file_id` VARCHAR(255) DEFAULT NULL AFTER `image_hash`");
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

        // Seed missing trial_ids for existing rows
        $pdo->exec("UPDATE `fingerprint_tests` SET `trial_id` = CONCAT('TR-', LPAD(id, 4, '0')) WHERE `trial_id` IS NULL OR `trial_id` = ''");

        // ============================================================
        // 3. Create SAFETY_CLIMATE_LOG table
        // ============================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `safety_climate_log` (
            `id`                INT AUTO_INCREMENT PRIMARY KEY,
            `student_id`        INT NOT NULL,
            `trial_id`          INT DEFAULT NULL,
            `powder_type`       VARCHAR(100) NOT NULL DEFAULT 'eggshell',
            `surface_type`      VARCHAR(100) NOT NULL DEFAULT 'glass',
            `temperature`       DECIMAL(5,2) DEFAULT NULL,
            `humidity`          DECIMAL(5,2) DEFAULT NULL,
            `health_feedback`   VARCHAR(255) DEFAULT NULL,
            `irritation_status` ENUM('none','mild','moderate','severe') DEFAULT 'none',
            `remarks`           TEXT DEFAULT NULL,
            `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`trial_id`)   REFERENCES `fingerprint_tests`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ============================================================
        // 4. Create STUDENT SAFETY_LOGS table
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
        // 5. Create FINGERPRINT_IMAGES table
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
        // 6. Create FACULTY_REMARKS table
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

        // ============================================================
        // 7. Create REPORTS table
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
        // 8. Create FIELD_FEEDBACK table
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
        // 9. Create ACTIVITY_LOGS table
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
        // 10. Create ACCOUNT_UNLOCK_REQUESTS table
        // ============================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `account_unlock_requests` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `user_id`      INT NULL,
            `email`        VARCHAR(255) NOT NULL,
            `reason`       TEXT NULL,
            `status`       ENUM('pending','approved','rejected') DEFAULT 'pending',
            `requested_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `reviewed_by`  INT NULL,
            `reviewed_at`  DATETIME NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ============================================================
        // 11. Create SMS_LOGS table
        // ============================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `sms_logs` (
            `id`              INT AUTO_INCREMENT PRIMARY KEY,
            `recipient_phone` VARCHAR(50) NOT NULL,
            `message`         TEXT NOT NULL,
            `provider`        VARCHAR(50) DEFAULT 'Audit Log',
            `status`          VARCHAR(20) DEFAULT 'sent',
            `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ============================================================
        // 12. Create SYSTEM_SETTINGS table
        // ============================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
            `setting_key`   VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ============================================================
        // 13. Create USER_LOGIN_LOGS table
        // ============================================================
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_login_logs` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `user_id`     INT NOT NULL,
            `ip_address`  VARCHAR(45) NOT NULL,
            `user_agent`  VARCHAR(255) DEFAULT NULL,
            `device_type` VARCHAR(50) DEFAULT 'Desktop',
            `status`      ENUM('success', 'failed') DEFAULT 'success',
            `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed default system settings
        $defaultSettings = [
            'system_name' => 'Green Forensics Evaluating System',
            'system_email' => 'admin@greenforensics.edu.ph',
            'allowed_registration_roles' => 'criminology_student,faculty_researcher,alumni_police_partner',
            'maintenance_mode' => '0',
            'max_login_attempts' => '5',
            'lockout_time' => '15',
            'schema_version' => (string)APP_SCHEMA_VERSION
        ];
        $checkSetting = $pdo->prepare("SELECT COUNT(*) FROM `system_settings` WHERE `setting_key` = :key");
        $insertSetting = $pdo->prepare("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES (:key, :val) ON DUPLICATE KEY UPDATE `setting_value` = :val_up");
        foreach ($defaultSettings as $key => $val) {
            $insertSetting->execute([':key' => $key, ':val' => $val, ':val_up' => $val]);
        }

        // ============================================================
        // 14. Seed default accounts using INSERT IGNORE
        // ============================================================
        $defaultAccounts = [
            [
                'first_name'  => 'System',
                'middle_name' => null,
                'last_name'   => 'Administrator',
                'full_name'   => 'System Administrator',
                'email'       => 'admin@greenforensics.com',
                'password'    => password_hash('admin123', PASSWORD_DEFAULT),
                'role'        => 'super_admin',
                'status'      => 'active'
            ],
            [
                'first_name'  => 'System',
                'middle_name' => null,
                'last_name'   => 'Administrator',
                'full_name'   => 'System Administrator (Edu)',
                'email'       => 'admin@greenforensics.edu.ph',
                'password'    => password_hash('admin123', PASSWORD_DEFAULT),
                'role'        => 'super_admin',
                'status'      => 'active'
            ],
            [
                'first_name'  => 'Maria',
                'middle_name' => null,
                'last_name'   => 'Santos',
                'full_name'   => 'Dr. Maria Santos',
                'email'       => 'faculty@greenforensics.edu.ph',
                'password'    => password_hash('faculty123', PASSWORD_DEFAULT),
                'role'        => 'faculty_researcher',
                'status'      => 'active'
            ],
            [
                'first_name'  => 'Juan',
                'middle_name' => null,
                'last_name'   => 'dela Cruz',
                'full_name'   => 'Juan dela Cruz',
                'email'       => 'student@greenforensics.edu.ph',
                'password'    => password_hash('student123', PASSWORD_DEFAULT),
                'role'        => 'criminology_student',
                'status'      => 'active'
            ],
        ];
        $ins = $pdo->prepare(
            "INSERT IGNORE INTO `users`
                (`first_name`, `middle_name`, `last_name`, `full_name`, `email`, `password`, `role`, `status`)
             VALUES
                (:first_name, :middle_name, :last_name, :full_name, :email, :password, :role, :status)"
        );
        foreach ($defaultAccounts as $acc) {
            $ins->execute($acc);
        }

    } catch (Exception $e) {
        error_log("Database Migration Warning: " . $e->getMessage());
    } finally {
        try {
            $pdo->exec("SELECT RELEASE_LOCK('green_forensics_migration_lock')");
        } catch (Exception $e) {
        }
    }
}

// Establish single direct connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Set MySQL session time zone to match Asia/Manila (+08:00)
    $pdo->exec("SET time_zone = '+08:00'");

    // Check / initialize database schema once if needed
    ensure_database_schema($pdo);

} catch (PDOException $e) {
    // If unknown database, attempt to auto-create and connect
    if ($e->getCode() == 1049 || stripos($e->getMessage(), 'Unknown database') !== false) {
        try {
            $pdo_init = new PDO("mysql:host=" . DB_SERVER . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
            $pdo_init->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo_init = null;

            $pdo = new PDO(
                "mysql:host=" . DB_SERVER . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USERNAME,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $pdo->exec("SET time_zone = '+08:00'");
            ensure_database_schema($pdo);
        } catch (PDOException $e2) {
            die("DATABASE ERROR: " . $e2->getMessage());
        }
    } else {
        die("DATABASE ERROR: " . $e->getMessage());
    }
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