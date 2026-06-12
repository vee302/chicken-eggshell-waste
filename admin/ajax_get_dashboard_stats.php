<?php
// admin/ajax_get_dashboard_stats.php — Super Admin AJAX Fetch Dashboard User Stats
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $total_users = (int)$stmt->fetchColumn();

    // Pending approvals
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status='pending'");
    $stmt->execute();
    $pending_count = (int)$stmt->fetchColumn();

    // Active users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status='active'");
    $stmt->execute();
    $active_users = (int)$stmt->fetchColumn();

    // Suspended / Rejected users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status IN ('suspended', 'rejected')");
    $stmt->execute();
    $suspended_rejected_users = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Dashboard user stats retrieved successfully.',
        'data' => [
            'total_users' => $total_users,
            'pending_count' => $pending_count,
            'active_users' => $active_users,
            'suspended_rejected_users' => $suspended_rejected_users
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
