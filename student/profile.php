<?php
// student/profile.php — Criminology Student Account Profile & SMS Settings
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page = 'profile';
$student_id  = $_SESSION['user_id'] ?? 0;

$success = '';
$error   = '';

// Fetch current user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("User account not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle Form Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name     = trim($_POST['first_name'] ?? '');
    $middle_name    = trim($_POST['middle_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $id_number      = trim($_POST['id_number'] ?? '');
    $department     = trim($_POST['department'] ?? '');
    $new_password   = trim($_POST['new_password'] ?? '');

    if (empty($first_name) || empty($last_name)) {
        $error = "First name and last name are required.";
    } else {
        // Validate & format phone number if provided
        $formatted_phone = null;
        if (!empty($contact_number)) {
            if (file_exists(dirname(__DIR__) . '/includes/sms_service.php')) {
                require_once dirname(__DIR__) . '/includes/sms_service.php';
                $formatted_phone = format_ph_phone_number($contact_number);
                if (!$formatted_phone) {
                    $error = "Invalid Philippine mobile phone number format (e.g. 09171234567).";
                }
            } else {
                $formatted_phone = $contact_number;
            }
        }

        if (empty($error)) {
            try {
                $full_name = trim("$first_name " . ($middle_name ? "$middle_name " : "") . $last_name);

                if (!empty($new_password)) {
                    if (strlen($new_password) < 6) {
                        $error = "New password must be at least 6 characters long.";
                    } else {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $upd_stmt = $pdo->prepare("
                            UPDATE users 
                            SET first_name = ?, middle_name = ?, last_name = ?, full_name = ?, contact_number = ?, id_number = ?, department = ?, password = ?
                            WHERE id = ?
                        ");
                        $upd_stmt->execute([$first_name, $middle_name, $last_name, $full_name, $formatted_phone, $id_number, $department, $hashed, $student_id]);
                    }
                } else {
                    $upd_stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, middle_name = ?, last_name = ?, full_name = ?, contact_number = ?, id_number = ?, department = ?
                        WHERE id = ?
                    ");
                    $upd_stmt->execute([$first_name, $middle_name, $last_name, $full_name, $formatted_phone, $id_number, $department, $student_id]);
                }

                if (empty($error)) {
                    $_SESSION['user_name'] = $full_name;
                    $success = "Profile and SMS contact preferences updated successfully!";

                    // Refresh user data
                    $stmt->execute([$student_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $error = "Failed to update profile: " . $e->getMessage();
            }
        }
    }
}

$has_phone = !empty($user['contact_number']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile & Settings — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.1">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .profile-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .profile-header-block {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 2rem;
        }

        .avatar-circle-lg {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--medium-green, #2d6a4f), #1b4332);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(45, 106, 79, 0.25);
        }

        .profile-header-info h2 {
            margin: 0 0 4px 0;
            font-size: 1.4rem;
            color: var(--dark-green, #1b4332);
        }

        .profile-header-info p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .sms-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-top: 8px;
        }

        .sms-active {
            background: rgba(82, 183, 136, 0.15);
            color: #1e7e34;
            border: 1px solid rgba(82, 183, 136, 0.3);
        }

        .sms-inactive {
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.25);
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .form-group-custom {
            margin-bottom: 1.25rem;
        }

        .form-group-custom label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark-green, #1b4332);
            margin-bottom: 6px;
        }

        .form-control-custom {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            font-size: 0.92rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .form-control-custom:focus {
            border-color: var(--medium-green, #2d6a4f);
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.15);
        }

        .form-control-custom[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
            color: #6c757d;
        }

        .phone-input-wrap {
            position: relative;
        }

        .phone-hint {
            font-size: 0.78rem;
            color: #6c757d;
            margin-top: 4px;
        }

        .alert-custom {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .alert-success-custom {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger-custom {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="student-app-layout">

        <!-- SIDEBAR -->
        <?php require_once '_sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="student-main">
            <header class="student-header">
                <div class="header-left">
                    <button class="menu-toggle" id="sidebarCollapse" aria-label="Toggle sidebar">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                    </button>
                    <div class="header-title">
                        <h2>My Profile &amp; Account Settings</h2>
                    </div>
                </div>
                <div class="header-right" style="display:flex;align-items:center;gap:10px;">
                    <div class="header-role-chip">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                        </svg>
                        Criminology Student
                    </div>
                    <a href="profile.php" class="btn-profile-top" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#2d6a4f;border:1.5px solid #2d6a4f;border-radius:20px;color:#ffffff;text-decoration:none;font-weight:700;font-size:0.83rem;box-shadow:0 2px 6px rgba(0,0,0,0.06);">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        <span>SMS &amp; Account Profile</span>
                    </a>
                </div>
            </header>

            <div class="student-content">
                <div class="profile-container">

                    <?php if ($success): ?>
                        <div class="alert-custom alert-success-custom">
                            ✓ <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert-custom alert-danger-custom">
                            ⚠ <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="update_profile">

                        <!-- CARD 1: PROFILE OVERVIEW -->
                        <div class="profile-card">
                            <div class="profile-header-block">
                                <div class="avatar-circle-lg">
                                    <?= htmlspecialchars(strtoupper(substr($user['first_name'] ?? 'S', 0, 1) . substr($user['last_name'] ?? 'T', 0, 1))) ?>
                                </div>
                                <div class="profile-header-info">
                                    <h2><?= htmlspecialchars($user['full_name'] ?? 'Criminology Student') ?></h2>
                                    <p><?= htmlspecialchars($user['email']) ?> • Criminology Student</p>
                                    
                                    <?php if ($has_phone): ?>
                                        <div class="sms-status-pill sms-active">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                <polyline points="22 4 12 14.01 9 11.01"/>
                                            </svg>
                                            SMS Notifications Enabled (<?= htmlspecialchars($user['contact_number']) ?>)
                                        </div>
                                    <?php else: ?>
                                        <div class="sms-status-pill sms-inactive">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="12" y1="8" x2="12" y2="12"/>
                                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                                            </svg>
                                            SMS Disabled (Missing Phone Number — Enter below to enable)
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group-custom">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control-custom"
                                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                </div>

                                <div class="form-group-custom">
                                    <label for="middle_name">Middle Name</label>
                                    <input type="text" id="middle_name" name="middle_name" class="form-control-custom"
                                           value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group-custom">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control-custom"
                                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                </div>

                                <div class="form-group-custom">
                                    <label for="contact_number">Mobile / Contact Number (For SMS Alerts) *</label>
                                    <div class="phone-input-wrap">
                                        <input type="text" id="contact_number" name="contact_number" class="form-control-custom"
                                               placeholder="09171234567" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
                                    </div>
                                    <div class="phone-hint">Enter your 11-digit PH mobile number to receive trial grade notifications.</div>
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group-custom">
                                    <label for="id_number">Student ID / Badge Number</label>
                                    <input type="text" id="id_number" name="id_number" class="form-control-custom"
                                           value="<?= htmlspecialchars($user['id_number'] ?? '') ?>" placeholder="e.g. 2026-1049">
                                </div>

                                <div class="form-group-custom">
                                    <label for="department">Department / Course</label>
                                    <input type="text" id="department" name="department" class="form-control-custom"
                                           value="<?= htmlspecialchars($user['department'] ?? 'College of Criminology') ?>">
                                </div>
                            </div>

                            <div class="form-group-custom">
                                <label>Email Address</label>
                                <input type="email" class="form-control-custom" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                <div class="phone-hint">Email address cannot be modified directly. Contact Admin for changes.</div>
                            </div>
                        </div>

                        <!-- CARD 2: PASSWORD CHANGE (OPTIONAL) -->
                        <div class="profile-card">
                            <h3 style="font-size:1.1rem;color:var(--dark-green,#1b4332);margin:0 0 1rem 0;">Security & Password</h3>
                            
                            <div class="form-group-custom">
                                <label for="new_password">New Password (Leave blank to keep current password)</label>
                                <input type="password" id="new_password" name="new_password" class="form-control-custom"
                                       placeholder="Enter at least 6 characters">
                            </div>
                        </div>

                        <!-- SUBMIT BUTTON -->
                        <div style="text-align:right;">
                            <button type="submit" class="btn btn-primary" style="padding:12px 28px;font-size:0.95rem;background:var(--medium-green,#2d6a4f);border:none;border-radius:8px;color:#fff;cursor:pointer;font-weight:700;">
                                Save Profile &amp; Preferences
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </main>
    </div>

    <?php require_once '_sidebar_js.php'; ?>
</body>

</html>
