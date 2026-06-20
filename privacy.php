<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Green Forensics</title>
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
                <h2>Privacy Policy</h2>
                <p>Last updated: June 2026</p>
            </div>

            <div class="terms-content">
                <p>This Privacy Policy describes how the Green Forensics Evaluating System collects, uses, and protects your information when you register and use our system. We are committed to ensuring your privacy is protected.</p>

                <h3>1. Information We Collect</h3>
                <p>During the registration process, we collect the following personal and professional details:</p>
                <ul>
                    <li>Full Name (First, Middle, and Last Name)</li>
                    <li>Student, Employee, or Partner ID Number</li>
                    <li>Contact Number (Philippine Mobile Number)</li>
                    <li>Email Address</li>
                    <li>Requested Role and Reason for Access</li>
                    <li>Proof of Affiliation (Uploaded Document/Image)</li>
                </ul>

                <h3>2. Role-Based Visibility and Access Controls</h3>
                <p>We implement role-based access control (RBAC) to ensure security and privacy. Your profile, affiliation proof, and system records are only accessible to the Super Administrator for account review and authentication audits. Specific application activities are isolated based on your approved role (e.g., student, faculty researcher, or police partner).</p>

                <div class="highlight-notice">
                    <strong>Critical Information on Fingerprint Data:</strong><br>
                    Any fingerprint images uploaded or processed within the Green Forensics Evaluating System are used strictly and exclusively for academic research evaluation, testing of the green eggshell-based powder, and image quality assessment. The system does not store, use, or share fingerprint images for biometric identification, personal tracking, or law enforcement search purposes.
                </div>

                <h3>3. Security of Your Information</h3>
                <p>We are committed to securing your data. We implement industry-standard database security practices, including password hashing and restricted folder access control, to prevent unauthorized access, alteration, or disclosure of your registration and research logs.</p>

                <h3>4. Data Retention and Limits</h3>
                <p>Your registration data is retained as long as your account remains active or pending review. If an account is rejected or deleted, all associated details, including the proof of affiliation document, are purged from the system. Uploaded evaluation fingerprint images are archived exclusively for academic reporting and system evaluation metrics.</p>
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
<?php include 'includes/support_chat_widget.php'; ?>
</body>
</html>
