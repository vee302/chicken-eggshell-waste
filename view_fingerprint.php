<?php
// view_fingerprint.php — Secure Fingerprint Image Viewer
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo "Unauthorized access: Please log in first.";
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
$user_id   = $_SESSION['user_id']  ?? 0;

// Get test_id from URL
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
if ($test_id <= 0) {
    http_response_code(400);
    echo "Bad Request: Invalid trial ID.";
    exit;
}

try {
    // Query fingerprint_tests
    $stmt = $pdo->prepare("SELECT student_id, image_path, enhanced_image_path, status FROM fingerprint_tests WHERE id = ?");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        http_response_code(404);
        echo "Not Found: Record not found.";
        exit;
    }

    $owner_id            = (int)$test['student_id'];
    $image_path          = $test['image_path'];
    $enhanced_image_path = $test['enhanced_image_path'];
    $status              = $test['status'];

    // Check authorization:
    // - super_admin
    // - faculty_researcher
    // - alumni_police_partner for approved records only
    // - criminology_student only if they own the record
    $authorized = false;
    if ($user_role === 'super_admin' || $user_role === 'faculty_researcher') {
        $authorized = true;
    } elseif ($user_role === 'alumni_police_partner') {
        if ($status === 'approved') {
            $authorized = true;
        }
    } elseif ($user_role === 'criminology_student') {
        if ($user_id === $owner_id) {
            $authorized = true;
        }
    }

    if (!$authorized) {
        http_response_code(403);
        echo "Unauthorized access: You do not have permission to view this asset.";
        exit;
    }

    $mode = $_GET['mode'] ?? '';
    if (isset($_GET['enhanced']) && $_GET['enhanced'] == 1) {
        $mode = 'enhanced';
    }

    if ($mode === 'enhanced') {
        if (empty($enhanced_image_path)) {
            http_response_code(404);
            echo "Not Found: No enhanced image available for this record.";
            exit;
        }
        $filepath = __DIR__ . '/uploads/fingerprint_enhanced/' . $enhanced_image_path;
    } else {
        if (empty($image_path)) {
            http_response_code(404);
            echo "Not Found: No original image associated with this record.";
            exit;
        }
        $filepath = __DIR__ . '/uploads/fingerprints/' . $image_path;
    }

    // Check if the file exists on the server
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo "Not Found: Image file is missing on the server.";
        exit;
    }

    // Get Content-Type of the file
    $mime = mime_content_type($filepath);
    if (!$mime || !str_starts_with($mime, 'image/')) {
        // Fallback mime type based on extension
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if ($ext === 'png') $mime = 'image/png';
        elseif ($ext === 'webp') $mime = 'image/webp';
        else $mime = 'image/jpeg';
    }

    header("Content-Type: " . $mime);
    header("Content-Length: " . filesize($filepath));
    readfile($filepath);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Internal Server Error: " . $e->getMessage();
    exit;
}
