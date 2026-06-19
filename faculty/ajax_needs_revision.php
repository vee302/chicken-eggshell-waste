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
$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';

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

    // Verify if test ID is valid
    $check_stmt = $pdo->prepare("SELECT id FROM fingerprint_tests WHERE id = ?");
    $check_stmt->execute([$test_id]);
    if ($check_stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Trial record not found.']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE fingerprint_tests 
        SET status = 'needs_revision',
            faculty_remarks = ?,
            validated_by = ?,
            validated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$remarks, $faculty_id, $test_id]);

    $stmt = $pdo->prepare("
        INSERT INTO faculty_remarks (test_id, faculty_id, remarks, decision, created_at)
        VALUES (?, ?, ?, 'needs_revision', NOW())
    ");
    $stmt->execute([$test_id, $faculty_id, $remarks]);

    // Fetch validated_at timestamp from DB to align timezone/time precisely
    $time_stmt = $pdo->prepare("SELECT validated_at FROM fingerprint_tests WHERE id = ?");
    $time_stmt->execute([$test_id]);
    $validated_at = $time_stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Trial marked as needs revision successfully.',
        'data' => [
            'test_id' => $test_id,
            'status' => 'needs_revision',
            'validated_by' => $faculty_name,
            'validated_at' => $validated_at
        ]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
