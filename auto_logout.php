<?php
// auto_logout.php — Centralized session destruction endpoint for AJAX auto-logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Logged out due to inactivity.'
]);
exit;
