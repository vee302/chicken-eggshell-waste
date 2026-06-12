<?php
// partner/ajax_submit_feedback.php — Partner AJAX Submit Feedback
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'alumni_police_partner') {
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

$partner_id = $_SESSION['user_id'] ?? 0;
$feedback_type = trim($_POST['feedback_type'] ?? '');
$surface_type = trim($_POST['surface_type'] ?? '');
$powder_type = trim($_POST['powder_type'] ?? '');
$observation = trim($_POST['observation'] ?? '');
$usability_rating = isset($_POST['usability_rating']) ? (int)$_POST['usability_rating'] : 0;
$suggested_improvement = trim($_POST['suggested_improvement'] ?? '');

if (empty($feedback_type)) {
    echo json_encode(['success' => false, 'message' => 'Feedback type is required.']);
    exit;
}

if (empty($observation)) {
    echo json_encode(['success' => false, 'message' => 'Field observation details are required.']);
    exit;
}

if ($usability_rating < 1 || $usability_rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid usability rating between 1 and 5.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO field_feedback (
            partner_id, 
            feedback_type, 
            surface_type, 
            powder_type, 
            observation, 
            usability_rating, 
            suggested_improvement
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $partner_id,
        $feedback_type,
        $surface_type !== 'none' && $surface_type !== '' ? $surface_type : null,
        $powder_type !== 'none' && $powder_type !== '' ? $powder_type : null,
        $observation,
        $usability_rating,
        !empty($suggested_improvement) ? $suggested_improvement : null
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Feedback submitted successfully.',
        'data' => [
            'id' => $pdo->lastInsertId(),
            'feedback_type' => $feedback_type,
            'surface_type' => $surface_type,
            'powder_type' => $powder_type,
            'observation' => $observation,
            'usability_rating' => $usability_rating,
            'suggested_improvement' => $suggested_improvement,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
