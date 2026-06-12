<?php
// partner/ajax_change_password.php — Partner AJAX Change Password
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'alumni_police_partner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// CSRF Token validation
$headers = getallheaders();
$csrf_token = $_POST['csrf_token'] ?? $headers['X-CSRF-Token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$current_pwd = $_POST['current_password'] ?? '';
$new_pwd = $_POST['new_password'] ?? '';
$confirm_pwd = $_POST['confirm_password'] ?? '';
$partner_id = $_SESSION['user_id'] ?? 0;

if (empty($current_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
    echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
    exit;
}

if ($new_pwd !== $confirm_pwd) {
    echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
    exit;
}

if (strlen($new_pwd) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
    exit;
}

try {
    // Fetch user details
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$partner_id]);
    $curr_hash = $stmt->fetchColumn();

    if (!$curr_hash || !password_verify($current_pwd, $curr_hash)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $new_hashed = password_hash($new_pwd, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$new_hashed, $partner_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully.'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
