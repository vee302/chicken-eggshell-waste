<?php
// student/accuracy_rating.php — View Accuracy Ratings
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'accuracy_rating';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

$trials = [];
$overall_avg = 0;
$has_pending = false;
$approved_trials = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM fingerprint_tests WHERE student_id = ? ORDER BY submitted_at DESC");
    $stmt->execute([$student_id]);
    $trials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($trials as $t) {
        if ($t['status'] === 'pending_validation') {
            $has_pending = true;
        }
    }
    
    $approved_trials = array_filter($trials, function($t) {
        return $t['status'] === 'approved' && $t['accuracy_score'] !== null;
    });
    
    if ($approved_trials) {
        $overall_avg = round(array_sum(array_column($approved_trials, 'accuracy_score')) / count($approved_trials), 1);
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Accuracy Rating — Green Forensics">
    <title>Accuracy Rating — Green Forensics</title>
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
                <div class="header-title"><h2>Accuracy Rating</h2></div>
            </div>
            <div class="header-right"><div class="header-role-chip">Criminology Student</div></div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>View Accuracy Rating</h1>
                    <p>Track your fingerprint extraction accuracy scores across all trials.</p>
                </div>
            </div>

            <!-- Overall Score Card -->
            <div class="stats-grid" style="margin-bottom:2rem;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Overall Average</span>
                        <div class="stat-icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
                    </div>
                    <div class="stat-value">
                        <?php if ($approved_trials): ?>
                            <?= $overall_avg ?>%
                        <?php elseif ($has_pending): ?>
                            Awaiting Validation
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                    <div class="stat-desc">Average across approved trials</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Trials</span>
                        <div class="stat-icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                    </div>
                    <div class="stat-value"><?= count($trials) ?></div>
                    <div class="stat-desc">Submitted trials</div>
                </div>
                <?php if ($approved_trials): $scores = array_column($approved_trials, 'accuracy_score'); ?>
                <div class="stat-card card-approved">
                    <div class="stat-header">
                        <span class="stat-title">Best Score</span>
                        <div class="stat-icon" style="background:rgba(82,183,136,.12);color:#2d6a4f;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
                    </div>
                    <div class="stat-value" style="color:#2d6a4f;"><?= round(max($scores), 1) ?>%</div>
                    <div class="stat-desc">Highest accuracy achieved</div>
                </div>
                <div class="stat-card card-rejected">
                    <div class="stat-header">
                        <span class="stat-title">Lowest Score</span>
                        <div class="stat-icon" style="background:rgba(224,122,95,.12);color:#c0392b;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
                    </div>
                    <div class="stat-value" style="color:#c0392b;"><?= round(min($scores), 1) ?>%</div>
                    <div class="stat-desc">Lowest recorded score</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Accuracy Table -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>All Trial Scores</h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Accuracy Score</th>
                                <th>Score Bar</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($trials)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#6c757d;padding:2rem;">No trial records yet. <a href="submit_trial.php" style="color:var(--medium-green);font-weight:600;">Submit a trial →</a></td></tr>
                        <?php else: ?>
                            <?php foreach ($trials as $i => $t): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($t['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($t['surface_type']) ?></td>
                                <td>
                                    <strong>
                                        <?php if ($t['status'] === 'approved' && $t['accuracy_score'] !== null): ?>
                                            <?= number_format($t['accuracy_score'], 1) ?>%
                                        <?php elseif ($t['status'] === 'pending_validation'): ?>
                                            Awaiting Validation
                                        <?php elseif ($t['status'] === 'needs_revision'): ?>
                                            Needs Revision
                                        <?php elseif ($t['status'] === 'rejected'): ?>
                                            Rejected
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td style="min-width:130px;">
                                    <?php if ($t['status'] === 'approved' && $t['accuracy_score'] !== null): ?>
                                        <div class="score-bar">
                                            <div class="score-bar-track">
                                                <div class="score-bar-fill" style="width:<?= min(100, $t['accuracy_score']) ?>%"></div>
                                            </div>
                                            <span style="font-size:.75rem;color:var(--gray);width:35px;text-align:right;"><?= number_format($t['accuracy_score'], 0) ?>%</span>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size:.75rem;color:var(--gray);font-style:italic;">Awaiting review</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $t['status'] ?>">
                                        <?php 
                                            if ($t['status'] === 'pending_validation') {
                                                echo 'Pending Validation';
                                            } elseif ($t['status'] === 'needs_revision') {
                                                echo 'Needs Revision';
                                            } else {
                                                echo ucfirst($t['status']);
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($t['submitted_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once '_sidebar_js.php'; ?>
</body>
</html>
