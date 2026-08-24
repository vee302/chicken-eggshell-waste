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

// Fetch Recent Login Security Logs for the Student
$login_logs = [];
try {
    $log_query = $pdo->prepare("SELECT ip_address, device_type, status, created_at FROM user_login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $log_query->execute([$student_id]);
    $login_logs = $log_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $login_logs = [];
}

// Fallback: If no logs exist yet (initial run), generate active session entry
if (empty($login_logs)) {
    $current_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (strpos($current_ip, ',') !== false) {
        $current_ip = trim(explode(',', $current_ip)[0]);
    }
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device_type = (preg_match('/(android|bb\d+|meego).+mobile|avail|blackberry|iphone|ipad|ipod|palm|phone|tablet|windows phone/i', $user_agent)) ? 'Mobile' : 'Desktop';
    $login_logs = [
        [
            'ip_address' => $current_ip,
            'device_type' => $device_type,
            'status' => 'success',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
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
    $remove_avatar  = trim($_POST['remove_avatar'] ?? '0');

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
                $profile_pic_filename = $user['profile_picture'] ?? null;

                // Process Avatar Removal if requested
                if ($remove_avatar === '1' && !empty($profile_pic_filename)) {
                    $old_file = dirname(__DIR__) . '/uploads/avatars/' . $profile_pic_filename;
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                    $profile_pic_filename = null;
                }

                // Process New Avatar Upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp  = $_FILES['profile_picture']['tmp_name'];
                    $file_name = $_FILES['profile_picture']['name'];
                    $file_size = $_FILES['profile_picture']['size'];

                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

                    if (!in_array($ext, $allowed_exts)) {
                        $error = "Invalid image format. Allowed formats: JPG, PNG, WEBP.";
                    } elseif ($file_size > 5 * 1024 * 1024) {
                        $error = "Profile picture size exceeds 5MB limit.";
                    } else {
                        $upload_dir = dirname(__DIR__) . '/uploads/avatars/';
                        if (!is_dir($upload_dir)) {
                            @mkdir($upload_dir, 0777, true);
                        }

                        // Remove previous picture
                        if (!empty($profile_pic_filename)) {
                            $old_file = $upload_dir . $profile_pic_filename;
                            if (file_exists($old_file)) {
                                @unlink($old_file);
                            }
                        }

                        $new_filename = 'avatar_' . $student_id . '_' . time() . '.' . $ext;
                        $target_file  = $upload_dir . $new_filename;

                        if (move_uploaded_file($file_tmp, $target_file)) {
                            @chmod($target_file, 0777);
                            $profile_pic_filename = $new_filename;
                        } else {
                            $error = "Failed to save profile picture to server.";
                        }
                    }
                }

                if (empty($error)) {
                    if (!empty($new_password)) {
                        if (strlen($new_password) < 6) {
                            $error = "New password must be at least 6 characters long.";
                        } else {
                            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                            $upd_stmt = $pdo->prepare("
                                UPDATE users 
                                SET first_name = ?, middle_name = ?, last_name = ?, full_name = ?, contact_number = ?, id_number = ?, department = ?, profile_picture = ?, password = ?
                                WHERE id = ?
                            ");
                            $upd_stmt->execute([$first_name, $middle_name, $last_name, $full_name, $formatted_phone, $id_number, $department, $profile_pic_filename, $hashed, $student_id]);
                        }
                    } else {
                        $upd_stmt = $pdo->prepare("
                            UPDATE users 
                            SET first_name = ?, middle_name = ?, last_name = ?, full_name = ?, contact_number = ?, id_number = ?, department = ?, profile_picture = ?
                            WHERE id = ?
                        ");
                        $upd_stmt->execute([$first_name, $middle_name, $last_name, $full_name, $formatted_phone, $id_number, $department, $profile_pic_filename, $student_id]);
                    }

                    if (empty($error)) {
                        $_SESSION['user_name'] = $full_name;
                        $success = "Profile details and avatar updated successfully!";

                        // Refresh user data
                        $stmt->execute([$student_id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
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

                    <form method="POST" action="profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">

                        <!-- CARD 1: PROFILE OVERVIEW -->
                        <div class="profile-card">
                            <div class="profile-header-block" style="flex-wrap:wrap;">
                                <?php 
                                $avatar_file = $user['profile_picture'] ?? '';
                                $avatar_exists = !empty($avatar_file) && file_exists(dirname(__DIR__) . '/uploads/avatars/' . $avatar_file);
                                $avatar_src = $avatar_exists ? '../uploads/avatars/' . htmlspecialchars($avatar_file) : '';
                                ?>
                                <div class="avatar-circle-lg" style="overflow:hidden;position:relative;flex-shrink:0;">
                                    <img id="avatar-img-preview" src="<?= $avatar_src ?>" alt="Profile Avatar" style="width:100%;height:100%;object-fit:cover;display:<?= $avatar_exists ? 'block' : 'none' ?>;">
                                    <span id="avatar-initials-span" style="display:<?= $avatar_exists ? 'none' : 'block' ?>;">
                                        <?= htmlspecialchars(strtoupper(substr($user['first_name'] ?? 'S', 0, 1) . substr($user['last_name'] ?? 'T', 0, 1))) ?>
                                    </span>
                                </div>
                                <div class="profile-header-info" style="flex:1;min-width:240px;">
                                    <h2><?= htmlspecialchars($user['full_name'] ?? 'Criminology Student') ?></h2>
                                    <p><?= htmlspecialchars($user['email']) ?> • Criminology Student</p>
                                    
                                    <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <label for="profile_picture_input" style="padding:7px 14px;background:#2d6a4f;color:#ffffff;border-radius:6px;font-size:0.83rem;font-weight:700;cursor:pointer;display:inline-block;">
                                            Upload Profile Picture
                                        </label>
                                        <input type="file" id="profile_picture_input" name="profile_picture" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleAvatarPreview(this)">
                                        
                                        <input type="hidden" name="remove_avatar" id="remove_avatar_input" value="0">
                                        <button type="button" id="btn-remove-avatar" onclick="removeAvatarImage()" style="padding:7px 14px;background:#f8f9fa;color:#dc3545;border:1px solid #dc3545;border-radius:6px;font-size:0.83rem;font-weight:700;cursor:pointer;display:<?= $avatar_exists ? 'inline-block' : 'none' ?>;">
                                            Remove Photo
                                        </button>
                                    </div>
                                    <div style="font-size:0.75rem;color:#6c757d;margin-top:4px;">Allowed formats: JPG, PNG, WEBP (Max 5MB). Live preview enabled.</div>
                                </div>
                            </div>
                                    
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
                            <h3 style="font-size:1.1rem;color:var(--dark-green,#1b4332);margin:0 0 1rem 0;">Security &amp; Password</h3>
                            
                            <div class="form-group-custom">
                                <label for="new_password">New Password (Leave blank to keep current password)</label>
                                <input type="password" id="new_password" name="new_password" class="form-control-custom"
                                       placeholder="Enter at least 6 characters">
                            </div>
                        </div>

                        <!-- CARD 3: RECENT LOGIN HISTORY -->
                        <div class="profile-card">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:10px;">
                                <h3 style="font-size:1.1rem;color:var(--dark-green,#1b4332);margin:0;">
                                    Recent Security &amp; Login History
                                </h3>
                                <span style="font-size:0.78rem;color:#2d6a4f;background:rgba(45,106,79,0.1);padding:4px 12px;border-radius:12px;border:1px solid rgba(45,106,79,0.2);font-weight:700;">
                                    Last 3 Active Logins
                                </span>
                            </div>

                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:0.88rem;text-align:left;">
                                    <thead>
                                        <tr style="background:#f8f9fa;border-bottom:2px solid #e9ecef;color:var(--dark-green,#1b4332);">
                                            <th style="padding:10px 12px;font-weight:700;">Date &amp; Time</th>
                                            <th style="padding:10px 12px;font-weight:700;">IP Address</th>
                                            <th style="padding:10px 12px;font-weight:700;">Device</th>
                                            <th style="padding:10px 12px;font-weight:700;text-align:right;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($login_logs, 0, 3) as $log): ?>
                                            <tr style="border-bottom:1px solid #f1f3f5;">
                                                <td style="padding:12px;color:#212529;font-weight:600;">
                                                    <?= date('M d, Y • h:i A', strtotime($log['created_at'])) ?>
                                                </td>
                                                <td style="padding:12px;font-family:monospace;color:#495057;font-weight:600;">
                                                    <?= htmlspecialchars($log['ip_address']) ?>
                                                </td>
                                                <td style="padding:12px;color:#495057;font-weight:600;">
                                                    <?php if (strtolower($log['device_type']) === 'mobile'): ?>
                                                        Mobile Device
                                                    <?php else: ?>
                                                        Desktop PC
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding:12px;text-align:right;">
                                                    <span style="display:inline-block;padding:4px 10px;border-radius:12px;font-size:0.75rem;font-weight:700;background:rgba(40,167,69,0.12);color:#1e7e34;border:1px solid rgba(40,167,69,0.25);">
                                                        Success
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
    <script>
    function handleAvatarPreview(input) {
        if (input.files && input.files[0]) {
            var file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                alert('File size exceeds 5MB limit.');
                input.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatar-img-preview').src = e.target.result;
                document.getElementById('avatar-img-preview').style.display = 'block';
                document.getElementById('avatar-initials-span').style.display = 'none';
                document.getElementById('btn-remove-avatar').style.display = 'inline-block';
                document.getElementById('remove_avatar_input').value = '0';
            };
            reader.readAsDataURL(file);
        }
    }

    function removeAvatarImage() {
        document.getElementById('avatar-img-preview').src = '';
        document.getElementById('avatar-img-preview').style.display = 'none';
        document.getElementById('avatar-initials-span').style.display = 'block';
        document.getElementById('btn-remove-avatar').style.display = 'none';
        document.getElementById('profile_picture_input').value = '';
        document.getElementById('remove_avatar_input').value = '1';
    }
    </script>
</body>

</html>
