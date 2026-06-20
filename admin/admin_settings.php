<?php
// admin/admin_settings.php - System Settings Panel
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

// 1. PROCESS AJAX ACTIONS (Test Connection / Test Email)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "test_gemini") {
        header('Content-Type: application/json');
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $apiKey = env('GEMINI_API_KEY');
        $model = env('GEMINI_MODEL', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'message' => 'Gemini API is not connected. Please check environment variables.']);
            exit;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apiKey;
        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => "ping"]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.1,
                "maxOutputTokens" => 5
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);

        if (env('APP_ENV') !== 'production') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo json_encode(['success' => true, 'message' => 'Gemini API is connected.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gemini API is not connected. Please check environment variables.']);
        }
        exit;
    }

    if ($_POST["action"] === "test_email") {
        header('Content-Type: application/json');
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Email notification is not configured yet.']);
        exit;
    }
}

$error = "";
$success = "";

// 2. PROCESS FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "save_settings") {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        // Retrieve and sanitize fields
        $system_contact_email = trim($_POST['system_contact_email'] ?? '');
        $notification_email = trim($_POST['notification_email'] ?? '');
        
        $public_registration_enabled = isset($_POST['public_registration_enabled']) ? '1' : '0';
        $require_proof_affiliation = isset($_POST['require_proof_affiliation']) ? '1' : '0';
        $require_terms_agreement = isset($_POST['require_terms_agreement']) ? '1' : '0';
        
        $allow_role_criminology_student = isset($_POST['allow_role_criminology_student']) ? '1' : '0';
        $allow_role_faculty_researcher = isset($_POST['allow_role_faculty_researcher']) ? '1' : '0';
        $allow_role_alumni_police_partner = isset($_POST['allow_role_alumni_police_partner']) ? '1' : '0';
        
        $max_failed_login_attempts = intval($_POST['max_failed_login_attempts'] ?? 5);
        $login_lockout_minutes = intval($_POST['login_lockout_minutes'] ?? 15);
        
        $max_fingerprint_upload_mb = intval($_POST['max_fingerprint_upload_mb'] ?? 5);
        $max_proof_upload_mb = intval($_POST['max_proof_upload_mb'] ?? 5);
        
        $allowed_image_types = strtolower(trim($_POST['allowed_image_types'] ?? ''));
        $allowed_proof_types = strtolower(trim($_POST['allowed_proof_types'] ?? ''));
        
        $support_assistant_enabled = isset($_POST['support_assistant_enabled']) ? '1' : '0';

        // Validation Checks
        if (!filter_var($system_contact_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid System Contact Email format.";
        } elseif (!filter_var($notification_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid Notification Email format.";
        } elseif ($max_failed_login_attempts < 1) {
            $error = "Max Failed Login Attempts must be at least 1.";
        } elseif ($login_lockout_minutes < 1) {
            $error = "Lockout Duration must be at least 1 minute.";
        } elseif ($max_fingerprint_upload_mb < 1) {
            $error = "Max Fingerprint Upload Size must be at least 1 MB.";
        } elseif ($max_proof_upload_mb < 1) {
            $error = "Max Proof of Affiliation Upload Size must be at least 1 MB.";
        } elseif (empty($allowed_image_types)) {
            $error = "Allowed Image Types cannot be empty.";
        } elseif (empty($allowed_proof_types)) {
            $error = "Allowed Proof File Types cannot be empty.";
        } else {
            $admin_id = $_SESSION['user_id'] ?? null;
            $fields_to_update = [
                'system_contact_email' => $system_contact_email,
                'public_registration_enabled' => $public_registration_enabled,
                'require_proof_affiliation' => $require_proof_affiliation,
                'require_terms_agreement' => $require_terms_agreement,
                'allow_role_criminology_student' => $allow_role_criminology_student,
                'allow_role_faculty_researcher' => $allow_role_faculty_researcher,
                'allow_role_alumni_police_partner' => $allow_role_alumni_police_partner,
                'max_failed_login_attempts' => (string)$max_failed_login_attempts,
                'login_lockout_minutes' => (string)$login_lockout_minutes,
                'max_fingerprint_upload_mb' => (string)$max_fingerprint_upload_mb,
                'max_proof_upload_mb' => (string)$max_proof_upload_mb,
                'allowed_image_types' => $allowed_image_types,
                'allowed_proof_types' => $allowed_proof_types,
                'support_assistant_enabled' => $support_assistant_enabled,
                'notification_email' => $notification_email
            ];

            try {
                $pdo->beginTransaction();
                
                $getOld = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
                $upd = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (:key, :val, :admin) ON DUPLICATE KEY UPDATE setting_value = :val, updated_by = :admin");
                $logStmt = $pdo->prepare("INSERT INTO system_settings_logs (setting_name, old_value, new_value, updated_by) VALUES (?, ?, ?, ?)");

                foreach ($fields_to_update as $key => $new_val) {
                    $getOld->execute([$key]);
                    $old_val = $getOld->fetchColumn();
                    
                    if ($old_val === false) {
                        // Insert new setting
                        $upd->execute([':key' => $key, ':val' => $new_val, ':admin' => $admin_id]);
                        $logStmt->execute([$key, null, $new_val, $admin_id]);
                    } elseif ($old_val !== $new_val) {
                        // Update setting
                        $upd->execute([':key' => $key, ':val' => $new_val, ':admin' => $admin_id]);
                        $logStmt->execute([$key, $old_val, $new_val, $admin_id]);
                    }
                }
                
                $pdo->commit();
                
                // Refresh local cache
                foreach ($fields_to_update as $key => $val) {
                    $GLOBALS['system_settings'][$key] = $val;
                }
                
                log_activity("Update Settings", "Updated system configurations");
                $success = "System settings updated successfully!";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// 3. FETCH RECENT SETTINGS LOGS
$logs = [];
try {
    $stmt = $pdo->query("
        SELECT l.setting_name, l.old_value, l.new_value, u.full_name as updated_by_name, l.updated_at 
        FROM system_settings_logs l 
        LEFT JOIN users u ON l.updated_by = u.id 
        ORDER BY l.updated_at DESC LIMIT 20
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; }
        .alert-danger { background-color: rgba(224, 122, 95, 0.15); color: var(--danger); border: 1px solid rgba(224, 122, 95, 0.2); }
        .alert-success { background-color: rgba(82, 183, 136, 0.15); color: var(--medium-green); border: 1px solid rgba(82, 183, 136, 0.2); }
        
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .settings-col {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .settings-card {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 2rem;
            border: 1px solid rgba(27, 67, 50, 0.08);
            box-shadow: var(--box-shadow);
        }

        .settings-section-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--medium-green);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.25rem;
            border-bottom: 1.5px solid var(--light-gray);
            padding-bottom: 0.4rem;
        }

        /* Toggle switch */
        .switch-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--cream);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid rgba(27, 67, 50, 0.05);
            margin-bottom: 1.25rem;
        }

        .switch-label-details h4 {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark-green);
        }

        .switch-label-details p {
            font-size: 0.72rem;
            color: var(--gray);
            margin-top: 1px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--medium-green);
        }

        input:checked + .slider:before {
            transform: translateX(22px);
        }

        /* Checkbox lists */
        .checkbox-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            cursor: pointer;
            color: var(--dark);
        }

        .checkbox-item input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--medium-green);
        }

        .required-star {
            color: var(--danger);
        }

        .field-hint {
            font-size: 0.72rem;
            color: var(--gray);
            margin-top: 2px;
        }

        /* Log Table */
        .logs-table-wrap {
            width: 100%;
            overflow-x: auto;
            margin-top: 1rem;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            text-align: left;
        }

        .logs-table th, .logs-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--light-gray);
        }

        .logs-table th {
            background-color: var(--cream);
            color: var(--dark-green);
            font-weight: 700;
        }

        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="admin-wrapper">
        <!-- SIDEBAR NAVIGATION -->
        <?php include "sidebar.php"; ?>

        <!-- MAIN LAYOUT CONTENT -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-left">
                    <button class="menu-toggle" id="sidebarCollapse">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <div class="header-title">
                        <h2>Green Forensics — Super Administrator Dashboard</h2>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <div class="admin-content">
                <div class="page-header-wrap">
                    <div class="page-title">
                        <h1>System Configuration &amp; Settings</h1>
                        <p>Configure registration policies, login security thresholds, image uploads, AI support assistant, and monitor configuration changes.</p>
                    </div>
                </div>

                <!-- ALERTS -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="admin_settings.php">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="settings-grid">
                        <!-- Left Column -->
                        <div class="settings-col">
                            
                            <!-- Card 1: General Contact Settings -->
                            <div class="settings-card">
                                <div class="settings-section-title">General Platform Settings</div>
                                <div class="form-group">
                                    <label>System Name (Fixed)</label>
                                    <input type="text" class="form-control" value="Green Forensics Evaluating System" disabled style="background-color: #f4f6f0; cursor: not-allowed; font-weight: 600;">
                                    <p class="field-hint">The system name is fixed and cannot be edited.</p>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label for="system_contact_email">System Contact Email <span class="required-star">*</span></label>
                                    <input type="email" name="system_contact_email" id="system_contact_email" class="form-control"
                                        value="<?php echo htmlspecialchars(get_setting('system_contact_email', 'admin@greenforensics.edu.ph')); ?>" required>
                                    <p class="field-hint">System-wide email address visible to the public.</p>
                                </div>
                            </div>

                            <!-- Card 2: Registration Settings -->
                            <div class="settings-card">
                                <div class="settings-section-title">Registration Settings</div>
                                
                                <div class="switch-wrap">
                                    <div class="switch-label-details">
                                        <h4>Enable Public Registration</h4>
                                        <p>Allow new users to register via the registration page.</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="public_registration_enabled" value="1" <?php echo get_setting('public_registration_enabled', '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="switch-wrap">
                                    <div class="switch-label-details">
                                        <h4>Require Proof of Affiliation</h4>
                                        <p>Enforce uploading proof of affiliation during registration.</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="require_proof_affiliation" value="1" <?php echo get_setting('require_proof_affiliation', '0') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="switch-wrap">
                                    <div class="switch-label-details">
                                        <h4>Require Terms Agreement</h4>
                                        <p>Require agreeing to Terms of Use &amp; Privacy Policy.</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="require_terms_agreement" value="1" <?php echo get_setting('require_terms_agreement', '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="form-group" style="margin-top: 1rem;">
                                    <label>Allowed Registration Request Roles</label>
                                    <p class="field-hint" style="margin-bottom: 0.5rem;">Select which roles are visible to the public on the self-registration page:</p>
                                    <div class="checkbox-list">
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="allow_role_criminology_student" value="1" 
                                                <?php echo get_setting('allow_role_criminology_student', '1') === '1' ? 'checked' : ''; ?>>
                                            <span>Criminology Student</span>
                                        </label>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="allow_role_faculty_researcher" value="1"
                                                <?php echo get_setting('allow_role_faculty_researcher', '1') === '1' ? 'checked' : ''; ?>>
                                            <span>Faculty Researcher</span>
                                        </label>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="allow_role_alumni_police_partner" value="1"
                                                <?php echo get_setting('allow_role_alumni_police_partner', '1') === '1' ? 'checked' : ''; ?>>
                                            <span>Alumni / Police Partner</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 3: Login Security Settings -->
                            <div class="settings-card">
                                <div class="settings-section-title">Login Security Settings</div>
                                <div class="form-group">
                                    <label for="max_failed_login_attempts">Max Failed Login Attempts <span class="required-star">*</span></label>
                                    <input type="number" name="max_failed_login_attempts" id="max_failed_login_attempts" class="form-control"
                                        value="<?php echo (int)get_setting('max_failed_login_attempts', 5); ?>" min="1" required>
                                    <p class="field-hint">Temporarily lock user account after this number of failed logins.</p>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label for="login_lockout_minutes">Lockout Duration (Minutes) <span class="required-star">*</span></label>
                                    <input type="number" name="login_lockout_minutes" id="login_lockout_minutes" class="form-control"
                                        value="<?php echo (int)get_setting('login_lockout_minutes', 15); ?>" min="1" required>
                                    <p class="field-hint">The amount of minutes account will remain locked.</p>
                                </div>
                            </div>

                        </div>

                        <!-- Right Column -->
                        <div class="settings-col">
                            
                            <!-- Card 4: Upload Settings -->
                            <div class="settings-card">
                                <div class="settings-section-title">Upload Settings</div>
                                <div class="form-group">
                                    <label for="max_fingerprint_upload_mb">Max Fingerprint Upload Size (MB) <span class="required-star">*</span></label>
                                    <input type="number" name="max_fingerprint_upload_mb" id="max_fingerprint_upload_mb" class="form-control"
                                        value="<?php echo (int)get_setting('max_fingerprint_upload_mb', 5); ?>" min="1" required>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label for="max_proof_upload_mb">Max Proof of Affiliation Upload Size (MB) <span class="required-star">*</span></label>
                                    <input type="number" name="max_proof_upload_mb" id="max_proof_upload_mb" class="form-control"
                                        value="<?php echo (int)get_setting('max_proof_upload_mb', 5); ?>" min="1" required>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label for="allowed_image_types">Allowed Image Types (comma-separated) <span class="required-star">*</span></label>
                                    <input type="text" name="allowed_image_types" id="allowed_image_types" class="form-control"
                                        value="<?php echo htmlspecialchars(get_setting('allowed_image_types', 'jpg,jpeg,png,webp')); ?>" required>
                                    <p class="field-hint">Extensions allowed for fingerprint uploads.</p>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label for="allowed_proof_types">Allowed Proof File Types (comma-separated) <span class="required-star">*</span></label>
                                    <input type="text" name="allowed_proof_types" id="allowed_proof_types" class="form-control"
                                        value="<?php echo htmlspecialchars(get_setting('allowed_proof_types', 'jpg,jpeg,png,pdf')); ?>" required>
                                    <p class="field-hint">Extensions allowed for affiliation proof documents.</p>
                                </div>
                            </div>

                            <!-- Card 5: Support Assistant Settings -->
                            <div class="settings-card">
                                <div class="settings-section-title">Support Assistant Settings</div>
                                <div class="switch-wrap">
                                    <div class="switch-label-details">
                                        <h4>Enable Support Assistant</h4>
                                        <p>Enable Gemini AI Support Assistant widget on dashboard pages.</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="support_assistant_enabled" value="1" <?php echo get_setting('support_assistant_enabled', '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label>Gemini Model</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(env('GEMINI_MODEL', 'gemini-2.5-flash')); ?>" disabled style="background-color: #f4f6f0; cursor: not-allowed;">
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label style="display: block; margin-bottom: 0.35rem;">Gemini API Status</label>
                                    <div id="gemini-status-badge" style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: #555; background: var(--cream); padding: 6px 12px; border-radius: 6px; border: 1px solid rgba(27,67,50,0.05);">
                                        <span class="status-dot" style="width: 8px; height: 8px; border-radius: 50%; background-color: #ccc; display: inline-block;"></span>
                                        <span class="status-text">Checking...</span>
                                    </div>
                                    <button type="button" id="btn-test-gemini" class="btn" style="margin-top: 10px; display: block; padding: 0.5rem 1rem; font-size: 0.8rem; font-weight: 700; border: 1.5px solid var(--mint-green); background-color: #fff; color: var(--dark-green); border-radius: 6px; cursor: pointer;">
                                        Test Gemini Connection
                                    </button>
                                </div>
                            </div>

                            <!-- Card 6: Email Notification Settings -->
                            <div class="settings-card">
                                <div class="settings-section-title">Email Notification Settings</div>
                                <div class="form-group">
                                    <label for="notification_email">Notification Email <span class="required-star">*</span></label>
                                    <input type="email" name="notification_email" id="notification_email" class="form-control"
                                        value="<?php echo htmlspecialchars(get_setting('notification_email', 'admin@greenforensics.edu.ph')); ?>" required>
                                    <p class="field-hint">Email address where admin notifications are sent.</p>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label style="display: block; margin-bottom: 0.35rem;">SMTP Status</label>
                                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--danger); background: #fdf5f5; border: 1px solid rgba(224,122,95,0.15); padding: 8px 12px; border-radius: 6px; display: inline-block;">
                                        Email notification is not configured yet.
                                    </div>
                                    <button type="button" id="btn-test-email" class="btn" style="margin-top: 10px; display: block; padding: 0.5rem 1rem; font-size: 0.8rem; font-weight: 700; border: 1.5px solid var(--mint-green); background-color: #fff; color: var(--dark-green); border-radius: 6px; cursor: pointer;">
                                        Test Email
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Save Action Card (Full Width) -->
                    <div class="settings-card" style="margin-top: 1.5rem; text-align: center;">
                        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; max-width: 400px; margin: 0 auto; display: flex; align-items: center; gap: 8px;">
                            <span>Save System Configurations</span>
                        </button>
                    </div>
                </form>

                <!-- Card 7: Settings Activity Log -->
                <div class="settings-card" style="margin-top: 1.5rem;">
                    <div class="settings-section-title">Settings Activity Log</div>
                    <p style="font-size: 0.78rem; color: var(--gray); margin-bottom: 0.5rem;">Recent administrative modifications to the system settings database.</p>
                    
                    <div class="logs-table-wrap">
                        <?php if (empty($logs)): ?>
                            <p style="font-size: 0.85rem; color: var(--gray); text-align: center; padding: 1.5rem 0;">No settings logs found.</p>
                        <?php else: ?>
                            <table class="logs-table">
                                <thead>
                                    <tr>
                                        <th>Setting Name</th>
                                        <th>Old Value</th>
                                        <th>New Value</th>
                                        <th>Updated By</th>
                                        <th>Date Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--dark-green);"><?php echo htmlspecialchars($log['setting_name']); ?></td>
                                            <td style="color: #666; font-style: italic; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo $log['old_value'] === null ? '<span style="color:#aaa;">[NULL]</span>' : htmlspecialchars($log['old_value']); ?>
                                            </td>
                                            <td style="font-weight: 500; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo htmlspecialchars($log['new_value']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['updated_by_name'] ?? 'System / Seeder'); ?></td>
                                            <td><?php echo date('M d, Y H:i:s', strtotime($log['updated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- JS Handling -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Sidebar collapse logic
            const sidebar = document.getElementById("sidebar");
            const toggleBtn = document.getElementById("sidebarCollapse");

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener("click", (e) => {
                    e.stopPropagation();
                    sidebar.classList.toggle("active");
                });

                document.addEventListener("click", (e) => {
                    if (window.innerWidth <= 768 && sidebar.classList.contains("active")) {
                        if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                            sidebar.classList.remove("active");
                        }
                    }
                });
            }

            // Gemini API Status Check
            const checkGeminiStatus = () => {
                const badge = document.getElementById("gemini-status-badge");
                if (!badge) return;
                const dot = badge.querySelector(".status-dot");
                const text = badge.querySelector(".status-text");
                
                dot.style.backgroundColor = "#ffcc00"; 
                text.textContent = "Checking...";
                
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                const formData = new FormData();
                formData.append("action", "test_gemini");
                formData.append("csrf_token", csrfToken);
                
                fetch("admin_settings.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        dot.style.backgroundColor = "#52b788"; 
                        text.textContent = "Connected";
                    } else {
                        dot.style.backgroundColor = "#e07a5f"; 
                        text.textContent = "Not Connected";
                    }
                })
                .catch(err => {
                    dot.style.backgroundColor = "#e07a5f";
                    text.textContent = "Not Connected";
                });
            };
            
            checkGeminiStatus();
            
            // Test Gemini button action
            const testGeminiBtn = document.getElementById("btn-test-gemini");
            if (testGeminiBtn) {
                testGeminiBtn.addEventListener("click", () => {
                    const originalText = testGeminiBtn.textContent;
                    testGeminiBtn.textContent = "Testing...";
                    testGeminiBtn.disabled = true;
                    
                    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                    const formData = new FormData();
                    formData.append("action", "test_gemini");
                    formData.append("csrf_token", csrfToken);
                    
                    fetch("admin_settings.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        alert(data.message);
                        checkGeminiStatus();
                        testGeminiBtn.textContent = originalText;
                        testGeminiBtn.disabled = false;
                    })
                    .catch(err => {
                        alert("Gemini API is not connected. Please check environment variables.");
                        checkGeminiStatus();
                        testGeminiBtn.textContent = originalText;
                        testGeminiBtn.disabled = false;
                    });
                });
            }
            
            // Test Email button action
            const testEmailBtn = document.getElementById("btn-test-email");
            if (testEmailBtn) {
                testEmailBtn.addEventListener("click", () => {
                    const originalText = testEmailBtn.textContent;
                    testEmailBtn.textContent = "Testing...";
                    testEmailBtn.disabled = true;
                    
                    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                    const formData = new FormData();
                    formData.append("action", "test_email");
                    formData.append("csrf_token", csrfToken);
                    
                    fetch("admin_settings.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        alert(data.message);
                        testEmailBtn.textContent = originalText;
                        testEmailBtn.disabled = false;
                    })
                    .catch(err => {
                        alert("Email notification is not configured yet.");
                        testEmailBtn.textContent = originalText;
                        testEmailBtn.disabled = false;
                    });
                });
            }
        });
    </script>
</body>

</html>
