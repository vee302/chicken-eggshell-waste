<?php
// logout.php - Destroys active user sessions and redirects to login page

// Initialize session
session_start();

// Unset all session variables
$_SESSION = array();

// If session cookie exists, delete it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page
$redirect_url = "login.php";
if (isset($_GET['idle']) && $_GET['idle'] === '1') {
    $redirect_url .= "?idle=1";
}
header("Location: " . $redirect_url);
exit;
?>
