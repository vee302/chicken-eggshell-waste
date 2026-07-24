<?php
// includes/gdrive_service.php — Google Drive API v3 Native Service Account Module

function get_gdrive_access_token()
{
    static $cached_token = null;
    static $token_expires_at = 0;

    if ($cached_token !== null && time() < $token_expires_at - 60) {
        return $cached_token;
    }

    $client_email = null;
    $private_key = null;

    $cred_file = dirname(__DIR__) . '/config/gdrive_credentials.json';
    if (file_exists($cred_file)) {
        $creds = json_decode(file_get_contents($cred_file), true);
        if ($creds) {
            $client_email = $creds['client_email'] ?? null;
            $private_key  = $creds['private_key'] ?? null;
        }
    }

    if (!$client_email || !$private_key) {
        $gdrive_json_env = env('GDRIVE_CREDENTIALS_JSON');
        if (!empty($gdrive_json_env)) {
            $creds = json_decode($gdrive_json_env, true);
            if ($creds) {
                $client_email = $creds['client_email'] ?? null;
                $private_key  = $creds['private_key'] ?? null;
            }
        }
    }

    if (!$client_email || !$private_key) {
        $client_email = env('GDRIVE_CLIENT_EMAIL');
        $private_key  = env('GDRIVE_PRIVATE_KEY');
    }

    if (!$client_email || !$private_key) {
        error_log("Google Drive Credentials missing from file and environment variables.");
        return false;
    }

    $now = time();
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $claim = json_encode([
        'iss' => $client_email,
        'scope' => 'https://www.googleapis.com/auth/drive',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));

    $signatureInput = $base64UrlHeader . "." . $base64UrlClaim;
    $privateKey = str_replace('\n', "\n", $private_key);
    $pkey = openssl_pkey_get_private($privateKey);
    if (!$pkey) {
        error_log("Google Drive Private Key parsing failed.");
        return false;
    }

    $binarySignature = '';
    $success = openssl_sign($signatureInput, $binarySignature, $pkey, OPENSSL_ALGO_SHA256);
    if (!$success) {
        error_log("Google Drive JWT signing failed.");
        return false;
    }

    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($binarySignature));
    $jwt = $signatureInput . "." . $base64UrlSignature;

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("Google Drive Access Token Request failed: HTTP $httpCode Response: $response");
        return false;
    }

    $tokenData = json_decode($response, true);
    if (!empty($tokenData['access_token'])) {
        $cached_token = $tokenData['access_token'];
        $token_expires_at = time() + ($tokenData['expires_in'] ?? 3600);
        return $cached_token;
    }

    return false;
}

/**
 * Upload a local file to Google Drive.
 * 
 * @param string $localFilePath Path to local file
 * @param string $fileName Target filename in Drive
 * @param string|null $folderId Optional parent Google Drive folder ID
 * @return string|false Google Drive File ID on success, false on failure
 */
function gdrive_upload_file($localFilePath, $fileName, $folderId = null)
{
    if (!file_exists($localFilePath)) {
        return false;
    }

    $token = get_gdrive_access_token();
    if (!$token) {
        return false;
    }

    if (!$folderId) {
        $folderId = env('GDRIVE_FOLDER_ID', '1ng2iHXR2KzHSBQTr-F60TwkxiVloRmym');
    }

    $mimeType = mime_content_type($localFilePath) ?: 'image/jpeg';
    $fileData = file_get_contents($localFilePath);

    $metadata = [
        'name' => $fileName
    ];
    if (!empty($folderId)) {
        $metadata['parents'] = [$folderId];
    }

    $boundary = '-------' . md5(time());
    $delimiter = "\r\n--" . $boundary . "\r\n";
    $closeDelimiter = "\r\n--" . $boundary . "--";

    $postData = $delimiter .
        "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
        json_encode($metadata) .
        $delimiter .
        "Content-Type: " . $mimeType . "\r\n" .
        "Content-Transfer-Encoding: binary\r\n\r\n" .
        $fileData .
        $closeDelimiter;

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: multipart/related; boundary=' . $boundary,
        'Content-Length: ' . strlen($postData)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (($httpCode === 200 || $httpCode === 201) && $response) {
        $resData = json_decode($response, true);
        return $resData['id'] ?? false;
    }

    error_log("gdrive_upload_file failed: HTTP $httpCode - Response: $response");
    return false;
}

/**
 * Stream binary content of a file from Google Drive directly to the client browser.
 * 
 * @param string $fileId Google Drive File ID
 * @return bool True if streamed successfully
 */
function gdrive_stream_file($fileId)
{
    $token = get_gdrive_access_token();
    if (!$token) {
        return false;
    }

    $url = "https://www.googleapis.com/drive/v3/files/" . urlencode($fileId) . "?alt=media";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($httpCode === 200 && $data) {
        if ($contentType) {
            header("Content-Type: " . $contentType);
        }
        header("Content-Length: " . strlen($data));
        echo $data;
        return true;
    }

    return false;
}

/**
 * Delete a file from Google Drive by File ID.
 * 
 * @param string $fileId Google Drive File ID
 * @return bool True if deleted successfully
 */
function gdrive_delete_file($fileId)
{
    if (empty($fileId))
        return false;

    $token = get_gdrive_access_token();
    if (!$token)
        return false;

    $url = "https://www.googleapis.com/drive/v3/files/" . urlencode($fileId);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200 || $httpCode === 204);
}
