<?php
// student/ajax_submit_trial.php — Student AJAX Submit Trial Data
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'criminology_student') {
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

$powder_type    = trim($_POST['powder_type']    ?? '');
$surface_type   = trim($_POST['surface_type']   ?? '');
$accuracy_score = isset($_POST['accuracy_score']) ? floatval($_POST['accuracy_score']) : 0;
$notes          = trim($_POST['notes']          ?? '');
$student_id     = $_SESSION['user_id'] ?? 0;

if (!$powder_type || !$surface_type || $accuracy_score <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields with valid values.']);
    exit;
}

try {
    // Generate a unique trial_id
    $stmt = $pdo->prepare("SELECT MAX(id) FROM fingerprint_tests");
    $stmt->execute();
    $max_id = $stmt->fetchColumn() ?: 0;
    $next_id = $max_id + 1;
    $trial_id = 'TR-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("
        INSERT INTO fingerprint_tests (trial_id, student_id, powder_type, surface_type, accuracy_score, notes, status, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending_validation', NOW())
    ");
    $stmt->execute([$trial_id, $student_id, $powder_type, $surface_type, $accuracy_score, $notes]);

    echo json_encode([
        'success' => true,
        'message' => 'Trial data submitted successfully! It is now pending faculty review.',
        'data' => [
            'id' => $pdo->lastInsertId(),
            'trial_id' => $trial_id,
            'powder_type' => $powder_type,
            'surface_type' => $surface_type,
            'accuracy_score' => $accuracy_score,
            'notes' => $notes,
            'status' => 'pending_validation',
            'submitted_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
