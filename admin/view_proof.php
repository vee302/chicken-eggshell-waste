<?php
// admin/view_proof.php - Secure Proof of Affiliation Viewer for Super Admin
require_once '../config.php';
require_once 'auth.php';

// Session Role check - Only Super Admin can access
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    http_response_code(403);
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <title>Access Denied</title>
        <style>
            body { font-family: sans-serif; text-align: center; padding: 50px; background-color: #f8f9fa; color: #721c24; }
            .card { max-width: 500px; margin: auto; padding: 30px; background: white; border: 1px solid #f5c6cb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class='card'>
            <h2>403 - Access Denied</h2>
            <p>You do not have permission to view this resource. This document is restricted to Super Administrators only.</p>
        </div>
    </body>
    </html>";
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    http_response_code(400);
    echo "Bad Request: Invalid User ID.";
    exit;
}

try {
    // Fetch proof of affiliation from DB
    $stmt = $pdo->prepare("SELECT proof_of_affiliation FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $proof_path = $stmt->fetchColumn();

    if (empty($proof_path)) {
        http_response_code(404);
        echo "No proof of affiliation file was uploaded for this user.";
        exit;
    }

    // Resolve absolute path and prevent directory traversal
    $base_dir = dirname(__DIR__); // root directory of the application
    $file_path = $base_dir . '/' . $proof_path;
    $real_path = realpath($file_path);
    $allowed_dir = realpath($base_dir . '/uploads/proofs');

    if ($real_path === false || !file_exists($real_path)) {
        http_response_code(404);
        echo "The requested file could not be found on the server.";
        exit;
    }

    // Path traversal validation
    if ($allowed_dir === false || strpos($real_path, $allowed_dir) !== 0) {
        http_response_code(403);
        echo "Forbidden: Invalid file path.";
        exit;
    }

    // Determine content type
    $mime = null;
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($real_path);
    }
    if (!$mime) {
        $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
        $mimes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'pdf'  => 'application/pdf'
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';
    }

    // Clear buffer to avoid issues
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Output secure headers and stream the file
    header("Content-Type: " . $mime);
    header("Content-Length: " . filesize($real_path));
    header("Content-Disposition: inline; filename=\"" . basename($real_path) . "\"");
    header("Cache-Control: private, max-age=86400");
    
    readfile($real_path);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Database error: " . htmlspecialchars($e->getMessage());
    exit;
}
