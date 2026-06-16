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
$avg_display = 'N/A';
try {
    $total    = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id")->fetchColumn();
    $pending  = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='pending_validation'")->fetchColumn();
    $approved = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='approved'")->fetchColumn();
    $rejected = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='rejected'")->fetchColumn();
    
    // Only calculate average accuracy from approved records and accuracy_score IS NOT NULL
    $avg_score = $pdo->query("SELECT ROUND(AVG(accuracy_score),1) FROM fingerprint_tests WHERE student_id = $student_id AND status='approved' AND accuracy_score IS NOT NULL")->fetchColumn();
    
    if ($avg_score !== null) {
        $avg_display = $avg_score . '%';
    } else {
        $avg_display = ($pending > 0) ? 'Awaiting Evaluation' : 'N/A';
    }
} catch (PDOException $e) {}

// Recent 5 submissions
$recent = [];
try {
    $stmt = $pdo->prepare("
        SELECT ft.*, fr.remarks AS faculty_remarks, faculty.full_name AS faculty_validator
        FROM fingerprint_tests ft
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE ft.student_id = ? 
        ORDER BY ft.submitted_at DESC LIMIT 5
    ");
    $stmt->execute([$student_id]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recent as &$r) {
        $r['image_exists'] = false;
        if (!empty($r['image_path'])) {
            $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $r['image_path'];
            if (file_exists($filePath)) {
                $r['image_exists'] = true;
            }
        }
    }
    unset($r);
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
    <style>
        .badge-pending_validation  { background: rgba(244,162,97,.15); color: #c97d2a; border: 1px solid rgba(244,162,97,.25); }
        .badge-approved { background: rgba(82,183,136,.15);  color: #2d6a4f; border: 1px solid rgba(82,183,136,.25); }
        .badge-rejected { background: rgba(224,122,95,.15);  color: #c0392b; border: 1px solid rgba(224,122,95,.2); }
        .badge-needs_revision { background: rgba(230,57,70,.12); color: #e63946; border: 1px solid rgba(230,57,70,.2); }
        
        .custom-table tbody tr { cursor: pointer; transition: background 0.2s; }
        .custom-table tbody tr:hover { background: #f9fbf7; }

        /* Detail Modal styling matching super admin */
        .detail-overlay { display:none; position:fixed; inset:0; background:rgba(27, 67, 50, 0.45); backdrop-filter: blur(4px); z-index:9999; align-items:center; justify-content:center; }
        .detail-overlay.open { display:flex; }
        .detail-modal { background:#fff; border-radius:16px; max-width:600px; width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); border: 1px solid rgba(27,67,50,0.1); }
        .detail-modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:var(--dark-green); color:#fff; }
        .detail-modal-header h3 { color:#fff; font-size:1.05rem; font-weight:700; margin:0; }
        .detail-modal-body { padding:1.5rem; }
        .detail-row { display:flex; gap:.5rem; margin-bottom:.75rem; font-size:.85rem; }
        .detail-label { min-width:160px; font-weight:600; color:var(--dark-green); }
        .detail-value { color:#5f5f5f; flex:1; }
        .modal-close-btn { background:none; border:none; font-size:1.4rem; cursor:pointer; color:#fff; opacity:0.8; line-height:1; }
        .modal-close-btn:hover { opacity:1; }
        .section-divider { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#6B8F71; border-bottom:1px solid #D2E2D5; padding-bottom:.35rem; margin:1.25rem 0 .6rem; }
        .section-divider:first-child { margin-top: 0; }
        
        .score-box { background: var(--cream); border-radius:8px; padding:10px 15px; margin-bottom:1rem; border:1px solid rgba(45,106,79,0.08); }
        .score-title { font-size:0.75rem; font-weight:700; color:var(--medium-green); margin-bottom:6px; text-transform:uppercase; }
        .score-values { display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; text-align:center; }
        .score-val { font-size:1.15rem; font-weight:800; color:var(--dark-green); }
        .score-lbl { font-size:0.65rem; color:var(--gray); font-weight:600; text-transform:uppercase; }
    </style>
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
                    <div class="stat-value" id="val-total"><?= $total ?></div>
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
                    <div class="stat-value" id="val-pending" style="color:#c97d2a;"><?= $pending ?></div>
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
                    <div class="stat-value" id="val-approved" style="color:#2d6a4f;"><?= $approved ?></div>
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
                    <div class="stat-value" id="val-rejected" style="color:#c0392b;"><?= $rejected ?></div>
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
                    <div class="stat-value" id="val-avg" style="font-size: <?= (strlen($avg_display) > 6) ? '1.25rem' : '2rem' ?>;"><?= htmlspecialchars($avg_display) ?></div>
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
                        <tbody id="recentSubmissionsBody">
                        <?php if (empty($recent)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:#6c757d;padding:2rem;">
                                    No submissions yet. <a href="submit_trial.php" style="color:var(--medium-green);font-weight:600;">Submit your first trial →</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent as $row): ?>
                            <tr data-trial-db-id="<?= $row['id'] ?>" onclick='openDetailModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['surface_type']) ?></td>
                                <td>
                                    <?php 
                                        if ($row['status'] === 'approved' && $row['accuracy_score'] !== null) {
                                            echo number_format($row['accuracy_score'], 1) . '%';
                                        } elseif ($row['status'] === 'pending_validation') {
                                            echo 'Awaiting Validation';
                                        } elseif ($row['status'] === 'needs_revision') {
                                            echo 'Needs Revision';
                                        } elseif ($row['status'] === 'rejected') {
                                            echo 'Rejected';
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $row['status'] ?>">
                                        <?php
                                            $status_labels = [
                                                'pending_validation' => 'Pending Validation',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                                'needs_revision' => 'Needs Revision'
                                            ];
                                            echo htmlspecialchars($status_labels[$row['status']] ?? ucfirst($row['status']));
                                        ?>
                                    </span>
                                </td>
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

<!-- VIEW DETAILS MODAL -->
<div class="detail-overlay" id="detailOverlay">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3 id="modalDetailTitle">Trial Submission Details</h3>
            <button class="modal-close-btn" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="detail-modal-body">
            <p class="section-divider">Forensic Submission Details</p>
            <div class="detail-row"><span class="detail-label">Trial ID</span><span class="detail-value" id="det-trial-id"></span></div>
            <div class="detail-row"><span class="detail-label">Powder Type Used</span><span class="detail-value" id="det-powder" style="text-transform: capitalize; font-weight: 600;"></span></div>
            <div class="detail-row"><span class="detail-label">Surface Material Type</span><span class="detail-value" id="det-surface" style="text-transform: capitalize; font-weight: 600;"></span></div>
            <div class="detail-row"><span class="detail-label">Image Label</span><span class="detail-value" id="det-label"></span></div>
            <div class="detail-row"><span class="detail-label">Notes from Submission</span><span class="detail-value" id="det-notes"></span></div>
            <div class="detail-row"><span class="detail-label">Date Submitted</span><span class="detail-value" id="det-submitted-at"></span></div>

            <p class="section-divider">Fingerprint Image Asset</p>
            <div style="text-align:center; margin-bottom:1rem; border:1px solid #e9ecef; padding:10px; border-radius:8px; background:#fafafa;" id="det-img-wrapper">
                <img src="" style="max-height:220px; max-width:100%; object-fit:contain; border-radius:6px; border:1px solid #ddd;" alt="Fingerprint" id="det-img">
            </div>

            <p class="section-divider">Evaluation & Scores</p>
            <div class="score-box" id="det-score-box">
                <div class="score-title">Forensic Performance Metrics</div>
                <div class="score-values">
                    <div>
                        <div class="score-val" id="det-clarity">—</div>
                        <div class="score-lbl">Clarity</div>
                    </div>
                    <div>
                        <div class="score-val" id="det-visibility">—</div>
                        <div class="score-lbl">Visibility</div>
                    </div>
                    <div>
                        <div class="score-val" id="det-adhesion">—</div>
                        <div class="score-lbl">Adhesion</div>
                    </div>
                    <div>
                        <div class="score-val" id="det-contrast">—</div>
                        <div class="score-lbl">Contrast</div>
                    </div>
                </div>
            </div>
            
            <div class="detail-row" id="det-ai-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green); margin-bottom: 0.5rem;">
                <span class="detail-label" style="font-weight: 700;">AI Preliminary Score</span>
                <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;" id="det-ai-score">—</span>
            </div>
            
            <div class="detail-row" id="det-faculty-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green);">
                <span class="detail-label" style="font-weight: 700;">Faculty Final Score</span>
                <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;" id="det-faculty-score">—</span>
            </div>

            <p class="section-divider" id="det-validation-divider">Validation Details</p>
            <div class="detail-row" id="det-status-row"><span class="detail-label">Validation Status</span><span class="detail-value" id="det-status"></span></div>
            <div class="detail-row" id="det-reviewer-row"><span class="detail-label">Faculty Reviewer</span><span class="detail-value" id="det-reviewer" style="font-weight: 600;"></span></div>
            <div class="detail-row" id="det-validated-date-row"><span class="detail-label">Review Date</span><span class="detail-value" id="det-validated-at"></span></div>
            <div class="detail-row" id="det-remarks-row"><span class="detail-label">Remarks from Reviewer</span><span class="detail-value" id="det-remarks" style="font-style: italic;"></span></div>
        </div>
    </div>
</div>

<?php require_once '_sidebar_js.php'; ?>

<script>
let isFetchingStats = false;
let isFetchingRecords = false;

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function getBadgeClass(status) {
    if (status === 'pending_validation') return 'badge-pending_validation';
    if (status === 'needs_revision') return 'badge-needs_revision';
    if (status === 'approved') return 'badge-approved';
    if (status === 'rejected') return 'badge-rejected';
    return 'badge-' + status;
}

function getStatusLabel(status) {
    if (status === 'pending_validation') return 'Pending Validation';
    if (status === 'needs_revision') return 'Needs Revision';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function isPollingPaused() {
    const isModalOpen = document.getElementById('detailOverlay').classList.contains('open');
    const isUserTyping = document.activeElement && (
        document.activeElement.tagName === 'INPUT' || 
        document.activeElement.tagName === 'TEXTAREA' || 
        document.activeElement.tagName === 'SELECT'
    );
    return isModalOpen || isUserTyping || isFetchingStats || isFetchingRecords;
}

function refreshStudentDashboardStats() {
    if (isPollingPaused()) return;
    
    isFetchingStats = true;
    fetch('ajax_get_student_dashboard_stats.php')
        .then(res => res.json())
        .then(data => {
            isFetchingStats = false;
            if (data.success) {
                const s = data.data;
                document.getElementById('val-total').textContent = s.total;
                document.getElementById('val-pending').textContent = s.pending;
                document.getElementById('val-approved').textContent = s.approved;
                document.getElementById('val-rejected').textContent = s.rejected;
                
                const avgEl = document.getElementById('val-avg');
                avgEl.textContent = s.avg_score;
                if (s.avg_score.length > 6) {
                    avgEl.style.fontSize = '1.25rem';
                } else {
                    avgEl.style.fontSize = '2rem';
                }
            }
        })
        .catch(err => {
            isFetchingStats = false;
        });
}

function refreshRecentSubmissions() {
    if (isPollingPaused()) return;
    
    isFetchingRecords = true;
    fetch('ajax_get_student_records.php')
        .then(res => res.json())
        .then(data => {
            isFetchingRecords = false;
            if (data.success) {
                const records = data.data.records.slice(0, 5); // Limit 5 on dashboard
                renderRecentTable(records);
            }
        })
        .catch(err => {
            isFetchingRecords = false;
        });
}

function renderRecentTable(records) {
    const tbody = document.getElementById('recentSubmissionsBody');
    if (records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center;color:#6c757d;padding:2rem;">
                    No submissions yet. <a href="submit_trial.php" style="color:var(--medium-green);font-weight:600;">Submit your first trial →</a>
                </td>
            </tr>`;
        return;
    }

    const existingRows = Array.from(tbody.querySelectorAll('tr[data-trial-db-id]'));
    const existingIds = existingRows.map(row => parseInt(row.getAttribute('data-trial-db-id')));
    const newIds = records.map(r => parseInt(r.id));

    // Remove rows no longer matching
    existingRows.forEach(row => {
        const id = parseInt(row.getAttribute('data-trial-db-id'));
        if (!newIds.includes(id)) {
            row.remove();
        }
    });

    records.forEach(r => {
        let row = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
        
        const isApproved = r.status === 'approved';
        const scoreText = isApproved ? (r.accuracy_score !== null ? parseFloat(r.accuracy_score).toFixed(1) + '%' : '—') : (r.status === 'pending_validation' ? 'Awaiting Validation' : (r.status === 'needs_revision' ? 'Needs Revision' : (r.status === 'rejected' ? 'Rejected' : 'N/A')));
        
        const rowHtml = `
            <td style="text-transform:capitalize;">${escapeHtml(r.powder_type)}</td>
            <td style="text-transform:capitalize;">${escapeHtml(r.surface_type)}</td>
            <td>${scoreText}</td>
            <td>
                <span class="badge ${getBadgeClass(r.status)}">${getStatusLabel(r.status)}</span>
            </td>
            <td>${new Date(r.submitted_at.replace(/-/g, "/")).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
        `;

        if (row) {
            // Update
            row.innerHTML = rowHtml;
        } else {
            // Prepend new row
            const tr = document.createElement('tr');
            tr.setAttribute('data-trial-db-id', r.id);
            tr.innerHTML = rowHtml;
            
            const noData = tbody.querySelector('.no-data-row');
            if (noData) noData.remove();
            tbody.insertBefore(tr, tbody.firstChild);
        }
        
        // Update/bind row click listener
        const trNode = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
        if (trNode) {
            trNode.onclick = () => openDetailModal(r);
        }
    });
}

function openDetailModal(row) {
    document.getElementById('det-trial-id').textContent = row.trial_id || 'TR-' + String(row.id).padStart(4, '0');
    document.getElementById('det-powder').textContent = row.powder_type || '';
    document.getElementById('det-surface').textContent = row.surface_type || '';
    document.getElementById('det-label').textContent = row.image_label || 'Untitled';
    document.getElementById('det-notes').innerHTML = row.notes ? escapeHtml(row.notes).replace(/\n/g, '<br>') : 'No notes provided.';
    document.getElementById('det-submitted-at').textContent = new Date(row.submitted_at.replace(/-/g, "/")).toLocaleString();

    const imgWrapper = document.getElementById('det-img-wrapper');
    const img = document.getElementById('det-img');
    if (row.image_path && row.image_exists) {
        img.src = '../view_fingerprint.php?test_id=' + row.id;
        imgWrapper.style.display = 'block';
    } else {
        imgWrapper.style.display = 'none';
    }

    // Performance Metrics
    document.getElementById('det-clarity').textContent = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score).toFixed(1) + '%' : '—';
    document.getElementById('det-visibility').textContent = row.visibility_score !== null ? parseFloat(row.visibility_score).toFixed(1) + '%' : '—';
    document.getElementById('det-adhesion').textContent = row.adhesion_score !== null ? parseFloat(row.adhesion_score).toFixed(1) + '%' : '—';
    document.getElementById('det-contrast').textContent = row.contrast_score !== null ? parseFloat(row.contrast_score).toFixed(1) + '%' : '—';

    // AI score
    document.getElementById('det-ai-score').textContent = row.ai_accuracy_score !== null ? parseFloat(row.ai_accuracy_score).toFixed(1) + '%' : 'Awaiting AI Evaluation';
    
    // Faculty Final Score
    if (row.status === 'approved' && row.faculty_final_score !== null) {
        document.getElementById('det-faculty-score').textContent = parseFloat(row.faculty_final_score).toFixed(1) + '%';
        document.getElementById('det-faculty-row').style.display = 'flex';
    } else if (row.status === 'pending_validation') {
        document.getElementById('det-faculty-score').textContent = 'Awaiting Validation';
        document.getElementById('det-faculty-row').style.display = 'flex';
    } else {
        document.getElementById('det-faculty-row').style.display = 'none';
    }

    // Validation Details
    document.getElementById('det-status').innerHTML = `<span class="badge ${getBadgeClass(row.status)}">${getStatusLabel(row.status)}</span>`;
    
    const reviewerRow = document.getElementById('det-reviewer-row');
    const validatedDateRow = document.getElementById('det-validated-date-row');
    const remarksRow = document.getElementById('det-remarks-row');

    if (row.status === 'pending_validation') {
        reviewerRow.style.display = 'none';
        validatedDateRow.style.display = 'none';
        remarksRow.style.display = 'none';
    } else {
        reviewerRow.style.display = 'flex';
        validatedDateRow.style.display = 'flex';
        remarksRow.style.display = 'flex';

        document.getElementById('det-reviewer').textContent = row.faculty_validator || 'Faculty Reviewer';
        document.getElementById('det-validated-at').textContent = row.validated_at ? new Date(row.validated_at.replace(/-/g, "/")).toLocaleString() : '—';
        document.getElementById('det-remarks').innerHTML = row.faculty_remarks ? escapeHtml(row.faculty_remarks).replace(/\n/g, '<br>') : 'No evaluation remarks submitted.';
    }

    document.getElementById('detailOverlay').classList.add('open');
}

function closeDetailModal() {
    document.getElementById('detailOverlay').classList.remove('open');
}

// Close modal when clicking outside content
document.getElementById('detailOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('detailOverlay')) closeDetailModal();
});

document.addEventListener('DOMContentLoaded', () => {
    // 10s auto-refresh
    setInterval(refreshStudentDashboardStats, 10000);
    setInterval(refreshRecentSubmissions, 10000);
});
</script>
</body>
</html>
