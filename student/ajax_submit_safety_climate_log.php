<?php
// student/ajax_submit_safety_climate_log.php — Student AJAX Submit Safety & Climate Log
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

$student_id = $_SESSION['user_id'] ?? 0;
$trial_id = isset($_POST['trial_id']) && $_POST['trial_id'] !== '' && $_POST['trial_id'] !== 'none' ? (int)$_POST['trial_id'] : null;
$powder_type = trim($_POST['powder_type'] ?? '');
$surface_type = trim($_POST['surface_type'] ?? '');
$temperature = isset($_POST['temperature']) && $_POST['temperature'] !== '' ? floatval($_POST['temperature']) : null;
$humidity = isset($_POST['humidity']) && $_POST['humidity'] !== '' ? floatval($_POST['humidity']) : null;
$health_feedback = isset($_POST['health_feedback']) ? substr(trim($_POST['health_feedback']), 0, 255) : null;
$irritation_status = trim($_POST['irritation_status'] ?? 'none');
$remarks = isset($_POST['remarks']) && $_POST['remarks'] !== '' ? trim($_POST['remarks']) : null;

// Validation rules
if (empty($powder_type)) {
    echo json_encode(['success' => false, 'message' => 'Powder Type is required.']);
    exit;
}
if ($powder_type !== 'eggshell' && $powder_type !== 'commercial') {
    echo json_encode(['success' => false, 'message' => 'Invalid Powder Type.']);
    exit;
}

if (empty($surface_type)) {
    echo json_encode(['success' => false, 'message' => 'Surface Type is required.']);
    exit;
}
$allowed_surfaces = ['glass','paper','wood','plastic','metal','ceramic','fabric'];
if (!in_array($surface_type, $allowed_surfaces)) {
    echo json_encode(['success' => false, 'message' => 'Invalid Surface Type.']);
    exit;
}

if ($humidity !== null && ($humidity < 0 || $humidity > 100)) {
    echo json_encode(['success' => false, 'message' => 'Humidity must be between 0 and 100.']);
    exit;
}

$allowed_irritation = ['none','mild','moderate','severe'];
if (!in_array($irritation_status, $allowed_irritation)) {
    echo json_encode(['success' => false, 'message' => 'Invalid Irritation Status.']);
    exit;
}

// Verify if the trial belongs to this student
if ($trial_id !== null) {
    $trial_stmt = $pdo->prepare("SELECT id FROM fingerprint_tests WHERE id = ? AND student_id = ?");
    $trial_stmt->execute([$trial_id, $student_id]);
    if ($trial_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid trial connection.']);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO safety_climate_log (student_id, trial_id, powder_type, surface_type, temperature, humidity, health_feedback, irritation_status, remarks, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $student_id,
        $trial_id,
        $powder_type,
        $surface_type,
        $temperature,
        $humidity,
        $health_feedback,
        $irritation_status,
        $remarks
    ]);

    // Fetch the inserted record's log date
    $log_id = $pdo->lastInsertId();
    $log_stmt = $pdo->prepare("
        SELECT scl.*, DATE_FORMAT(scl.created_at, '%M %d, %Y %H:%i') as formatted_date, ft.trial_id as trial_code
        FROM safety_climate_log scl
        LEFT JOIN fingerprint_tests ft ON ft.id = scl.trial_id
        WHERE scl.id = ?
    ");
    $log_stmt->execute([$log_id]);
    $new_log = $log_stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Safety and climate log submitted successfully.',
        'data' => $new_log
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
