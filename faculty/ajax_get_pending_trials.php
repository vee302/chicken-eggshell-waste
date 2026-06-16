<?php
// faculty/ajax_get_pending_trials.php — Faculty AJAX Fetch Pending Validation Trials
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'faculty_researcher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
          ft.*,
          student.full_name AS student_name
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        WHERE ft.status = 'pending_validation'
        ORDER BY ft.submitted_at DESC
    ");
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($submissions as &$s) {
        $s['image_exists'] = false;
        if (!empty($s['image_path'])) {
            $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $s['image_path'];
            if (file_exists($filePath)) {
                $s['image_exists'] = true;
            }
        }
    }
    unset($s);

    echo json_encode([
        'success' => true,
        'message' => 'Pending submissions retrieved successfully.',
        'data' => [
            'submissions' => $submissions
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
