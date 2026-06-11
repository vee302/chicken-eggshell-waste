<?php
// pending_approval.php - Registration status page
session_start();
require_once "config.php";

$account = null;
$accountStatus = 'pending';
$accountEmail = $_SESSION['pending_registration_email'] ?? '';
$accountId = isset($_SESSION['pending_registration_user_id']) ? (int)$_SESSION['pending_registration_user_id'] : 0;

try {
    if ($accountId > 0) {
        $stmt = $pdo->prepare("SELECT id, full_name, email, status, role FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$account && !empty($accountEmail)) {
        $stmt = $pdo->prepare("SELECT id, full_name, email, status, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $accountEmail]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $account = null;
}

if ($account) {
    $accountStatus = $account['status'] ?? 'pending';
    $_SESSION['pending_registration_user_id'] = (int)$account['id'];
    $_SESSION['pending_registration_email'] = $account['email'];
}

// Determine content strings based on account status
$statusBadge = 'Pending Approval';
$cardTitle = 'Account Registration Submitted';
$mainMessage = 'Your registration has been submitted successfully.';
$secondaryMessage = 'Your account is currently under review by the Super Administrator. You will be able to log in once your account has been approved.';
$showWaiting = true;
$showApprovalPopup = false;

// Status Panel Values
$regStatus = 'Submitted';
$revStatus = 'In Progress';
$accStatus = 'Locked until approval';

if ($accountStatus === 'active') {
    $statusBadge = 'Approved';
    $cardTitle = 'Account Approved';
    $mainMessage = 'Your account has been approved. You may now log in.';
    $secondaryMessage = 'Access is now available through the login page.';
    $showWaiting = false;
    $showApprovalPopup = true;
    
    $regStatus = 'Approved';
    $revStatus = 'Completed';
    $accStatus = 'Unlocked';
} elseif ($accountStatus === 'rejected') {
    $statusBadge = 'Not Approved';
    $cardTitle = 'Account Not Approved';
    $mainMessage = 'Your registration was not approved. Please contact the system administrator.';
    $secondaryMessage = 'For additional guidance, please contact the Super Administrator or return to the homepage.';
    $showWaiting = false;
    
    $regStatus = 'Rejected';
    $revStatus = 'Completed';
    $accStatus = 'Denied';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval - Green Forensics Evaluating System</title>
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --dark-green: #2F4F3A;
            --forest-green: #1F3F2A;
            --soft-green: #6B8F71;
            --mint-green: #D2E2D5;
            --cream: #FAF8F1;
            --white: #FFFFFF;
            --gray: #5F5F5F;
            --light-gray: #E8E8E2;
            --warning: #B7770D;
            --danger: #B94A48;
            --shadow: rgba(47, 79, 58, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--cream);
            color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .page {
            width: 100%;
            max-width: 620px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--dark-green);
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 0.95rem;
            color: var(--gray);
            font-weight: 500;
        }

        .status-card {
            background: var(--white);
            border: 1px solid rgba(107, 143, 113, 0.15);
            border-radius: 18px;
            box-shadow: 0 16px 36px var(--shadow);
            padding: clamp(1.5rem, 5vw, 2.5rem);
        }

        .card-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .card-heading h2 {
            color: var(--dark-green);
            font-size: clamp(1.2rem, 3vw, 1.45rem);
            font-weight: 800;
            line-height: 1.25;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: var(--warning);
            background: rgba(183, 119, 13, 0.08);
            border: 1px solid rgba(183, 119, 13, 0.2);
            transition: all 0.3s ease;
        }

        .status-badge.active {
            color: var(--forest-green);
            background: rgba(107, 143, 113, 0.12);
            border-color: rgba(107, 143, 113, 0.25);
        }

        .status-badge.rejected {
            color: var(--danger);
            background: rgba(185, 74, 72, 0.08);
            border-color: rgba(185, 74, 72, 0.18);
        }

        .message-block {
            text-align: center;
            margin-bottom: 2rem;
        }

        .message-block .main-message {
            color: var(--dark-green);
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }

        .message-block .secondary-message {
            font-size: 0.92rem;
            line-height: 1.6;
            color: var(--gray);
        }

        /* Scanner Pad Container */
        .scanner-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        .scanner-pad {
            position: relative;
            width: 140px;
            height: 140px;
            background: var(--cream);
            border: 2px solid var(--mint-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: inset 0 4px 10px rgba(0, 0, 0, 0.02), 0 8px 24px rgba(107, 143, 113, 0.1);
            transition: all 0.3s ease;
        }

        .scanner-pad.pending {
            border-color: var(--soft-green);
            box-shadow: 0 0 20px rgba(107, 143, 113, 0.15);
        }

        .scanner-pad.active {
            border-color: var(--forest-green);
            background: rgba(210, 226, 213, 0.25);
            box-shadow: 0 0 25px rgba(31, 63, 42, 0.2);
        }

        .scanner-pad.rejected {
            border-color: var(--danger);
            background: rgba(185, 74, 72, 0.04);
            box-shadow: 0 0 20px rgba(185, 74, 72, 0.12);
        }

        .scanner-fingerprint {
            width: 76px;
            height: 95px;
            transition: all 0.3s ease;
        }

        .scanner-pad.pending .scanner-fingerprint {
            color: var(--soft-green);
        }

        .scanner-pad.active .scanner-fingerprint {
            color: var(--dark-green);
        }

        .scanner-pad.rejected .scanner-fingerprint {
            color: #C0C0B8;
        }

        /* Scanning Animation Line */
        .scan-line {
            position: absolute;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, rgba(107, 143, 113, 0) 10%, var(--soft-green) 50%, rgba(107, 143, 113, 0) 90%);
            box-shadow: 0 0 10px var(--soft-green);
            opacity: 0.85;
            animation: scanMove 2.5s ease-in-out infinite;
        }

        @keyframes scanMove {
            0%, 100% {
                top: 10%;
            }
            50% {
                top: 90%;
            }
        }

        /* Circle Scanner overlay badge for results */
        .badge-overlay {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
            border: 2.5px solid var(--white);
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .badge-overlay.success {
            background: var(--dark-green);
            color: var(--white);
        }

        .badge-overlay.error {
            background: var(--danger);
            color: var(--white);
        }

        @keyframes popIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Helper verification texts under scanner */
        .scanner-status-text {
            text-align: center;
            margin-top: 1.25rem;
        }

        .scanner-status-text h4 {
            color: var(--dark-green);
            font-size: 0.98rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .scanner-status-text p {
            color: var(--gray);
            font-size: 0.85rem;
        }

        /* Status Details Table */
        .status-panel {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            background: rgba(250, 248, 241, 0.55);
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            padding: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.88rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            padding-bottom: 0.5rem;
        }

        .status-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .status-label {
            color: var(--gray);
            font-weight: 500;
        }

        .status-value {
            color: var(--dark-green);
            font-weight: 700;
        }

        .status-value.pending-text {
            color: var(--warning);
        }

        .status-value.approved-text {
            color: var(--forest-green);
        }

        .status-value.rejected-text {
            color: var(--danger);
        }

        /* Information note box */
        .info-note {
            border-left: 4px solid var(--soft-green);
            background: rgba(107, 143, 113, 0.06);
            color: var(--gray);
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.86rem;
            line-height: 1.55;
            margin-bottom: 1.75rem;
        }

        /* Centered CTA Buttons */
        .actions {
            display: flex;
            justify-content: center;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border-radius: 10px;
            padding: 0.65rem 1.35rem;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
            border: 1.5px solid transparent;
        }

        .btn-primary {
            color: var(--white);
            background: var(--dark-green);
            border-color: var(--dark-green);
            box-shadow: 0 4px 10px rgba(47, 79, 58, 0.12);
        }

        .btn-primary:hover {
            background: var(--forest-green);
            border-color: var(--forest-green);
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(31, 63, 42, 0.22);
        }

        .btn-secondary {
            color: var(--dark-green);
            background: var(--white);
            border-color: var(--mint-green);
        }

        .btn-secondary:hover {
            background: rgba(210, 226, 213, 0.2);
            border-color: var(--soft-green);
            transform: translateY(-1px);
        }

        /* Overlay modal for real-time approval popup */
        .approval-overlay {
            position: fixed;
            inset: 0;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(31, 63, 42, 0.45);
            backdrop-filter: blur(4px);
            padding: 1.25rem;
        }

        .approval-modal {
            width: min(100%, 460px);
            background: var(--white);
            border-radius: 16px;
            border: 1px solid rgba(107, 143, 113, 0.2);
            box-shadow: 0 24px 60px rgba(31, 63, 42, 0.22);
            padding: 2rem;
            text-align: center;
            animation: modalPop 0.4s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
        }

        @keyframes modalPop {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .approval-modal h2 {
            color: var(--dark-green);
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
        }

        .approval-modal p {
            color: var(--gray);
            font-size: 0.94rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .approval-modal .modal-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.4rem 0.9rem;
            color: var(--forest-green);
            background: rgba(107, 143, 113, 0.12);
            border: 1px solid rgba(107, 143, 113, 0.25);
            font-size: 0.78rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media (max-width: 640px) {
            .card-heading {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .actions, .btn, .modal-actions {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="page-header">
            <h1>Green Forensics Evaluating System</h1>
            <p>Request an authorized account to access the evaluating system</p>
        </header>

        <section class="status-card" aria-labelledby="statusTitle">
            <div class="card-heading">
                <h2 id="statusTitle"><?php echo htmlspecialchars($cardTitle); ?></h2>
                <span class="status-badge <?php echo htmlspecialchars($accountStatus); ?>">
                    <?php echo htmlspecialchars($statusBadge); ?>
                </span>
            </div>

            <div class="message-block">
                <p class="main-message"><?php echo htmlspecialchars($mainMessage); ?></p>
                <p class="secondary-message"><?php echo htmlspecialchars($secondaryMessage); ?></p>
            </div>

            <!-- Fingerprint Verification / Scan Waiting Panel -->
            <div class="scanner-section">
                <div class="scanner-pad <?php echo htmlspecialchars($accountStatus); ?>" aria-live="polite">
                    <!-- Fingerprint SVG Graphic -->
                    <svg class="scanner-fingerprint" viewBox="0 0 24 30" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 0 0-7.3 16.8"></path>
                        <path d="M12 2a10 10 0 0 1 7.3 16.8"></path>
                        <path d="M12 6a6 6 0 0 0-4.4 10.1"></path>
                        <path d="M12 6a6 6 0 0 1 4.4 10.1"></path>
                        <path d="M12 10a2 2 0 0 0-1.5 3.4"></path>
                        <path d="M12 10a2 2 0 0 1 1.5 3.4"></path>
                        <path d="M12 14v6"></path>
                        <path d="M8 22c1.2 1.5 2.8 2 4 2s2.8-.5 4-2"></path>
                        <path d="M5.5 19.5c.8 1.5 2.5 3 6.5 3s5.7-1.5 6.5-3"></path>
                    </svg>

                    <?php if ($showWaiting): ?>
                        <div class="scan-line" aria-hidden="true"></div>
                    <?php endif; ?>

                    <!-- Overlay Success/Error Indicator Badge -->
                    <?php if ($accountStatus === 'active'): ?>
                        <div class="badge-overlay success" title="Verification Approved">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                    <?php elseif ($accountStatus === 'rejected'): ?>
                        <div class="badge-overlay error" title="Verification Denied">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="scanner-status-text">
                    <?php if ($accountStatus === 'pending'): ?>
                        <h4>Waiting for Super Administrator approval...</h4>
                        <p>Your account request is being reviewed and verified.</p>
                    <?php elseif ($accountStatus === 'active'): ?>
                        <h4>Verification Complete</h4>
                        <p>Your identity has been successfully authenticated.</p>
                    <?php elseif ($accountStatus === 'rejected'): ?>
                        <h4>Verification Declined</h4>
                        <p>The registration request did not meet security review criteria.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Account Status Detail Table -->
            <div class="status-panel">
                <div class="status-item">
                    <span class="status-label">Registration Status</span>
                    <span class="status-value"><?php echo htmlspecialchars($regStatus); ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Review Status</span>
                    <span class="status-value <?php 
                        if ($accountStatus === 'pending') echo 'pending-text';
                        elseif ($accountStatus === 'active') echo 'approved-text';
                        else echo 'rejected-text';
                    ?>"><?php echo htmlspecialchars($revStatus); ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Account Access</span>
                    <span class="status-value <?php 
                        if ($accountStatus === 'pending') echo 'pending-text';
                        elseif ($accountStatus === 'active') echo 'approved-text';
                        else echo 'rejected-text';
                    ?>"><?php echo htmlspecialchars($accStatus); ?></span>
                </div>
            </div>

            <!-- Information Note Box -->
            <div class="info-note">
                Approval may take some time depending on administrator review. Please return to the login page after your account has been approved.
            </div>

            <!-- Page Actions -->
            <div class="actions">
                <a href="pending_approval.php" class="btn btn-secondary">Refresh Status</a>
                <a href="login.php" class="btn btn-primary">Go to Login</a>
                <a href="desktop.php" class="btn btn-secondary">Back to Homepage</a>
            </div>
        </section>
    </main>

    <!-- Success Modal Popup -->
    <?php if ($showApprovalPopup): ?>
        <div class="approval-overlay" id="approvalModal" role="dialog" aria-modal="true" aria-labelledby="approvalTitle">
            <section class="approval-modal">
                <div class="modal-status">Account Approved</div>
                <h2 id="approvalTitle">Account Approval Successful</h2>
                <p>Your account has been approved by the Super Administrator. You may now proceed to the login page and access the system using your approved account.</p>
                <div class="modal-actions">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                    <button type="button" class="btn btn-secondary" id="closeApprovalModal">Stay on Page</button>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <script>
        // Auto-refresh page every 30 seconds if registration status is pending
        <?php if ($accountStatus === 'pending'): ?>
        window.setTimeout(() => {
            window.location.reload();
        }, 30000);
        <?php endif; ?>

        // Close approval modal handler
        const closeApprovalModal = document.getElementById('closeApprovalModal');
        const approvalModal = document.getElementById('approvalModal');
        if (closeApprovalModal && approvalModal) {
            closeApprovalModal.addEventListener('click', () => {
                approvalModal.style.display = 'none';
            });
        }
    </script>
</body>
</html>
