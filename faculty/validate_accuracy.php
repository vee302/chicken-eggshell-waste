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
        $contrast   = isset($_POST['contrast_score']) ? floatval($_POST['contrast_score']) : NULL;
        
        if ($clarity === NULL || $visibility === NULL || $adhesion === NULL || $contrast === NULL || $clarity < 0 || $clarity > 100 || $visibility < 0 || $visibility > 100 || $adhesion < 0 || $adhesion > 100 || $contrast < 0 || $contrast > 100) {
            $error = 'Please provide valid scores (0-100) for Clarity, Visibility, Adhesion, and Contrast.';
        } else {
            $accuracy = ($clarity + $visibility + $adhesion + $contrast) / 4;
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE fingerprint_tests 
                    SET status = 'approved',
                        ridge_clarity_score = ?,
                        visibility_score = ?,
                        adhesion_score = ?,
                        contrast_score = ?,
                        accuracy_score = ?,
                        faculty_final_score = ?,
                        validated_by = ?,
                        validated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$clarity, $visibility, $adhesion, $contrast, $accuracy, $accuracy, $faculty_id, $test_id]);
                
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
    foreach ($submissions as &$row) {
        $row['image_exists'] = false;
        if (!empty($row['image_path'])) {
            $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $row['image_path'];
            if (file_exists($filePath)) {
                $row['image_exists'] = true;
            }
        }
    }
    unset($row);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validate Accuracy Scores - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
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

            <div id="alertContainer"></div>

            <div class="dashboard-card">
                <div class="table-responsive">
                    <table class="custom-table" id="trialsTable">
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
                        <tbody id="trialsTableBody">
                        <?php if (empty($submissions)): ?>
                            <tr class="no-data-row"><td colspan="9" style="text-align:center;padding:2rem;color:#6c757d;">No pending validation trials found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $row): ?>
                            <tr data-trial-db-id="<?= $row['id'] ?>">
                                <td style="font-weight: 700; color: var(--dark-green);"><?= htmlspecialchars($row['trial_id'] ?: 'TR-'.str_pad($row['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                <td>
                                    <?php if ($row['image_path'] && file_exists(dirname(__DIR__) . '/uploads/fingerprints/'.$row['image_path'])): ?>
                                        <a href="../view_fingerprint.php?test_id=<?= $row['id'] ?>" target="_blank" class="fp-image-link">
                                            <img src="../view_fingerprint.php?test_id=<?= $row['id'] ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="Fingerprint">
                                        </a>
                                    <?php else: ?>
                                        <div style="width:50px;height:50px;border-radius:8px;background:#f4f6f0;display:flex;align-items:center;justify-content:center;">
                                            <span style="font-size:0.65rem;color:var(--danger);font-weight:600;text-align:center;padding:2px;">Image not found</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['image_label'] ?: 'Untitled') ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['surface_type']) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($row['submitted_at'])) ?></td>
                                <td><span class="badge badge-pending">Pending Validation</span></td>
                                <td style="text-align: right;">
                                    <div class="btn-group" style="display:inline-flex; gap:6px;">
                                        <button class="btn btn-primary btn-sm" onclick="openModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>,'approve')">Approve</button>
                                        <button class="btn btn-danger btn-sm" onclick="openModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>,'reject')">Reject</button>
                                        <button class="btn btn-secondary btn-sm" style="background:#e07a5f; border-color:#e07a5f; color:#fff;" onclick="openModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>,'needs_revision')">Needs Revision</button>
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
                
                <!-- Image Preview Section -->
                <div style="text-align:center; margin-bottom:1rem; border:1px solid #e9ecef; padding:8px; border-radius:8px; background:#fafafa; display:none;" id="modalImgWrapper">
                    <img id="modalImgPreview" src="" style="max-height:180px; max-width:100%; object-fit:contain; border-radius:6px; border:1px solid #ddd;" alt="Fingerprint Preview">
                </div>

                <!-- AI Preliminary Scores Panel -->
                <div id="aiScorePanel" style="display:none; background:rgba(45,106,79,0.06); border-radius:8px; padding:12px; margin-bottom:1rem; border:1px solid rgba(45,106,79,0.12);">
                    <div style="font-size:0.75rem; font-weight:700; color:var(--dark-green); text-transform:uppercase; margin-bottom:8px; letter-spacing:0.5px;">AI Preliminary Score (Automated Image Evaluation)</div>
                    <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:6px; text-align:center;">
                        <div style="background:#fff; padding:6px; border-radius:6px; border:1px solid #e2e8e0;">
                            <span style="font-size:0.6rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Clarity</span>
                            <strong style="font-size:0.85rem; color:var(--dark-green);" id="ai_clarity_lbl">—</strong>
                        </div>
                        <div style="background:#fff; padding:6px; border-radius:6px; border:1px solid #e2e8e0;">
                            <span style="font-size:0.6rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Visibility</span>
                            <strong style="font-size:0.85rem; color:var(--dark-green);" id="ai_visibility_lbl">—</strong>
                        </div>
                        <div style="background:#fff; padding:6px; border-radius:6px; border:1px solid #e2e8e0;">
                            <span style="font-size:0.6rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Adhesion</span>
                            <strong style="font-size:0.85rem; color:var(--dark-green);" id="ai_adhesion_lbl">—</strong>
                        </div>
                        <div style="background:#fff; padding:6px; border-radius:6px; border:1px solid #e2e8e0;">
                            <span style="font-size:0.6rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Contrast</span>
                            <strong style="font-size:0.85rem; color:var(--dark-green);" id="ai_contrast_lbl">—</strong>
                        </div>
                        <div style="background:var(--cream); padding:6px; border-radius:6px; border:1px solid rgba(45,106,79,0.2);">
                            <span style="font-size:0.6rem; color:var(--medium-green); display:block; text-transform:uppercase; font-weight:700;">Overall</span>
                            <strong style="font-size:0.85rem; color:var(--dark-green);" id="ai_overall_lbl">—</strong>
                        </div>
                    </div>
                    <p style="font-size:0.65rem; color:var(--gray); margin-top:6px; margin-bottom:0; font-style:italic;" id="ai_evaluated_date_lbl"></p>
                </div>

                <!-- Faculty Final Score Inputs -->
                <div id="scoreFields" style="display:none; margin-bottom: 1.25rem;">
                    <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--dark-green); margin-bottom:8px; border-bottom:1px solid #D2E2D5; padding-bottom:4px;">Faculty Final Score</div>
                    <p style="font-size:0.75rem; color:#6c757d; margin-top:0; margin-bottom:10px;">Pre-populated with AI evaluation. Adjust the scores below if needed.</p>
                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="ridge_clarity" style="font-size:0.7rem; font-weight:700;">Clarity (%)</label>
                            <input type="number" name="ridge_clarity_score" id="ridge_clarity" class="form-control" min="0" max="100" step="0.01" value="0.0">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="visibility" style="font-size:0.7rem; font-weight:700;">Visibility (%)</label>
                            <input type="number" name="visibility_score" id="visibility" class="form-control" min="0" max="100" step="0.01" value="0.0">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="adhesion" style="font-size:0.7rem; font-weight:700;">Adhesion (%)</label>
                            <input type="number" name="adhesion_score" id="adhesion" class="form-control" min="0" max="100" step="0.01" value="0.0">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="contrast" style="font-size:0.7rem; font-weight:700;">Contrast (%)</label>
                            <input type="number" name="contrast_score" id="contrast" class="form-control" min="0" max="100" step="0.01" value="0.0">
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
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let isSubmitting = false;

function openModal(row, action) {
    const id = row.id;
    document.getElementById('modalTestId').value = id;
    document.getElementById('modalAction').value  = action;
    const titles = { approve:'Approve Submission & Set Scores (Faculty Validation)', reject:'Reject Submission', needs_revision:'Request Revision (Needs Revision)' };
    document.getElementById('modalTitle').textContent = titles[action] || 'Validate';
    
    const btn = document.getElementById('submitBtn');
    const scoreFields = document.getElementById('scoreFields');
    const aiScorePanel = document.getElementById('aiScorePanel');
    const remarksRequired = document.getElementById('remarksRequired');
    const remarksField = document.getElementById('remarksField');
    
    const clarityInp = document.getElementById('ridge_clarity');
    const visibilityInp = document.getElementById('visibility');
    const adhesionInp = document.getElementById('adhesion');
    const contrastInp = document.getElementById('contrast');
    
    // Image Preview setup
    const modalImgWrapper = document.getElementById('modalImgWrapper');
    const modalImgPreview = document.getElementById('modalImgPreview');
    if (row.image_path && row.image_exists) {
        modalImgPreview.src = '../view_fingerprint.php?test_id=' + row.id;
        modalImgWrapper.style.display = 'block';
    } else {
        modalImgWrapper.style.display = 'none';
    }

    if (action === 'approve') {
        btn.className = 'btn btn-primary';
        btn.textContent = 'Confirm Approval';
        scoreFields.style.display = 'block';
        aiScorePanel.style.display = 'block';
        remarksRequired.style.display = 'none';
        remarksField.required = false;
        remarksField.placeholder = 'Enter approval remarks (optional)...';
        
        // Pre-populate input fields with AI scores (defaults)
        clarityInp.value = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score) : 0;
        visibilityInp.value = row.visibility_score !== null ? parseFloat(row.visibility_score) : 0;
        adhesionInp.value = row.adhesion_score !== null ? parseFloat(row.adhesion_score) : 0;
        contrastInp.value = row.contrast_score !== null ? parseFloat(row.contrast_score) : 0;
        
        // Static AI score labels
        document.getElementById('ai_clarity_lbl').textContent = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score).toFixed(1) + '%' : 'N/A';
        document.getElementById('ai_visibility_lbl').textContent = row.visibility_score !== null ? parseFloat(row.visibility_score).toFixed(1) + '%' : 'N/A';
        document.getElementById('ai_adhesion_lbl').textContent = row.adhesion_score !== null ? parseFloat(row.adhesion_score).toFixed(1) + '%' : 'N/A';
        document.getElementById('ai_contrast_lbl').textContent = row.contrast_score !== null ? parseFloat(row.contrast_score).toFixed(1) + '%' : 'N/A';
        document.getElementById('ai_overall_lbl').textContent = row.accuracy_score !== null ? parseFloat(row.accuracy_score).toFixed(1) + '%' : 'N/A';
        
        const evalDate = row.ai_evaluated_at ? new Date(row.ai_evaluated_at).toLocaleString() : 'N/A';
        document.getElementById('ai_evaluated_date_lbl').textContent = 'AI evaluation source: ' + (row.evaluation_source || 'AI Preliminary') + ' | Processed: ' + evalDate;
        
        clarityInp.required = true;
        visibilityInp.required = true;
        adhesionInp.required = true;
        contrastInp.required = true;
    } else {
        btn.className = 'btn btn-danger';
        btn.textContent = action === 'reject' ? 'Confirm Rejection' : 'Confirm Revision Request';
        scoreFields.style.display = 'none';
        aiScorePanel.style.display = 'none';
        remarksRequired.style.display = 'block';
        remarksField.required = true;
        remarksField.placeholder = 'Explain feedback / reason (required)...';
        
        clarityInp.required = false;
        visibilityInp.required = false;
        adhesionInp.required = false;
        contrastInp.required = false;
    }
    
    document.getElementById('actionModal').classList.add('active');
}

function closeModal() { 
    document.getElementById('actionModal').classList.remove('active'); 
}

document.getElementById('actionModal').addEventListener('click', e => { 
    if (e.target === document.getElementById('actionModal')) closeModal(); 
});

// Display Toast/Alert Notification
function showNotification(type, message) {
    const container = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    container.innerHTML = `<div class="alert-msg ${alertClass}">${message}</div>`;
    setTimeout(() => {
        container.innerHTML = '';
    }, 5000);
}

// Handle dynamic validation form submit
document.getElementById('validationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (isSubmitting) return;

    const action = document.getElementById('modalAction').value;
    const testId = document.getElementById('modalTestId').value;
    const remarks = document.getElementById('remarksField').value;
    const btn = document.getElementById('submitBtn');
    
    let endpoint = '';
    const formData = new FormData();
    formData.append('test_id', testId);
    formData.append('remarks', remarks);
    formData.append('csrf_token', csrfToken);

    if (action === 'approve') {
        endpoint = 'ajax_approve_trial.php';
        formData.append('ridge_clarity_score', document.getElementById('ridge_clarity').value);
        formData.append('visibility_score', document.getElementById('visibility').value);
        formData.append('adhesion_score', document.getElementById('adhesion').value);
        formData.append('contrast_score', document.getElementById('contrast').value);
    } else if (action === 'reject') {
        endpoint = 'ajax_reject_trial.php';
    } else if (action === 'needs_revision') {
        endpoint = 'ajax_needs_revision.php';
    }

    const originalText = btn.textContent;
    btn.textContent = 'Saving...';
    btn.disabled = true;
    isSubmitting = true;

    fetch(endpoint, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(res => res.json())
    .then(data => {
        isSubmitting = false;
        btn.textContent = originalText;
        btn.disabled = false;
        if (data.success) {
            showNotification('success', data.message);
            closeModal();
            removeTrialRow(testId);
        } else {
            showNotification('danger', data.message);
        }
    })
    .catch(err => {
        isSubmitting = false;
        btn.textContent = originalText;
        btn.disabled = false;
        showNotification('danger', 'An error occurred during submission.');
    });
});

function removeTrialRow(testId) {
    const row = document.querySelector(`tr[data-trial-db-id="${testId}"]`);
    if (row) {
        row.remove();
    }
    
    // Check if table is empty
    const tbody = document.getElementById('trialsTableBody');
    const rows = tbody.querySelectorAll('tr[data-trial-db-id]');
    if (rows.length === 0) {
        tbody.innerHTML = '<tr class="no-data-row"><td colspan="9" style="text-align:center;padding:2rem;color:#6c757d;">No pending validation trials found.</td></tr>';
    }
}

// Auto-refresh control (10s)
function isAutoRefreshPaused() {
    const isModalActive = document.getElementById('actionModal').classList.contains('active');
    const isUserTyping = document.activeElement && (
        document.activeElement.tagName === 'INPUT' || 
        document.activeElement.tagName === 'TEXTAREA' || 
        document.activeElement.tagName === 'SELECT'
    );
    return isModalActive || isUserTyping || isSubmitting;
}

function autoRefreshTrials() {
    if (isAutoRefreshPaused()) return;

    fetch('ajax_get_pending_trials.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('trialsTableBody');
                const submissions = data.data.submissions;
                
                const existingRows = Array.from(tbody.querySelectorAll('tr[data-trial-db-id]'));
                const existingIds = existingRows.map(row => parseInt(row.getAttribute('data-trial-db-id')));
                const newIds = submissions.map(s => parseInt(s.id));

                // Remove rows no longer pending
                existingRows.forEach(row => {
                    const id = parseInt(row.getAttribute('data-trial-db-id'));
                    if (!newIds.includes(id)) {
                        row.remove();
                    }
                });

                // Add or update rows
                submissions.forEach(s => {
                    let row = tbody.querySelector(`tr[data-trial-db-id="${s.id}"]`);
                    if (row) {
                        // Row exists: update label, powder, surface
                        row.children[1].textContent = s.student_name || '—';
                        row.children[3].textContent = s.image_label || 'Untitled';
                        row.children[4].textContent = s.powder_type || '';
                        row.children[5].textContent = s.surface_type || '';
                    } else {
                        // Insert new row
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-trial-db-id', s.id);
                        
                        let imageHtml = `
                            <div style="width:50px;height:50px;border-radius:8px;background:#f4f6f0;display:flex;align-items:center;justify-content:center;">
                                <span style="font-size:0.65rem;color:var(--danger);font-weight:600;text-align:center;padding:2px;">Image not found</span>
                            </div>`;
                        if (s.image_path && s.image_exists) {
                            imageHtml = `
                                <a href="../view_fingerprint.php?test_id=${s.id}" target="_blank" class="fp-image-link">
                                    <img src="../view_fingerprint.php?test_id=${s.id}" style="width:50px;height:50px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="Fingerprint">
                                </a>`;
                        }

                        tr.innerHTML = `
                            <td style="font-weight: 700; color: var(--dark-green);">${s.trial_id || 'TR-' + String(s.id).padStart(4, '0')}</td>
                            <td>${s.student_name || '—'}</td>
                            <td>${imageHtml}</td>
                            <td>${s.image_label || 'Untitled'}</td>
                            <td style="text-transform:capitalize;">${s.powder_type || ''}</td>
                            <td style="text-transform:capitalize;">${s.surface_type || ''}</td>
                            <td>${new Date(s.submitted_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} ${new Date(s.submitted_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</td>
                            <td><span class="badge badge-pending">Pending Validation</span></td>
                            <td style="text-align: right;">
                                <div class="btn-group" style="display:inline-flex; gap:6px;">
                                    <button class="btn btn-primary btn-sm" onclick='openModal(${JSON.stringify(s)}, "approve")'>Approve</button>
                                    <button class="btn btn-danger btn-sm" onclick='openModal(${JSON.stringify(s)}, "reject")'>Reject</button>
                                    <button class="btn btn-secondary btn-sm" style="background:#e07a5f; border-color:#e07a5f; color:#fff;" onclick='openModal(${JSON.stringify(s)}, "needs_revision")'>Needs Revision</button>
                                </div>
                            </td>
                        `;
                        // Prepend
                        const noData = tbody.querySelector('.no-data-row');
                        if (noData) noData.remove();
                        tbody.insertBefore(tr, tbody.firstChild);
                    }
                });

                if (submissions.length === 0 && !tbody.querySelector('.no-data-row')) {
                    tbody.innerHTML = '<tr class="no-data-row"><td colspan="9" style="text-align:center;padding:2rem;color:#6c757d;">No pending validation trials found.</td></tr>';
                }
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    
    // Start auto-refresh
    setInterval(autoRefreshTrials, 10000);
});
</script>
</body>
</html>
