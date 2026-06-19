<?php
// student/ajax_get_student_dashboard_stats.php — Student AJAX Fetch Dashboard Stats
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'criminology_student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$student_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $total = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = ? AND status='pending_validation'");
    $stmt->execute([$student_id]);
    $pending = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = ? AND status='approved'");
    $stmt->execute([$student_id]);
    $approved = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = ? AND status='rejected'");
    $stmt->execute([$student_id]);
    $rejected = (int)$stmt->fetchColumn();

    // Only calculate average from approved records
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = ? AND status='approved' AND (faculty_final_score IS NOT NULL OR accuracy_score IS NOT NULL)");
    $stmt->execute([$student_id]);
    $approved_count = (int)$stmt->fetchColumn();
 
    if ($approved_count > 0) {
        $stmt = $pdo->prepare("SELECT ROUND(AVG(COALESCE(faculty_final_score, accuracy_score)),1) FROM fingerprint_tests WHERE student_id = ? AND status='approved'");
        $stmt->execute([$student_id]);
        $avg_score_val = $stmt->fetchColumn();
        $avg_score = $avg_score_val . '%';
    } else {
        $avg_score = ($pending > 0) ? 'Awaiting Validation' : 'N/A';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Student dashboard stats retrieved successfully.',
        'data' => [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'avg_score' => $avg_score
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
