<?php
// admin/auth.php - Admin Session Authentication Protection

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the current user session is a valid logged-in Super Administrator.
 * If not, redirects to login page or blocks access.
 */
function check_admin_auth() {
    // 1. Check if logged in
    if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
        header("Location: ../login.php");
        exit;
    }

    // 2. Validate role is super_admin
    if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== 'super_admin') {
        $role = $_SESSION["user_role"] ?? '';
        if ($role === 'faculty_researcher') {
            header("Location: ../faculty/faculty_dashboard.php");
            exit;
        } elseif ($role === 'criminology_student') {
            header("Location: ../student/student_dashboard.php");
            exit;
        } elseif ($role === 'alumni_police_partner') {
            header("Location: ../police-partner/partner_dashboard.php");
            exit;
        } else {
            // Logged in but not an admin, deny access
            http_response_code(403);
            echo "<!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Unauthorized Access</title>
                <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'>
                <style>
                    body {
                        font-family: 'Inter', sans-serif;
                        background-color: #F8F9FA;
                        color: #2F4F3A;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 100vh;
                        margin: 0;
                        text-align: center;
                    }
                    .error-card {
                        background: #FFFFFF;
                        padding: 3rem;
                        border-radius: 20px;
                        box-shadow: 0 10px 30px rgba(47, 79, 58, 0.08);
                        max-width: 450px;
                        border: 1px solid rgba(47, 79, 58, 0.1);
                    }
                    svg {
                        color: #E07A5F;
                        margin-bottom: 1.5rem;
                    }
                    h1 {
                        font-size: 1.5rem;
                        font-weight: 700;
                        margin: 0 0 1rem 0;
                    }
                    p {
                        font-size: 0.9rem;
                        color: #6c757d;
                        line-height: 1.6;
                        margin: 0 0 2rem 0;
                    }
                    .btn {
                        display: inline-block;
                        background: #2F4F3A;
                        color: #FFFFFF;
                        text-decoration: none;
                        padding: 0.75rem 1.5rem;
                        border-radius: 8px;
                        font-weight: 600;
                        font-size: 0.9rem;
                        transition: background-color 0.2s ease;
                    }
                    .btn:hover {
                        background: #243E2E;
                    }
                </style>
            </head>
            <body>
                <div class='error-card'>
                    <svg viewBox='0 0 24 24' width='64' height='64' fill='none' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                        <path d='M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'></path>
                        <line x1='12' y1='9' x2='12' y2='13'></line>
                        <line x1='12' y1='17' x2='12.01' y2='17'></line>
                    </svg>
                    <h1>Unauthorized Access</h1>
                    <p>You do not have permission to view this page. Access is restricted to Super Administrator accounts only.</p>
                    <a href='../dashboard.php' class='btn'>Back to Dashboard</a>
                </div>
            </body>
            </html>";
            exit;
        }
    }
}

/**
 * Log activity to the activity_logs table
 */
function log_activity($action, $details) {
    global $pdo;
    if (!$pdo) {
        // Try including config.php if not included
        if (file_exists("../config.php")) {
            require_once "../config.php";
        } elseif (file_exists("config.php")) {
            require_once "config.php";
        }
    }
    
    if (!$pdo) return;

    try {
        $user_id = $_SESSION["user_id"] ?? null;
        $user_email = $_SESSION["user_email"] ?? 'system';
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
        // Silent fail to prevent breaking UI if database log fails
    }
}
?>
