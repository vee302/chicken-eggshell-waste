<?php
// partner/ajax_get_feedback_history.php — Partner AJAX Fetch Feedback History
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'alumni_police_partner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$partner_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM field_feedback WHERE partner_id = ? ORDER BY created_at DESC");
    $stmt->execute([$partner_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Feedback history retrieved successfully.',
        'data' => [
            'history' => $history
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
