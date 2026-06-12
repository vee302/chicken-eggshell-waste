<?php
// faculty/ajax_get_faculty_dashboard_stats.php — Faculty AJAX Fetch Dashboard Stats
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'faculty_researcher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$faculty_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests");
    $stmt->execute();
    $total_submissions = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE status='pending_validation'");
    $stmt->execute();
    $pending = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE status='approved'");
    $stmt->execute();
    $approved = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE status='rejected'");
    $stmt->execute();
    $rejected = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT ROUND(AVG(accuracy_score),1) FROM fingerprint_tests");
    $stmt->execute();
    $avg_accuracy = $stmt->fetchColumn() ?? 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE generated_by = ?");
    $stmt->execute([$faculty_id]);
    $report_count = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Faculty dashboard stats retrieved successfully.',
        'data' => [
            'total_submissions' => $total_submissions,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'avg_accuracy' => $avg_accuracy,
            'report_count' => $report_count
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
