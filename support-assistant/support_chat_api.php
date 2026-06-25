<?php
ob_start(); // Buffer output to prevent warnings or database notices from corrupting the JSON payload
// support-assistant/support_chat_api.php - Backend API Handler for Gemini AI Support Assistant
header('Content-Type: application/json');

require_once dirname(__DIR__) . "/config.php";

// Helper function to safely output JSON and exit
function send_response($success, $reply, $source = 'offline', $http_code = 200)
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($http_code);
    echo json_encode([
        "success" => $success,
        "reply" => $reply,
        "source" => $source
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
        "If your account is locked after multiple failed login attempts, you may wait 15 minutes or submit an unlock request for Super Admin review. Open the Request Unlock page here: request_unlock.php",
        "offline"
    );
}

// Fallback password warning
if (strpos($lowerMessage, 'password') !== false || strpos($lowerMessage, 'passcode') !== false || strpos($lowerMessage, 'credential') !== false) {
    send_response(
        true,
        "For security reasons, never share your password. If you need help with your password or your account is locked, please use the Request Unlock page or contact the Super Administrator directly.",
        "offline"
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
        "Ang developer nitong system ay si Yvez Jayvee Gesmundo ang full stock developer. ang frontend ay si Marron Brimbuela at si Kevin Cloud Fajardo.",
        "offline"
    );
}


// Helper for local diagnostic logging
function debug_log($message, $is_error = false)
{
    $env = env('APP_ENV', 'production');
    $isLocalDev = ($env === 'local' || $env === 'development');
    if ($isLocalDev) {
        $file = dirname(__DIR__) . '/debug_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
        if ($is_error) {
            error_log($message);
        }
    }
}

// Offline fallback responder for common Green Forensics questions
function getOfflineSupportAnswer($message)
{
    $lowerMessage = strtolower(trim($message));

    // 1. Account Lock / Unlock / Login issues
    $unlockKeywords = [
        'unlock account', 'locked account', 'cannot login', 'can\'t login', 
        'cant login', 'login failed', 'forgot password', 'request unlock', 
        'locked', 'lockout'
    ];
    foreach ($unlockKeywords as $keyword) {
        if (strpos($lowerMessage, $keyword) !== false) {
            return "If your account is locked after multiple failed login attempts, you may wait 15 minutes or submit an unlock request for Super Admin review. Open the Request Unlock page here: request_unlock.php";
        }
    }

    // 2. Developer Information
    $developerKeywords = ['developer', 'developers', 'gumawa', 'creator', 'creators', 'who made'];
    foreach ($developerKeywords as $keyword) {
        if (strpos($lowerMessage, $keyword) !== false) {
            return "Ang developer nitong system ay si Yvez Jayvee Gesmundo ang full stock developer. ang frontend ay si Marron Brimbuela at si Kevin Cloud Fajardo.";
        }
    }

    // 3. Password / Credentials
    if (strpos($lowerMessage, 'password') !== false || strpos($lowerMessage, 'passcode') !== false || strpos($lowerMessage, 'credential') !== false) {
        return "For security reasons, never share your password. If you need help with your password or your account is locked, please use the Request Unlock page or contact the Super Administrator directly.";
    }

    // 4. Pending Validation status
    if (strpos($lowerMessage, 'pending validation') !== false || (strpos($lowerMessage, 'pending') !== false && strpos($lowerMessage, 'validation') !== false)) {
        return "Pending Validation means your fingerprint submission was received but still needs to be reviewed by the Faculty Researcher.";
    }

    // 5. Needs Revision status
    if (strpos($lowerMessage, 'needs revision') !== false || strpos($lowerMessage, 'need revision') !== false || strpos($lowerMessage, 'revision') !== false) {
        return "Needs Revision means your submission needs improvement. Read the faculty remarks and upload a clearer or corrected fingerprint image.";
    }

    // 6. Why is my account pending?
    if (strpos($lowerMessage, 'pending') !== false) {
        return "Your account is pending because the Super Administrator still needs to review and approve your registration. Please wait for approval or contact your instructor/admin.";
    }

    // 7. How to upload fingerprint image
    if (strpos($lowerMessage, 'upload') !== false) {
        return "Go to Upload Fingerprint Images, choose powder type and surface type, upload or capture the fingerprint image, then submit. Your record will be marked as Pending Validation until faculty reviews it.";
    }

    // 8. Approved status
    if (strpos($lowerMessage, 'approved') !== false || strpos($lowerMessage, 'approval') !== false) {
        return "Approved means your fingerprint submission has been reviewed and validated by the Faculty Researcher.";
    }

    // 9. Rejected status
    if (strpos($lowerMessage, 'rejected') !== false || strpos($lowerMessage, 'reject') !== false) {
        return "Rejected means the submission did not meet the required quality or information. Check the faculty remarks and submit a better image if needed.";
    }

    // 10. How does faculty validation work?
    if (strpos($lowerMessage, 'faculty validation') !== false || strpos($lowerMessage, 'validation work') !== false || strpos($lowerMessage, 'validation') !== false) {
        return "The system gives an AI preliminary score first, then the Faculty Researcher reviews the image and gives the official final score.";
    }

    // 11. Fingerprint image used for identification disclaimer
    if (strpos($lowerMessage, 'identification') !== false || strpos($lowerMessage, 'biometric') !== false || strpos($lowerMessage, 'identify') !== false) {
        return "No. In this system, fingerprint images are used for academic image quality evaluation only, not for personal biometric identification.";
    }

    // 12. How do I logout?
    if (strpos($lowerMessage, 'logout') !== false || strpos($lowerMessage, 'log out') !== false) {
        return "Tap your profile initials on the top right, then select Logout.";
    }

    // 13. Safety & Climate Log
    if (strpos($lowerMessage, 'safety') !== false || strpos($lowerMessage, 'climate') !== false) {
        return "Safety & Climate Log records powder type, surface type, temperature, humidity, irritation status, and remarks during fingerprint testing.";
    }

    // 14. Greetings like "hi", "hello", "help"
    if (preg_match('/\bhi\b/', $lowerMessage) || preg_match('/\bhello\b/', $lowerMessage) || preg_match('/\bhelp\b/', $lowerMessage) || preg_match('/\bhey\b/', $lowerMessage)) {
        return "Hi! I can help you with account approval, fingerprint upload, validation status, reports, safety logs, and logout. What do you need help with?";
    }

    // 15. Default fallback response
    return "I can help with registration, account approval, fingerprint upload, validation status, reports, safety logs, and logout.";
}

// 2. Fetch Gemini API Key
$apiKey = env('GEMINI_API_KEY');
if (empty($apiKey)) {
    $err = "Gemini API Error: GEMINI_API_KEY is not defined or empty in the environment configuration.";
    debug_log($err, true);
    $reply = getOfflineSupportAnswer($message);
    send_response(true, $reply, "offline");
}

// 3. Fetch Gemini Model (non-hardcoded)
$model = env('GEMINI_MODEL', 'gemini-3.5-flash');
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
    "system_instruction" => [
        "parts" => [
            ["text" => $systemInstruction]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.4,
        "maxOutputTokens" => 800,
        "thinkingConfig" => [
            "thinkingLevel" => "minimal"
        ]
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
    debug_log($errMessage, true);
    $reply = getOfflineSupportAnswer($message);
    send_response(true, $reply, "offline");
}

$responseData = json_decode($response, true);
$replyText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($replyText)) {
    $errMessage = "Gemini API Error: empty response text structure. Response: " . $response;
    debug_log($errMessage, true);
    $reply = getOfflineSupportAnswer($message);
    send_response(true, $reply, "offline");
}

send_response(true, trim($replyText), "gemini");
?>