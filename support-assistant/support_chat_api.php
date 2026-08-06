<?php
ob_start();
// Backend API Handler for Support Assistant
header('Content-Type: application/json');

require_once dirname(__DIR__) . "/config.php";

// Output JSON and exit
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

// Require POST method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    send_response(false, "Method Not Allowed", 405);
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

// Session profanity tracking initialization
if (!isset($_SESSION['chat_profanity_count'])) {
    $_SESSION['chat_profanity_count'] = 0;
}
if (!isset($_SESSION['chat_profanity_blocked'])) {
    $_SESSION['chat_profanity_blocked'] = false;
}

// 0. Check if user is already blocked
if ($_SESSION['chat_profanity_blocked'] === true || $_SESSION['chat_profanity_count'] >= 2) {
    $_SESSION['chat_profanity_blocked'] = true;
    send_response(
        false,
        "Ang iyong access sa AI Support Assistant ay na-block dahil sa paulit-ulit na paggamit ng hindi angkop na pananalita.",
        "blocked"
    );
}

// 1. Profanity Filter & Strike System
$lowerMessage = strtolower($message);

$profanities = [
    'gago', 'gaga', 'tanga', 'bobo', 'buba', 'inutil', 'tarantado', 'ulol', 'leche', 'letche',
    'putangina', 'putang ina', 'pukinangina', 'pukinang ina', 'tangina', 'taena', 'tanginang',
    'pota', 'puta', 'putaena', 'pucha', 'puchang', 'pakyu', 'pokpok', 'bayag', 'titi', 'puki',
    'pepe', 'kiki', 'kupal', 'g@go', 't@nga', 'p@t@',
    'fuck', 'fucking', 'fucked', 'fucker', 'shit', 'shitty', 'bitch', 'asshole', 'bastard',
    'cunt', 'dick', 'pussy', 'motherfucker', 'bullshit', 'prick', 'cock'
];

$foundProfanity = false;
foreach ($profanities as $badWord) {
    if (preg_match('/\b' . preg_quote($badWord, '/') . '\b/i', $lowerMessage) || strpos($lowerMessage, $badWord) !== false) {
        $foundProfanity = true;
        break;
    }
}

if ($foundProfanity) {
    $_SESSION['chat_profanity_count']++;

    if ($_SESSION['chat_profanity_count'] >= 2) {
        $_SESSION['chat_profanity_blocked'] = true;
        send_response(
            false,
            "Ang iyong access sa AI Support Assistant ay na-block dahil sa paulit-ulit na paggamit ng hindi angkop na pananalita.",
            "blocked"
        );
    } else {
        send_response(
            true,
            "Babala (1/2): Mangyaring gumamit ng magalang at angkop na pananalita. Ang uuliting paggamit ng hindi angkop na salita ay magdudulot ng pagka-block ng iyong chat access.",
            "warning"
        );
    }
}

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


// Diagnostic logging
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

// Offline fallback answers
function getOfflineSupportAnswer($message)
{
    $lowerMessage = strtolower(trim($message));

    if (strpos($lowerMessage, 'unlock') !== false || strpos($lowerMessage, 'locked') !== false || strpos($lowerMessage, 'cant login') !== false) {
        return "If your account is locked after multiple failed login attempts, you may wait 15 minutes or submit an unlock request for Super Admin review. Open the Request Unlock page here: request_unlock.php";
    }

    if (strpos($lowerMessage, 'developer') !== false || strpos($lowerMessage, 'gumawa') !== false) {
        return "Ang developer nitong system ay si Yvez Jayvee Gesmundo ang full stock developer. ang frontend ay si Marron Brimbuela at si Kevin Cloud Fajardo.";
    }

    if (strpos($lowerMessage, 'password') !== false || strpos($lowerMessage, 'credential') !== false) {
        return "For security reasons, never share your password. If you need help with your password or your account is locked, please use the Request Unlock page or contact the Super Administrator directly.";
    }

    if (strpos($lowerMessage, 'pending validation') !== false) {
        return "Pending Validation means your fingerprint submission was received but still needs to be reviewed by the Faculty Researcher.";
    }

    if (strpos($lowerMessage, 'needs revision') !== false || strpos($lowerMessage, 'revision') !== false) {
        return "Needs Revision means your submission needs improvement. Read the faculty remarks and upload a clearer or corrected fingerprint image.";
    }

    if (strpos($lowerMessage, 'pending') !== false) {
        return "Your account is pending because the Super Administrator still needs to review and approve your registration. Please wait for approval or contact your instructor/admin.";
    }

    if (strpos($lowerMessage, 'upload') !== false) {
        return "Go to Upload Fingerprint Images, choose powder type and surface type, upload or capture the fingerprint image, then submit.";
    }

    if (strpos($lowerMessage, 'approved') !== false) {
        return "Approved means your fingerprint submission has been reviewed and validated by the Faculty Researcher.";
    }

    if (strpos($lowerMessage, 'rejected') !== false) {
        return "Rejected means the submission did not meet the required quality or information. Check the faculty remarks and submit a better image if needed.";
    }

    if (strpos($lowerMessage, 'faculty validation') !== false || strpos($lowerMessage, 'validation') !== false) {
        return "The system gives an AI preliminary score first, then the Faculty Researcher reviews the image and gives the official final score.";
    }

    if (strpos($lowerMessage, 'biometric') !== false || strpos($lowerMessage, 'identify') !== false) {
        return "No. In this system, fingerprint images are used for academic image quality evaluation only, not for personal biometric identification.";
    }

    if (strpos($lowerMessage, 'logout') !== false) {
        return "Tap your profile initials on the top right, then select Logout.";
    }

    if (strpos($lowerMessage, 'safety') !== false || strpos($lowerMessage, 'climate') !== false) {
        return "Safety & Climate Log records powder type, surface type, temperature, humidity, irritation status, and remarks during fingerprint testing.";
    }

    if (preg_match('/\bhi\b/', $lowerMessage) || preg_match('/\bhello\b/', $lowerMessage) || preg_match('/\bhelp\b/', $lowerMessage) || preg_match('/\btulong\b/', $lowerMessage)) {
        return "Hi! I can help you with account approval, fingerprint upload, validation status, reports, safety logs, and logout. What do you need help with?";
    }

    return "I can help with registration, account approval, fingerprint upload, validation status, reports, safety logs, and logout.";
}

// Pollinations AI query
function callPollinationsAI($message, $systemInstruction)
{
    $url = "https://text.pollinations.ai/";
    $data = [
        "messages" => [
            ["role" => "system", "content" => $systemInstruction],
            ["role" => "user", "content" => $message]
        ],
        "model" => "openai",
        "json" => false
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($response)) {
        return trim($response);
    }
    return null;
}

// Groq AI query
function callGroqAI($message, $systemInstruction)
{
    $groqKey = env('GROQ_API_KEY');
    if (empty($groqKey)) {
        return null;
    }

    $model = env('GROQ_MODEL', 'llama-3.3-70b-versatile');
    $url = "https://api.groq.com/openai/v1/chat/completions";
    $data = [
        "model" => $model,
        "messages" => [
            ["role" => "system", "content" => $systemInstruction],
            ["role" => "user", "content" => $message]
        ],
        "temperature" => 0.4,
        "max_tokens" => 800
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($response)) {
        $res = json_decode($response, true);
        $reply = $res['choices'][0]['message']['content'] ?? null;
        if (!empty($reply)) {
            return trim($reply);
        }
    }
    return null;
}

$systemInstruction = "You are the Green Forensics Support Assistant. Help users with the Green Forensics Evaluating System. Answer clearly, politely, and briefly. You can help with registration, pending accounts, login lockout, account unlock requests, fingerprint image upload, webcam capture, AI-assisted image quality evaluation, faculty validation, Terms of Use, Privacy Policy, and role-based dashboards. For account lockouts, password resets, failed logins, or unlock requests, guide the user to visit request_unlock.php. Do not ask for their password or private credentials. Fingerprint images are used only for academic research evaluation and image quality assessment, not biometric identification. If a user asks about locked account, login failed, forgot password, cannot login, or requesting an unlock, you must respond with: 'If your account is locked after multiple failed login attempts, you may wait 15 minutes or submit an unlock request for Super Admin review. Open the Request Unlock page here: request_unlock.php'. If a user asks who the developer of the system is, respond with: 'Ang developer nitong system ay si Yvez Jayvee Gesmundo ang full stock developer. ang frontend ay si Marron Brimbuela at si Kevin Cloud Fajardo.' If the user greets you, respond warmly and ask how you can help.";

// 1. Primary: Groq AI
$groqReply = callGroqAI($message, $systemInstruction);
if ($groqReply !== null) {
    send_response(true, $groqReply, "groq");
}

// 2. Fallback: Pollinations AI
$pollinationsReply = callPollinationsAI($message, $systemInstruction);
if ($pollinationsReply !== null) {
    send_response(true, $pollinationsReply, "pollinations");
}

// 3. Fallback: Offline
$reply = getOfflineSupportAnswer($message);
send_response(true, $reply, "offline");
?>