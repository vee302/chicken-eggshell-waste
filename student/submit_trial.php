<?php
// student/submit_trial.php — Submit Fingerprint Trial Data
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'submit_trial';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $powder_type    = trim($_POST['powder_type']    ?? '');
    $surface_type   = trim($_POST['surface_type']   ?? '');
    $accuracy_score = floatval($_POST['accuracy_score'] ?? 0);
    $notes          = trim($_POST['notes']          ?? '');

    if (!$powder_type || !$surface_type || $accuracy_score <= 0) {
        $msg = 'Please fill in all required fields with valid values.';
        $msg_type = 'error';
    } else {
            try {
                // Generate a unique trial_id
                $stmt = $pdo->query("SELECT MAX(id) FROM fingerprint_tests");
                $max_id = $stmt->fetchColumn() ?: 0;
                $next_id = $max_id + 1;
                $trial_id = 'TR-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO fingerprint_tests (trial_id, student_id, powder_type, surface_type, accuracy_score, notes, status, submitted_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending_validation', NOW())
                ");
                $stmt->execute([$trial_id, $student_id, $powder_type, $surface_type, $accuracy_score, $notes]);
                $msg = 'Trial data submitted successfully! It is now pending faculty review.';
                $msg_type = 'success';
            } catch (PDOException $e) {
            $msg = 'Database error. Please try again.';
            $msg_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Submit Fingerprint Trial Data — Green Forensics">
    <title>Submit Trial Data — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
</head>
<body>
<div class="student-wrapper">
    <div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;"
         onclick="this.style.display='none';document.getElementById('sidebar').classList.remove('active')"></div>

    <?php require_once '_sidebar.php'; ?>

    <main class="student-main">
        <header class="student-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <div class="header-title"><h2>Submit Trial Data</h2></div>
            </div>
            <div class="header-right">
                <div class="header-role-chip">Criminology Student</div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Submit Trial Data</h1>
                    <p>Record your fingerprint powder application trial results for faculty review.</p>
                </div>
                <a href="student_records.php" class="btn btn-secondary">View My Records</a>
            </div>

            <?php if ($msg): ?>
                <div class="alert-msg alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div class="dashboard-card" style="max-width:720px;">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="11" x2="12" y2="17"/>
                            <line x1="9" y1="14" x2="15" y2="14"/>
                        </svg>
                        New Trial Entry
                    </h3>
                </div>

                <form method="POST" action="submit_trial.php" id="form-submit-trial">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="powder_type">Powder Type <span style="color:var(--danger)">*</span></label>
                            <select name="powder_type" id="powder_type" class="form-control" required>
                                <option value="">— Select Powder —</option>
                                <option value="eggshell">Eggshell Powder</option>
                                <option value="commercial">Commercial Powder</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="surface_type">Surface Type <span style="color:var(--danger)">*</span></label>
                            <select name="surface_type" id="surface_type" class="form-control" required>
                                <option value="">— Select Surface —</option>
                                <option value="glass">Glass</option>
                                <option value="plastic">Plastic</option>
                                <option value="metal">Metal</option>
                                <option value="paper">Paper</option>
                                <option value="wood">Wood</option>
                                <option value="ceramic">Ceramic</option>
                                <option value="fabric">Fabric</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="accuracy_score">Accuracy Score (%) <span style="color:var(--danger)">*</span></label>
                        <input type="number" name="accuracy_score" id="accuracy_score" class="form-control"
                               min="0" max="100" step="0.1" placeholder="e.g. 87.5" required>
                    </div>

                    <div class="form-group">
                        <label for="notes">Observations / Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4"
                                  placeholder="Describe your observations, conditions, or any relevant notes..."></textarea>
                    </div>

                    <div style="display:flex;gap:.75rem;margin-top:.5rem;">
                        <button type="submit" class="btn btn-primary" id="btn-submit-trial">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Submit Trial
                        </button>
                        <button type="reset" class="btn btn-secondary" id="btn-reset-trial">Clear Form</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once '_sidebar_js.php'; ?>
</body>
</html>
