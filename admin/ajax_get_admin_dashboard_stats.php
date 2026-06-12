<?php
// admin/ajax_get_admin_dashboard_stats.php — Super Admin AJAX Fetch Dashboard Trial/Activity Stats
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    // Total trials
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests");
    $stmt->execute();
    $total_trials = (int)$stmt->fetchColumn();

    // Uploaded images
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE image_path IS NOT NULL AND image_path != ''");
    $stmt->execute();
    $total_images = (int)$stmt->fetchColumn();

    // Reports generated
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports");
    $stmt->execute();
    $total_reports = (int)$stmt->fetchColumn();

    // Total activity logs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs");
    $stmt->execute();
    $total_activities = (int)$stmt->fetchColumn();

    // Approved trials
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE status = 'approved'");
    $stmt->execute();
    $approved_count = (int)$stmt->fetchColumn();

    // Pending trials
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE status = 'pending_validation'");
    $stmt->execute();
    $pending_validation_count = (int)$stmt->fetchColumn();

    // Rejected trials
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE status = 'rejected'");
    $stmt->execute();
    $rejected_count = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Admin dashboard stats retrieved successfully.',
        'data' => [
            'total_trials' => $total_trials,
            'total_images' => $total_images,
            'total_reports' => $total_reports,
            'total_activities' => $total_activities,
            'approved_count' => $approved_count,
            'pending_validation_count' => $pending_validation_count,
            'rejected_count' => $rejected_count
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
