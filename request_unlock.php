<?php
// request_unlock.php - Lockout recovery request page for Green Forensics Evaluating System
session_start();
require_once "config.php";

$email = "";
$reason = "";
$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $reason = trim($_POST["reason"] ?? "");

    if (empty($email)) {
        $error_message = "Registered Email Address is required.";
    } else {
        try {
            // Check if there is an existing pending request for this email
            $chk_stmt = $pdo->prepare("SELECT COUNT(*) FROM account_unlock_requests WHERE email = :email AND status = 'pending'");
            $chk_stmt->execute([':email' => $email]);
            $pending_count = (int)$chk_stmt->fetchColumn();

            if ($pending_count > 0) {
                $error_message = "You already have a pending unlock request.";
            } else {
                // Find if user exists to associate user_id
                $user_stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $user_stmt->execute([':email' => $email]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $user ? (int)$user['id'] : null;

                // Save unlock request
                $ins_stmt = $pdo->prepare("INSERT INTO account_unlock_requests (user_id, email, reason, status) VALUES (:user_id, :email, :reason, 'pending')");
                $ins_stmt->execute([
                    ':user_id' => $user_id,
                    ':email' => $email,
                    ':reason' => !empty($reason) ? $reason : null
                ]);

                // Add activity log record
                $ip_address = $_SERVER["REMOTE_ADDR"] ?? '127.0.0.1';
                $user_agent = $_SERVER["HTTP_USER_AGENT"] ?? 'Unknown';
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_email, action, details, ip_address, user_agent) VALUES (:user_id, :user_email, 'Unlock Request Submitted', :details, :ip, :ua)");
                $log_stmt->execute([
                    ':user_id' => $user_id,
                    ':user_email' => $email,
                    ':details' => "Unlock request submitted for $email" . (!empty($reason) ? " with reason: $reason" : ""),
                    ':ip' => $ip_address,
                    ':ua' => $user_agent
                ]);

                // Set success message (generic for security)
                $success_message = "Your unlock request has been submitted. If the account exists, the Super Administrator will review it.";
                
                // Clear fields on success
                $email = "";
                $reason = "";
            }
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Account Unlock - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="css/login.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .textarea-plain {
            width: 100%;
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1.5px solid var(--light-gray);
            border-radius: 12px;
            color: var(--dark-green);
            background-color: var(--white);
            outline: none;
            transition: all 0.25s ease;
            resize: vertical;
        }
        .textarea-plain:focus {
            border-color: var(--soft-green);
            box-shadow: 0 0 0 4px rgba(107, 143, 113, 0.15);
        }
    </style>
</head>
<body>
    <header>
        <h1>Green Forensics Evaluating System</h1>
        <p>Innovative Sustainable Fingerprint Powder Using Chicken Eggshell Waste</p>
    </header>

    <main class="login-container">
        <!-- SKELETON LOADER (visible first) -->
        <div class="login-card" id="skeletonCard">
            <div class="skeleton-loader">
                <div class="skeleton-item skeleton-icon"></div>
                <div class="skeleton-item skeleton-title"></div>
                <div class="skeleton-item skeleton-subtitle"></div>
                <div class="skeleton-item skeleton-label"></div>
                <div class="skeleton-item skeleton-input"></div>
                <div class="skeleton-item skeleton-label"></div>
                <div class="skeleton-item skeleton-input"></div>
                <div class="skeleton-item skeleton-button"></div>
            </div>
        </div>

        <!-- REAL REQUEST FORM (hidden initially) -->
        <div class="login-card" id="realCard" style="display: none; opacity: 0;">
            <div class="card-header">
                <h2>Request Account Unlock</h2>
                <p>Submit a request to unlock your locked-out account</p>
            </div>

            <!-- Error Alerts -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Success Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="email">Registered Email Address <span style="color: var(--error-red);">*</span></label>
                    <input type="email" name="email" id="email" class="form-control-plain" placeholder="you@example.com"
                        value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

                <div class="form-group">
                    <label for="reason">Reason / Message (Optional)</label>
                    <textarea name="reason" id="reason" class="textarea-plain" rows="4" 
                        placeholder="Explain the reason for this unlock request..."><?php echo htmlspecialchars($reason); ?></textarea>
                </div>

                <button type="submit" name="submit_request" class="btn-primary">
                    <span>Submit Request</span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <div class="register-link-wrapper" style="text-align: center; margin-top: 1.5rem;">
                <a href="login.php" class="register-link">Back to Login</a>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Green Forensics Project | LSPU CCJE San Pablo City Campus</p>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const skeleton = document.getElementById("skeletonCard");
            const realCard = document.getElementById("realCard");

            setTimeout(() => {
                skeleton.style.transition = "opacity 0.4s ease";
                skeleton.style.opacity = "0";
                setTimeout(() => {
                    skeleton.style.display = "none";
                    realCard.style.display = "block";
                    requestAnimationFrame(() => {
                        realCard.style.transition = "opacity 0.5s ease";
                        realCard.style.opacity = "1";
                    });
                }, 400);
            }, 800);
        });
    </script>
</body>
</html>
