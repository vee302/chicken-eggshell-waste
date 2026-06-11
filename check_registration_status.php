<?php
// check_registration_status.php - JSON API: returns current pending user account status
// Called via AJAX from pending_approval.php every 5 seconds.
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Identify user from session
$accountId    = isset($_SESSION['pending_registration_user_id']) ? (int)$_SESSION['pending_registration_user_id'] : 0;
$accountEmail = $_SESSION['pending_registration_email'] ?? '';

$account = null;

try {
    if ($accountId > 0) {
        $stmt = $pdo->prepare("SELECT id, status FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$account && !empty($accountEmail)) {
        $stmt = $pdo->prepare("SELECT id, status FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $accountEmail]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
    exit;
}

if (!$account) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired or account not found. Please register again.']);
    exit;
}

// Always keep session in sync with the latest DB record
$_SESSION['pending_registration_user_id'] = (int)$account['id'];
$status = $account['status'];

$messages = [
    'pending'  => 'Your account is still pending approval.',
    'active'   => 'Your account has been approved. You may now log in.',
    'rejected' => 'Your registration was not approved. Please contact the system administrator.',
    'suspended'=> 'Your account has been suspended.',
];

echo json_encode([
    'status'  => $status,
    'message' => $messages[$status] ?? 'Unknown account status.',
]);
exit;
