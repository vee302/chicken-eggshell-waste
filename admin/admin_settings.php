<?php
// admin/admin_settings.php - System Settings Panel
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

$error = "";
$success = "";

// 1. PROCESS FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "save_settings") {
    $name = trim($_POST["system_name"] ?? "");
    $email = trim($_POST["system_email"] ?? "");
    $roles_arr = $_POST["allowed_roles"] ?? [];
    $roles = implode(",", $roles_arr);
    $maintenance = isset($_POST["maintenance_mode"]) ? "1" : "0";
    $max_attempts = intval($_POST["max_login_attempts"] ?? 5);
    $lockout = intval($_POST["lockout_time"] ?? 15);

    if (empty($name) || empty($email)) {
        $error = "System Name and System Contact Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid system contact email address format.";
    } else {
        try {
            $upd = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            
            $upd->execute([':key' => 'system_name', ':val' => $name]);
            $upd->execute([':key' => 'system_email', ':val' => $email]);
            $upd->execute([':key' => 'allowed_registration_roles', ':val' => $roles]);
            $upd->execute([':key' => 'maintenance_mode', ':val' => $maintenance]);
            $upd->execute([':key' => 'max_login_attempts', ':val' => $max_attempts]);
            $upd->execute([':key' => 'lockout_time', ':val' => $lockout]);

            log_activity("Update Settings", "Updated system configurations (Maintenance Mode: " . ($maintenance === "1" ? "ON" : "OFF") . ")");
            $success = "System configuration updated successfully.";
        } catch (PDOException $e) {
            $error = "Unable to update system configuration. Please try again.";
        }
    }
}

// 2. READ ALL CURRENT SETTINGS KEYS
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {}

// Extract settings with default fallbacks
$system_name = $settings['system_name'] ?? 'Green Forensics Evaluating System';
$system_email = $settings['system_email'] ?? 'admin@greenforensics.edu.ph';
$allowed_registration_roles = explode(',', $settings['allowed_registration_roles'] ?? 'criminology_student,faculty_researcher,alumni_police_partner');
$maintenance_mode = $settings['maintenance_mode'] ?? '0';
$max_login_attempts = $settings['max_login_attempts'] ?? '5';
$lockout_time = $settings['lockout_time'] ?? '15';
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
            grid-template-columns: 2fr 1fr;
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
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--medium-green);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
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
            margin-bottom: 1.5rem;
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
            margin-bottom: 1.5rem;
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
                        <p>Configure academic parameters, registration authentication rules, login security thresholds, and toggle maintenance state.</p>
                    </div>
                </div>

                <!-- ALERTS -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="settings-grid">
                    <!-- Settings Form Card -->
                    <div class="settings-card">
                        <form method="POST" action="admin_settings.php">
                            <input type="hidden" name="action" value="save_settings">

                            <!-- Section 1: General configuration -->
                            <div class="settings-section-title">General Platform Config</div>
                            <div class="form-group">
                                <label for="system_name">System Name (Acronym &amp; Detail)</label>
                                <input type="text" name="system_name" id="system_name" class="form-control"
                                    value="<?php echo htmlspecialchars($system_name); ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 2rem;">
                                <label for="system_email">System Contact / Notification Email</label>
                                <input type="email" name="system_email" id="system_email" class="form-control"
                                    value="<?php echo htmlspecialchars($system_email); ?>" required>
                            </div>

                            <!-- Section 2: Registration policies -->
                            <div class="settings-section-title">Registration Access Policies</div>
                            <div class="form-group">
                                <label>Allowed Registration Request Roles</label>
                                <p style="font-size: 0.75rem; color: var(--gray); margin-bottom: 0.5rem; font-weight:500;">Select which roles are visible to the public on the self-registration page:</p>
                                <div class="checkbox-list">
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="allowed_roles[]" value="criminology_student" 
                                            <?php echo in_array('criminology_student', $allowed_registration_roles) ? 'checked' : ''; ?>>
                                        <span>Criminology Student Portal Access</span>
                                    </label>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="allowed_roles[]" value="faculty_researcher"
                                            <?php echo in_array('faculty_researcher', $allowed_registration_roles) ? 'checked' : ''; ?>>
                                        <span>Faculty Researcher Validator Portal Access</span>
                                    </label>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="allowed_roles[]" value="alumni_police_partner"
                                            <?php echo in_array('alumni_police_partner', $allowed_registration_roles) ? 'checked' : ''; ?>>
                                        <span>Alumni / Police Partner Feedbacks Scope</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Section 3: Login security rules -->
                            <div class="settings-section-title" style="margin-top:2rem;">Login Auditing &amp; Security</div>
                            <div class="form-group">
                                <label for="max_login_attempts">Max Failed Login Attempts</label>
                                <input type="number" name="max_login_attempts" id="max_login_attempts" class="form-control"
                                    value="<?php echo (int)$max_login_attempts; ?>" min="3" max="10" required>
                                <p style="font-size:0.72rem; color:var(--gray); margin-top:2px;">Lock user account temporarily after this number of failed logins.</p>
                            </div>
                            <div class="form-group" style="margin-bottom: 2rem;">
                                <label for="lockout_time">Lockout Duration (Minutes)</label>
                                <input type="number" name="lockout_time" id="lockout_time" class="form-control"
                                    value="<?php echo (int)$lockout_time; ?>" min="5" max="60" required>
                            </div>

                            <!-- Section 4: Maintenance mode -->
                            <div class="settings-section-title">System Offline / Maintenance</div>
                            <div class="switch-wrap">
                                <div class="switch-label-details">
                                    <h4>Toggle Maintenance State</h4>
                                    <p>Offline mode redirects students/faculty researchers to a temporary offline card during schema structure edits.</p>
                                    <p style="font-size: 0.78rem; color: var(--gray); margin-top: 4px;">When enabled, student, faculty, and partner portals will be temporarily redirected to the maintenance page.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="maintenance_mode" value="1" <?php echo ($maintenance_mode === "1") ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem;">
                                <span>Save System Configurations</span>
                            </button>
                        </form>
                    </div>

                    <!-- Panel Info Card -->
                    <div style="display:flex; flex-direction:column; gap:1.25rem;">
                        <div class="dashboard-card">
                            <div class="card-title-wrap">
                                <h3>
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                    <span>Settings Guidelines</span>
                                </h3>
                            </div>
                            <p style="font-size:0.8rem; color: var(--gray); line-height:1.5; margin-bottom:0.75rem;">
                                <strong>System Name:</strong> Updates header display brand strings across student, faculty, and administrator dashboard views.
                            </p>
                            <p style="font-size:0.8rem; color: var(--gray); line-height:1.5;">
                                <strong>Access Policies:</strong> Disabling roles immediately hides those registration categories on the public self-registration interface.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JS Toggles -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
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
        });
    </script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>

</html>
