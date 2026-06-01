<?php
// register.php - Multi-Step User Registration for Green Forensics Evaluating System
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'super_admin') header("Location: admin/admin_dashboard.php");
    elseif ($role === 'faculty_researcher') header("Location: faculty/faculty_dashboard.php");
    else header("Location: dashboard.php");
    exit;
}

require_once "config.php";

$error_message = "";
$success_message = "";
$form_data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_registration'])) {
    $first_name      = trim($_POST["first_name"] ?? "");
    $middle_name     = trim($_POST["middle_name"] ?? "");
    $last_name       = trim($_POST["last_name"] ?? "");
    $id_number       = trim($_POST["id_number"] ?? "");
    $department      = trim($_POST["department"] ?? "");
    $contact_number  = trim($_POST["contact_number"] ?? "");
    $email           = strtolower(trim($_POST["email"] ?? ""));
    $requested_role  = trim($_POST["requested_role"] ?? "");
    $reason          = trim($_POST["reason_for_access"] ?? "");
    $password        = trim($_POST["password"] ?? "");
    $confirm_pass    = trim($_POST["confirm_password"] ?? "");
    $full_name       = trim("$first_name $middle_name $last_name");

    // Preserve form data for re-fill
    $form_data = compact('first_name','middle_name','last_name','id_number','department','contact_number','email','requested_role','reason');

    // Validation
    if (empty($first_name) || empty($last_name) || empty($id_number) || empty($contact_number) || empty($email) || empty($requested_role) || empty($reason) || empty($password) || empty($confirm_pass)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (!preg_match('/^(09|\+639)\d{9}$/', $contact_number)) {
        $error_message = "Contact number must be a valid Philippine mobile number (e.g. 09XXXXXXXXX).";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_pass) {
        $error_message = "Passwords do not match.";
    } elseif (!in_array($requested_role, ['criminology_student','faculty_researcher','alumni_police_partner'])) {
        $error_message = "Invalid requested role.";
    } else {
        try {
            $chk = $pdo->prepare("SELECT id, status FROM users WHERE email = :email LIMIT 1");
            $chk->execute([':email' => $email]);
            $existing_user = $chk->fetch(PDO::FETCH_ASSOC);

            if ($existing_user && $existing_user['status'] === 'active') {
                $error_message = "An active account with this email already exists. Please login or use another email.";
            } elseif ($existing_user && in_array($existing_user['status'], ['inactive', 'suspended'], true)) {
                $error_message = "This email belongs to an inactive account. Please contact the administrator.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $registrationData = [
                    ':fn'      => $first_name,
                    ':mn'      => $middle_name,
                    ':ln'      => $last_name,
                    ':full'    => $full_name,
                    ':email'   => $email,
                    ':pass'    => $hashed,
                    ':contact' => $contact_number,
                    ':idnum'   => $id_number,
                    ':dept'    => '',
                    ':aff'     => '',
                    ':reqrole' => $requested_role,
                    ':reason'  => $reason,
                ];

                if ($existing_user) {
                    $upd = $pdo->prepare("UPDATE users SET
                        first_name = :fn,
                        middle_name = :mn,
                        last_name = :ln,
                        full_name = :full,
                        email = :email,
                        password = :pass,
                        contact_number = :contact,
                        id_number = :idnum,
                        department = :dept,
                        affiliation = :aff,
                        requested_role = :reqrole,
                        reason_for_access = :reason,
                        role = NULL,
                        status = 'pending',
                        created_at = CURRENT_TIMESTAMP
                        WHERE id = :existing_id");
                    $registrationData[':existing_id'] = $existing_user['id'];
                    $upd->execute($registrationData);
                } else {
                    $ins = $pdo->prepare("INSERT INTO users
                        (first_name, middle_name, last_name, full_name, email, password, contact_number, id_number, department, affiliation, requested_role, reason_for_access, role, status)
                        VALUES (:fn,:mn,:ln,:full,:email,:pass,:contact,:idnum,:dept,:aff,:reqrole,:reason,NULL,'pending')");
                    $ins->execute($registrationData);
                }

                $success_message = "Registration submitted successfully. Please wait for Super Administrator approval.";
                $form_data = [];
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
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
        .login-container { max-width: 560px; }

        /* Progress Steps */
        .step-progress {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.75rem;
            gap: 0;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            flex: 1;
            position: relative;
        }
        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 18px;
            left: 60%;
            width: 80%;
            height: 2px;
            background: var(--light-gray);
            z-index: 0;
            transition: background 0.4s;
        }
        .step-item.completed:not(:last-child)::after,
        .step-item.active:not(:last-child)::after {
            background: var(--soft-green);
        }
        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            border: 2px solid var(--light-gray);
            background: var(--white);
            color: var(--gray);
            z-index: 1;
            transition: all 0.3s ease;
        }
        .step-item.active .step-circle {
            border-color: var(--dark-green);
            background: var(--dark-green);
            color: var(--white);
            box-shadow: 0 0 0 4px rgba(47,79,58,0.15);
        }
        .step-item.completed .step-circle {
            border-color: var(--soft-green);
            background: var(--soft-green);
            color: var(--white);
        }
        .step-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray);
            text-align: center;
        }
        .step-item.active .step-label { color: var(--dark-green); }
        .step-item.completed .step-label { color: var(--soft-green); }

        /* Form Steps */
        .form-step { display: none; }
        .form-step.active { display: block; animation: fadeStep 0.35s ease; }
        @keyframes fadeStep {
            from { opacity: 0; transform: translateX(16px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .form-step.back-anim { animation: fadeStepBack 0.35s ease; }
        @keyframes fadeStepBack {
            from { opacity: 0; transform: translateX(-16px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* Two-column grid */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 1rem;
        }
        @media (max-width: 520px) { .form-row { grid-template-columns: 1fr; } }

        .form-control-plain {
            width: 100%;
            padding: 0.85rem 1rem;
            font-size: 0.9rem;
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
            box-shadow: 0 0 0 4px rgba(107,143,113,0.15);
        }
        select.form-control-plain { cursor: pointer; }

        .section-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--soft-green);
            margin-bottom: 0.75rem;
            padding-bottom: 0.4rem;
            border-bottom: 1px solid var(--mint-green);
        }

        /* Nav buttons */
        .form-nav {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        .btn-next, .btn-back, .btn-cancel {
            flex: 1;
            padding: 0.85rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-next {
            background: var(--dark-green);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(47,79,58,0.2);
        }
        .btn-next:hover { background: var(--forest-green); transform: translateY(-1px); }
        .btn-back {
            background: var(--white);
            color: var(--dark-green);
            border: 1.5px solid var(--mint-green);
        }
        .btn-back:hover { background: var(--mint-green); }
        .btn-cancel {
            background: transparent;
            color: var(--gray);
            border: 1.5px solid var(--light-gray);
            font-size: 0.82rem;
            flex: 0 0 auto;
            padding: 0.85rem 1rem;
        }
        .btn-cancel:hover { background: #f5f5f5; }

        .field-hint {
            font-size: 0.74rem;
            color: var(--soft-green);
            margin-top: 0.3rem;
        }
        .required-star { color: #c0392b; margin-left: 2px; }

        .success-box {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
        }
        .success-box .check-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(107,143,113,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--soft-green);
        }
        .success-box h3 { color: var(--dark-green); font-size: 1.1rem; margin-bottom: 0.5rem; }
        .success-box p { font-size: 0.875rem; color: var(--gray); line-height: 1.55; }
    </style>
</head>
<body>
    <header>
        <h1>Green Forensics Evaluating System</h1>
        <p>Request an authorized account to access the evaluating system</p>
    </header>

    <main class="login-container">
        <!-- Skeleton -->
        <div class="login-card" id="skeletonCard">
            <div class="skeleton-loader">
                <div class="skeleton-item skeleton-icon"></div>
                <div class="skeleton-item skeleton-title"></div>
                <div class="skeleton-item skeleton-subtitle"></div>
                <div class="skeleton-item skeleton-label"></div>
                <div class="skeleton-item skeleton-input"></div>
                <div class="skeleton-item skeleton-label"></div>
                <div class="skeleton-item skeleton-input"></div>
                <div class="skeleton-item skeleton-label"></div>
                <div class="skeleton-item skeleton-input"></div>
                <div class="skeleton-item skeleton-button"></div>
            </div>
        </div>

        <!-- Real Register Card -->
        <div class="login-card" id="realRegisterCard" style="display:none;opacity:0;">
            <div class="card-header">
                <div class="card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                </div>
                <h2>Create Account</h2>
                <p>Register as an authorized Green Forensics user</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="success-box">
                    <div class="check-icon">
                        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <h3>Registration Submitted!</h3>
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                    <div style="margin-top:1.5rem;">
                        <a href="login.php" class="btn-primary" style="display:inline-flex;text-decoration:none;max-width:220px;margin:0 auto;">Go to Login</a>
                    </div>
                </div>
            <?php else: ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Progress Indicator -->
            <div class="step-progress" id="stepProgress">
                <div class="step-item active" id="progressStep1">
                    <div class="step-circle">1</div>
                    <span class="step-label">Profile &amp; Identity</span>
                </div>
                <div class="step-item" id="progressStep2">
                    <div class="step-circle">2</div>
                    <span class="step-label">Access Request</span>
                </div>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="registerForm" autocomplete="off" novalidate>

                <!-- ===== STEP 1: Profile & Identity ===== -->
                <div class="form-step active" id="step1">
                    <p class="section-label">Personal Information</p>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required-star">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="form-control-plain"
                                placeholder="e.g. Juan"
                                value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" class="form-control-plain"
                                placeholder="e.g. Santos"
                                value="<?php echo htmlspecialchars($form_data['middle_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required-star">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control-plain"
                            placeholder="e.g. Dela Cruz"
                            value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="id_number">Student / Employee / Partner ID Number <span class="required-star">*</span></label>
                        <input type="text" id="id_number" name="id_number" class="form-control-plain"
                            placeholder="e.g. 2024-CCJE-0001"
                            value="<?php echo htmlspecialchars($form_data['id_number'] ?? ''); ?>">
                    </div>



                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_number">Contact Number <span class="required-star">*</span></label>
                            <input type="tel" id="contact_number" name="contact_number" class="form-control-plain"
                                placeholder="09XXXXXXXXX"
                                value="<?php echo htmlspecialchars($form_data['contact_number'] ?? ''); ?>">
                            <p class="field-hint">Philippine mobile format</p>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address <span class="required-star">*</span></label>
                            <input type="email" id="email" name="email" class="form-control-plain"
                                placeholder="you@example.com"
                                value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-nav">
                        <a href="login.php" class="btn-cancel">Cancel</a>
                        <button type="button" class="btn-next" id="nextBtn" onclick="goToStep2()">
                            Next Step
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- ===== STEP 2: Account & Access Request ===== -->
                <div class="form-step" id="step2">
                    <p class="section-label">Account &amp; Access Request</p>

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
                        <textarea id="reason_for_access" name="reason_for_access" class="form-control-plain"
                            rows="3" placeholder="Briefly explain your purpose for accessing the Green Forensics system..."
                            style="resize:vertical;"><?php echo htmlspecialchars($form_data['reason'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="password">Password <span class="required-star">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-control-plain"
                                placeholder="Minimum 6 characters">
                            <button type="button" class="password-toggle" data-password-toggle="password"
                                aria-label="Show password" aria-pressed="false">
                                <span class="icon-eye">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </span>
                                <span class="icon-eye-off">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94"></path>
                                        <path d="M9.9 4.24A10.84 10.84 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19"></path>
                                        <path d="M14.12 14.12A3 3 0 0 1 9.88 9.88"></path>
                                        <line x1="3" y1="3" x2="21" y2="21"></line>
                                    </svg>
                                </span>
                            </button>
                        </div>
                        <p class="field-hint">At least 6 characters</p>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required-star">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control-plain"
                                placeholder="Re-enter your password">
                            <button type="button" class="password-toggle" data-password-toggle="confirm_password"
                                aria-label="Show password" aria-pressed="false">
                                <span class="icon-eye">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </span>
                                <span class="icon-eye-off">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94"></path>
                                        <path d="M9.9 4.24A10.84 10.84 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19"></path>
                                        <path d="M14.12 14.12A3 3 0 0 1 9.88 9.88"></path>
                                        <line x1="3" y1="3" x2="21" y2="21"></line>
                                    </svg>
                                </span>
                            </button>
                        </div>
                        <p class="field-hint" id="passMatchHint" style="display:none;color:#c0392b;">Passwords do not match.</p>
                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-back" onclick="goToStep1()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Back
                        </button>
                        <button type="submit" name="submit_registration" class="btn-next" id="submitBtn">
                            Submit Registration
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </button>
                    </div>
                </div>

            </form>

            <?php endif; ?>

            <?php if (empty($success_message)): ?>
            <div class="login-link-wrapper">
                <span>Already have an account?</span>
                <a href="login.php" class="login-link">Login here</a>
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
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Green Forensics Project | LSPU CCJE San Pablo City Campus</p>
    </footer>

    <script>
        // Skeleton reveal
        document.addEventListener("DOMContentLoaded", () => {
            const skeleton = document.getElementById("skeletonCard");
            const realCard = document.getElementById("realRegisterCard");
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
                    // If there was a server error on step 2 fields, go to step2
                    <?php if (!empty($error_message) && !empty($form_data['requested_role'])): ?>
                    goToStep2(true);
                    <?php endif; ?>
                }, 400);
            }, 1200);

            // Live password match check
            const passInput = document.getElementById("password");
            const confInput = document.getElementById("confirm_password");
            const hint = document.getElementById("passMatchHint");
            if (confInput && passInput) {
                confInput.addEventListener("input", () => {
                    if (confInput.value && passInput.value !== confInput.value) {
                        hint.style.display = "block";
                    } else {
                        hint.style.display = "none";
                    }
                });
            }

            document.querySelectorAll("[data-password-toggle]").forEach((button) => {
                const input = document.getElementById(button.dataset.passwordToggle);
                if (!input) return;

                button.addEventListener("click", () => {
                    const shouldShow = input.type === "password";
                    input.type = shouldShow ? "text" : "password";
                    button.classList.toggle("is-visible", shouldShow);
                    button.setAttribute("aria-pressed", shouldShow ? "true" : "false");
                    button.setAttribute("aria-label", shouldShow ? "Hide password" : "Show password");
                });
            });
        });

        function goToStep2(fromError = false) {
            // Validate step 1 fields
            const fields = ['first_name','last_name','id_number','contact_number','email'];
            let valid = true;
            fields.forEach(f => {
                const el = document.getElementById(f);
                if (!el || !el.value.trim()) { valid = false; el && (el.style.borderColor = '#c0392b'); }
                else el.style.borderColor = '';
            });
            // Email check
            const emailEl = document.getElementById('email');
            if (emailEl && emailEl.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value)) {
                valid = false; emailEl.style.borderColor = '#c0392b';
            }
            if (!valid && !fromError) {
                alert('Please fill in all required fields in Step 1 correctly.');
                return;
            }

            document.getElementById('step1').classList.remove('active');
            const s2 = document.getElementById('step2');
            s2.classList.add('active');
            document.getElementById('progressStep1').classList.remove('active');
            document.getElementById('progressStep1').classList.add('completed');
            document.getElementById('progressStep2').classList.add('active');
        }

        function goToStep1() {
            document.getElementById('step2').classList.remove('active');
            const s1 = document.getElementById('step1');
            s1.classList.add('back-anim');
            s1.classList.add('active');
            setTimeout(() => s1.classList.remove('back-anim'), 400);
            document.getElementById('progressStep2').classList.remove('active');
            document.getElementById('progressStep1').classList.remove('completed');
            document.getElementById('progressStep1').classList.add('active');
        }
    </script>
</body>
</html>
