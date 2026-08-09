<?php
// student/ajax_update_contact_number.php — Update Student Mobile Phone Number
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

header('Content-Type: application/json');

$student_id = $_SESSION['user_id'] ?? 0;
$raw_phone  = trim($_POST['contact_number'] ?? '');

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if (empty($raw_phone)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid mobile phone number.']);
    exit;
}

// Clean and format phone number
if (file_exists(dirname(__DIR__) . '/includes/sms_service.php')) {
    require_once dirname(__DIR__) . '/includes/sms_service.php';
    $formatted = format_ph_phone_number($raw_phone);
    if (!$formatted) {
        echo json_encode(['success' => false, 'message' => 'Invalid Philippine mobile phone number format (e.g. 09171234567).']);
        exit;
    }
} else {
    $formatted = $raw_phone;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET contact_number = ? WHERE id = ?");
    $stmt->execute([$formatted, $student_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Mobile phone number updated successfully!',
        'contact_number' => $formatted
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
