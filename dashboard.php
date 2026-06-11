<?php
// dashboard.php - Protected dashboard for authorized users

// Start the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

// Redirect to correct role-based dashboard
$role = $_SESSION['user_role'] ?? '';
if ($role === 'super_admin') {
    header("Location: admin/dashboard.php");
    exit;
} elseif ($role === 'faculty_researcher') {
    header("Location: faculty/faculty_dashboard.php");
    exit;
} elseif ($role === 'criminology_student') {
    header("Location: student/student_dashboard.php");
    exit;
} elseif ($role === 'alumni_police_partner') {
    header("Location: partner/partner_dashboard.php");
    exit;
}

// Get user session data (fallback for any other roles)
$user_name  = $_SESSION["user_name"];
$user_email = $_SESSION["user_email"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Green Forensics Evaluating System</title>
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

    <main class="dashboard-container">
        <div class="dashboard-card">
            <div class="dashboard-header">
                <div class="user-welcome">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p>Logged in as: <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
                </div>
                <div>
                    <a href="logout.php" class="btn-logout">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>

            <div class="dashboard-body">
                <div class="dashboard-placeholder">
                    <!-- Shield/Checkmark SVG -->
                    <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <polyline points="9 11 11 13 15 9"></polyline>
                    </svg>
                    <h3>Secure Authentication Successful</h3>
                    <p>You have successfully logged into the Green Forensics Evaluating System. This dashboard is
                        currently in phase 1 of development. Future updates will include student evaluation scores,
                        police reporting modules, and python-based latent fingerprint matching metrics.</p>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Green Forensics Project | LSPU CCJE San Pablo City Campus</p>
    </footer>

</body>

</html>