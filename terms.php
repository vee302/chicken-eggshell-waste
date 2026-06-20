<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Use - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="css/login.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .terms-container {
            width: 100%;
            max-width: 680px;
            margin: 0 auto;
            z-index: 10;
        }
        .terms-card {
            background-color: var(--white);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            box-shadow: 0 10px 30px var(--shadow);
            border: 1px solid rgba(107, 143, 113, 0.15);
        }
        .terms-content {
            text-align: left;
            margin-top: 1.5rem;
            color: var(--gray);
            line-height: 1.6;
            font-size: 0.92rem;
        }
        .terms-content h3 {
            color: var(--dark-green);
            font-size: 1.1rem;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .terms-content p {
            margin-bottom: 1rem;
        }
        .terms-content ul {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .terms-content li {
            margin-bottom: 0.5rem;
        }
        .highlight-notice {
            background-color: rgba(47, 79, 58, 0.05);
            border-left: 4px solid var(--dark-green);
            padding: 1rem;
            border-radius: 0 12px 12px 0;
            margin: 1.5rem 0;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <header>
        <h1>Green Forensics Evaluating System</h1>
        <p>Innovative Sustainable Fingerprint Powder Using Chicken Eggshell Waste</p>
    </header>

    <main class="terms-container">
        <div class="terms-card">
            <div class="card-header">
                <h2>Terms of Use</h2>
                <p>Last updated: June 2026</p>
            </div>

            <div class="terms-content">
                <p>Welcome to the Green Forensics Evaluating System. By registering an account or using our system, you agree to comply with and be bound by the following Terms of Use. Please review them carefully.</p>

                <h3>1. Authorized Access Only</h3>
                <p>Access to this evaluating system is restricted to authorized academic researchers, students, and collaborative police partners. Registration requests must be approved by the Super Administrator before system access is granted.</p>

                <h3>2. Account Approval & Review</h3>
                <p>All user accounts are subject to administrative review. The Super Administrator reserves the right to approve, reject, or suspend any user account at any time for security audits or system safety maintenance.</p>

                <h3>3. Accurate Information Submission</h3>
                <p>When creating your registration request, you must provide true, accurate, and complete information, including your full name, employee/student ID number, email, and requested role. Submitting fraudulent details or falsified credentials will result in immediate rejection or termination of the account.</p>

                <h3>4. Acceptable System Use</h3>
                <p>Users must not misuse the system. This includes but is not limited to attempting to bypass system authentication, injecting malicious code, scraping data, or abusing collaborative evaluation records. Any suspicious activities will be recorded in the security audit logs and may be reported to institution authorities.</p>

                <div class="highlight-notice">
                    <strong>Critical Information on Fingerprint Data:</strong><br>
                    Please note that any fingerprint images uploaded to the Green Forensics Evaluating System are used strictly and only for academic research evaluation and image quality assessment. The system is not designed, used, nor intended for biometric identification or tracking purposes.
                </div>

                <h3>5. Liability & Disclaimer</h3>
                <p>This evaluating system is provided on an "as is" and "as available" basis for academic research and evaluation. The development team does not guarantee uninterrupted or error-free operations.</p>
            </div>

            <div class="back-link-wrapper" style="margin-top: 2rem;">
                <button onclick="window.close();" class="btn-primary" style="max-width: 200px; margin: 0 auto;">
                    <span>Close Window</span>
                </button>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Green Forensics Project | LSPU CCJE San Pablo City Campus</p>
    </footer>
<?php include __DIR__ . '/support-assistant/support_widget.php'; ?>
</body>
</html>
