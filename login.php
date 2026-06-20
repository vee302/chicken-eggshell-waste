<?php
// login.php - Secure login system for Green Forensics Evaluating System

// Start the session
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'super_admin') {
        header("Location: admin/dashboard.php");
    } elseif ($role === 'faculty_researcher') {
        header("Location: faculty/faculty_dashboard.php");
    } elseif ($role === 'criminology_student') {
        header("Location: student/student_dashboard.php");
    } elseif ($role === 'alumni_police_partner') {
        header("Location: police-partner/partner_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

// Include database configuration
require_once "config.php";

$email = $password = "";
$error_message = "";
$info_message = "";

if (isset($_GET['idle']) && $_GET['idle'] === '1') {
    $info_message = "You have been automatically logged out due to inactivity.";
}

// Helper to log login activity
function log_login_activity($pdo, $action, $details, $user_id = null, $user_email = 'system') {
    try {
        $ip_address = $_SERVER["REMOTE_ADDR"] ?? '127.0.0.1';
        $user_agent = $_SERVER["HTTP_USER_AGENT"] ?? 'Unknown';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_email, action, details, ip_address, user_agent) VALUES (:user_id, :user_email, :action, :details, :ip, :ua)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':user_email' => $user_email,
            ':action' => $action,
            ':details' => $details,
            ':ip' => $ip_address,
            ':ua' => $user_agent
        ]);
    } catch (PDOException $e) {
        // Silent fail
    }
}

// Process form data when post request is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Trim input values
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Validate credentials
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id, full_name, email, password, role, status, failed_login_attempts, locked_until, last_failed_login FROM users WHERE email = :email";

        try {
            if ($stmt = $pdo->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
                $param_email = $email;

                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    // Check if email exists
                    if ($stmt->rowCount() == 1) {
                        if ($row = $stmt->fetch()) {
                            $id = $row["id"];
                            $full_name = $row["full_name"];
                            $hashed_password = $row["password"];
                            $user_role = $row["role"];
                            $status = $row["status"];
                            $failed_attempts = (int)$row["failed_login_attempts"];
                            $locked_until = $row["locked_until"];

                            // Check if lockout active
                            $is_locked = false;
                            if ($locked_until !== null) {
                                $lockTime = strtotime($locked_until);
                                $now = time();
                                if ($now < $lockTime) {
                                    $is_locked = true;
                                    $error_message = "Too many failed login attempts. Please try again after 15 minutes or contact the Super Administrator.";
                                } else {
                                    // Lockout expired! Automatically reset lockout details.
                                    try {
                                        $reset_stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_failed_login = NULL WHERE id = :id");
                                        $reset_stmt->execute([':id' => $id]);
                                        $failed_attempts = 0;
                                        $locked_until = null;
                                    } catch (PDOException $e) {
                                        // Ignore
                                    }
                                }
                            }

                            if (!$is_locked) {
                                // Verify password
                                if (password_verify($password, $hashed_password)) {
                                    // Check status first
                                    if ($status === 'active') {
                                        // Reset failed attempts upon successful login
                                        try {
                                            $reset_stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_failed_login = NULL WHERE id = :id");
                                            $reset_stmt->execute([':id' => $id]);
                                        } catch (PDOException $e) {
                                            // Ignore
                                        }

                                        // Set session variables
                                        $_SESSION["logged_in"] = true;
                                        $_SESSION["user_id"] = $id;
                                        $_SESSION["user_name"] = $full_name;
                                        $_SESSION["user_email"] = $email;
                                        $_SESSION["user_role"] = $user_role;

                                        // Redirect based on role
                                        if ($user_role === 'super_admin') {
                                            header("Location: admin/dashboard.php");
                                        } elseif ($user_role === 'faculty_researcher') {
                                            header("Location: faculty/faculty_dashboard.php");
                                        } elseif ($user_role === 'criminology_student') {
                                            header("Location: student/student_dashboard.php");
                                        } elseif ($user_role === 'alumni_police_partner') {
                                            header("Location: police-partner/partner_dashboard.php");
                                        } else {
                                            header("Location: dashboard.php");
                                        }
                                        exit;
                                    } elseif ($status === 'pending') {
                                        $error_message = "Your account is still pending approval.";
                                    } elseif ($status === 'rejected') {
                                        $error_message = "Your registration was not approved.";
                                    } elseif ($status === 'suspended') {
                                        $error_message = "Your account has been suspended. Please contact the Super Administrator.";
                                    } else {
                                        $error_message = "Your account is currently inactive. Please contact the administrator.";
                                    }
                                } else {
                                    // Password is wrong - increment attempts
                                    $new_attempts = $failed_attempts + 1;
                                    if ($new_attempts >= 5) {
                                        $locked_until_val = date('Y-m-d H:i:s', time() + 15 * 60);
                                        try {
                                            $update_stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked_until, last_failed_login = NOW() WHERE id = :id");
                                            $update_stmt->execute([
                                                ':attempts' => $new_attempts,
                                                ':locked_until' => $locked_until_val,
                                                ':id' => $id
                                            ]);
                                            log_login_activity($pdo, "Account Locked", "Account temporarily locked for 15 minutes due to 5 failed login attempts", $id, $email);
                                        } catch (PDOException $e) {
                                            // Ignore
                                        }
                                        $error_message = "Too many failed login attempts. Your account has been temporarily locked for 15 minutes.";
                                    } else {
                                        try {
                                            $update_stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = :attempts, last_failed_login = NOW() WHERE id = :id");
                                            $update_stmt->execute([
                                                ':attempts' => $new_attempts,
                                                ':id' => $id
                                            ]);
                                            log_login_activity($pdo, "Failed Login Attempt", "Failed login attempt (attempts remaining: " . (5 - $new_attempts) . ")", $id, $email);
                                        } catch (PDOException $e) {
                                            // Ignore
                                        }
                                        $error_message = "Invalid email or password. Attempts remaining: " . (5 - $new_attempts);
                                    }
                                }
                            }
                        }
                    } else {
                        // Display generic error for security
                        $error_message = "Invalid email or password.";
                    }
                } else {
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }

                // Close statement
                unset($stmt);
            }
        } catch (PDOException $e) {
            $error_message = "Connection error: " . $e->getMessage();
        }
    }

    // Close connection
    unset($pdo);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Forensics Evaluating System - Login</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="css/login.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

    <header>
        <h1>Green Forensics Evaluating System</h1>
        <p>Innovative Sustainable Fingerprint Powder Using Chicken Eggshell Waste</p>
    </header>

    <main class="login-container">
        <!-- SKELETON LOADER (visible first) -->
        <div class="login-card" id="skeletonCard">
            <div class="skeleton-loader">
                <div class="skeleton-item skeleton-icon"></div>
                <div class="skeleton-item skeleton-title"></div>
                <div class="skeleton-item skeleton-subtitle"></div>
                <div class="skeleton-item skeleton-label"></div>
                <div class="skeleton-item skeleton-input"></div>
                <div class="skeleton-item skeleton-label"></div>
                <div class="skeleton-item skeleton-input"></div>
                <div class="skeleton-item skeleton-button"></div>
            </div>
        </div>

        <!-- REAL LOGIN FORM (hidden initially) -->
        <div class="login-card" id="realLoginCard" style="display: none; opacity: 0;">
            <div class="card-header">
                <div class="card-icon">
                    <!-- Fingerprint SVG Icon -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M12 2a10 10 0 0 0-7.3 16.8"></path>
                        <path d="M12 2a10 10 0 0 1 7.3 16.8"></path>
                        <path d="M12 6a6 6 0 0 0-4.4 10.1"></path>
                        <path d="M12 6a6 6 0 0 1 4.4 10.1"></path>
                        <path d="M12 10a2 2 0 0 0-1.5 3.4"></path>
                        <path d="M12 10a2 2 0 0 1 1.5 3.4"></path>
                        <path d="M12 14v4"></path>
                    </svg>
                </div>
                <h2>System Authentication</h2>
                <p>Please enter your credentials below</p>
            </div>

            <!-- Info Alert -->
            <?php if (!empty($info_message)): ?>
                <div class="alert alert-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($info_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Error Alert -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <?php if (strpos($error_message, 'Invalid email or password') !== false || strpos($error_message, 'Too many failed login attempts') !== false): ?>
                    <div style="font-size: 0.8rem; color: var(--error-red); margin-bottom: 1.25rem; font-weight: 500; text-align: center;">
                        Forgot your password or locked out? Contact the Super Administrator for account recovery.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                </path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </span>
                        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email"
                            value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper password-wrapper">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" data-password-toggle="password"
                            aria-label="Show password" aria-pressed="false">
                            <span class="icon-eye">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                            <span class="icon-eye-off">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94"></path>
                                    <path d="M9.9 4.24A10.84 10.84 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19"></path>
                                    <path d="M14.12 14.12A3 3 0 0 1 9.88 9.88"></path>
                                    <line x1="3" y1="3" x2="21" y2="21"></line>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Login Securely</span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <div
                style="text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: var(--gray); font-style: italic;">
                Authorized access only. Registering users must be approved by the Super Administrator.
            </div>

            <div class="register-link-wrapper" style="display: flex; flex-direction: column; gap: 0.5rem; align-items: center; margin-top: 1.25rem;">
                <div>
                    <span>Don't have an account?</span>
                    <a href="register.php" class="register-link">Register here</a>
                </div>
                <div>
                    <a href="request_unlock.php" class="register-link" style="color: var(--soft-green); border-bottom-color: rgba(107, 143, 113, 0.4);">Need help accessing your account? Request unlock</a>
                </div>
            </div>

            <div class="back-link-wrapper">
                <a href="index.php" class="back-link">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Back to Homepage</span>
                </a>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Green Forensics Project | LSPU CCJE San Pablo City Campus</p>
        <p style="margin-top: 5px;">
            <a href="terms.php" target="_blank" rel="noopener noreferrer" style="color: var(--gray); text-decoration: underline; margin-right: 10px;">Terms of Use</a>
            <a href="privacy.php" target="_blank" rel="noopener noreferrer" style="color: var(--gray); text-decoration: underline;">Privacy Policy</a>
        </p>
    </footer>

    <script>
        // Skeleton → Real Form reveal transition
        document.addEventListener("DOMContentLoaded", () => {
            const skeleton = document.getElementById("skeletonCard");
            const realCard = document.getElementById("realLoginCard");

            setTimeout(() => {
                // Fade out skeleton
                skeleton.style.transition = "opacity 0.4s ease";
                skeleton.style.opacity = "0";

                setTimeout(() => {
                    skeleton.style.display = "none";
                    // Show real card
                    realCard.style.display = "block";
                    // Trigger reflow then fade in
                    requestAnimationFrame(() => {
                        realCard.style.transition = "opacity 0.5s ease";
                        realCard.style.opacity = "1";
                    });
                }, 400);
            }, 1500);

            document.querySelectorAll("[data-password-toggle]").forEach((button) => {
                const input = document.getElementById(button.dataset.passwordToggle);
                if (!input) return;

                button.addEventListener("click", () => {
                    const shouldShow = input.type === "password";
                    input.type = shouldShow ? "text" : "password";
                    button.classList.toggle("is-visible", shouldShow);
                    button.setAttribute("aria-pressed", shouldShow ? "true" : "false");
                    button.setAttribute("aria-label", shouldShow ? "Hide password" : "Show password");
                });
            });
        });
    </script>

<?php include 'includes/support_chat_widget.php'; ?>
</body>

</html>
