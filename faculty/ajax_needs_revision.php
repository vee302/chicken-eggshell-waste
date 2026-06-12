<?php
// faculty/ajax_needs_revision.php — Faculty AJAX Needs Revision Fingerprint Trial
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'faculty_researcher') {
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

$test_id  = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
$remarks  = trim($_POST['remarks'] ?? '');
$faculty_id = $_SESSION['user_id'] ?? 0;

if ($test_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid test ID.']);
    exit;
}

if (empty($remarks)) {
    echo json_encode(['success' => false, 'message' => 'Remarks are required when requesting revision.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE fingerprint_tests 
        SET status = 'needs_revision',
            validated_by = ?,
            validated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$faculty_id, $test_id]);

    $stmt = $pdo->prepare("
        INSERT INTO faculty_remarks (test_id, faculty_id, remarks, decision, created_at)
        VALUES (?, ?, ?, 'needs_revision', NOW())
    ");
    $stmt->execute([$test_id, $faculty_id, $remarks]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Submission marked as needs revision and remarks saved.',
        'data' => [
            'test_id' => $test_id,
            'status' => 'needs_revision'
        ]
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
