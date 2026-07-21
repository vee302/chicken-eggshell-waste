<?php
// student/ajax_upload_fingerprint.php — Student AJAX Upload Fingerprint with Anti-Spam & Duplicate Submission Protection
@ob_start();
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Helper function to send standard JSON response with new token and exit
function sendResponse($success, $message, $data = null) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    $resp = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $resp['data'] = $data;
    }
    // Always include a fresh submit token if it exists in session
    if (isset($_SESSION['submit_token'])) {
        $resp['new_token'] = $_SESSION['submit_token'];
    }
    echo json_encode($resp);
    exit;
}

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'criminology_student') {
    sendResponse(false, 'Unauthorized access.');
}

// CSRF Token validation
$headers = getallheaders();
$csrf_token = $_POST['csrf_token'] ?? $headers['X-CSRF-Token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    sendResponse(false, 'Invalid CSRF token.');
}

// Submission (Idempotency) Token validation
$submission_token = $_POST['submission_token'] ?? '';
if (empty($submission_token) || empty($_SESSION['submit_token']) || !hash_equals($_SESSION['submit_token'], $submission_token)) {
    // Generate new token on failure
    $_SESSION['submit_token'] = bin2hex(random_bytes(32));
    sendResponse(false, 'Invalid or expired submission token.');
}

// Invalidate token immediately
unset($_SESSION['submit_token']);
// Generate a fresh token ready for the next request/response
$_SESSION['submit_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['fingerprint_image'])) {
    sendResponse(false, 'No image file uploaded.');
}

$file         = $_FILES['fingerprint_image'];
$powder_type  = trim($_POST['powder_type'] ?? '');
$surface_type = trim($_POST['surface_type'] ?? '');
$label        = trim($_POST['image_label'] ?? '');
$student_id   = $_SESSION['user_id'] ?? 0;

$allowed_exts  = ['jpg', 'jpeg', 'png', 'webp'];
$max_bytes     = 5 * 1024 * 1024; // 5 MB

if (!$powder_type || !$surface_type) {
    sendResponse(false, 'Powder Type and Surface Type are required.');
}

$allowed_surface_types = ['glass', 'plastic', 'metal', 'wood'];
if (!in_array(strtolower($surface_type), $allowed_surface_types)) {
    sendResponse(false, 'Invalid surface type. Allowed surfaces are Glass, Plastic, Metal, and Wood.');
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendResponse(false, 'File upload error code: ' . $file['error']);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_exts)) {
    sendResponse(false, 'Only JPG, JPEG, PNG and WebP images are allowed.');
}

if ($file['size'] > $max_bytes) {
    sendResponse(false, 'File size must not exceed 5 MB.');
}

// Compute the SHA-256 hash of the uploaded temporary file
$image_hash = hash_file('sha256', $file['tmp_name']);
if (!$image_hash) {
    sendResponse(false, 'Failed to process fingerprint image hash.');
}

// Generate a unique trial_id early to use as the filename
try {
    $stmt = $pdo->prepare("SELECT MAX(id) FROM fingerprint_tests");
    $stmt->execute();
    $max_id = $stmt->fetchColumn() ?: 0;
    $next_id = $max_id + 1;
    $trial_id = 'TR-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
} catch (PDOException $e) {
    sendResponse(false, 'Database error generating ID: ' . $e->getMessage());
}

$filename = $trial_id . '.' . $ext;
$dest_dir = dirname(__DIR__) . '/uploads/fingerprints/';
if (!is_dir($dest_dir)) {
    @mkdir($dest_dir, 0777, true);
} else {
    @chmod($dest_dir, 0777);
}
$dest = $dest_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    @chmod($dest, 0777);
    // Confirm the file actually exists after moving
    if (!file_exists($dest)) {
        sendResponse(false, 'Failed to verify file upload. File is missing.');
    }

    // Determine absolute paths for Python script execution
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
    $enhanced_image_path = null;
    
    $ai_msg = "";
    $ai_success = false;
    
    // Execute Python script safely checking if shell_exec is disabled
    $output = null;
    if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
        $command = "python " . escapeshellarg($python_script) . " " . escapeshellarg($dest) . " " . escapeshellarg($surface_type) . " 2>&1";
        $output = @shell_exec($command);
    }
    
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
                $enhanced_image_path = $ai_res['enhanced_image_path'] ?? null;
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

    // Wrap the entire insert process in a transaction
    $pdo->beginTransaction();
    try {
        // 1. Duplicate Image Check
        $stmt = $pdo->prepare("SELECT id FROM fingerprint_tests WHERE student_id = ? AND image_hash = ? LIMIT 1");
        $stmt->execute([$student_id, $image_hash]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            // Delete moved file to save space
            @unlink($dest);
            if ($enhanced_image_path) {
                @unlink(dirname(__DIR__) . '/uploads/fingerprint_enhanced/' . $enhanced_image_path);
            }
            sendResponse(false, 'This fingerprint image has already been submitted.');
        }

        // 2. Cooldown Check (15 seconds)
        $stmt = $pdo->prepare("SELECT id FROM fingerprint_tests WHERE student_id = ? AND submitted_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND) LIMIT 1");
        $stmt->execute([$student_id]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            // Delete moved file
            @unlink($dest);
            if ($enhanced_image_path) {
                @unlink(dirname(__DIR__) . '/uploads/fingerprint_enhanced/' . $enhanced_image_path);
            }
            sendResponse(false, 'Please wait 15 seconds before submitting another fingerprint evaluation.');
        }

        // Insert trial record
        $stmt = $pdo->prepare("
            INSERT INTO fingerprint_tests 
                (trial_id, student_id, image_path, enhanced_image_path, image_label, image_hash, powder_type, surface_type, 
                 ridge_clarity_score, visibility_score, adhesion_score, contrast_score, accuracy_score, 
                 status, submitted_at, ai_evaluated_at, evaluation_source, ai_accuracy_score)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_validation', NOW(), ?, ?, ?)
        ");
        $stmt->execute([
            $trial_id, $student_id, $filename, $enhanced_image_path, $label, $image_hash, $powder_type, $surface_type,
            $clarity, $visibility, $adhesion, $contrast, $accuracy, 
            $ai_evaluated_at, $evaluation_source, $ai_accuracy
        ]);

        $inserted_id = $pdo->lastInsertId();
        $pdo->commit();

        sendResponse(true, $ai_msg, [
            'id' => $inserted_id,
            'trial_id' => $trial_id,
            'image_path' => $filename,
            'enhanced_image_path' => $enhanced_image_path,
            'image_label' => $label ? $label : 'Untitled',
            'powder_type' => $powder_type,
            'surface_type' => $surface_type,
            'status' => 'pending_validation',
            'submitted_at' => date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        @unlink($dest);
        if ($enhanced_image_path) {
            @unlink(dirname(__DIR__) . '/uploads/fingerprint_enhanced/' . $enhanced_image_path);
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
} else {
    sendResponse(false, 'Failed to save image. Check uploads directory permissions.');
}
exit;
