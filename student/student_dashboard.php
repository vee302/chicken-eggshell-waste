<?php
// student/student_dashboard.php — Criminology Student Main Dashboard
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page = 'dashboard';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

// Summary stats
$total = $pending = $approved = $rejected = 0;
$avg_score = 0;
try {
    $total    = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id")->fetchColumn();
    $pending  = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='pending_validation'")->fetchColumn();
    $approved = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='approved'")->fetchColumn();
    $rejected = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='rejected'")->fetchColumn();
    $avg_score = $pdo->query("SELECT ROUND(AVG(accuracy_score),1) FROM fingerprint_tests WHERE student_id = $student_id")->fetchColumn() ?? 0;
} catch (PDOException $e) {}

// Recent 5 submissions
$recent = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM fingerprint_tests WHERE student_id = ? ORDER BY submitted_at DESC LIMIT 5");
    $stmt->execute([$student_id]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Criminology Student Dashboard — Green Forensics Evaluating System">
    <title>Student Dashboard — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
</head>
<body>

<div class="student-wrapper">

    <!-- Mobile overlay -->
    <div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;transition:opacity .3s;"
         onclick="this.style.display='none';document.getElementById('sidebar').classList.remove('active')"></div>

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
                    <h2>Green Forensics — Student Dashboard</h2>
                </div>
            </div>
            <div class="header-right">
                <div class="header-role-chip">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                    </svg>
                    Criminology Student
                </div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back, <?= htmlspecialchars($student_name) ?>. Here is a summary of your forensic submissions.</p>
                </div>
                <a href="submit_trial.php" class="btn btn-primary" id="btn-submit-new">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Submission
                </a>
            </div>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Submissions</span>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $total ?></div>
                    <div class="stat-desc">Fingerprint trial records submitted</div>
                </div>

                <div class="stat-card card-pending">
                    <div class="stat-header">
                        <span class="stat-title">Pending Review</span>
                        <div class="stat-icon" style="background:rgba(244,162,97,.12);color:#c97d2a;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color:#c97d2a;"><?= $pending ?></div>
                    <div class="stat-desc">Awaiting faculty validation</div>
                </div>

                <div class="stat-card card-approved">
                    <div class="stat-header">
                        <span class="stat-title">Approved</span>
                        <div class="stat-icon" style="background:rgba(82,183,136,.12);color:#2d6a4f;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color:#2d6a4f;"><?= $approved ?></div>
                    <div class="stat-desc">Validated and confirmed records</div>
                </div>

                <div class="stat-card card-rejected">
                    <div class="stat-header">
                        <span class="stat-title">Rejected</span>
                        <div class="stat-icon" style="background:rgba(224,122,95,.12);color:#c0392b;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color:#c0392b;"><?= $rejected ?></div>
                    <div class="stat-desc">Returned for revision</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Avg. Accuracy Score</span>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                                <polyline points="16 7 22 7 22 13"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $avg_score ?>%</div>
                    <div class="stat-desc">Average across all your submissions</div>
                </div>
            </div>

            <!-- QUICK LINKS -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        Quick Actions
                    </h3>
                </div>
                <div class="quicklinks-grid">
                    <a href="submit_trial.php" class="quicklink-card" id="ql-submit-trial">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="12" y1="11" x2="12" y2="17"/>
                                <line x1="9"  y1="14" x2="15" y2="14"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Submit Trial Data</span>
                    </a>
                    <a href="upload_fingerprint.php" class="quicklink-card" id="ql-upload">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Upload Fingerprint Images</span>
                    </a>
                    <a href="surface_performance.php" class="quicklink-card" id="ql-surface">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Surface Performance</span>
                    </a>
                    <a href="accuracy_rating.php" class="quicklink-card" id="ql-accuracy">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                                <polyline points="16 7 22 7 22 13"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Accuracy Rating</span>
                    </a>
                    <a href="safety_climate_log.php" class="quicklink-card" id="ql-safety">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Safety &amp; Climate Log</span>
                    </a>
                    <a href="student_records.php" class="quicklink-card" id="ql-records">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Records / Reports</span>
                    </a>
                </div>
            </div>

            <!-- RECENT SUBMISSIONS -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Recent Submissions
                    </h3>
                    <a href="student_records.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Accuracy</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recent)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:#6c757d;padding:2rem;">
                                    No submissions yet. <a href="submit_trial.php" style="color:var(--medium-green);font-weight:600;">Submit your first trial →</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent as $row): ?>
                            <tr>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['surface_type']) ?></td>
                                <td><?= number_format($row['accuracy_score'], 1) ?>%</td>
                                <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
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

<?php require_once '_sidebar_js.php'; ?>
</body>
</html>
