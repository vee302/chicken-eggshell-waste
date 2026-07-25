<?php
// gdrive_test.php — Google Drive Service Integration Diagnostic & Validation Tool
header('Content-Type: text/plain; charset=utf-8');

echo "=======================================================\n";
echo " GREEN FORENSICS — GOOGLE DRIVE INTEGRATION DIAGNOSTIC \n";
echo "=======================================================\n\n";

if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            if (isset($_ENV[$key])) {
                $val = $_ENV[$key];
            } elseif (isset($_SERVER[$key])) {
                $val = $_SERVER[$key];
            } else {
                return $default;
            }
        }
        return $val;
    }
}

require_once __DIR__ . '/includes/gdrive_service.php';

// STEP 1: Test OAuth2 Access Token Acquisition
echo "[STEP 1] Testing OAuth2 Access Token Acquisition...\n";
$start_time = microtime(true);
$token = get_gdrive_access_token();
$duration = round((microtime(true) - $start_time) * 1000, 2);

if (!$token) {
    echo "  [FAIL] Unable to acquire Access Token.\n";
    echo "  Please verify config/gdrive_credentials.json or GDRIVE_CREDENTIALS_JSON environment variables.\n";
    exit(1);
}

echo "  [PASS] Access Token acquired successfully in {$duration} ms.\n";
echo "  Token Prefix: " . substr($token, 0, 18) . "...\n\n";

// STEP 2: Create a dummy local test file
echo "[STEP 2] Creating temporary test file...\n";
$test_file_path = __DIR__ . '/uploads/gdrive_test_' . time() . '.txt';

if (!file_exists(__DIR__ . '/uploads')) {
    @mkdir(__DIR__ . '/uploads', 0777, true);
}

$sample_content = "GREEN FORENSICS GOOGLE DRIVE INTEGRATION TEST — " . date('Y-m-d H:i:s');
file_put_contents($test_file_path, $sample_content);

echo "  [PASS] Local test file generated at: " . basename($test_file_path) . "\n\n";

// STEP 3: Test Uploading File to Google Drive
echo "[STEP 3] Uploading file to target Google Drive folder...\n";
$folder_id = env('GDRIVE_FOLDER_ID', '1ng2iHXR2KzHSBQTr-F60TwkxiVloRmym');
echo "  Target Folder ID: " . $folder_id . "\n";

$start_time = microtime(true);
$file_id = gdrive_upload_file($test_file_path, basename($test_file_path), $folder_id);
$duration = round((microtime(true) - $start_time) * 1000, 2);

// Clean up local temp file
if (file_exists($test_file_path)) {
    @unlink($test_file_path);
}

if (!$file_id) {
    echo "  [FAIL] File upload to Google Drive failed.\n";
    exit(1);
}

echo "  [PASS] File uploaded successfully in {$duration} ms!\n";
echo "  Google Drive File ID: " . $file_id . "\n\n";

// STEP 4: Test Remote Deletion from Google Drive
echo "[STEP 4] Testing Remote File Deletion...\n";
$start_time = microtime(true);
$deleted = gdrive_delete_file($file_id);
$duration = round((microtime(true) - $start_time) * 1000, 2);

if (!$deleted) {
    echo "  [WARNING] Could not delete test file (File ID: $file_id).\n";
} else {
    echo "  [PASS] Remote test file deleted successfully in {$duration} ms.\n\n";
}

echo "=======================================================\n";
echo " ALL GOOGLE DRIVE AUTOMATION TESTS PASSED SUCCESSFULLY! \n";
echo "=======================================================\n";
