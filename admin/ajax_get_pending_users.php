<?php
// admin/ajax_get_pending_users.php — Super Admin AJAX Fetch Pending Users
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$search = trim($_GET['search'] ?? '');

try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("
            SELECT id, first_name, middle_name, last_name, full_name, email, contact_number, id_number, department, requested_role, reason_for_access, status, created_at 
            FROM users 
            WHERE status='pending' AND (full_name LIKE ? OR email LIKE ? OR id_number LIKE ?) 
            ORDER BY created_at DESC
        ");
        $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, first_name, middle_name, last_name, full_name, email, contact_number, id_number, department, requested_role, reason_for_access, status, created_at 
            FROM users 
            WHERE status='pending' 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
    }
    $pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Pending users retrieved successfully.',
        'data' => [
            'pending_users' => $pending_users
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
