<?php
// includes/sms_service.php - GreenForensics Automated SMS Notification Service

require_once __DIR__ . '/../config.php';

/**
 * Format Philippine Mobile Phone Numbers into standard format
 * 
 * @param string $phone
 * @param string $style 'e164' (+639...), 'national' (09...), or 'international' (639...)
 * @return string|false
 */
function format_ph_phone_number($phone, $style = 'e164')
{
    if (empty($phone)) return false;
    
    // Remove any non-numeric characters
    $clean = preg_replace('/[^0-9]/', '', $phone);
    
    $mobile10 = null;
    if (strlen($clean) === 11 && substr($clean, 0, 2) === '09') {
        $mobile10 = substr($clean, 1);
    } elseif (strlen($clean) === 10 && substr($clean, 0, 1) === '9') {
        $mobile10 = $clean;
    } elseif (strlen($clean) === 12 && substr($clean, 0, 2) === '63' && substr($clean, 2, 1) === '9') {
        $mobile10 = substr($clean, 2);
    }
    
    if (!$mobile10) {
        return false;
    }
    
    switch ($style) {
        case 'national':
            return '0' . $mobile10;
        case 'international':
            return '63' . $mobile10;
        case 'e164':
        default:
            return '+63' . $mobile10;
    }
}

/**
 * Send Automated SMS Notification
 * 
 * Supports:
 * - Traccar SMS Gateway (Local & Cloud Relay)
 * - System Audit Log Fallback (for local testing & audit logs)
 */
function send_sms_notification($phone_number, $message)
{
    if (empty($phone_number) || empty($message)) {
        return false;
    }

    $target_phone = format_ph_phone_number($phone_number) ?: $phone_number;

    $traccar_url   = env('TRACCAR_GATEWAY_URL', 'http://192.168.1.14:8082');
    $traccar_token = env('TRACCAR_GATEWAY_TOKEN', '47ef11ea-31dc-4096-887c-679e1f044193');
    $cloud_token   = env('TRACCAR_CLOUD_TOKEN', 'd0Hicn0OSw2PxjJlaP5KUp:APA91bGnvzSieZz1iPzeU57NBWN2PeQMMlcNGjL-r4YKqKqFqZYJhem-gqtiT7cOt8yv0kObILwr9ZHvzu7s5hYx-vx2XHhxDgw4DO2B48H8EOTo3xd-o5q8');

    $sent = false;
    $provider = 'System Audit Log';

    $payload = json_encode([
        'to'      => $target_phone,
        'message' => $message
    ]);

    // 1. Try Local Traccar Gateway IP first
    if (!empty($traccar_url)) {
        $ch = curl_init();
        $endpoint = rtrim($traccar_url, '/');
        $parsed_path = parse_url($endpoint, PHP_URL_PATH);
        if (empty($parsed_path) || $parsed_path === '/') {
            $endpoint .= '/send';
        }
        $headers = ['Content-Type: application/json'];
        if (!empty($traccar_token)) {
            $headers[] = 'Authorization: ' . $traccar_token;
        }

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 || $http_code === 201 || $http_code === 202) {
            $sent = true;
            $provider = 'Traccar Gateway (Local)';
        }
    }

    // 2. Fallback to Traccar Cloud Relay (for AWS Web App) if local IP is unreachable
    if (!$sent && !empty($cloud_token)) {
        $ch = curl_init('https://www.traccar.org/sms/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $cloud_token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 || $http_code === 201 || $http_code === 202) {
            $sent = true;
            $provider = 'Traccar Gateway (Cloud Relay)';
        }
    }

    // 3. Always record SMS Audit Log in database
    try {
        global $pdo;
        if (isset($pdo)) {
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
    return $sent;
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
        $clean_remarks = !empty($remarks) ? mb_strimwidth(trim($remarks), 0, 60, '...') : '';

        if (strtolower($status) === 'approved') {
            $score_str = ($faculty_score !== null) ? number_format((float) $faculty_score, 1) . '%' : 'Pass';
            $msg = "GreenForensics: Hi {$first_name}, your fingerprint trial {$trial_code} ({$powder_name} on {$surface_name}) has been APPROVED by Faculty with a score of {$score_str}! Check your dashboard for details.";
        } else {
            $status_upper = strtoupper(str_replace('_', ' ', $status));
            $remark_str = !empty($clean_remarks) ? " Remarks: {$clean_remarks}." : "";
            $msg = "GreenForensics: Hi {$first_name}, your fingerprint trial {$trial_code} ({$powder_name} on {$surface_name}) status has been updated to {$status_upper}.{$remark_str} Check your dashboard.";
        }

        return send_sms_notification($phone, $msg);
    } catch (Exception $e) {
        error_log("send_trial_status_sms Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger User Account Registration Approval SMS Notification
 */
function send_user_approval_sms($user_id, $assigned_role)
{
    global $pdo;
    if (!isset($pdo) || empty($user_id))
        return false;

    try {
        $stmt = $pdo->prepare("SELECT first_name, full_name, contact_number FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['contact_number'])) {
            return false;
        }

        $first_name = !empty($user['first_name']) ? $user['first_name'] : explode(' ', $user['full_name'])[0];
        $phone = $user['contact_number'];
        $role_label = ucwords(str_replace('_', ' ', $assigned_role));

        $msg = "GreenForensics: Hi {$first_name}, your account registration has been APPROVED by the Administrator as {$role_label}! You may now log in to the portal.";

        return send_sms_notification($phone, $msg);
    } catch (Exception $e) {
        error_log("send_user_approval_sms Error: " . $e->getMessage());
        return false;
    }
}

