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

$statusBadge = 'Pending Approval';
$cardTitle = 'Account Registration Submitted';
$mainMessage = 'Your registration has been submitted successfully.';
$secondaryMessage = 'Your account is currently under review by the Super Administrator. You will be able to log in once your account has been approved.';
$showWaiting = true;

if ($accountStatus === 'active') {
    $statusBadge = 'Approved';
    $mainMessage = 'Your account has been approved. You may now log in.';
    $secondaryMessage = 'Access is now available through the login page.';
    $showWaiting = false;
} elseif ($accountStatus === 'rejected') {
    $statusBadge = 'Not Approved';
    $mainMessage = 'Your registration was not approved. Please contact the system administrator.';
    $secondaryMessage = 'For additional guidance, please contact the Super Administrator or return to the homepage.';
    $showWaiting = false;
}

$processSteps = [
    ['number' => '1.', 'label' => 'Registration Submitted', 'status' => 'Completed'],
    ['number' => '2.', 'label' => 'Admin Review', 'status' => 'In Progress'],
    ['number' => '3.', 'label' => 'Role Assignment', 'status' => 'Pending'],
    ['number' => '4.', 'label' => 'Account Activation', 'status' => 'Pending'],
    ['number' => '5.', 'label' => 'Login Access', 'status' => 'Pending'],
];

if ($accountStatus === 'active') {
    $processSteps = [
        ['number' => '1.', 'label' => 'Registration Submitted', 'status' => 'Completed'],
        ['number' => '2.', 'label' => 'Admin Review', 'status' => 'Completed'],
        ['number' => '3.', 'label' => 'Role Assignment', 'status' => 'Completed'],
        ['number' => '4.', 'label' => 'Account Activation', 'status' => 'Completed'],
        ['number' => '5.', 'label' => 'Login Access', 'status' => 'Completed'],
    ];
} elseif ($accountStatus === 'rejected') {
    $processSteps = [
        ['number' => '1.', 'label' => 'Registration Submitted', 'status' => 'Completed'],
        ['number' => '2.', 'label' => 'Admin Review', 'status' => 'Completed'],
        ['number' => '3.', 'label' => 'Role Assignment', 'status' => 'Not Approved'],
        ['number' => '4.', 'label' => 'Account Activation', 'status' => 'Pending'],
        ['number' => '5.', 'label' => 'Login Access', 'status' => 'Pending'],
    ];
}

function status_class($status) {
    $key = strtolower(str_replace(' ', '-', $status));
    return 'step-status status-' . $key;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval - Green Forensics Evaluating System</title>
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
            --shadow: rgba(47, 79, 58, 0.10);
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
            max-width: 780px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            color: var(--dark-green);
            font-size: clamp(1.6rem, 4vw, 2.25rem);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 0.65rem;
        }

        .page-header p {
            font-size: 0.96rem;
            line-height: 1.5;
        }

        .status-card {
            background: var(--white);
            border: 1px solid rgba(107, 143, 113, 0.18);
            border-radius: 18px;
            box-shadow: 0 18px 42px var(--shadow);
            padding: clamp(1.5rem, 4vw, 2.5rem);
        }

        .card-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 1.25rem;
            margin-bottom: 1.35rem;
        }

        .card-heading h2 {
            color: var(--dark-green);
            font-size: clamp(1.25rem, 3vw, 1.65rem);
            font-weight: 800;
            line-height: 1.25;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: var(--dark-green);
            background: rgba(210, 226, 213, 0.7);
            border: 1px solid rgba(107, 143, 113, 0.24);
        }

        .status-badge.active {
            color: var(--forest-green);
            background: rgba(107, 143, 113, 0.14);
        }

        .status-badge.rejected {
            color: var(--danger);
            background: rgba(185, 74, 72, 0.09);
            border-color: rgba(185, 74, 72, 0.18);
        }

        .message-block {
            margin-bottom: 1.5rem;
        }

        .message-block .main-message {
            color: var(--dark-green);
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.55;
            margin-bottom: 0.45rem;
        }

        .message-block .secondary-message {
            font-size: 0.92rem;
            line-height: 1.65;
        }

        .waiting-section {
            border: 1px solid rgba(107, 143, 113, 0.18);
            background: rgba(250, 248, 241, 0.7);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .waiting-section p {
            color: var(--dark-green);
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .loading-line {
            width: 100%;
            height: 7px;
            background: #EDEDE7;
            border-radius: 999px;
            overflow: hidden;
        }

        .loading-line span {
            display: block;
            width: 42%;
            height: 100%;
            background: var(--soft-green);
            border-radius: inherit;
            animation: waitingLine 1.7s ease-in-out infinite;
        }

        @keyframes waitingLine {
            0% { transform: translateX(-110%); }
            50% { transform: translateX(70%); }
            100% { transform: translateX(250%); }
        }

        .process-section {
            margin-bottom: 1.5rem;
        }

        .process-section h3 {
            color: var(--dark-green);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 0.85rem;
        }

        .process-list {
            list-style: none;
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            overflow: hidden;
        }

        .process-list li {
            display: grid;
            grid-template-columns: 2rem 1fr auto;
            gap: 0.85rem;
            align-items: center;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--light-gray);
            font-size: 0.9rem;
        }

        .process-list li:last-child {
            border-bottom: none;
        }

        .step-number,
        .step-label {
            color: var(--dark-green);
            font-weight: 700;
        }

        .step-status {
            border-radius: 999px;
            padding: 0.28rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 800;
            white-space: nowrap;
            color: var(--gray);
            background: #F1F1EC;
        }

        .status-completed {
            color: var(--forest-green);
            background: rgba(107, 143, 113, 0.13);
        }

        .status-in-progress {
            color: var(--warning);
            background: rgba(183, 119, 13, 0.10);
        }

        .status-not-approved {
            color: var(--danger);
            background: rgba(185, 74, 72, 0.09);
        }

        .info-note {
            border-left: 4px solid var(--mint-green);
            background: rgba(210, 226, 213, 0.22);
            color: var(--gray);
            border-radius: 10px;
            padding: 1rem;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            font-weight: 800;
            text-decoration: none;
            border: 1.5px solid var(--mint-green);
            cursor: pointer;
            font-family: inherit;
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        .btn-primary {
            color: var(--white);
            background: var(--dark-green);
            border-color: var(--dark-green);
        }

        .btn-primary:hover {
            background: var(--forest-green);
            border-color: var(--forest-green);
        }

        .btn-secondary {
            color: var(--dark-green);
            background: var(--white);
        }

        .btn-secondary:hover {
            background: rgba(210, 226, 213, 0.35);
            border-color: var(--soft-green);
        }

        .refresh-form {
            display: contents;
        }

        @media (max-width: 640px) {
            body {
                align-items: flex-start;
            }

            .card-heading {
                flex-direction: column;
            }

            .process-list li {
                grid-template-columns: 1.75rem 1fr;
            }

            .step-status {
                grid-column: 2;
                width: fit-content;
            }

            .actions,
            .btn {
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

            <?php if ($showWaiting): ?>
                <div class="waiting-section" aria-live="polite">
                    <p>Waiting for Super Administrator approval...</p>
                    <div class="loading-line" aria-hidden="true"><span></span></div>
                </div>
            <?php endif; ?>

            <div class="process-section">
                <h3>Registration Process</h3>
                <ol class="process-list">
                    <?php foreach ($processSteps as $step): ?>
                        <li>
                            <span class="step-number"><?php echo htmlspecialchars($step['number']); ?></span>
                            <span class="step-label"><?php echo htmlspecialchars($step['label']); ?></span>
                            <span class="<?php echo htmlspecialchars(status_class($step['status'])); ?>">
                                <?php echo htmlspecialchars($step['status']); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <p class="info-note">
                Approval may take some time depending on administrator review. Please return to the login page after your account has been approved.
            </p>

            <div class="actions">
                <form class="refresh-form" method="GET" action="pending_approval.php">
                    <button type="submit" class="btn btn-secondary">Refresh Status</button>
                </form>
                <a href="login.php" class="btn btn-primary">Go to Login</a>
                <a href="desktop.php" class="btn btn-secondary">Back to Homepage</a>
            </div>
        </section>
    </main>
</body>
</html>
