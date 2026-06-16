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
    <style>
        .badge-pending_validation { background:rgba(244,162,97,.15); color:#c97d2a; border:1px solid rgba(244,162,97,.25); }
        .badge-needs_revision { background:rgba(230,57,70,.12); color:#e63946; border:1px solid rgba(230,57,70,.2); }
        .badge-approved { background:rgba(82,183,136,.15); color:#2d6a4f; border:1px solid rgba(82,183,136,.25); }
        .badge-rejected { background:rgba(224,122,95,.15); color:#c0392b; border:1px solid rgba(224,122,95,.2); }

        /* Detail Modal styling */
        .detail-overlay { display:none; position:fixed; inset:0; background:rgba(27, 67, 50, 0.45); backdrop-filter: blur(4px); z-index:9999; align-items:center; justify-content:center; }
        .detail-overlay.open { display:flex; }
        .detail-modal { background:#fff; border-radius:16px; max-width:650px; width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); border: 1px solid rgba(27,67,50,0.1); }
        .detail-modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:var(--dark-green); color:#fff; }
        .detail-modal-header h3 { color:#fff; font-size:1.05rem; font-weight:700; margin:0; }
        .detail-modal-body { padding:1.5rem; }
        .detail-row { display:flex; gap:.5rem; margin-bottom:.75rem; font-size:.85rem; }
        .detail-label { min-width:180px; font-weight:600; color:var(--dark-green); }
        .detail-value { color:#5f5f5f; flex:1; }
        .modal-close-btn { background:none; border:none; font-size:1.4rem; cursor:pointer; color:#fff; opacity:0.8; line-height:1; }
        .modal-close-btn:hover { opacity:1; }
        .section-divider { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#6B8F71; border-bottom:1px solid #D2E2D5; padding-bottom:.35rem; margin:1.25rem 0 .6rem; }
        .section-divider:first-child { margin-top: 0; }
        
        .score-box { background: var(--cream); border-radius:8px; padding:10px 15px; margin-bottom:1rem; border:1px solid rgba(45,106,79,0.08); }
        
        @media print {
            body > div:not(#inspectionOverlay) {
                display: none !important;
            }
            #sidebar, .student-sidebar, .student-header, .no-print {
                display: none !important;
            }
            #inspectionOverlay {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: none !important;
                backdrop-filter: none !important;
                display: block !important;
            }
            .modal-close-btn {
                display: none !important;
            }
            .detail-modal {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
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
                    <table class="custom-table" id="trialScoresTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Accuracy Score</th>
                                <th>Score Bar</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($trials)): ?>
                            <tr><td colspan="8" style="text-align:center;color:#6c757d;padding:2rem;">No trial records yet. <a href="submit_trial.php" style="color:var(--medium-green);font-weight:600;">Submit a trial →</a></td></tr>
                        <?php else: ?>
                            <?php foreach ($trials as $i => $t): ?>
                            <tr data-trial-id="<?= $t['id'] ?>">
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
                                <td style="text-align: right;">
                                    <button class="btn btn-secondary btn-sm" onclick="openInspectionModal(<?= $t['id'] ?>)">View Details</button>
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

<!-- DETAILED QUALITY INSPECTION MODAL -->
<div class="detail-overlay" id="inspectionOverlay">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3>Detailed Quality Inspection</h3>
            <button class="modal-close-btn" onclick="closeInspectionModal()">&times;</button>
        </div>
        <div class="detail-modal-body">
            
            <div id="modalLoading" style="text-align:center; padding: 2rem; color: var(--gray);">
                Loading trial details...
            </div>
            
            <div id="modalContent" style="display:none;">
                <p class="section-divider">Fingerprint Image Asset</p>
                <div style="text-align:center; margin-bottom:1rem; border:1px solid #e9ecef; padding:10px; border-radius:8px; background:#fafafa;" id="inspect-img-wrapper">
                    <img src="" style="max-height:220px; max-width:100%; object-fit:contain; border-radius:6px; border:1px solid #ddd;" alt="Fingerprint" id="inspect-img">
                </div>
                <div style="text-align:center; color: var(--danger); font-weight:600; margin-bottom:1rem; display:none;" id="inspect-img-missing">
                    Image not found.
                </div>

                <p class="section-divider">Trial Information</p>
                <div class="detail-row"><span class="detail-label">Trial ID</span><span class="detail-value" id="inspect-trial-id"></span></div>
                <div class="detail-row"><span class="detail-label">Powder Type</span><span class="detail-value" id="inspect-powder" style="text-transform: capitalize; font-weight:600;"></span></div>
                <div class="detail-row"><span class="detail-label">Surface Type</span><span class="detail-value" id="inspect-surface" style="text-transform: capitalize; font-weight:600;"></span></div>
                <div class="detail-row"><span class="detail-label">Image Label</span><span class="detail-value" id="inspect-label"></span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" id="inspect-status"></span></div>

                <p class="section-divider">Quality Metrics</p>
                <div class="score-box">
                    <!-- Ridge Clarity -->
                    <div style="margin-bottom:0.75rem;">
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; color:var(--dark-green); margin-bottom:4px;">
                            <span>Ridge Clarity</span>
                            <span id="inspect-val-clarity">—</span>
                        </div>
                        <div class="score-bar-track" style="height:8px; background:#e2e8e0; border-radius:4px; overflow:hidden;">
                            <div class="score-bar-fill" id="inspect-fill-clarity" style="height:100%; background:var(--medium-green); width:0%;"></div>
                        </div>
                    </div>
                    <!-- Contrast Quality -->
                    <div style="margin-bottom:0.75rem;">
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; color:var(--dark-green); margin-bottom:4px;">
                            <span>Contrast Quality</span>
                            <span id="inspect-val-contrast">—</span>
                        </div>
                        <div class="score-bar-track" style="height:8px; background:#e2e8e0; border-radius:4px; overflow:hidden;">
                            <div class="score-bar-fill" id="inspect-fill-contrast" style="height:100%; background:var(--medium-green); width:0%;"></div>
                        </div>
                    </div>
                    <!-- Minutiae Visibility -->
                    <div style="margin-bottom:0.75rem;">
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; color:var(--dark-green); margin-bottom:4px;">
                            <span>Minutiae Visibility</span>
                            <span id="inspect-val-visibility">—</span>
                        </div>
                        <div class="score-bar-track" style="height:8px; background:#e2e8e0; border-radius:4px; overflow:hidden;">
                            <div class="score-bar-fill" id="inspect-fill-visibility" style="height:100%; background:var(--medium-green); width:0%;"></div>
                        </div>
                    </div>
                    <!-- Fingerprint Sharpness -->
                    <div style="margin-bottom:0.75rem;">
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; color:var(--dark-green); margin-bottom:4px;">
                            <span>Fingerprint Sharpness</span>
                            <span id="inspect-val-sharpness">—</span>
                        </div>
                        <div class="score-bar-track" style="height:8px; background:#e2e8e0; border-radius:4px; overflow:hidden;">
                            <div class="score-bar-fill" id="inspect-fill-sharpness" style="height:100%; background:var(--medium-green); width:0%;"></div>
                        </div>
                    </div>
                    <!-- Adhesion Quality -->
                    <div style="margin-bottom:0.75rem;">
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; color:var(--dark-green); margin-bottom:4px;">
                            <span>Adhesion Quality</span>
                            <span id="inspect-val-adhesion">—</span>
                        </div>
                        <div class="score-bar-track" style="height:8px; background:#e2e8e0; border-radius:4px; overflow:hidden;">
                            <div class="score-bar-fill" id="inspect-fill-adhesion" style="height:100%; background:var(--medium-green); width:0%;"></div>
                        </div>
                    </div>
                    <!-- Composite Accuracy Score -->
                    <div style="margin-top:1.25rem; border-top:1px solid #D2E2D5; padding-top:1rem;">
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:800; color:var(--dark-green); margin-bottom:6px;">
                            <span>Composite Accuracy Score</span>
                            <span id="inspect-val-accuracy">—</span>
                        </div>
                        <div class="score-bar-track" style="height:10px; background:#e2e8e0; border-radius:5px; overflow:hidden;">
                            <div class="score-bar-fill" id="inspect-fill-accuracy" style="height:100%; background:#2d6a4f; width:0%;"></div>
                        </div>
                    </div>
                </div>

                <p class="section-divider">Lab Analysis Notes</p>
                <div class="detail-row"><span class="detail-label">AI Preliminary Score</span><span class="detail-value" id="inspect-ai-score" style="font-weight: 700;"></span></div>
                <div class="detail-row" id="row-inspect-faculty-score"><span class="detail-label" id="inspect-faculty-score-label">Faculty Final Score</span><span class="detail-value" id="inspect-faculty-score" style="font-weight: 700; color: var(--dark-green);"></span></div>
                <div class="detail-row" id="row-inspect-reviewer"><span class="detail-label">Faculty Reviewer</span><span class="detail-value" id="inspect-reviewer" style="font-weight:600;"></span></div>
                <div class="detail-row" id="row-inspect-remarks"><span class="detail-label" id="inspect-remarks-label">Faculty Remarks</span><span class="detail-value" id="inspect-remarks" style="font-style: italic;"></span></div>
                <div class="detail-row"><span class="detail-label">Evaluation Date</span><span class="detail-value" id="inspect-evaluation-date"></span></div>
                <div class="detail-row" id="row-inspect-validated-at"><span class="detail-label">Validation Date</span><span class="detail-value" id="inspect-validated-at"></span></div>

                <div style="display:flex; gap:10px; margin-top:2rem;" class="no-print">
                    <button type="button" class="btn btn-secondary" onclick="closeInspectionModal()" style="flex:1;">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printTrialDetails()" style="flex:1;">Print Details</button>
                </div>
            </div>

            <div id="modalError" style="display:none; text-align:center; padding: 2rem; color: var(--danger); font-weight: 600;">
                Unable to load trial details.
            </div>

        </div>
    </div>
</div>

<?php require_once '_sidebar_js.php'; ?>
<script>
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

function openInspectionModal(testId) {
    const overlay = document.getElementById('inspectionOverlay');
    const loading = document.getElementById('modalLoading');
    const content = document.getElementById('modalContent');
    const errorMsg = document.getElementById('modalError');

    // Reset view
    loading.style.display = 'block';
    content.style.display = 'none';
    errorMsg.style.display = 'none';
    overlay.classList.add('open');

    fetch(`ajax_get_trial_details.php?test_id=${testId}`)
        .then(res => {
            if (!res.ok) throw new Error();
            return res.json();
        })
        .then(data => {
            if (data.success) {
                loading.style.display = 'none';
                content.style.display = 'block';
                populateInspectionPanel(data.data);
            } else {
                loading.style.display = 'none';
                errorMsg.style.display = 'block';
            }
        })
        .catch(err => {
            loading.style.display = 'none';
            errorMsg.style.display = 'block';
        });
}

function closeInspectionModal() {
    document.getElementById('inspectionOverlay').classList.remove('open');
}

// Close when clicking outside overlay
document.getElementById('inspectionOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('inspectionOverlay')) {
        closeInspectionModal();
    }
});

function populateInspectionPanel(row) {
    document.getElementById('inspect-trial-id').textContent = row.trial_id || 'TR-' + String(row.id).padStart(4, '0');
    document.getElementById('inspect-powder').textContent = row.powder_type || '';
    document.getElementById('inspect-surface').textContent = row.surface_type || '';
    document.getElementById('inspect-label').textContent = row.image_label || 'Untitled';
    
    // Evaluation Date mapping
    const evalDate = row.ai_evaluated_at ? new Date(row.ai_evaluated_at.replace(/-/g, "/")).toLocaleString() : (row.submitted_at ? new Date(row.submitted_at.replace(/-/g, "/")).toLocaleString() : '—');
    document.getElementById('inspect-evaluation-date').textContent = evalDate;

    // Image viewer logic
    const img = document.getElementById('inspect-img');
    const imgWrapper = document.getElementById('inspect-img-wrapper');
    const imgMissing = document.getElementById('inspect-img-missing');
    
    if (row.image_path && row.image_exists) {
        img.src = '../view_fingerprint.php?test_id=' + row.id;
        imgWrapper.style.display = 'block';
        imgMissing.style.display = 'none';
    } else {
        imgWrapper.style.display = 'none';
        imgMissing.style.display = 'block';
    }

    // Quality metrics values mapping
    const clarity = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score) : 0;
    const contrast = row.contrast_score !== null ? parseFloat(row.contrast_score) : 0;
    const visibility = row.visibility_score !== null ? parseFloat(row.visibility_score) : 0;
    const sharpness = clarity; // Use ridge_clarity_score as sharpness display value
    const adhesion = row.adhesion_score !== null ? parseFloat(row.adhesion_score) : 0;
    const accuracy = row.accuracy_score !== null ? parseFloat(row.accuracy_score) : 0;

    // Set text labels
    document.getElementById('inspect-val-clarity').textContent = clarity > 0 ? clarity.toFixed(1) + '%' : '—';
    document.getElementById('inspect-val-contrast').textContent = contrast > 0 ? contrast.toFixed(1) + '%' : '—';
    document.getElementById('inspect-val-visibility').textContent = visibility > 0 ? visibility.toFixed(1) + '%' : '—';
    document.getElementById('inspect-val-sharpness').textContent = sharpness > 0 ? sharpness.toFixed(1) + '%' : '—';
    document.getElementById('inspect-val-adhesion').textContent = adhesion > 0 ? adhesion.toFixed(1) + '%' : '—';
    document.getElementById('inspect-val-accuracy').textContent = accuracy > 0 ? accuracy.toFixed(1) + '%' : '—';

    // Set progress bar widths
    document.getElementById('inspect-fill-clarity').style.width = clarity + '%';
    document.getElementById('inspect-fill-contrast').style.width = contrast + '%';
    document.getElementById('inspect-fill-visibility').style.width = visibility + '%';
    document.getElementById('inspect-fill-sharpness').style.width = sharpness + '%';
    document.getElementById('inspect-fill-adhesion').style.width = adhesion + '%';
    document.getElementById('inspect-fill-accuracy').style.width = accuracy + '%';

    // Lab Analysis Notes mapping
    document.getElementById('inspect-ai-score').textContent = row.ai_accuracy_score !== null ? parseFloat(row.ai_accuracy_score).toFixed(1) + '%' : 'Awaiting AI Evaluation';

    // Conditional elements based on status
    const statusVal = document.getElementById('inspect-status');
    const reviewerRow = document.getElementById('row-inspect-reviewer');
    const validatedAtRow = document.getElementById('row-inspect-validated-at');
    const remarksRow = document.getElementById('row-inspect-remarks');
    const facultyScoreRow = document.getElementById('row-inspect-faculty-score');

    if (row.status === 'pending_validation') {
        statusVal.innerHTML = '<span class="badge badge-pending_validation">Pending Validation</span>';
        reviewerRow.style.display = 'none';
        validatedAtRow.style.display = 'none';
        facultyScoreRow.style.display = 'flex';
        
        document.getElementById('inspect-faculty-score-label').textContent = 'Accuracy';
        document.getElementById('inspect-faculty-score').textContent = 'Awaiting Faculty Validation';
        
        remarksRow.style.display = 'flex';
        document.getElementById('inspect-remarks-label').textContent = 'Notes';
        document.getElementById('inspect-remarks').innerHTML = 'This record is still awaiting faculty review.';
    } else {
        reviewerRow.style.display = 'flex';
        validatedAtRow.style.display = 'flex';
        remarksRow.style.display = 'flex';
        
        document.getElementById('inspect-reviewer').textContent = row.faculty_reviewer || 'Faculty Reviewer';
        document.getElementById('inspect-validated-at').textContent = row.validated_at ? new Date(row.validated_at.replace(/-/g, "/")).toLocaleString() : '—';
        
        document.getElementById('inspect-remarks-label').textContent = 'Faculty Remarks';
        document.getElementById('inspect-remarks').innerHTML = row.faculty_remarks ? escapeHtml(row.faculty_remarks).replace(/\n/g, '<br>') : 'No remarks provided.';

        if (row.status === 'approved') {
            statusVal.innerHTML = '<span class="badge badge-approved">Approved</span>';
            facultyScoreRow.style.display = 'flex';
            document.getElementById('inspect-faculty-score-label').textContent = 'Faculty Final Score';
            document.getElementById('inspect-faculty-score').textContent = row.faculty_final_score !== null ? parseFloat(row.faculty_final_score).toFixed(1) + '%' : '—';
        } else if (row.status === 'rejected') {
            statusVal.innerHTML = '<span class="badge badge-rejected">Rejected</span>';
            facultyScoreRow.style.display = 'none';
        } else if (row.status === 'needs_revision') {
            statusVal.innerHTML = '<span class="badge badge-needs_revision">Needs Revision</span>';
            facultyScoreRow.style.display = 'none';
        }
    }
}

function printTrialDetails() {
    window.print();
}
</script>
</body>
</html>
