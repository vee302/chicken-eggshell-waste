<?php
// student/auth.php - Criminology Student Session Authentication

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the current session belongs to a criminology_student.
 * Redirects to login or shows Unauthorized Access if not.
 */
function check_student_auth() {
    // 1. Must be logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: ../login.php');
        exit;
    }

    // 2. Must be a criminology_student
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'criminology_student') {
        $role = $_SESSION['user_role'] ?? '';

        // Redirect to correct dashboard by role
        if ($role === 'super_admin') {
            header('Location: ../admin/dashboard.php');
            exit;
        } elseif ($role === 'faculty_researcher') {
            header('Location: ../faculty/faculty_dashboard.php');
            exit;
        } elseif ($role === 'alumni_police_partner') {
            header('Location: ../police-partner/partner_dashboard.php');
            exit;
        }

        // Unknown role — show Unauthorized Access page
        http_response_code(403);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Green Forensics</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; background: #f4f6f0; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #fff; padding: 3rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(27,67,50,.08); max-width: 440px; text-align: center; border: 1px solid rgba(27,67,50,.08); }
        .icon { color: #e07a5f; margin-bottom: 1.5rem; }
        h1 { font-size: 1.4rem; color: #1b4332; margin-bottom: 0.75rem; }
        p { font-size: 0.9rem; color: #6c757d; line-height: 1.6; margin-bottom: 2rem; }
        .btn { display: inline-block; background: #2d6a4f; color: #fff; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .btn:hover { background: #1b4332; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <h1>Unauthorized Access</h1>
        <p>You do not have permission to view this page. This area is restricted to Criminology Student accounts only.</p>
        <a href="../login.php" class="btn">Back to Login</a>
    </div>
</body>
</html>';
        exit;
    }
}
?>
