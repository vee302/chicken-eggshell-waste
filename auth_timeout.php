<?php
// auth_timeout.php - Shared PHP session inactivity timeout check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_script = basename($_SERVER['SCRIPT_NAME']);
$public_pages = [
    'login.php', 
    'register.php', 
    'pending_approval.php', 
    'check_registration_status.php', 
    'logout.php', 
    'desktop.php',
    'deskstop.php',
    'index.php'
];

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (!in_array($current_script, $public_pages)) {
        // Exclude admin from inactivity check
        $is_admin = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) || 
                    (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin');

        if (!$is_admin) {
            if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 300)) {
                // Session expired! Clear session variables and destroy session
                $_SESSION = array();
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();
                
                // Redirect to login.php?idle=1
                $is_subdir = (strpos($_SERVER['SCRIPT_NAME'], '/faculty/') !== false || 
                              strpos($_SERVER['SCRIPT_NAME'], '/student/') !== false || 
                              strpos($_SERVER['SCRIPT_NAME'], '/police-partner/') !== false);
                $redirect_url = $is_subdir ? '../login.php?idle=1' : 'login.php?idle=1';
                header("Location: $redirect_url");
                exit;
            } else {
                // Update last activity timestamp
                $_SESSION['LAST_ACTIVITY'] = time();
            }
        }
    }
}
