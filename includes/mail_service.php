<?php
// includes/mail_service.php - GreenForensics Automated Email & SMTP Notification Service

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../config.php';

// Include PHPMailer autoload / class files
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
}

/**
 * Instantiate configured PHPMailer instance
 * 
 * @return PHPMailer
 */
function get_mailer_instance()
{
    $mail = new PHPMailer(true);

    $smtpHost   = env('SMTP_HOST', 'smtp.gmail.com');
    $smtpPort   = (int) env('SMTP_PORT', 587);
    $smtpUser   = env('SMTP_USERNAME', '');
    $smtpPass   = env('SMTP_PASSWORD', '');
    $smtpSecure = env('SMTP_ENCRYPTION', 'tls'); // 'tls' (587) or 'ssl' (465)
    $fromEmail  = env('SMTP_FROM_ADDRESS', $smtpUser ?: 'no-reply@greenforensics.edu.ph');
    $fromName   = env('SMTP_FROM_NAME', 'Green Forensics Evaluating System');

    if (!empty($smtpUser) && !empty($smtpPass)) {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = strtolower($smtpSecure) === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
    } else {
        // Fallback to PHP native mail() if SMTP credentials are not yet configured
        $mail->isMail();
        $mail->CharSet = 'UTF-8';
    }

    $mail->setFrom($fromEmail, $fromName);
    return $mail;
}

/**
 * Base HTML Template wrapper for Green Forensics branded emails
 * 
 * @param string $title
 * @param string $bodyContent
 * @param string $actionUrl
 * @param string $actionText
 * @return string
 */
function build_branded_email_html($title, $bodyContent, $actionUrl = '', $actionText = '')
{
    $appUrl = env('APP_URL', 'https://green-forensics.duckdns.org');
    $actionButtonHtml = '';
    if (!empty($actionUrl) && !empty($actionText)) {
        $actionButtonHtml = "
        <div style='text-align: center; margin: 30px 0;'>
            <a href='{$actionUrl}' style='background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; padding: 14px 32px; font-weight: bold; border-radius: 8px; display: inline-block; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35); font-size: 15px;'>
                {$actionText}
            </a>
        </div>";
    }

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$title}</title>
    </head>
    <body style='margin: 0; padding: 0; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #334155;'>
        <table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background-color: #0f172a; padding: 30px 15px;'>
            <tr>
                <td align='center'>
                    <table role='presentation' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.4);' cellspacing='0' cellpadding='0'>
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #064e3b, #047857); padding: 35px 30px; text-align: center;'>
                                <div style='font-size: 32px; line-height: 1; margin-bottom: 8px;'>🌿🔬</div>
                                <h1 style='margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;'>Green Forensics</h1>
                                <p style='margin: 4px 0 0; color: #a7f3d0; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;'>Sustainable Eggshell Waste Evaluation System</p>
                            </td>
                        </tr>
                        <!-- Content -->
                        <tr>
                            <td style='padding: 35px 30px;'>
                                <h2 style='margin: 0 0 18px; color: #0f172a; font-size: 20px; font-weight: 600;'>{$title}</h2>
                                <div style='color: #475569; font-size: 15px; line-height: 1.6;'>
                                    {$bodyContent}
                                </div>
                                {$actionButtonHtml}
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8;'>
                                <p style='margin: 0 0 6px;'>This is an automated notification from Green Forensics System.</p>
                                <p style='margin: 0;'><a href='{$appUrl}' style='color: #059669; text-decoration: none; font-weight: 600;'>Visit Portal</a> &bull; Laguna State Polytechnic University</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";
}

/**
 * Send generic notification email
 * 
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlContent
 * @param string $actionUrl
 * @param string $actionText
 * @return array ['success' => bool, 'message' => string]
 */
function send_email_notification($toEmail, $toName, $subject, $htmlContent, $actionUrl = '', $actionText = '')
{
    if (empty($toEmail)) {
        return ['success' => false, 'message' => 'Recipient email is missing.'];
    }

    try {
        $mail = get_mailer_instance();
        $mail->addAddress($toEmail, $toName ?: $toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = build_branded_email_html($subject, $htmlContent, $actionUrl, $actionText);
        $mail->AltBody = strip_tags(str_replace(['<br>', '<p>', '</p>'], ["\n", "\n", "\n\n"], $htmlContent));

        $mail->send();
        log_activity("Email Sent", "Email successfully sent to {$toEmail} (Subject: {$subject})");
        return ['success' => true, 'message' => 'Email sent successfully.'];
    } catch (Exception $e) {
        $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
        error_log("PHPMailer Error: Failed to send email to {$toEmail}. Error: {$errorMsg}");
        return ['success' => false, 'message' => $errorMsg];
    }
}

/**
 * Send User Account Approval Notification Email
 * 
 * @param int $userId
 * @param string $approvedRole
 * @return array
 */
function send_user_approval_email($userId, $approvedRole)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT email, full_name, username FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['email'])) {
            return ['success' => false, 'message' => 'User or email not found.'];
        }

        $roleLabels = [
            'criminology_student'   => 'Criminology Student Researcher',
            'faculty_researcher'    => 'Faculty Researcher / Advisor',
            'alumni_police_partner' => 'Law Enforcement / Police Partner',
            'super_admin'           => 'System Administrator'
        ];

        $roleTitle = $roleLabels[$approvedRole] ?? ucwords(str_replace('_', ' ', $approvedRole));
        $name = !empty($user['full_name']) ? htmlspecialchars($user['full_name']) : htmlspecialchars($user['username']);
        $appUrl = env('APP_URL', 'https://green-forensics.duckdns.org') . '/login.php';

        $subject = "Your Account Has Been Approved — Green Forensics";
        $content = "
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Great news! Your registration request for the <strong>Green Forensics Evaluating System</strong> has been reviewed and <strong style='color: #059669;'>APPROVED</strong> by the Administrator.</p>
            <div style='background-color: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 6px;'>
                <p style='margin: 0; color: #065f46;'><strong>Assigned Role:</strong> {$roleTitle}</p>
                <p style='margin: 6px 0 0; color: #065f46;'><strong>Username / Email:</strong> {$user['email']}</p>
            </div>
            <p>You can now log in to the portal and start accessing forensic evaluation tools, fingerprint analysis, and safety climate monitoring.</p>
        ";

        return send_email_notification($user['email'], $name, $subject, $content, $appUrl, "Log In to Portal Now");
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send User Account Rejection Notification Email
 * 
 * @param int $userId
 * @param string $reason
 * @return array
 */
function send_user_rejection_email($userId, $reason = '')
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT email, full_name, username FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['email'])) {
            return ['success' => false, 'message' => 'User or email not found.'];
        }

        $name = !empty($user['full_name']) ? htmlspecialchars($user['full_name']) : htmlspecialchars($user['username']);
        $reasonText = !empty($reason) ? htmlspecialchars($reason) : 'Provided credentials or affiliation could not be verified.';
        $appUrl = env('APP_URL', 'https://green-forensics.duckdns.org') . '/register.php';

        $subject = "Registration Status Update — Green Forensics";
        $content = "
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Thank you for your interest in the <strong>Green Forensics Evaluating System</strong>.</p>
            <p>After review by our administrators, your registration request could not be approved at this time.</p>
            <div style='background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 6px;'>
                <p style='margin: 0; color: #991b1b;'><strong>Reason:</strong> {$reasonText}</p>
            </div>
            <p>If you believe this was an error, please coordinate with your faculty coordinator or re-register with valid identification.</p>
        ";

        return send_email_notification($user['email'], $name, $subject, $content, $appUrl, "Re-submit Registration");
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send Account Lockout / Unlock Alert Email
 * 
 * @param string $toEmail
 * @param string $name
 * @param string $status 'locked' | 'unlocked'
 * @return array
 */
function send_account_security_email($toEmail, $name, $status = 'locked')
{
    $appUrl = env('APP_URL', 'https://green-forensics.duckdns.org') . '/request_unlock.php';

    if ($status === 'locked') {
        $subject = "Security Alert: Account Temporarily Locked — Green Forensics";
        $content = "
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Your Green Forensics account was temporarily locked due to multiple consecutive failed login attempts.</p>
            <p>If this was you, you can request an account unlock or wait for the automatic lockout period to expire.</p>
            <p>If you did not attempt these logins, please inform the administrator immediately.</p>
        ";
        return send_email_notification($toEmail, $name, $subject, $content, $appUrl, "Request Account Unlock");
    } else {
        $subject = "Account Unlocked — Green Forensics";
        $content = "
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Your Green Forensics account has been successfully unlocked by the Administrator. You can now log in securely.</p>
        ";
        return send_email_notification($toEmail, $name, $subject, $content, env('APP_URL', 'https://green-forensics.duckdns.org') . '/login.php', "Log In Now");
    }
}
