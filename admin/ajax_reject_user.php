<?php
// admin/ajax_reject_user.php — Super Admin AJAX Reject User Registration
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
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

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

try {
    // Check if user exists and is pending
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$user_id]);
    $email = $stmt->fetchColumn();

    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Pending user not found.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$user_id]);

    log_activity("Reject User", "Rejected user registration for $email");

    echo json_encode([
        'success' => true,
        'message' => 'Registration request rejected successfully.',
        'data' => [
            'user_id' => $user_id,
            'email' => $email
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
