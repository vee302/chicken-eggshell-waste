<?php
// register.php - Multi-Step User Registration for Green Forensics Evaluating System
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'super_admin') {
        header("Location: admin/dashboard.php");
    } elseif ($role === 'faculty_researcher') {
        header("Location: faculty/faculty_dashboard.php");
    } elseif ($role === 'criminology_student') {
        header("Location: student/student_dashboard.php");
    } elseif ($role === 'alumni_police_partner') {
        header("Location: police-partner/partner_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

require_once "config.php";

$error_message = "";
$form_data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_registration'])) {
    $first_name      = trim($_POST["first_name"] ?? "");
    $middle_name     = trim($_POST["middle_name"] ?? "");
    $last_name       = trim($_POST["last_name"] ?? "");
    $id_number       = trim($_POST["id_number"] ?? "");
    $contact_number  = trim($_POST["contact_number"] ?? "");
    $email           = strtolower(trim($_POST["email"] ?? ""));
    $requested_role  = trim($_POST["requested_role"] ?? "");
    $reason          = trim($_POST["reason_for_access"] ?? "");
    $password        = trim($_POST["password"] ?? "");
    $confirm_pass    = trim($_POST["confirm_password"] ?? "");
    $full_name       = trim("$first_name $middle_name $last_name");

    // Preserve form data for re-fill
    $form_data = compact('first_name', 'middle_name', 'last_name', 'id_number', 'contact_number', 'email', 'requested_role', 'reason');

    // Server-side Validation
    if (empty($first_name)) {
        $error_message = "First Name is required.";
    } elseif (empty($last_name)) {
        $error_message = "Last Name is required.";
    } elseif (empty($id_number)) {
        $error_message = "ID Number is required.";
    } elseif (empty($contact_number)) {
        $error_message = "Contact Number is required.";
    } elseif (empty($email)) {
        $error_message = "Email Address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (empty($requested_role)) {
        $error_message = "Requested Role is required.";
    } elseif (!in_array($requested_role, ['criminology_student', 'faculty_researcher', 'alumni_police_partner'])) {
        $error_message = "Invalid requested role selected.";
    } elseif (empty($reason)) {
        $error_message = "Reason for Access is required.";
    } elseif (empty($password)) {
        $error_message = "Password is required.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters.";
    } elseif (empty($confirm_pass)) {
        $error_message = "Confirm Password is required.";
    } elseif ($password !== $confirm_pass) {
        $error_message = "Password and Confirm Password must match.";
    } else {
        try {
            // Check email uniqueness
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $chk->execute([':email' => $email]);
            
            if ($chk->fetch()) {
                $error_message = "An account with this email address already exists.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $ins = $pdo->prepare("INSERT INTO users
                    (first_name, middle_name, last_name, full_name, email, password, contact_number, id_number, requested_role, reason_for_access, role, status)
                    VALUES (:fn, :mn, :ln, :full, :email, :pass, :contact, :idnum, :reqrole, :reason, NULL, 'pending')");
                
                $ins->execute([
                    ':fn'      => $first_name,
                    ':mn'      => $middle_name !== "" ? $middle_name : null,
                    ':ln'      => $last_name,
                    ':full'    => $full_name,
                    ':email'   => $email,
                    ':pass'    => $hashed,
                    ':contact' => $contact_number,
                    ':idnum'   => $id_number,
                    ':reqrole' => $requested_role,
                    ':reason'  => $reason
                ]);

                $registeredUserId = (int)$pdo->lastInsertId();
                $_SESSION['pending_registration_user_id'] = $registeredUserId;
                $_SESSION['pending_registration_email'] = $email;

                header("Location: pending_approval.php");
                exit;
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
    <title>Register - Green Forensics Evaluating System</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .login-container { max-width: 640px; }
        
        /* Progress Indicator Styles */
        .progress-indicator {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2.25rem;
            position: relative;
        }
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        .progress-step .step-num {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--white);
            border: 2px solid var(--light-gray);
            color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            margin-bottom: 0.5rem;
        }
        .progress-step .step-text {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--gray);
            text-align: center;
            transition: all 0.3s ease;
        }
        .progress-step.active .step-num {
            border-color: var(--dark-green);
            background-color: var(--dark-green);
            color: var(--white);
            box-shadow: 0 0 0 4px rgba(47, 79, 58, 0.15);
        }
        .progress-step.active .step-text {
            color: var(--dark-green);
            font-weight: 700;
        }
        .progress-step.completed .step-num {
            border-color: var(--soft-green);
            background-color: var(--soft-green);
            color: var(--white);
        }
        .progress-step.completed .step-text {
            color: var(--soft-green);
        }
        .progress-line {
            position: absolute;
            top: 18px;
            left: 25%;
            right: 25%;
            height: 2px;
            background-color: var(--light-gray);
            z-index: 1;
            transition: all 0.3s ease;
        }
        .progress-line.active {
            background-color: var(--soft-green);
        }

        /* Form Steps Transitions */
        .form-step { display: none; }
        .form-step.active { display: block; animation: fadeStep 0.35s ease; }
        @keyframes fadeStep {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Form Layout Grid */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 1.25rem;
        }
        @media (max-width: 576px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        .form-control-plain {
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
        }
        .form-control-plain:focus {
            border-color: var(--soft-green);
            box-shadow: 0 0 0 4px rgba(107, 143, 113, 0.15);
        }
        select.form-control-plain { cursor: pointer; }

        .required-star { color: var(--error-red); margin-left: 2px; }
        
        .field-hint {
            font-size: 0.76rem;
            color: var(--gray);
            margin-top: 0.35rem;
            line-height: 1.4;
        }

        /* Step Navigation Buttons */
        .form-nav {
            display: flex;
            gap: 1rem;
            margin-top: 1.75rem;
            border-top: 1.5px solid var(--cream);
            padding-top: 1.5rem;
        }
        .btn-next, .btn-back, .btn-cancel {
            padding: 0.85rem;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-next {
            flex: 2;
            background: var(--dark-green);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(47, 79, 58, 0.15);
        }
        .btn-next:hover {
            background: var(--forest-green);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(47, 79, 58, 0.25);
        }
        .btn-back {
            flex: 1;
            background: var(--white);
            color: var(--dark-green);
            border: 1.5px solid var(--mint-green);
        }
        .btn-back:hover {
            background: var(--cream);
        }
        .btn-cancel {
            flex: 1;
            background: transparent;
            color: var(--gray);
            border: 1.5px solid var(--light-gray);
        }
        .btn-cancel:hover {
            background: rgba(0, 0, 0, 0.03);
        }
    </style>
</head>
<body>
    <header>
        <h1>Green Forensics Evaluating System</h1>
        <p>Innovative Sustainable Fingerprint Powder Using Chicken Eggshell Waste</p>
    </header>

    <main class="login-container">
        <!-- Skeleton Loader -->
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

        <!-- Real Form Card -->
        <div class="login-card" id="realRegisterCard" style="display:none;opacity:0;">
            <div class="card-header">
                <h2>Create Account</h2>
                <p>Register as an authorized user of the Evaluating System</p>
            </div>

            <!-- Error Alerts -->
            <div class="alert alert-danger" id="clientErrorBox" style="display:none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span id="clientErrorMessage"></span>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" id="serverErrorBox">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Cleaner Progress Indicator -->
            <div class="progress-indicator">
                <div class="progress-step active" id="progStep1">
                    <div class="step-num">1</div>
                    <span class="step-text">Profile &amp; Identity</span>
                </div>
                <div class="progress-line" id="progLine"></div>
                <div class="progress-step" id="progStep2">
                    <div class="step-num">2</div>
                    <span class="step-text">Access Request</span>
                </div>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="registerForm" autocomplete="off" novalidate>
                <!-- ===== STEP 1: Profile & Identity ===== -->
                <div class="form-step active" id="step1">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required-star">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="form-control-plain" placeholder="First Name" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" class="form-control-plain" placeholder="Middle Name" value="<?php echo htmlspecialchars($form_data['middle_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required-star">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control-plain" placeholder="Last Name" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="id_number">Student / Employee / Partner ID Number <span class="required-star">*</span></label>
                        <input type="text" id="id_number" name="id_number" class="form-control-plain" placeholder="e.g. 2024-CCJE-0001" value="<?php echo htmlspecialchars($form_data['id_number'] ?? ''); ?>">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="contact_number">Contact Number <span class="required-star">*</span></label>
                            <input type="tel" id="contact_number" name="contact_number" class="form-control-plain" placeholder="e.g. 09XXXXXXXXX" value="<?php echo htmlspecialchars($form_data['contact_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address <span class="required-star">*</span></label>
                            <input type="email" id="email" name="email" class="form-control-plain" placeholder="you@example.com" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-nav">
                        <a href="login.php" class="btn-cancel">Cancel</a>
                        <button type="button" class="btn-next" id="nextBtn" onclick="goToStep2()">Next Step</button>
                    </div>
                </div>

                <!-- ===== STEP 2: Access Request ===== -->
                <div class="form-step" id="step2">
                    <div class="form-group">
                        <label for="requested_role">Requested Role <span class="required-star">*</span></label>
                        <select id="requested_role" name="requested_role" class="form-control-plain">
                            <option value="" disabled <?php echo empty($form_data['requested_role']) ? 'selected' : ''; ?>>Select your role</option>
                            <option value="criminology_student" <?php echo ($form_data['requested_role'] ?? '') === 'criminology_student' ? 'selected' : ''; ?>>Criminology Student</option>
                            <option value="faculty_researcher" <?php echo ($form_data['requested_role'] ?? '') === 'faculty_researcher' ? 'selected' : ''; ?>>Faculty Researcher</option>
                            <option value="alumni_police_partner" <?php echo ($form_data['requested_role'] ?? '') === 'alumni_police_partner' ? 'selected' : ''; ?>>Alumni / Police Partner</option>
                        </select>
                        <p class="field-hint">Super Administrator role is not available for public registration.</p>
                    </div>

                    <div class="form-group">
                        <label for="reason_for_access">Reason for Access <span class="required-star">*</span></label>
                        <textarea id="reason_for_access" name="reason_for_access" class="form-control-plain" rows="3" placeholder="Briefly explain your purpose for accessing the Green Forensics system."><?php echo htmlspecialchars($form_data['reason'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="password">Password <span class="required-star">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-control-plain" placeholder="Minimum 6 characters">
                            <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                <span class="icon-eye">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </span>
                                <span class="icon-eye-off">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94"></path>
                                        <path d="M9.9 4.24A10.84 10.84 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19"></path>
                                        <path d="M14.12 14.12A3 3 0 0 1 9.88 9.88"></path>
                                        <line x1="3" y1="3" x2="21" y2="21"></line>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required-star">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control-plain" placeholder="Confirm your password">
                            <button type="button" class="password-toggle" data-password-toggle="confirm_password" aria-label="Show password" aria-pressed="false">
                                <span class="icon-eye">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </span>
                                <span class="icon-eye-off">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94"></path>
                                        <path d="M9.9 4.24A10.84 10.84 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19"></path>
                                        <path d="M14.12 14.12A3 3 0 0 1 9.88 9.88"></path>
                                        <line x1="3" y1="3" x2="21" y2="21"></line>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-back" onclick="goToStep1()">Back</button>
                        <button type="submit" name="submit_registration" class="btn-next" id="submitBtn" onclick="return validateStep2()">Submit Registration</button>
                    </div>
                </div>
            </form>

            <div class="register-link-wrapper">
                <span>Already have an account?</span>
                <a href="login.php" class="register-link">Login here</a>
            </div>

            <div class="back-link-wrapper">
                <a href="index.php" class="back-link">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Back to Homepage</span>
                </a>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Green Forensics Project | LSPU CCJE San Pablo City Campus</p>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const skeleton = document.getElementById("skeletonCard");
            const realCard = document.getElementById("realRegisterCard");

            // Smoothly reveal real form card
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

                    // If server error occurred, check if we need to return to step 2
                    <?php if (!empty($error_message) && !empty($form_data['requested_role'])): ?>
                    goToStep2(true);
                    <?php endif; ?>
                }, 400);
            }, 1000);

            // Password field toggle visibility helper
            document.querySelectorAll("[data-password-toggle]").forEach((button) => {
                const input = document.getElementById(button.dataset.passwordToggle);
                if (!input) return;

                button.addEventListener("click", () => {
                    const shouldShow = input.type === "password";
                    input.type = shouldShow ? "text" : "password";
                    button.classList.toggle("is-visible", shouldShow);
                    button.setAttribute("aria-pressed", shouldShow ? "true" : "false");
                });
            });
        });

        const clientErrorBox = document.getElementById("clientErrorBox");
        const clientErrorMessage = document.getElementById("clientErrorMessage");

        function showClientError(msg) {
            clientErrorMessage.textContent = msg;
            clientErrorBox.style.display = "flex";
            const serverError = document.getElementById("serverErrorBox");
            if (serverError) serverError.style.display = "none";
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function hideClientError() {
            clientErrorBox.style.display = "none";
        }

        function goToStep2(bypassValidation = false) {
            hideClientError();

            if (!bypassValidation) {
                const fn = document.getElementById("first_name").value.trim();
                const ln = document.getElementById("last_name").value.trim();
                const idNum = document.getElementById("id_number").value.trim();
                const contact = document.getElementById("contact_number").value.trim();
                const email = document.getElementById("email").value.trim();

                if (!fn) { showClientError("First Name is required."); return; }
                if (!ln) { showClientError("Last Name is required."); return; }
                if (!idNum) { showClientError("ID Number is required."); return; }
                if (!contact) { showClientError("Contact Number is required."); return; }
                if (!email) { showClientError("Email Address is required."); return; }
                
                // Email format validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showClientError("Please enter a valid email address.");
                    return;
                }
            }

            document.getElementById("step1").classList.remove("active");
            document.getElementById("step2").classList.add("active");

            document.getElementById("progStep1").classList.remove("active");
            document.getElementById("progStep1").classList.add("completed");
            document.getElementById("progLine").classList.add("active");
            document.getElementById("progStep2").classList.add("active");
        }

        function goToStep1() {
            hideClientError();

            document.getElementById("step2").classList.remove("active");
            document.getElementById("step1").classList.add("active");

            document.getElementById("progStep2").classList.remove("active");
            document.getElementById("progLine").classList.remove("active");
            document.getElementById("progStep1").classList.remove("completed");
            document.getElementById("progStep1").classList.add("active");
        }

        function validateStep2() {
            hideClientError();
            
            const role = document.getElementById("requested_role").value;
            const reason = document.getElementById("reason_for_access").value.trim();
            const pass = document.getElementById("password").value;
            const conf = document.getElementById("confirm_password").value;

            if (!role) { showClientError("Requested Role is required."); return false; }
            if (!reason) { showClientError("Reason for Access is required."); return false; }
            if (!pass) { showClientError("Password is required."); return false; }
            if (pass.length < 6) { showClientError("Password must be at least 6 characters."); return false; }
            if (!conf) { showClientError("Confirm Password is required."); return false; }
            if (pass !== conf) { showClientError("Password and Confirm Password must match."); return false; }

            return true;
        }
    </script>
</body>
</html>
