<?php
// student/ajax_submit_climate_log.php — Student AJAX Submit Safety & Climate Log
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

$temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : 0;
$humidity    = isset($_POST['humidity']) ? floatval($_POST['humidity']) : 0;
$ppe_worn    = trim($_POST['ppe_worn']    ?? '');
$conditions  = trim($_POST['conditions']  ?? '');
$notes       = trim($_POST['notes']       ?? '');
$student_id  = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        INSERT INTO safety_logs (student_id, temperature, humidity, ppe_worn, lab_conditions, notes, logged_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$student_id, $temperature, $humidity, $ppe_worn, $conditions, $notes]);

    echo json_encode([
        'success' => true,
        'message' => 'Safety and climate log submitted successfully.',
        'data' => [
            'id' => $pdo->lastInsertId(),
            'temperature' => $temperature,
            'humidity' => $humidity,
            'ppe_worn' => $ppe_worn,
            'lab_conditions' => $conditions,
            'notes' => $notes,
            'logged_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
