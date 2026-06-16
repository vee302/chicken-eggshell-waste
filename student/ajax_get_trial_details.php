<?php
// student/ajax_get_trial_details.php — Criminology Student AJAX Fetch Trial Details
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'criminology_student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$student_id = $_SESSION['user_id'] ?? 0;
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

if ($test_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid trial ID.']);
    exit;
}

try {
    // Select record details making sure the logged-in student owns it
    $stmt = $pdo->prepare("
        SELECT 
            ft.*, 
            fr.remarks AS faculty_remarks, 
            faculty.full_name AS faculty_reviewer,
            student.full_name AS student_name,
            student.email AS student_email
        FROM fingerprint_tests ft
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE ft.id = ? AND ft.student_id = ?
    ");
    $stmt->execute([$test_id, $student_id]);
    $trial = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trial) {
        echo json_encode(['success' => false, 'message' => 'Trial record not found or access denied.']);
        exit;
    }

    // Add image exists check
    $trial['image_exists'] = false;
    if (!empty($trial['image_path'])) {
        $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $trial['image_path'];
        if (file_exists($filePath)) {
            $trial['image_exists'] = true;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Trial details retrieved successfully.',
        'data' => $trial
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
