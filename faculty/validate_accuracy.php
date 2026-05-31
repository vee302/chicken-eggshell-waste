<?php
// faculty/validate_accuracy.php - Validate Student Fingerprint Submissions
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';
$faculty_id   = $_SESSION['user_id']  ?? 0;
$message = $error = '';

// Handle Approve / Reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['test_id'])) {
    $test_id  = (int) $_POST['test_id'];
    $action   = $_POST['action'];
    $remarks  = trim($_POST['remarks'] ?? '');

    if ($action === 'approve') {
        try {
            $pdo->prepare("UPDATE fingerprint_tests SET status='approved' WHERE id=?")->execute([$test_id]);
            $pdo->prepare("INSERT INTO faculty_remarks (test_id, faculty_id, remarks, decision) VALUES (?,?,?,'approved')")
                ->execute([$test_id, $faculty_id, $remarks ?: 'Approved by faculty researcher.']);
            $message = 'Record approved successfully.';
        } catch (PDOException $e) { $error = 'Database error: '.$e->getMessage(); }

    } elseif ($action === 'reject') {
        if (empty($remarks)) {
            $error = 'Remarks are required when rejecting a submission.';
        } else {
            try {
                $pdo->prepare("UPDATE fingerprint_tests SET status='rejected' WHERE id=?")->execute([$test_id]);
                $pdo->prepare("INSERT INTO faculty_remarks (test_id, faculty_id, remarks, decision) VALUES (?,?,?,'rejected')")
                    ->execute([$test_id, $faculty_id, $remarks]);
                $message = 'Record rejected and remarks saved.';
            } catch (PDOException $e) { $error = 'Database error: '.$e->getMessage(); }
        }
    }
}

// Fetch all submissions with student name & latest faculty remark
$submissions = [];
try {
    $stmt = $pdo->query("
        SELECT ft.*, u.full_name AS student_name,
               fr.remarks AS faculty_remarks, fr.decision AS faculty_decision
        FROM fingerprint_tests ft
        JOIN users u ON u.id = ft.student_id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        ORDER BY FIELD(ft.status,'pending','rejected','approved'), ft.submitted_at DESC
    ");
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validate Accuracy Scores - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .badge-pending  { background:rgba(244,162,97,.15);  color:#c97d2a; }
        .badge-approved { background:rgba(82,183,136,.15);  color:#2d6a4f; }
        .badge-rejected { background:rgba(224,122,95,.15);  color:#c0392b; }
        .score-bar { display:flex; align-items:center; gap:8px; }
        .score-bar-track { flex:1; height:6px; background:#e9ecef; border-radius:3px; overflow:hidden; }
        .score-bar-fill  { height:100%; background:#2d6a4f; border-radius:3px; }
        .alert-msg { padding:.85rem 1.2rem; border-radius:10px; margin-bottom:1.5rem; font-weight:600; font-size:.9rem; }
        .alert-success { background:rgba(82,183,136,.12); color:#2d6a4f; border:1px solid rgba(82,183,136,.3); }
        .alert-error   { background:rgba(224,122,95,.12);  color:#c0392b; border:1px solid rgba(224,122,95,.3); }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="brand-text"><span>GREEN</span><span class="brand-accent">FORENSICS</span></div></div>
        <div class="sidebar-user">
            <div class="user-info">
                <div class="user-avatar">FR</div>
                <div class="user-details"><h4><?= htmlspecialchars($faculty_name) ?></h4><span>Faculty Researcher</span></div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="faculty_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="comparison_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Comparison Dashboard</span></a></li>
            <li class="menu-item active"><a href="validate_accuracy.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Validate Accuracy Scores</span></a></li>
            <li class="menu-item"><a href="surface_performance.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg><span>Surface Performance</span></a></li>
            <li class="menu-item"><a href="safety_climate_log.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Safety &amp; Climate Log</span></a></li>
            <li class="menu-item"><a href="student_records.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>View Student Records</span></a></li>
            <li class="menu-item"><a href="generate_reports.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Generate Reports</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../logout.php" class="menu-link" style="color:#e07a5f;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="header-title"><h2>Green Forensics — Validate Accuracy Scores</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Validate Accuracy Scores</h1>
                    <p>Review, approve, or reject student fingerprint trial submissions.</p>
                </div>
            </div>

            <?php if ($message): ?><div class="alert-msg alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert-msg alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="dashboard-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Image</th>
                                <th>Powder</th>
                                <th>Surface</th>
                                <th>Ridge Clarity</th>
                                <th>Visibility</th>
                                <th>Adhesion</th>
                                <th>Accuracy</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($submissions)): ?>
                            <tr><td colspan="12" style="text-align:center;padding:2rem;color:#6c757d;">No submissions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $i => $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                <td>
                                    <?php if ($row['fingerprint_image'] && file_exists('../uploads/fingerprints/'.$row['fingerprint_image'])): ?>
                                        <a href="../uploads/fingerprints/<?= htmlspecialchars($row['fingerprint_image']) ?>" target="_blank">
                                            <img src="../uploads/fingerprints/<?= htmlspecialchars($row['fingerprint_image']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="Fingerprint">
                                        </a>
                                    <?php else: ?>
                                        <div style="width:50px;height:50px;border-radius:8px;background:#f4f6f0;display:flex;align-items:center;justify-content:center;">
                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#adb5bd" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-transform:capitalize;"><?= $row['powder_type'] ?></td>
                                <td style="text-transform:capitalize;"><?= $row['surface_type'] ?></td>
                                <td>
                                    <div class="score-bar">
                                        <div class="score-bar-track"><div class="score-bar-fill" style="width:<?= min($row['ridge_clarity_score'],100) ?>%"></div></div>
                                        <span style="font-size:.8rem;font-weight:600;"><?= number_format($row['ridge_clarity_score'],1) ?>%</span>
                                    </div>
                                </td>
                                <td><?= number_format($row['visibility_score'],1) ?>%</td>
                                <td><?= number_format($row['adhesion_score'],1) ?>%</td>
                                <td><strong><?= number_format($row['accuracy_score'],1) ?>%</strong></td>
                                <td><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
                                <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($row['status'] === 'pending'): ?>
                                        <button class="btn btn-primary btn-sm" onclick="openModal(<?= $row['id'] ?>,'approve')">Approve</button>
                                        <button class="btn btn-danger btn-sm" onclick="openModal(<?= $row['id'] ?>,'reject')">Reject</button>
                                        <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" onclick="openModal(<?= $row['id'] ?>,'remark')">Remark</button>
                                        <?php endif; ?>
                                    </div>
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

<!-- ACTION MODAL -->
<div class="action-modal-overlay" id="actionModal">
    <div class="action-modal">
        <div class="modal-header">
            <h3 id="modalTitle">Validate Submission</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="test_id" id="modalTestId">
                <input type="hidden" name="action"  id="modalAction">
                <div class="form-group">
                    <label for="remarksField">Faculty Remarks</label>
                    <textarea name="remarks" id="remarksField" class="form-control" rows="4" placeholder="Enter your evaluation remarks..."></textarea>
                    <p id="remarksRequired" style="display:none;color:#c0392b;font-size:.8rem;margin-top:.4rem;">Remarks are required when rejecting a submission.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, action) {
    document.getElementById('modalTestId').value = id;
    document.getElementById('modalAction').value  = action;
    const titles = { approve:'Approve Submission', reject:'Reject Submission', remark:'Add Remarks' };
    document.getElementById('modalTitle').textContent = titles[action] || 'Validate';
    const btn = document.getElementById('submitBtn');
    btn.className = 'btn ' + (action === 'reject' ? 'btn-danger' : 'btn-primary');
    btn.textContent = action === 'approve' ? 'Confirm Approval' : action === 'reject' ? 'Confirm Rejection' : 'Save Remarks';
    document.getElementById('actionModal').classList.add('active');
}
function closeModal() { document.getElementById('actionModal').classList.remove('active'); }
document.getElementById('actionModal').addEventListener('click', e => { if (e.target === document.getElementById('actionModal')) closeModal(); });
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
});
</script>
</body>
</html>
