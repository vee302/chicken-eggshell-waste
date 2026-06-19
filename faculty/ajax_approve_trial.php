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

$faculty_final_score = isset($_POST['faculty_final_score']) ? floatval($_POST['faculty_final_score']) : null;

// Fallback: If individual scores are passed instead of a single final score
if ($faculty_final_score === null) {
    $clarity  = isset($_POST['ridge_clarity_score']) ? floatval($_POST['ridge_clarity_score']) : null;
    $visibility = isset($_POST['visibility_score']) ? floatval($_POST['visibility_score']) : null;
    $adhesion   = isset($_POST['adhesion_score']) ? floatval($_POST['adhesion_score']) : null;
    $contrast   = isset($_POST['contrast_score']) ? floatval($_POST['contrast_score']) : null;
    
    if ($clarity !== null && $visibility !== null && $adhesion !== null && $contrast !== null) {
        $faculty_final_score = ($clarity + $visibility + $adhesion + $contrast) / 4.0;
    }
}

if ($faculty_final_score === null || $faculty_final_score < 0 || $faculty_final_score > 100) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid final score (0-100).']);
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
        SET status = 'approved',
            accuracy_score = ?,
            faculty_final_score = ?,
            validated_by = ?,
            validated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$faculty_final_score, $faculty_final_score, $faculty_id, $test_id]);

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
