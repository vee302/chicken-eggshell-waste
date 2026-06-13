<?php
// student/ajax_upload_fingerprint.php — Student AJAX Upload Fingerprint with Image Evaluation
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['fingerprint_image'])) {
    echo json_encode(['success' => false, 'message' => 'No image file uploaded.']);
    exit;
}

$file         = $_FILES['fingerprint_image'];
$powder_type  = trim($_POST['powder_type'] ?? '');
$surface_type = trim($_POST['surface_type'] ?? '');
$label        = trim($_POST['image_label'] ?? '');
$student_id   = $_SESSION['user_id'] ?? 0;

$allowed_exts  = ['jpg', 'jpeg', 'png', 'webp'];
$max_bytes     = 5 * 1024 * 1024; // 5 MB

if (!$powder_type || !$surface_type) {
    echo json_encode(['success' => false, 'message' => 'Powder Type and Surface Type are required.']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $file['error']]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_exts)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG and WebP images are allowed.']);
    exit;
}

if ($file['size'] > $max_bytes) {
    echo json_encode(['success' => false, 'message' => 'File size must not exceed 5 MB.']);
    exit;
}

$filename = 'fp_' . $student_id . '_' . time() . '.' . $ext;
$dest_dir = '../uploads/fingerprints/';
if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0777, true);
}
$dest = $dest_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    // Determine absolute paths for Python script execution
    $dest_abs = dirname(__DIR__) . '/uploads/fingerprints/' . $filename;
    $python_script = dirname(__DIR__) . '/python/evaluate_fingerprint.py';
    
    // Default score fields to NULL (Awaiting Faculty Validation)
    $clarity = null;
    $visibility = null;
    $adhesion = null;
    $contrast = null;
    $accuracy = null;
    $ai_accuracy = null;
    $ai_evaluated_at = null;
    $evaluation_source = 'AI Preliminary';
    
    $ai_msg = "";
    $ai_success = false;
    
    // Execute Python script
    $command = "python " . escapeshellarg($python_script) . " " . escapeshellarg($dest_abs) . " 2>&1";
    $output = shell_exec($command);
    
    if ($output === null || empty(trim($output))) {
        // Python missing or command failed
        $ai_msg = "AI evaluation service is currently unavailable. Please contact the administrator.";
    } else {
        $ai_res = json_decode($output, true);
        if ($ai_res === null || json_last_error() !== JSON_ERROR_NONE) {
            // JSON parsing error or python script output unexpected format
            $ai_msg = "AI evaluation service is currently unavailable. Please contact the administrator.";
        } else {
            if (isset($ai_res['success']) && $ai_res['success'] === true) {
                // Evaluation succeeded
                $clarity = $ai_res['ridge_clarity_score'];
                $visibility = $ai_res['visibility_score'];
                $adhesion = $ai_res['adhesion_score'];
                $contrast = $ai_res['contrast_score'];
                $accuracy = $ai_res['accuracy_score'];
                $ai_accuracy = $ai_res['accuracy_score'];
                $ai_evaluated_at = date('Y-m-d H:i:s');
                $ai_success = true;
                $ai_msg = "Fingerprint image uploaded successfully and evaluated using automated image evaluation.";
            } else {
                // Script caught an error (e.g. library missing or invalid image format)
                if (isset($ai_res['message']) && strpos($ai_res['message'], 'Required Python packages') !== false) {
                    $ai_msg = "AI evaluation service is currently unavailable. Please contact the administrator.";
                } else {
                    $ai_msg = "Awaiting Faculty Evaluation.";
                }
            }
        }
    }

    try {
        // Generate a unique trial_id
        $stmt = $pdo->prepare("SELECT MAX(id) FROM fingerprint_tests");
        $stmt->execute();
        $max_id = $stmt->fetchColumn() ?: 0;
        $next_id = $max_id + 1;
        $trial_id = 'TR-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

        // Insert trial record
        $stmt = $pdo->prepare("
            INSERT INTO fingerprint_tests 
                (trial_id, student_id, image_path, image_label, powder_type, surface_type, 
                 ridge_clarity_score, visibility_score, adhesion_score, contrast_score, accuracy_score, 
                 status, submitted_at, ai_evaluated_at, evaluation_source, ai_accuracy_score)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_validation', NOW(), ?, ?, ?)
        ");
        $stmt->execute([
            $trial_id, $student_id, $filename, $label, $powder_type, $surface_type,
            $clarity, $visibility, $adhesion, $contrast, $accuracy, 
            $ai_evaluated_at, $evaluation_source, $ai_accuracy
        ]);

        // Output response
        echo json_encode([
            'success' => true,
            'message' => $ai_success ? $ai_msg : $ai_msg,
            'data' => [
                'id' => $pdo->lastInsertId(),
                'trial_id' => $trial_id,
                'image_path' => $filename,
                'image_label' => $label ? $label : 'Untitled',
                'powder_type' => $powder_type,
                'surface_type' => $surface_type,
                'status' => 'pending_validation',
                'submitted_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save image. Check uploads directory permissions.']);
}
exit;
