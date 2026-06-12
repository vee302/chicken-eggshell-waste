<?php
// partner/field_feedback.php — Alumni / Police Partner Field Feedback Submission & History
require_once '../config.php';
require_once 'auth.php';
check_partner_auth();

$active_page = 'field_feedback';
$partner_name = $_SESSION['user_name'] ?? 'Partner';
$partner_id = $_SESSION['user_id'] ?? 0;

$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback_type = trim($_POST['feedback_type'] ?? '');
    $surface_type = trim($_POST['surface_type'] ?? '');
    $powder_type = trim($_POST['powder_type'] ?? '');
    $observation = trim($_POST['observation'] ?? '');
    $usability_rating = isset($_POST['usability_rating']) ? (int)$_POST['usability_rating'] : 0;
    $suggested_improvement = trim($_POST['suggested_improvement'] ?? '');

    // Validation
    if (empty($feedback_type)) {
        $error = 'Feedback type is required.';
    } elseif (empty($observation)) {
        $error = 'Field observation details are required.';
    } elseif ($usability_rating < 1 || $usability_rating > 5) {
        $error = 'Please provide a valid usability rating between 1 and 5.';
    } else {
        try {
            // Prepared Statement for insertion
            $stmt = $pdo->prepare("
                INSERT INTO field_feedback (
                    partner_id, 
                    feedback_type, 
                    surface_type, 
                    powder_type, 
                    observation, 
                    usability_rating, 
                    suggested_improvement
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $partner_id,
                $feedback_type,
                $surface_type !== 'none' ? $surface_type : null,
                $powder_type !== 'none' ? $powder_type : null,
                $observation,
                $usability_rating,
                !empty($suggested_improvement) ? $suggested_improvement : null
            ]);
            $success = 'Feedback submitted successfully.';
        } catch (PDOException $e) {
            $error = 'Database error while submitting feedback: ' . $e->getMessage();
        }
    }
}

// Fetch feedback history strictly for the logged-in partner using prepared statements
$feedback_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM field_feedback 
        WHERE partner_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$partner_id]);
    $feedback_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error fetching history: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Feedback — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .notice-banner {
            background-color: rgba(45, 106, 79, 0.08);
            border-left: 4px solid var(--medium-green);
            color: var(--dark-green);
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }
        .form-group-full {
            grid-column: span 2;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.88rem;
            background: #fff;
            color: #212529;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #2d6a4f;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.12);
        }
        label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1b4332;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
            margin-bottom: 0.4rem;
        }
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background-color: rgba(82, 183, 136, 0.15);
            color: #2d6a4f;
            border: 1px solid rgba(82, 183, 136, 0.25);
        }
        .alert-danger {
            background-color: rgba(224, 122, 95, 0.15);
            color: #d90429;
            border: 1px solid rgba(224, 122, 95, 0.25);
        }
        .star-rating {
            display: inline-flex;
            gap: 4px;
            color: #ffb703;
        }
    </style>
</head>
<body>

<div class="student-wrapper">
    <!-- Mobile overlay -->
    <div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;transition:opacity .3s;"
         onclick="this.style.display='none';document.getElementById('sidebar').classList.remove('active')"></div>

    <!-- SIDEBAR -->
    <?php require_once '_sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="student-main">
        <header class="student-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6"  x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <div class="header-title">
                    <h2>Alumni / Police Partner Portal</h2>
                </div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Field Feedback</h1>
                    <p>Submit usability feedback and real-world forensic observation logs.</p>
                </div>
            </div>

            <!-- Notice Banner -->
            <div class="notice-banner">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>Only faculty-approved records are visible in this portal.</span>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- SUBMISSION FORM -->
            <div class="dashboard-card" style="margin-bottom: 2rem;">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Log Field Observation &amp; Feedback
                    </h3>
                </div>
                <form method="POST" action="field_feedback.php">
                    <div class="form-grid">
                        <div class="form-item">
                            <label for="feedback_type">Feedback Type</label>
                            <select name="feedback_type" id="feedback_type" class="form-control" required>
                                <option value="Field Use Observation">Field Use Observation</option>
                                <option value="Training Use Observation">Training Use Observation</option>
                                <option value="Report Feedback">Report Feedback</option>
                                <option value="Suggested Improvement">Suggested Improvement</option>
                            </select>
                        </div>

                        <div class="form-item">
                            <label for="usability_rating">Usability Rating</label>
                            <select name="usability_rating" id="usability_rating" class="form-control" required>
                                <option value="5">5 ★★★★★ (Highly Usable / Clean Lift)</option>
                                <option value="4">4 ★★★★☆ (Good Usability / Minor Dust)</option>
                                <option value="3">3 ★★★☆☆ (Moderate Usability / Visible Smudge)</option>
                                <option value="2">2 ★★☆☆☆ (Poor Usability / Low Contrast)</option>
                                <option value="1">1 ★☆☆☆☆ (Unusable / Fails to Lift)</option>
                            </select>
                        </div>

                        <div class="form-item">
                            <label for="surface_type">Related Surface Type</label>
                            <select name="surface_type" id="surface_type" class="form-control">
                                <option value="none">None / Multi-Surface</option>
                                <?php foreach (['glass','paper','wood','plastic','metal','ceramic','fabric'] as $s): ?>
                                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-item">
                            <label for="powder_type">Related Powder Type</label>
                            <select name="powder_type" id="powder_type" class="form-control">
                                <option value="none">None / General</option>
                                <option value="eggshell">Eggshell-Based Powder</option>
                                <option value="commercial">Commercial Powder</option>
                            </select>
                        </div>

                        <div class="form-item form-group-full">
                            <label for="observation">Field Observation Details</label>
                            <textarea name="observation" id="observation" class="form-control" rows="4" placeholder="Detail your observation (e.g. powder contrast, consistency, speed of print development, brush adherence)..." required></textarea>
                        </div>

                        <div class="form-item form-group-full">
                            <label for="suggested_improvement">Suggested Improvement (Optional)</label>
                            <textarea name="suggested_improvement" id="suggested_improvement" class="form-control" rows="3" placeholder="Any formulation tweaks or lab recommendation suggestions for criminology research students..."></textarea>
                        </div>
                    </div>
                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    </div>
                </form>
            </div>

            <!-- FEEDBACK HISTORY TABLE -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Your Feedback History
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Date Submitted</th>
                                <th>Type</th>
                                <th>Powder</th>
                                <th>Surface</th>
                                <th>Rating</th>
                                <th>Observation</th>
                                <th>Suggestions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($feedback_history)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:#6c757d;padding:2rem;">
                                    You have not submitted any feedback logs yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($feedback_history as $row): ?>
                            <tr>
                                <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($row['feedback_type']) ?></strong></td>
                                <td style="text-transform:capitalize;"><?= $row['powder_type'] ? ($row['powder_type'] === 'eggshell' ? 'Eggshell-Based' : 'Commercial') : 'N/A' ?></td>
                                <td style="text-transform:capitalize;"><?= $row['surface_type'] ? htmlspecialchars($row['surface_type']) : 'N/A' ?></td>
                                <td>
                                    <span class="star-rating">
                                        <?= str_repeat('★', $row['usability_rating']) . str_repeat('☆', 5 - $row['usability_rating']) ?>
                                    </span>
                                </td>
                                <td><span title="<?= htmlspecialchars($row['observation']) ?>"><?= htmlspecialchars(substr($row['observation'], 0, 60)) . (strlen($row['observation']) > 60 ? '...' : '') ?></span></td>
                                <td>
                                    <?php if ($row['suggested_improvement']): ?>
                                        <span title="<?= htmlspecialchars($row['suggested_improvement']) ?>"><?= htmlspecialchars(substr($row['suggested_improvement'], 0, 40)) . (strlen($row['suggested_improvement']) > 40 ? '...' : '') ?></span>
                                    <?php else: ?>
                                        <span style="color:#aaa; font-style:italic;">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- end student-content -->
    </main>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarCollapse");
        const overlay = document.getElementById("sidebarOverlay");

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                sidebar.classList.toggle("active");
                if (overlay) overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";
            });

            document.addEventListener("click", (e) => {
                if (window.innerWidth <= 992 && sidebar.classList.contains("active")) {
                    if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                        sidebar.classList.remove("active");
                        if (overlay) overlay.style.display = "none";
                    }
                }
            });
        }
    });
</script>
</body>
</html>
