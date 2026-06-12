<?php
// partner/profile.php — Alumni / Police Partner User Profile Settings
require_once '../config.php';
require_once 'auth.php';
check_partner_auth();

$active_page = 'profile';
$partner_name = $_SESSION['user_name'] ?? 'Partner';
$partner_id = $_SESSION['user_id'] ?? 0;

$success = '';
$error = '';

// Fetch fresh user data using prepared statement
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$partner_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("User record not found.");
    }
} catch (PDOException $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Handle Update Profile Details & Password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $contact_number = trim($_POST['contact_number'] ?? '');
        $affiliation = trim($_POST['affiliation'] ?? '');
        $department = trim($_POST['department'] ?? '');

        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET contact_number = ?, affiliation = ?, department = ? 
                WHERE id = ?
            ");
            $stmt->execute([$contact_number, $affiliation, $department, $partner_id]);
            
            $success = "Profile details updated successfully.";
            
            // Reload user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$partner_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif ($action === 'change_password') {
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        if (empty($current_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
            $error = "All password fields are required.";
        } elseif ($new_pwd !== $confirm_pwd) {
            $error = "New password and confirmation do not match.";
        } elseif (strlen($new_pwd) < 6) {
            $error = "New password must be at least 6 characters long.";
        } else {
            // Verify current password
            if (password_verify($current_pwd, $user['password'])) {
                try {
                    $new_hashed = password_hash($new_pwd, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$new_hashed, $partner_id]);
                    $success = "Password changed successfully.";
                } catch (PDOException $e) {
                    $error = "Error updating password: " . $e->getMessage();
                }
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .notice-banner {
            background-color: rgba(45, 106, 79, 0.08);
            border-left: 4px solid var(--medium-green);
            color: var(--dark-green);
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }
        .form-group-full {
            grid-column: span 2;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.88rem;
            background: #fff;
            color: #212529;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #2d6a4f;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.12);
        }
        .form-control:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1b4332;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
            margin-bottom: 0.4rem;
        }
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background-color: rgba(82, 183, 136, 0.15);
            color: #2d6a4f;
            border: 1px solid rgba(82, 183, 136, 0.25);
        }
        .alert-danger {
            background-color: rgba(224, 122, 95, 0.15);
            color: #d90429;
            border: 1px solid rgba(224, 122, 95, 0.25);
        }
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.88rem;
        }
        .info-label {
            font-weight: 600;
            color: var(--dark-green);
        }
        .info-value {
            color: #555;
        }
    </style>
</head>
<body>

<div class="student-wrapper">
    <!-- Mobile overlay -->
    <div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;transition:opacity .3s;"
         onclick="this.style.display='none';document.getElementById('sidebar').classList.remove('active')"></div>

    <!-- SIDEBAR -->
    <?php require_once '_sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="student-main">
        <header class="student-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6"  x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <div class="header-title">
                    <h2>Alumni / Police Partner Portal</h2>
                </div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>My Profile</h1>
                    <p>Manage your account settings, contact details, and password security.</p>
                </div>
            </div>

            <!-- Notice Banner -->
            <div class="notice-banner">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>Only faculty-approved records are visible in this portal.</span>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- LEFT COLUMN: INFO & ACCOUNT DETAILS -->
                <div>
                    <div class="dashboard-card" style="margin-bottom: 2rem;">
                        <div class="card-title-wrap">
                            <h3>
                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                Account Information
                            </h3>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?= htmlspecialchars($user['full_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email Address</span>
                            <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Access Role</span>
                            <span class="info-value">Alumni / Police Partner</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Status</span>
                            <span class="info-value" style="text-transform: capitalize; color:#2d6a4f; font-weight:700;"><?= htmlspecialchars($user['status']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Member Since</span>
                            <span class="info-value"><?= date('F d, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>

                    <!-- PROFILE EDIT FORM -->
                    <div class="dashboard-card">
                        <div class="card-title-wrap">
                            <h3>
                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Edit Profile Details
                            </h3>
                        </div>
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-grid">
                                <div class="form-item form-group-full">
                                    <label for="contact_number">Contact Number</label>
                                    <input type="text" name="contact_number" id="contact_number" class="form-control" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>" placeholder="e.g. +639171234567">
                                </div>
                                <div class="form-item">
                                    <label for="affiliation">Affiliation / Organization</label>
                                    <input type="text" name="affiliation" id="affiliation" class="form-control" value="<?= htmlspecialchars($user['affiliation'] ?? '') ?>" placeholder="e.g. PNP Forensics Division">
                                </div>
                                <div class="form-item">
                                    <label for="department">Department / Branch</label>
                                    <input type="text" name="department" id="department" class="form-control" value="<?= htmlspecialchars($user['department'] ?? '') ?>" placeholder="e.g. Crime Laboratory Group">
                                </div>
                            </div>
                            <div style="margin-top: 1.5rem; text-align: right;">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- RIGHT COLUMN: CHANGE PASSWORD -->
                <div>
                    <div class="dashboard-card">
                        <div class="card-title-wrap">
                            <h3>
                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                Change Password
                            </h3>
                        </div>
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div style="margin-bottom: 1.25rem;">
                                <label for="current_password">Current Password</label>
                                <input type="password" name="current_password" id="current_password" class="form-control" required>
                            </div>

                            <div style="margin-bottom: 1.25rem;">
                                <label for="new_password">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            </div>

                            <div style="text-align: right;">
                                <button type="submit" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div><!-- end student-content -->
    </main>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarCollapse");
        const overlay = document.getElementById("sidebarOverlay");

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                sidebar.classList.toggle("active");
                if (overlay) overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";
            });

            document.addEventListener("click", (e) => {
                if (window.innerWidth <= 992 && sidebar.classList.contains("active")) {
                    if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                        sidebar.classList.remove("active");
                        if (overlay) overlay.style.display = "none";
                    }
                }
            });
        }
    });
</script>
</body>
</html>
