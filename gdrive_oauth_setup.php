<?php
// gdrive_oauth_setup.php — Google OAuth 2.0 User Refresh Token Generator Tool
session_start();
header('Content-Type: text/html; charset=utf-8');

$config_file = __DIR__ . '/config/gdrive_user_credentials.json';
$env_file    = __DIR__ . '/.env';

$message = '';
$error   = '';

// Load existing config if available
$user_creds = [];
if (file_exists($config_file)) {
    $user_creds = json_decode(file_get_contents($config_file), true) ?: [];
}

$client_id     = $_POST['client_id'] ?? $user_creds['client_id'] ?? '';
$client_secret = $_POST['client_secret'] ?? $user_creds['client_secret'] ?? '';
$auth_code     = $_POST['auth_code'] ?? '';

// Action: Exchange Auth Code for Refresh Token
if (isset($_POST['action']) && $_POST['action'] === 'exchange') {
    if (empty($client_id) || empty($client_secret) || empty($auth_code)) {
        $error = 'Please fill in Client ID, Client Secret, and Authorization Code.';
    } else {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'code'          => trim($auth_code),
            'client_id'     => trim($client_id),
            'client_secret' => trim($client_secret),
            'redirect_uri'  => 'https://developers.google.com/oauthplayground', // or oob
            'grant_type'    => 'authorization_code'
        ]));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $resData = json_decode($response, true);

        if (($httpCode === 200 || $httpCode === 201) && !empty($resData['refresh_token'])) {
            $refreshToken = $resData['refresh_token'];
            
            // Save to config/gdrive_user_credentials.json
            $saveData = [
                'client_id'     => trim($client_id),
                'client_secret' => trim($client_secret),
                'refresh_token' => $refreshToken,
                'updated_at'    => date('Y-m-d H:i:s')
            ];
            file_put_contents($config_file, json_encode($saveData, JSON_PRETTY_PRINT));

            // Update .env file
            if (file_exists($env_file)) {
                $envContent = file_get_contents($env_file);
                $updateEnv = function($key, $val, &$content) {
                    if (strpos($content, $key . '=') !== false) {
                        $content = preg_replace('/^' . preg_quote($key) . '=.*/m', $key . '="' . addslashes($val) . '"', $content);
                    } else {
                        $content .= "\n" . $key . '="' . addslashes($val) . '"';
                    }
                };
                $updateEnv('GDRIVE_CLIENT_ID', trim($client_id), $envContent);
                $updateEnv('GDRIVE_CLIENT_SECRET', trim($client_secret), $envContent);
                $updateEnv('GDRIVE_REFRESH_TOKEN', $refreshToken, $envContent);
                file_put_contents($env_file, $envContent);
            }

            $message = 'SUCCESS! Refresh Token generated & saved successfully! Storage quota linked to your 2TB Google Account.';
            $user_creds = $saveData;
        } else {
            // Try with urn:ietf:wg:oauth:2.0:oob redirect_uri fallback
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'code'          => trim($auth_code),
                'client_id'     => trim($client_id),
                'client_secret' => trim($client_secret),
                'redirect_uri'  => 'urn:ietf:wg:oauth:2.0:oob',
                'grant_type'    => 'authorization_code'
            ]));

            $response2 = curl_exec($ch);
            $httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $resData2 = json_decode($response2, true);

            if (($httpCode2 === 200 || $httpCode2 === 201) && !empty($resData2['refresh_token'])) {
                $refreshToken = $resData2['refresh_token'];
                $saveData = [
                    'client_id'     => trim($client_id),
                    'client_secret' => trim($client_secret),
                    'refresh_token' => $refreshToken,
                    'updated_at'    => date('Y-m-d H:i:s')
                ];
                file_put_contents($config_file, json_encode($saveData, JSON_PRETTY_PRINT));
                $message = 'SUCCESS! Refresh Token generated & saved successfully!';
                $user_creds = $saveData;
            } else {
                $error = 'Failed to exchange authorization code. Error: ' . ($resData['error_description'] ?? $resData['error'] ?? $response);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Drive OAuth Setup — Green Forensics</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; }
        .card { max-width: 680px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 2rem; border: 1px solid #334155; }
        h1 { font-size: 1.5rem; color: #4ade80; margin-top: 0; }
        p { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; }
        label { display: block; margin-top: 1rem; font-weight: 600; font-size: 0.85rem; color: #cbd5e1; }
        input[type="text"] { width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: #fff; box-sizing: border-box; margin-top: 0.25rem; }
        .btn { background: #22c55e; color: #0f172a; font-weight: 700; border: none; padding: 0.85rem 1.5rem; border-radius: 6px; cursor: pointer; margin-top: 1.5rem; width: 100%; font-size: 1rem; }
        .btn:hover { background: #16a34a; color: #fff; }
        .alert-success { background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; color: #4ade80; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        ol { color: #cbd5e1; padding-left: 1.2rem; font-size: 0.9rem; line-height: 1.6; }
        code { background: #0f172a; color: #38bdf8; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        a { color: #38bdf8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔑 Google Drive OAuth 2.0 User Setup</h1>
        <p>Connect your personal <strong>yvezjayveegesmundo@gmail.com (2TB Storage)</strong> account so files upload directly into your Google Drive folder.</p>

        <?php if ($message): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="background: rgba(56, 189, 248, 0.08); border-left: 4px solid #38bdf8; padding: 1rem; border-radius: 0 6px 6px 0; margin-bottom: 1.5rem;">
            <strong style="color: #38bdf8;">Easy Quick Setup Guide (Google OAuth Playground):</strong>
            <ol>
                <li>Open <a href="https://developers.google.com/oauthplayground" target="_blank">Google OAuth 2.0 Playground</a>.</li>
                <li>Click the ⚙️ <strong>Gear Icon (OAuth 2.0 Configuration)</strong> at top-right:
                    <ul style="margin-top: 4px;">
                        <li>Check <strong>"Use your own OAuth credentials"</strong>.</li>
                        <li>Enter your <code>OAuth Client ID</code> & <code>OAuth Client Secret</code> from Google Cloud Console.</li>
                    </ul>
                </li>
                <li>In Step 1 (Select & authorize APIs), scroll down to <strong>Drive API v3</strong> and select <code>https://www.googleapis.com/auth/drive.file</code>.</li>
                <li>Click <strong>Authorize APIs</strong> and sign in with <strong>yvezjayveegesmundo@gmail.com</strong>.</li>
                <li>In Step 2, click <strong>Exchange authorization code for tokens</strong>.</li>
                <li>Copy the <code>refresh_token</code> and paste it in your <code>.env</code> file as <code>GDRIVE_REFRESH_TOKEN="..."</code>!</li>
            </ol>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="exchange">

            <label>OAuth 2.0 Client ID:</label>
            <input type="text" name="client_id" value="<?= htmlspecialchars($client_id) ?>" placeholder="e.g. 123456789-abc.apps.googleusercontent.com" required>

            <label>OAuth 2.0 Client Secret:</label>
            <input type="text" name="client_secret" value="<?= htmlspecialchars($client_secret) ?>" placeholder="e.g. GOCSPX-abc123xyz..." required>

            <label>Authorization Code / Refresh Token:</label>
            <input type="text" name="auth_code" value="<?= htmlspecialchars($auth_code) ?>" placeholder="Paste Authorization Code from Google OAuth" required>

            <button type="submit" class="btn">Save & Authorize Storage</button>
        </form>
    </div>
</body>
</html>
