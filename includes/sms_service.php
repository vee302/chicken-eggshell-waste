<?php
// includes/sms_service.php - GreenForensics Automated SMS Notification Service

require_once __DIR__ . '/../config.php';

/**
 * Format Philippine Mobile Phone Numbers into standard format (+639XXXXXXXXX or 09XXXXXXXXX)
 */
function format_ph_phone_number($phone)
{
    if (empty($phone))
        return false;
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean) === 11 && substr($clean, 0, 2) === '09') {
        return '+63' . substr($clean, 1);
    } elseif (strlen($clean) === 10 && substr($clean, 0, 1) === '9') {
        return '+63' . $clean;
    } elseif (strlen($clean) === 12 && substr($clean, 0, 2) === '63') {
        return '+' . $clean;
    }
    return $phone;
}

/**
 * Send Automated SMS Notification
 * 
 * Supports:
 * - Semaphore SMS API (Semaphore.co - PH Standard)
 * - System Audit Log Fallback (for local testing & audit logs)
 */
function send_sms_notification($phone_number, $message)
{
    if (empty($phone_number) || empty($message)) {
        return false;
    }

    $target_phone = format_ph_phone_number($phone_number) ?: $phone_number;

    $traccar_url   = env('TRACCAR_GATEWAY_URL', '');
    $traccar_token = env('TRACCAR_GATEWAY_TOKEN', '');

    $sent = false;
    $provider = 'System Audit Log';

    // 1. Try Traccar SMS Gateway API if URL is configured
    if (!empty($traccar_url)) {
        $ch = curl_init();
        
        $payload = json_encode([
            'to'      => $target_phone,
            'message' => $message
        ]);

        $endpoint = rtrim($traccar_url, '/');

        $headers = ['Content-Type: application/json'];
        if (!empty($traccar_token)) {
            $headers[] = 'Authorization: ' . $traccar_token;
        }

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 || $http_code === 201 || $http_code === 202) {
            $sent = true;
            $provider = 'Traccar SMS Gateway';
        }
    }

    // 2. Always record SMS Audit Log in database
    try {
        global $pdo;
        if (isset($pdo)) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sms_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    recipient_phone VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    provider VARCHAR(50) DEFAULT 'Audit Log',
                    status VARCHAR(20) DEFAULT 'sent',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $log_stmt = $pdo->prepare("
                INSERT INTO sms_logs (recipient_phone, message, provider, status, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([$target_phone, $message, $provider, $sent ? 'sent' : 'logged']);
        }
    } catch (Exception $e) {
        error_log("SMS Log DB error: " . $e->getMessage());
    }

    error_log("[SMS SENT via {$provider}] To: {$target_phone} | Msg: {$message}");
    return true;
}

/**
 * Trigger Student Trial Status SMS Notification (Approved / Rejected / Revised)
 */
function send_trial_status_sms($test_id, $status, $faculty_score = null, $remarks = '')
{
    global $pdo;
    if (!isset($pdo) || empty($test_id))
        return false;

    try {
        $stmt = $pdo->prepare("
            SELECT ft.id, ft.trial_id, ft.powder_type, ft.surface_type, ft.student_id,
                   u.full_name, u.first_name, u.contact_number
            FROM fingerprint_tests ft
            JOIN users u ON u.id = ft.student_id
            WHERE ft.id = ?
        ");
        $stmt->execute([$test_id]);
        $trial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trial || empty($trial['contact_number'])) {
            return false;
        }

        $first_name = !empty($trial['first_name']) ? $trial['first_name'] : explode(' ', $trial['full_name'])[0];
        $trial_code = !empty($trial['trial_id']) ? $trial['trial_id'] : ('TR-' . str_pad($test_id, 4, '0', STR_PAD_LEFT));
        $powder_name = ucfirst($trial['powder_type'] ?? 'Powder');
        $surface_name = ucfirst($trial['surface_type'] ?? 'Surface');
        $phone = $trial['contact_number'];

        if (strtolower($status) === 'approved') {
            $score_str = ($faculty_score !== null) ? number_format((float) $faculty_score, 1) . '%' : 'Pass';
            $msg = "GreenForensics: Hi {$first_name}, your fingerprint trial {$trial_code} ({$powder_name} on {$surface_name}) has been APPROVED by Faculty with a score of {$score_str}! Check your dashboard for details.";
        } else {
            $status_upper = strtoupper(str_replace('_', ' ', $status));
            $remark_str = !empty($remarks) ? " Remarks: {$remarks}." : "";
            $msg = "GreenForensics: Hi {$first_name}, your fingerprint trial {$trial_code} ({$powder_name} on {$surface_name}) status has been updated to {$status_upper}.{$remark_str} Check your dashboard.";
        }

        return send_sms_notification($phone, $msg);
    } catch (Exception $e) {
        error_log("send_trial_status_sms Error: " . $e->getMessage());
        return false;
    }
}
