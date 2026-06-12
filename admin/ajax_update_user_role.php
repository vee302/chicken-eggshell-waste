<?php
// admin/ajax_update_user_role.php — Super Admin AJAX Update User Role
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
$role = trim($_POST['role'] ?? '');

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

if (empty($role) || !in_array($role, ['criminology_student', 'faculty_researcher', 'alumni_police_partner', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
    exit;
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT email, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if ($user['status'] === 'pending') {
        $stmt = $pdo->prepare("UPDATE users SET requested_role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);
        log_activity("Edit Requested Role", "Updated requested role for pending user $user[email] to $role");
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);
        log_activity("Change User Role", "Updated active role for $user[email] to $role");
    }

    echo json_encode([
        'success' => true,
        'message' => 'User role updated successfully.',
        'data' => [
            'user_id' => $user_id,
            'role' => $role
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
