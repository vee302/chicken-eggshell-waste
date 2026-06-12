<?php
// faculty/validate_accuracy.php - Validate Student Fingerprint Submissions
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';
$faculty_id   = $_SESSION['user_id']  ?? 0;
$message = $error = '';

// Handle Approve / Reject / Needs Revision POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['test_id'])) {
    $test_id  = (int) $_POST['test_id'];
    $action   = $_POST['action'];
    $remarks  = trim($_POST['remarks'] ?? '');

    if ($action === 'approve') {
        $clarity    = isset($_POST['ridge_clarity_score']) ? floatval($_POST['ridge_clarity_score']) : NULL;
        $visibility = isset($_POST['visibility_score']) ? floatval($_POST['visibility_score']) : NULL;
        $adhesion   = isset($_POST['adhesion_score']) ? floatval($_POST['adhesion_score']) : NULL;
        
        if ($clarity === NULL || $visibility === NULL || $adhesion === NULL || $clarity < 0 || $clarity > 100 || $visibility < 0 || $visibility > 100 || $adhesion < 0 || $adhesion > 100) {
            $error = 'Please provide valid scores (0-100) for Clarity, Visibility, and Adhesion.';
        } else {
            $accuracy = ($clarity + $visibility + $adhesion) / 3;
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE fingerprint_tests 
                    SET status = 'approved',
                        ridge_clarity_score = ?,
                        visibility_score = ?,
                        adhesion_score = ?,
                        accuracy_score = ?,
                        validated_by = ?,
                        validated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$clarity, $visibility, $adhesion, $accuracy, $faculty_id, $test_id]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_remarks (test_id, faculty_id, remarks, decision, created_at)
                    VALUES (?, ?, ?, 'approved', NOW())
                ");
                $stmt->execute([$test_id, $faculty_id, $remarks ?: 'Approved by faculty researcher.']);
                
                $pdo->commit();
                $message = 'Submission approved and scored successfully.';
            } catch (PDOException $e) { 
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage(); 
            }
        }
    } elseif ($action === 'reject' || $action === 'needs_revision') {
        if (empty($remarks)) {
            $error = 'Remarks are required when rejecting or requesting revision.';
        } else {
            $status = ($action === 'reject') ? 'rejected' : 'needs_revision';
            $success_text = ($action === 'reject') ? 'Submission rejected and remarks saved.' : 'Submission marked as needs revision and remarks saved.';
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE fingerprint_tests 
                    SET status = ?,
                        validated_by = ?,
                        validated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$status, $faculty_id, $test_id]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_remarks (test_id, faculty_id, remarks, decision, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$test_id, $faculty_id, $remarks, $status]);
                
                $pdo->commit();
                $message = $success_text;
            } catch (PDOException $e) { 
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage(); 
            }
        }
    }
}

$submissions = [];
try {
    $stmt = $pdo->query("
        SELECT 
          ft.*,
          student.full_name AS student_name
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        WHERE ft.status = 'pending_validation'
        ORDER BY ft.submitted_at DESC
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
                                <th>Trial ID</th>
                                <th>Student Name</th>
                                <th>Fingerprint Image</th>
                                <th>Image Label</th>
                                <th>Powder Type</th>
                                <th>Surface Type</th>
                                <th>Date Submitted</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($submissions)): ?>
                            <tr><td colspan="9" style="text-align:center;padding:2rem;color:#6c757d;">No pending validation trials found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $row): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--dark-green);"><?= htmlspecialchars($row['trial_id'] ?: 'TR-'.str_pad($row['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                <td>
                                    <?php if ($row['image_path'] && file_exists('../uploads/fingerprints/'.$row['image_path'])): ?>
                                        <a href="../uploads/fingerprints/<?= htmlspecialchars($row['image_path']) ?>" target="_blank">
                                            <img src="../uploads/fingerprints/<?= htmlspecialchars($row['image_path']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="Fingerprint">
                                        </a>
                                    <?php else: ?>
                                        <div style="width:50px;height:50px;border-radius:8px;background:#f4f6f0;display:flex;align-items:center;justify-content:center;">
                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#adb5bd" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['image_label'] ?: 'Untitled') ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['surface_type']) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($row['submitted_at'])) ?></td>
                                <td><span class="badge badge-<?= $row['status'] ?>">Pending Validation</span></td>
                                <td style="text-align: right;">
                                    <div class="btn-group" style="display:inline-flex; gap:6px;">
                                        <?php if ($row['image_path']): ?>
                                            <a href="../uploads/fingerprints/<?= htmlspecialchars($row['image_path']) ?>" target="_blank" class="btn btn-secondary btn-sm">View Image</a>
                                        <?php endif; ?>
                                        <button class="btn btn-primary btn-sm" onclick="openModal(<?= $row['id'] ?>,'approve')">Approve</button>
                                        <button class="btn btn-danger btn-sm" onclick="openModal(<?= $row['id'] ?>,'reject')">Reject</button>
                                        <button class="btn btn-secondary btn-sm" style="background:#e07a5f; border-color:#e07a5f; color:#fff;" onclick="openModal(<?= $row['id'] ?>,'needs_revision')">Needs Revision</button>
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
    <div class="action-modal" style="max-width:550px;">
        <div class="modal-header">
            <h3 id="modalTitle">Validate Submission</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" id="validationForm">
            <div class="modal-body">
                <input type="hidden" name="test_id" id="modalTestId">
                <input type="hidden" name="action"  id="modalAction">
                
                <div id="scoreFields" style="display:none; margin-bottom: 1.25rem;">
                    <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--dark-green); margin-bottom:8px; border-bottom:1px solid #D2E2D5; padding-bottom:4px;">Forensic Metric Scores</div>
                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:12px;">
                        <div class="form-group">
                            <label for="ridge_clarity">Ridge Clarity (%)</label>
                            <input type="number" name="ridge_clarity_score" id="ridge_clarity" class="form-control" min="0" max="100" step="0.1" value="85.0">
                        </div>
                        <div class="form-group">
                            <label for="visibility">Visibility (%)</label>
                            <input type="number" name="visibility_score" id="visibility" class="form-control" min="0" max="100" step="0.1" value="85.0">
                        </div>
                        <div class="form-group">
                            <label for="adhesion">Adhesion (%)</label>
                            <input type="number" name="adhesion_score" id="adhesion" class="form-control" min="0" max="100" step="0.1" value="85.0">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="remarksField">Faculty Remarks / Feedback</label>
                    <textarea name="remarks" id="remarksField" class="form-control" rows="4" placeholder="Enter evaluation feedback..."></textarea>
                    <p id="remarksRequired" style="display:none;color:#c0392b;font-size:.8rem;margin-top:.4rem;font-weight:600;">Remarks are required for this action.</p>
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
    const titles = { approve:'Approve Submission & Set Scores', reject:'Reject Submission', needs_revision:'Request Revision (Needs Revision)' };
    document.getElementById('modalTitle').textContent = titles[action] || 'Validate';
    
    const btn = document.getElementById('submitBtn');
    const scoreFields = document.getElementById('scoreFields');
    const remarksRequired = document.getElementById('remarksRequired');
    const remarksField = document.getElementById('remarksField');
    
    const clarityInp = document.getElementById('ridge_clarity');
    const visibilityInp = document.getElementById('visibility');
    const adhesionInp = document.getElementById('adhesion');
    
    if (action === 'approve') {
        btn.className = 'btn btn-primary';
        btn.textContent = 'Confirm Approval';
        scoreFields.style.display = 'block';
        remarksRequired.style.display = 'none';
        remarksField.required = false;
        remarksField.placeholder = 'Enter approval remarks (optional)...';
        
        clarityInp.required = true;
        visibilityInp.required = true;
        adhesionInp.required = true;
    } else {
        btn.className = 'btn btn-danger';
        btn.textContent = action === 'reject' ? 'Confirm Rejection' : 'Confirm Revision Request';
        scoreFields.style.display = 'none';
        remarksRequired.style.display = 'block';
        remarksField.required = true;
        remarksField.placeholder = 'Explain feedback / reason (required)...';
        
        clarityInp.required = false;
        visibilityInp.required = false;
        adhesionInp.required = false;
    }
    
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
