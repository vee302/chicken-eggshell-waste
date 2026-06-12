<?php
// partner/ajax_get_approved_reports.php — Partner AJAX Fetch Approved Reports & Trials
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'alumni_police_partner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    // 1. Fetch generated reports
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name AS compiler_name 
        FROM reports r 
        LEFT JOIN users u ON r.generated_by = u.id 
        ORDER BY r.generated_at DESC
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch all approved trials for reference
    $stmt = $pdo->prepare("
        SELECT 
            ft.*, 
            student.full_name AS student_name, 
            faculty.full_name AS faculty_validator,
            fr.remarks AS validation_remarks
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.decision = 'approved'
        WHERE ft.status = 'approved' 
        ORDER BY ft.validated_at DESC, ft.id DESC
    ");
    $stmt->execute();
    $trials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Approved reports and trials retrieved successfully.',
        'data' => [
            'reports' => $reports,
            'trials' => $trials
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
