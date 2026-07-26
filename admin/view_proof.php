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
    $stmt = $pdo->prepare("SELECT proof_of_affiliation, full_name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_row || empty($user_row['proof_of_affiliation'])) {
        http_response_code(404);
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Proof Not Found</title><style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f8f9fa;color:#333;}.card{max-width:500px;margin:auto;padding:30px;background:white;border:1px solid #ddd;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.05);}</style></head><body><div class='card'><h2>No Proof File</h2><p>No proof of affiliation file was uploaded for this user.</p></div></body></html>";
        exit;
    }

    $proof_path = $user_row['proof_of_affiliation'];
    $base_dir = dirname(__DIR__); // root directory of the application
    
    // Normalize slashes
    $clean_path = str_replace(['\\', '/'], '/', trim($proof_path));
    
    // Candidate paths to check
    $candidate_paths = [
        $base_dir . '/' . ltrim($clean_path, '/'),
        $clean_path,
        $base_dir . '/uploads/proofs/' . basename($clean_path),
        __DIR__ . '/' . ltrim($clean_path, '/')
    ];

    $real_path = false;
    foreach ($candidate_paths as $cand) {
        if (!empty($cand) && file_exists($cand) && !is_dir($cand)) {
            $real_path = realpath($cand) ?: $cand;
            break;
        }
    }

    if ($real_path === false || !file_exists($real_path)) {
        http_response_code(404);
        $user_name = htmlspecialchars($user_row['full_name'] ?? 'User');
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>File Not Found</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; text-align: center; padding: 50px; background-color: #f4f6f8; color: #333; }
                .card { max-width: 520px; margin: auto; padding: 30px; background: white; border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
                h2 { color: #d9534f; margin-top: 0; }
                p { font-size: 0.95rem; color: #555; line-height: 1.6; }
                .file-path { background: #f8f9fa; padding: 8px 12px; border-radius: 6px; font-family: monospace; font-size: 0.85rem; color: #666; word-break: break-all; margin: 15px 0; border: 1px solid #eee; }
                .btn { display: inline-block; padding: 8px 16px; background: #2D6A4F; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h2>File Not Found on Server</h2>
                <p>The uploaded proof document for <strong>{$user_name}</strong> is not available on the server filesystem.</p>
                <div class='file-path'>" . htmlspecialchars($proof_path) . "</div>
                <p>This can happen if the account was registered before mandatory file uploads were enforced, or if server files were reset.</p>
                <a href='javascript:window.close()' class='btn'>Close Window</a>
            </div>
        </body>
        </html>";
        exit;
    }

    // Path traversal validation
    $norm_real = strtolower(str_replace('\\', '/', $real_path));
    $norm_uploads = strtolower(str_replace('\\', '/', $base_dir . '/uploads'));
    if (strpos($norm_real, $norm_uploads) === false) {
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
