<?php
// faculty/ajax_approve_trial.php — Faculty AJAX Approve Fingerprint Trial
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

$faculty_accuracy_score      = isset($_POST['faculty_accuracy_score']) ? $_POST['faculty_accuracy_score'] : null;
$faculty_ridge_clarity_score = isset($_POST['faculty_ridge_clarity_score']) ? $_POST['faculty_ridge_clarity_score'] : null;
$faculty_visibility_score    = isset($_POST['faculty_visibility_score']) ? $_POST['faculty_visibility_score'] : null;
$faculty_adhesion_score      = isset($_POST['faculty_adhesion_score']) ? $_POST['faculty_adhesion_score'] : null;
$faculty_contrast_score      = isset($_POST['faculty_contrast_score']) ? $_POST['faculty_contrast_score'] : null;

if (
    $faculty_accuracy_score === null || $faculty_accuracy_score === '' || !is_numeric($faculty_accuracy_score) || $faculty_accuracy_score < 0 || $faculty_accuracy_score > 100 ||
    $faculty_ridge_clarity_score === null || $faculty_ridge_clarity_score === '' || !is_numeric($faculty_ridge_clarity_score) || $faculty_ridge_clarity_score < 0 || $faculty_ridge_clarity_score > 100 ||
    $faculty_visibility_score === null || $faculty_visibility_score === '' || !is_numeric($faculty_visibility_score) || $faculty_visibility_score < 0 || $faculty_visibility_score > 100 ||
    $faculty_adhesion_score === null || $faculty_adhesion_score === '' || !is_numeric($faculty_adhesion_score) || $faculty_adhesion_score < 0 || $faculty_adhesion_score > 100 ||
    $faculty_contrast_score === null || $faculty_contrast_score === '' || !is_numeric($faculty_contrast_score) || $faculty_contrast_score < 0 || $faculty_contrast_score > 100
) {
    echo json_encode(['success' => false, 'message' => 'Please provide valid scores (0-100) for all 5 metrics.']);
    exit;
}

$faculty_accuracy_score      = floatval($faculty_accuracy_score);
$faculty_ridge_clarity_score = floatval($faculty_ridge_clarity_score);
$faculty_visibility_score    = floatval($faculty_visibility_score);
$faculty_adhesion_score      = floatval($faculty_adhesion_score);
$faculty_contrast_score      = floatval($faculty_contrast_score);

// Calculate average of the 5 faculty final scores, rounded to 2 decimal places
$faculty_final_score = round(
    ($faculty_accuracy_score + $faculty_ridge_clarity_score + $faculty_visibility_score + $faculty_adhesion_score + $faculty_contrast_score) / 5.0,
    2
);

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
        SET status = 'approved',
            faculty_accuracy_score = ?,
            faculty_ridge_clarity_score = ?,
            faculty_visibility_score = ?,
            faculty_adhesion_score = ?,
            faculty_contrast_score = ?,
            faculty_final_score = ?,
            faculty_remarks = ?,
            validated_by = ?,
            validated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $faculty_accuracy_score,
        $faculty_ridge_clarity_score,
        $faculty_visibility_score,
        $faculty_adhesion_score,
        $faculty_contrast_score,
        $faculty_final_score,
        $remarks,
        $faculty_id,
        $test_id
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO faculty_remarks (test_id, faculty_id, remarks, decision, created_at)
        VALUES (?, ?, ?, 'approved', NOW())
    ");
    $stmt->execute([$test_id, $faculty_id, $remarks ?: 'Approved by faculty researcher.']);

    // Fetch validated_at timestamp from DB to align timezone/time precisely
    $time_stmt = $pdo->prepare("SELECT validated_at FROM fingerprint_tests WHERE id = ?");
    $time_stmt->execute([$test_id]);
    $validated_at = $time_stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Trial approved successfully.',
        'data' => [
            'test_id' => $test_id,
            'status' => 'approved',
            'validated_by' => $faculty_name,
            'validated_at' => $validated_at,
            'faculty_final_score' => $faculty_final_score
        ]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
