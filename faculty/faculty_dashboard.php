<?php
// faculty/faculty_dashboard.php - Faculty Researcher Main Dashboard
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';
$faculty_id   = $_SESSION['user_id']  ?? 0;

// Summary counts
$total_submissions = $pending = $approved = $rejected = $avg_accuracy = $report_count = 0;
try {
    $total_submissions = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests")->fetchColumn();
    $pending           = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='pending'")->fetchColumn();
    $approved          = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='approved'")->fetchColumn();
    $rejected          = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='rejected'")->fetchColumn();
    $avg_accuracy      = $pdo->query("SELECT ROUND(AVG(accuracy_score),1) FROM fingerprint_tests")->fetchColumn() ?? 0;
    $report_count      = $pdo->query("SELECT COUNT(*) FROM reports WHERE generated_by=$faculty_id")->fetchColumn();
} catch (PDOException $e) {}

// Recent 6 submissions
$recent = [];
try {
    $stmt = $pdo->prepare("
        SELECT ft.*, u.full_name AS student_name
        FROM fingerprint_tests ft
        JOIN users u ON u.id = ft.student_id
        ORDER BY ft.submitted_at DESC LIMIT 6
    ");
    $stmt->execute();
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .badge-pending  { background: rgba(244,162,97,.15); color: #c97d2a; }
        .badge-approved { background: rgba(82,183,136,.15);  color: #2d6a4f; }
        .badge-rejected { background: rgba(224,122,95,.15);  color: #c0392b; }
        .stat-card.pending-card::after  { background: #f4a261; }
        .stat-card.approved-card::after { background: #52b788; }
        .stat-card.rejected-card::after { background: #e07a5f; }
        .stat-card.avg-card::after      { background: #74c69d; }
        .thumb-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #e9ecef; }
        .thumb-placeholder { width:50px;height:50px;border-radius:8px;background:#f4f6f0;display:flex;align-items:center;justify-content:center; }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-text"><span>GREEN</span><span class="brand-accent">FORENSICS</span></div>
        </div>
        <div class="sidebar-user">
            <div class="user-info">
                <div class="user-avatar">FR</div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($faculty_name) ?></h4>
                    <span>Faculty Researcher</span>
                </div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-item active">
                <a href="faculty_dashboard.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="comparison_dashboard.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <span>Comparison Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="validate_accuracy.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span>Validate Accuracy Scores</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="surface_performance.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                    <span>Surface Performance</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="safety_climate_log.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Safety &amp; Climate Log</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="student_records.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>View Student Records</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="generate_reports.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Generate Reports</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="../logout.php" class="menu-link" style="color:#e07a5f;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="header-title"><h2>Green Forensics — Faculty Researcher Dashboard</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back, <?= htmlspecialchars($faculty_name) ?>. Here is your research summary.</p>
                </div>
                <a href="validate_accuracy.php" class="btn btn-primary">Review Pending Submissions</a>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Submissions</span>
                        <div class="stat-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                    </div>
                    <div class="stat-value"><?= $total_submissions ?></div>
                    <div class="stat-desc">Student trial submissions</div>
                </div>
                <div class="stat-card pending-card">
                    <div class="stat-header">
                        <span class="stat-title">Pending Validation</span>
                        <div class="stat-icon" style="background:rgba(244,162,97,.12);color:#c97d2a;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                    </div>
                    <div class="stat-value" style="color:#c97d2a;"><?= $pending ?></div>
                    <div class="stat-desc">Awaiting faculty review</div>
                </div>
                <div class="stat-card approved-card">
                    <div class="stat-header">
                        <span class="stat-title">Approved Records</span>
                        <div class="stat-icon" style="background:rgba(82,183,136,.12);color:#2d6a4f;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
                    </div>
                    <div class="stat-value" style="color:#2d6a4f;"><?= $approved ?></div>
                    <div class="stat-desc">Validated and confirmed</div>
                </div>
                <div class="stat-card rejected-card">
                    <div class="stat-header">
                        <span class="stat-title">Rejected Records</span>
                        <div class="stat-icon" style="background:rgba(224,122,95,.12);color:#c0392b;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
                    </div>
                    <div class="stat-value" style="color:#c0392b;"><?= $rejected ?></div>
                    <div class="stat-desc">Returned for revision</div>
                </div>
                <div class="stat-card avg-card">
                    <div class="stat-header">
                        <span class="stat-title">Average Accuracy Score</span>
                        <div class="stat-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
                    </div>
                    <div class="stat-value"><?= $avg_accuracy ?>%</div>
                    <div class="stat-desc">Across all submissions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Reports Generated</span>
                        <div class="stat-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg></div>
                    </div>
                    <div class="stat-value"><?= $report_count ?></div>
                    <div class="stat-desc">PDF reports generated by you</div>
                </div>
            </div>

            <!-- RECENT SUBMISSIONS TABLE -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>Recent Student Submissions</h3>
                    <a href="student_records.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Accuracy</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#6c757d;padding:2rem;">No submissions yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                <td><span style="text-transform:capitalize;"><?= $row['powder_type'] ?></span></td>
                                <td style="text-transform:capitalize;"><?= $row['surface_type'] ?></td>
                                <td><?= number_format($row['accuracy_score'], 1) ?>%</td>
                                <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
                                <td>
                                    <a href="validate_accuracy.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">Review</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- end admin-content -->
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) {
        toggle.addEventListener('click', e => { e.stopPropagation(); sidebar.classList.toggle('active'); });
        document.addEventListener('click', e => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active') && !sidebar.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }
});
</script>
</body>
</html>
