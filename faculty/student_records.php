<?php
// faculty/student_records.php
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';

$search  = trim($_GET['search']  ?? '');
$f_pwd   = $_GET['powder']  ?? '';
$f_surf  = $_GET['surface'] ?? '';
$f_stat  = $_GET['status']  ?? '';

$where = ['1=1'];
$params = [];
if ($search)  { $where[] = 'u.full_name LIKE ?'; $params[] = '%'.$search.'%'; }
if ($f_pwd)   { $where[] = 'ft.powder_type=?';  $params[] = $f_pwd; }
if ($f_surf)  { $where[] = 'ft.surface_type=?'; $params[] = $f_surf; }
if ($f_stat)  { $where[] = 'ft.status=?';       $params[] = $f_stat; }

$rows = [];
try {
    $stmt = $pdo->prepare("
        SELECT ft.*, u.full_name AS student_name,
               fac.full_name AS validator_name,
               COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks,
               fr.created_at AS validation_date
        FROM fingerprint_tests ft
        JOIN users u ON u.id = ft.student_id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id=(
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id=ft.id
        )
        LEFT JOIN users fac ON ft.validated_by = fac.id
        WHERE ".implode(' AND ',$where)."
        ORDER BY ft.submitted_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// View detail of a single record
$view_record = null;
if (isset($_GET['view'])) {
    $v_id = intval($_GET['view']);
    foreach ($rows as $rec) {
        if ((int)$rec['id'] === $v_id) {
            $view_record = $rec;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .badge-pending_validation { background:rgba(244,162,97,.15); color:#c97d2a; border:1px solid rgba(244,162,97,.25); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-needs_revision { background:rgba(230,57,70,.12); color:#e63946; border:1px solid rgba(230,57,70,.2); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-approved { background:rgba(82,183,136,.15); color:#2d6a4f; border:1px solid rgba(82,183,136,.25); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-rejected { background:rgba(224,122,95,.15); color:#c0392b; border:1px solid rgba(224,122,95,.2); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }

        /* Detail Modal */
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
        
        .score-box { background: var(--cream); border-radius:8px; padding:10px 15px; margin-bottom:1rem; border:1px solid rgba(45,106,79,0.08); }
        .score-title { font-size:0.75rem; font-weight:700; color:var(--medium-green); margin-bottom:6px; text-transform:uppercase; }
        .score-values { display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; text-align:center; }
        .score-val { font-size:1.15rem; font-weight:800; color:var(--dark-green); }
        .score-lbl { font-size:0.65rem; color:var(--gray); font-weight:600; text-transform:uppercase; }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="brand-text"><span>GREEN</span><span class="brand-accent">FORENSICS</span></div></div>
        <div class="sidebar-user"><div class="user-info"><div class="user-avatar">FR</div><div class="user-details"><h4><?= htmlspecialchars($faculty_name) ?></h4><span>Faculty Researcher</span></div></div></div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="faculty_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="comparison_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Comparison Dashboard</span></a></li>
            <li class="menu-item"><a href="validate_accuracy.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Validate Accuracy Scores</span></a></li>
            <li class="menu-item"><a href="surface_performance.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg><span>Surface Performance</span></a></li>
            <li class="menu-item"><a href="safety_climate_log.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Safety &amp; Climate Log</span></a></li>
            <li class="menu-item active"><a href="student_records.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>View Student Records</span></a></li>
            <li class="menu-item"><a href="generate_reports.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Generate Reports</span></a></li>
        </ul>
        <div class="sidebar-footer"><a href="../logout.php" class="menu-link" style="color:#e07a5f;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a></div>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="header-title"><h2>Green Forensics — Student Records</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>View Student Records</h1>
                    <p>All fingerprint trial submissions from registered criminology students.</p>
                </div>
                <a href="generate_reports.php" class="btn btn-primary">Generate Report</a>
            </div>

            <!-- Filter Bar -->
            <form method="GET" class="search-filter-bar">
                <div class="bar-left">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by student name..." class="form-control-inline" style="width:240px;">
                    <select name="powder" class="form-control-inline">
                        <option value="">All Powder Types</option>
                        <option value="eggshell"   <?= $f_pwd==='eggshell'  ?'selected':'' ?>>Eggshell-Based Powder</option>
                        <option value="commercial" <?= $f_pwd==='commercial'?'selected':'' ?>>Commercial Powder</option>
                    </select>
                    <select name="surface" class="form-control-inline">
                        <option value="">All Surfaces</option>
                        <?php foreach(['glass','paper','wood','plastic','metal','ceramic','fabric'] as $s): ?>
                        <option value="<?=$s?>" <?= $f_surf===$s?'selected':'' ?>><?=ucfirst($s)?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="form-control-inline">
                        <option value="">All Statuses</option>
                        <option value="pending_validation" <?= $f_stat==='pending_validation' ?'selected':'' ?>>Pending Validation</option>
                        <option value="approved"           <?= $f_stat==='approved'           ?'selected':'' ?>>Approved</option>
                        <option value="rejected"           <?= $f_stat==='rejected'           ?'selected':'' ?>>Rejected</option>
                        <option value="needs_revision"     <?= $f_stat==='needs_revision'     ?'selected':'' ?>>Needs Revision</option>
                    </select>
                </div>
                <div class="bar-right">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="student_records.php" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="dashboard-card">
                <div class="card-title-wrap"><h3>Submissions Found: <?= count($rows) ?></h3></div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Trial ID</th>
                                <th>Student Name</th>
                                <th>Powder Type</th>
                                <th>Surface Type</th>
                                <th>Fingerprint Image</th>
                                <th>Accuracy Score</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                                <th>Faculty Remarks</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="10" style="text-align:center;padding:2rem;color:#6c757d;">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                            <tr>
                                <td style="font-weight:700; color:var(--dark-green);"><?= htmlspecialchars($r['trial_id'] ?: 'TR-'.str_pad($r['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($r['student_name']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($r['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($r['surface_type']) ?></td>
                                <td>
                                    <?php if ($r['image_path'] && file_exists(dirname(__DIR__) . '/uploads/fingerprints/'.$r['image_path'])): ?>
                                        <a href="../view_fingerprint.php?test_id=<?= $r['id'] ?>" target="_blank">
                                            <img src="../view_fingerprint.php?test_id=<?= $r['id'] ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="FP">
                                        </a>
                                    <?php else: ?>
                                        <div style="width:48px;height:48px;border-radius:8px;background:#f4f6f0;display:flex;align-items:center;justify-content:center;"><span style="font-size:0.65rem;color:var(--danger);font-weight:600;text-align:center;padding:2px;">Image not found</span></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>
                                        <?php 
                                        $displayScore = $r['faculty_final_score'] !== null ? $r['faculty_final_score'] : $r['accuracy_score'];
                                        if ($r['status'] === 'approved' && $displayScore !== null): ?>
                                            <?= number_format($displayScore, 1) ?>%
                                        <?php elseif ($r['status'] === 'pending_validation'): ?>
                                            Awaiting Faculty Validation
                                        <?php elseif ($r['status'] === 'needs_revision'): ?>
                                            Needs Revision
                                        <?php elseif ($r['status'] === 'rejected'): ?>
                                            Rejected
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $r['status'] ?>">
                                        <?= $r['status'] === 'pending_validation' ? 'Pending Validation' : ($r['status'] === 'needs_revision' ? 'Needs Revision' : ucfirst($r['status'])) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($r['submitted_at'])) ?></td>
                                <td style="font-size:.82rem;color:#6c757d;max-width:180px;"><?= $r['faculty_remarks'] ? htmlspecialchars($r['faculty_remarks']) : '<em>No remarks yet</em>' ?></td>
                                <td style="text-align: right;">
                                    <a href="student_records.php?view=<?= $r['id'] ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $f_pwd ? '&powder='.urlencode($f_pwd) : '' ?><?= $f_surf ? '&surface='.urlencode($f_surf) : '' ?><?= $f_stat ? '&status='.urlencode($f_stat) : '' ?>" class="btn btn-secondary btn-sm">View Details</a>
                                </td>
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

<!-- VIEW RECORD MODAL -->
<?php if ($view_record): ?>
<div class="detail-overlay open" id="recordOverlay">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3>Trial Record Details: ID #<?= htmlspecialchars($view_record['trial_id'] ?: 'TR-'.str_pad($view_record['id'], 4, '0', STR_PAD_LEFT)) ?></h3>
            <button class="modal-close-btn" onclick="document.getElementById('recordOverlay').classList.remove('open')">&times;</button>
        </div>
        <div class="detail-modal-body">
            <p class="section-divider">Forensic Submission Details</p>
            <div class="detail-row"><span class="detail-label">Student Submitter</span><span class="detail-value"><?= htmlspecialchars($view_record['student_name']) ?></span></div>
            <div class="detail-row"><span class="detail-label">Powder Type Used</span><span class="detail-value" style="text-transform: capitalize; font-weight: 600;"><?= htmlspecialchars($view_record['powder_type']) ?></span></div>
            <div class="detail-row"><span class="detail-label">Surface Material Type</span><span class="detail-value" style="text-transform: capitalize; font-weight: 600;"><?= htmlspecialchars($view_record['surface_type']) ?></span></div>
            <div class="detail-row"><span class="detail-label">Image Label</span><span class="detail-value"><?= htmlspecialchars($view_record['image_label'] ?: 'Untitled') ?></span></div>
            <div class="detail-row"><span class="detail-label">Notes from Submission</span><span class="detail-value"><?= nl2br(htmlspecialchars($view_record['notes'] ?: 'No notes provided.')) ?></span></div>
            <div class="detail-row"><span class="detail-label">Date Submitted</span><span class="detail-value"><?= date('F d, Y g:i A', strtotime($view_record['submitted_at'])) ?></span></div>

            <p class="section-divider">Fingerprint Image Asset</p>
            <div style="text-align:center; margin-bottom:1rem; border:1px solid #e9ecef; padding:10px; border-radius:8px; background:#fafafa;">
                <?php if (!empty($view_record['image_path']) && file_exists(dirname(__DIR__) . '/uploads/fingerprints/'.$view_record['image_path'])): ?>
                    <a href="../view_fingerprint.php?test_id=<?= $view_record['id'] ?>" target="_blank">
                        <img src="../view_fingerprint.php?test_id=<?= $view_record['id'] ?>" style="max-height:220px; max-width:100%; object-fit:contain; border-radius:6px; border:1px solid #ddd;" alt="Fingerprint Image Asset">
                    </a>
                <?php else: ?>
                    <div style="padding:2rem; background:#f4f6f0; border-radius:6px; font-weight:600; color:var(--danger);">Image not found</div>
                <?php endif; ?>
            </div>

            <p class="section-divider">Automated Image Evaluation Scores</p>
            <div class="score-box">
                <div class="score-title">Individual Forensic Performance Metrics</div>
                <div class="score-values">
                    <div>
                        <div class="score-val"><?= $view_record['ridge_clarity_score'] !== null ? number_format($view_record['ridge_clarity_score'], 1) . '%' : '—' ?></div>
                        <div class="score-lbl">Clarity</div>
                    </div>
                    <div>
                        <div class="score-val"><?= $view_record['visibility_score'] !== null ? number_format($view_record['visibility_score'], 1) . '%' : '—' ?></div>
                        <div class="score-lbl">Visibility</div>
                    </div>
                    <div>
                        <div class="score-val"><?= $view_record['adhesion_score'] !== null ? number_format($view_record['adhesion_score'], 1) . '%' : '—' ?></div>
                        <div class="score-lbl">Adhesion</div>
                    </div>
                    <div>
                        <div class="score-val"><?= $view_record['contrast_score'] !== null ? number_format($view_record['contrast_score'], 1) . '%' : '—' ?></div>
                        <div class="score-lbl">Contrast</div>
                    </div>
                </div>
            </div>
            <div class="detail-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green); margin-bottom: 0.5rem;">
                <span class="detail-label" style="font-weight: 700;">AI Preliminary Score</span>
                <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;"><?= $view_record['ai_accuracy_score'] !== null ? number_format($view_record['ai_accuracy_score'], 1) . '%' : 'Awaiting AI Evaluation' ?></span>
            </div>
            <div class="detail-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green);">
                <span class="detail-label" style="font-weight: 700;">Faculty Final Score</span>
                <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;"><?= $view_record['faculty_final_score'] !== null ? number_format($view_record['faculty_final_score'], 1) . '%' : ($view_record['status'] === 'pending_validation' ? 'Awaiting Validation' : '—') ?></span>
            </div>

            <p class="section-divider">Validation Details</p>
            <div class="detail-row"><span class="detail-label">Validation Status</span><span class="detail-value">
                <span class="badge badge-<?= $view_record['status'] ?>">
                    <?= $view_record['status'] === 'pending_validation' ? 'Pending Validation' : ($view_record['status'] === 'needs_revision' ? 'Needs Revision' : ucfirst($view_record['status'])) ?>
                </span>
            </span></div>
            <div class="detail-row"><span class="detail-label">Faculty Reviewer</span><span class="detail-value" style="font-weight: 600;"><?= htmlspecialchars($view_record['validator_name'] ?: 'Awaiting Review') ?></span></div>
            <div class="detail-row"><span class="detail-label">Review Date</span><span class="detail-value"><?= $view_record['validation_date'] ? date('F d, Y g:i A', strtotime($view_record['validation_date'])) : '—' ?></span></div>
            <div class="detail-row"><span class="detail-label">Remarks from Reviewer</span><span class="detail-value" style="font-style: italic;"><?= nl2br(htmlspecialchars($view_record['faculty_remarks'] ?: 'No evaluation remarks submitted yet.')) ?></span></div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    
    const recordOverlay = document.getElementById('recordOverlay');
    if (recordOverlay) {
        recordOverlay.addEventListener('click', e => {
            if (e.target === recordOverlay) {
                recordOverlay.classList.remove('open');
            }
        });
    }
});
</script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
