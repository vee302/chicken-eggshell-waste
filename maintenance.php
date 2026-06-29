<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="System Offline - Green Forensics">
    <title>System Offline — Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="css/login.css?v=1.0">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .maintenance-card {
            text-align: center;
        }
        .maintenance-icon {
            color: #E07A5F; /* Warning / Alert Orange color */
            background: rgba(224, 122, 95, 0.1);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
        }
        .maintenance-title {
            font-size: 1.4rem;
            color: var(--dark-green);
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        .maintenance-message {
            font-size: 0.95rem;
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .maintenance-note {
            font-size: 0.82rem;
            color: #7A7A7A;
            background: var(--cream);
            padding: 1rem;
            border-radius: 10px;
            border: 1px dashed rgba(107, 143, 113, 0.3);
            line-height: 1.5;
            margin-bottom: 2rem;
        }
        .btn-back {
            display: block;
            width: 100%;
            background-color: var(--dark-green);
            color: var(--white);
            text-decoration: none;
            padding: 0.85rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.92rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            text-align: center;
        }
        .btn-back:hover {
            background-color: var(--forest-green);
            box-shadow: 0 4px 12px rgba(31, 63, 42, 0.15);
        }
    </style>
</head>
<body>
    <header style="margin-top: 2rem; z-index: 10;">
        <h1>Green Forensics</h1>
        <p>Evaluating System</p>
    </header>

    <div class="login-container">
        <div class="login-card maintenance-card">
            <div class="maintenance-icon">
                <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h2 class="maintenance-title">System Temporarily Under Maintenance</h2>
            <p class="maintenance-message">
                The Green Forensics Evaluating System is currently undergoing maintenance. Please try again later.
            </p>
            <div class="maintenance-note">
                This temporary downtime helps us improve system stability, security, and data reliability.
            </div>
            <a href="login.php" class="btn-back">Back to Login</a>
        </div>
    </div>

    <footer style="margin-bottom: 2rem; margin-top: 2rem; font-size: 0.8rem; color: var(--gray); text-align: center; z-index: 10;">
        <p>&copy; <?php echo date('Y'); ?> Green Forensics Evaluating System. All rights reserved.</p>
    </footer>
</body>
</html>
