<?php
// ajax_support_chat.php - Backend API Handler for Gemini AI Support Assistant
header('Content-Type: application/json');

require_once "config.php";

// Ensure request is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(["status" => "error", "message" => "Empty message"]);
    exit;
}

// 1. Password Security Check
$lowerMessage = strtolower($message);
if (strpos($lowerMessage, 'password') !== false || strpos($lowerMessage, 'passcode') !== false || strpos($lowerMessage, 'credential') !== false) {
    echo json_encode([
        "status" => "success",
        "reply" => "For security reasons, never share your password. If you need help with your password or your account is locked, please use the Request Unlock page or contact the Super Administrator directly."
    ]);
    exit;
}

// 2. Fallback Check (If API Key is not set/configured)
if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
    echo json_encode(["status" => "fallback"]);
    exit;
}

// 3. Call Google Gemini API
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_API_KEY;

// Construct payload
$systemInstruction = "You are the Green Forensics Support Assistant, an AI chatbot for the Green Forensics Evaluating System (an academic project at LSPU CCJE San Pablo City Campus, focusing on sustainable fingerprint powder using chicken eggshell waste).
Your job is to answer user questions about the system:
1. Registration: Tell them to fill Step 1 (Profile & Identity) and Step 2 (Access Request), upload Proof of Affiliation, agree to Terms of Use & Privacy Policy, and wait for Super Admin approval. Final roles are Criminology Student, Faculty Researcher, and Alumni/Police Partner.
2. Account status (Pending/Suspended): Super Admin reviews all accounts for authentication and system security.
3. Fingerprint uploads: Students upload trials or use webcam capture under Criminology Student dashboard.
4. Faculty validation: Faculty Researchers review trials, AI preliminary scores, and enter validation decisions (Approve, Reject, Needs Revision).
5. Lockouts: Locked accounts (after 5 failed attempts) stay locked for 15 minutes, or can submit an Unlock Request.
Rules:
- Be short, concise, and helpful. Keep responses to 2-3 sentences.
- Never ask for credentials or passwords.
- Only discuss Green Forensics Evaluating System and academic fingerprint powder topics.";

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

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// If request fails, fall back to rule-based logic
if ($response === false || $httpCode !== 200) {
    // Log curl error if necessary
    error_log("Gemini API Error. HTTP Code: $httpCode. Error: $curlError");
    echo json_encode(["status" => "fallback"]);
    exit;
}

$responseData = json_decode($response, true);
$replyText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($replyText)) {
    echo json_encode(["status" => "fallback"]);
    exit;
}

echo json_encode([
    "status" => "success",
    "reply" => trim($replyText)
]);
?>
