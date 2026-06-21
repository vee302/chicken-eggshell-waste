<?php
ob_start(); // Buffer output to prevent warnings or database notices from corrupting the JSON payload
// support-assistant/support_chat_api.php - Backend API Handler for Gemini AI Support Assistant
header('Content-Type: application/json');

require_once dirname(__DIR__) . "/config.php";

// Helper function to safely output JSON and exit
function send_response($success, $reply, $http_code = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($http_code);
    echo json_encode([
        "success" => $success,
        "reply" => $reply
    ]);
    exit;
}

// Ensure request is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    send_response(false, "Method Not Allowed", 405);
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    send_response(false, "Message cannot be empty.", 400);
}

// 1. Password Security & Account Unlock Check
$lowerMessage = strtolower($message);

// Check for unlock request intent first
$unlockKeywords = [
    'unlock account',
    'locked account',
    'cannot login',
    'can\'t login',
    'cant login',
    'login failed',
    'forgot password',
    'request unlock',
    'locked',
    'lockout'
];
$matchedUnlock = false;
foreach ($unlockKeywords as $keyword) {
    if (strpos($lowerMessage, $keyword) !== false) {
        $matchedUnlock = true;
        break;
    }
}

if ($matchedUnlock) {
    send_response(
        true,
        "If your account is locked after multiple failed login attempts, you may wait 15 minutes or submit an unlock request for Super Admin review. Open the Request Unlock page here: request_unlock.php"
    );
}

// Fallback password warning
if (strpos($lowerMessage, 'password') !== false || strpos($lowerMessage, 'passcode') !== false || strpos($lowerMessage, 'credential') !== false) {
    send_response(
        true,
        "For security reasons, never share your password. If you need help with your password or your account is locked, please use the Request Unlock page or contact the Super Administrator directly."
    );
}

// Developer query interceptor
$developerKeywords = [
    'developer',
    'developers',
    'gumawa',
    'creator',
    'creators',
    'who made'
];
$matchedDeveloper = false;
foreach ($developerKeywords as $keyword) {
    if (strpos($lowerMessage, $keyword) !== false) {
        $matchedDeveloper = true;
        break;
    }
}

if ($matchedDeveloper) {
    send_response(
        true,
        "Ang developer nitong system ay si Yvez Jayvee Gesmundo ang full stock developer. ang frontend ay si Marron Brimbuela at si Kevin Cloud Fajardo."
    );
}


// Helper for local diagnostic logging
function debug_log($message) {
    $file = dirname(__DIR__) . '/debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
}

// 2. Fetch Gemini API Key
$apiKey = env('GEMINI_API_KEY');
if (empty($apiKey)) {
    $err = "Gemini API Error: GEMINI_API_KEY is not defined or empty in the environment configuration.";
    error_log($err);
    debug_log($err);
    send_response(false, "Sorry, I cannot connect to the support assistant right now. Please contact the Super Administrator.");
}

// 3. Fetch Gemini Model (non-hardcoded)
$model = env('GEMINI_MODEL', 'gemini-1.5-flash');
debug_log("Attempting call with Model: $model");

// 4. Call Google Gemini API
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apiKey;

$systemInstruction = "You are the Green Forensics Support Assistant. Help users with the Green Forensics Evaluating System. Answer clearly, politely, and briefly. You can help with registration, pending accounts, login lockout, account unlock requests, fingerprint image upload, webcam capture, AI-assisted image quality evaluation, faculty validation, Terms of Use, Privacy Policy, and role-based dashboards. For account lockouts, password resets, failed logins, or unlock requests, guide the user to visit request_unlock.php. Do not ask for their password or private credentials. Fingerprint images are used only for academic research evaluation and image quality assessment, not biometric identification. If a user asks about locked account, login failed, forgot password, cannot login, or requesting an unlock, you must respond with: 'If your account is locked after multiple failed login attempts, you may wait 15 minutes or submit an unlock request for Super Admin review. Open the Request Unlock page here: request_unlock.php'. If a user asks who the developer of the system is, respond with: 'Ang developer nitong system ay si Yvez Jayvee Gesmundo ang full stock developer. ang frontend ay si Marron Brimbuela at si Kevin Cloud Fajardo.' If the user greets you, respond warmly and ask how you can help.";

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $message]
            ]
        ]
    ],
    "systemInstruction" => [
        "parts" => [
            ["text" => $systemInstruction]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.4,
        "maxOutputTokens" => 150
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

// Disable SSL verification locally if XAMPP setup lacks certificates
if (env('APP_ENV') !== 'production') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    debug_log("Local environment detected. Disabled cURL SSL verification.");
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// If request fails or API returns error response code
if ($response === false || $httpCode !== 200) {
    $errMessage = "Gemini API Error. HTTP Code: $httpCode. cURL Error: $curlError. Response: " . ($response !== false ? $response : 'No response');
    error_log($errMessage);
    debug_log($errMessage);
    send_response(false, "Sorry, I cannot connect to the support assistant right now. Please contact the Super Administrator.");
}

$responseData = json_decode($response, true);
$replyText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($replyText)) {
    $errMessage = "Gemini API Error: empty response text structure. Response: " . $response;
    error_log($errMessage);
    debug_log($errMessage);
    send_response(false, "Sorry, I cannot connect to the support assistant right now. Please contact the Super Administrator.");
}

send_response(true, trim($replyText));
?>
