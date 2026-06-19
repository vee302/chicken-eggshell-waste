<?php
// student/ajax_get_safety_climate_logs.php — Student AJAX Get Safety & Climate Logs
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
    $stmt = $pdo->prepare("
        SELECT scl.*, DATE_FORMAT(scl.created_at, '%M %d, %Y %H:%i') as formatted_date, ft.trial_id as trial_code
        FROM safety_climate_log scl
        LEFT JOIN fingerprint_tests ft ON ft.id = scl.trial_id
        WHERE scl.student_id = ?
        ORDER BY scl.created_at DESC
    ");
    $stmt->execute([$student_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
