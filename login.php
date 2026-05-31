<?php
// login.php - Secure login system for Green Forensics Evaluating System

// Start the session
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'super_admin') {
        header("Location: admin/admin_dashboard.php");
    } elseif ($role === 'faculty_researcher') {
        header("Location: faculty/faculty_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

// Include database configuration
require_once "config.php";

$email = $password = "";
$error_message = "";

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
        $sql = "SELECT id, full_name, email, password, role, status FROM users WHERE email = :email";
        
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
                            
                            // Verify password
                            if (password_verify($password, $hashed_password)) {
                                // Check if user account is active
                                if ($status === 'active') {
                                    // Set session variables
                                    $_SESSION["logged_in"] = true;
                                    $_SESSION["user_id"]   = $id;
                                    $_SESSION["user_name"] = $full_name;
                                    $_SESSION["user_email"]= $email;
                                    $_SESSION["user_role"] = $user_role;
                                    
                                    // Redirect based on role
                                    if ($user_role === 'super_admin') {
                                        header("Location: admin/admin_dashboard.php");
                                    } elseif ($user_role === 'faculty_researcher') {
                                        header("Location: faculty/faculty_dashboard.php");
                                    } else {
                                        header("Location: dashboard.php");
                                    }
                                    exit;
                                } else {
                                    $error_message = "Account is inactive.";
                                }
                            } else {
                                // Display generic error for security
                                $error_message = "Invalid email address or password.";
                            }
                        }
                    } else {
                        // Display generic error for security
                        $error_message = "Invalid email address or password.";
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

            <!-- Error Alert -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </span>
                        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Login Securely</span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: var(--gray); font-style: italic;">
                Authorized users only.
            </div>

            <div class="register-link-wrapper">
                <span>Don't have an account?</span>
                <a href="register.php" class="register-link">Register here</a>
            </div>

            <div class="back-link-wrapper">
                <a href="index.php" class="back-link">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
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
        });
    </script>

</body>
</html>
