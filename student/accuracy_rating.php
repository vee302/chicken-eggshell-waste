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
        $score = $t['faculty_final_score'] !== null ? $t['faculty_final_score'] : $t['accuracy_score'];
        return $t['status'] === 'approved' && $score !== null;
    });
    
    if ($approved_trials) {
        $scores_sum = array_sum(array_map(function($t) {
            return $t['faculty_final_score'] !== null ? $t['faculty_final_score'] : $t['accuracy_score'];
        }, $approved_trials));
        $overall_avg = round($scores_sum / count($approved_trials), 1);
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
        .detail-overlay { display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index:9999; align-items:center; justify-content:center; }
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

        /* Dark theme Detailed Quality Inspection modal scoped under #inspectionOverlay */
        #inspectionOverlay .detail-modal {
            background: #111a2e !important; /* Deep dark navy background */
            color: #f8fafc !important;
            border: 1px solid #1e293b !important;
            max-width: 800px !important;
            width: 95% !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            border-radius: 16px !important;
        }
        #inspectionOverlay .detail-modal-header {
            background: #1e293b !important;
            border-bottom: 1px solid #334155 !important;
            color: #f8fafc !important;
            padding: 1.1rem 1.5rem !important;
            border-top-left-radius: 15px !important;
            border-top-right-radius: 15px !important;
        }
        #inspectionOverlay .detail-modal-header h3 {
            color: #f8fafc !important;
            font-size: 1.2rem !important;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            margin: 0;
        }
        #inspectionOverlay .modal-close-btn {
            color: #94a3b8 !important;
            background: none !important;
            border: none !important;
            font-size: 1.6rem !important;
            cursor: pointer !important;
            opacity: 0.8 !important;
        }
        #inspectionOverlay .modal-close-btn:hover {
            color: #f8fafc !important;
            opacity: 1 !important;
        }
        #inspectionOverlay .detail-modal-body {
            padding: 1.5rem !important;
        }

        /* Layout Grid */
        .inspect-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .inspect-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        /* Column Titles */
        .column-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 0.5rem;
        }

        /* Image Preview Box */
        .inspect-img-box {
            background: #090d16;
            border: 1px solid #1e293b;
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 250px;
        }
        .inspect-img-box img {
            max-height: 250px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        .inspect-img-caption {
            font-size: 0.75rem;
            color: #64748b;
            text-align: center;
            line-height: 1.5;
            margin-top: 0.5rem;
        }

        /* Coefficient Section */
        .coefficient-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            background: #151e33;
            padding: 14px 20px;
            border-radius: 12px;
            border: 1px solid #27354f;
        }
        .overall-score-huge {
            font-size: 3.8rem;
            font-weight: 800;
            color: #10b981; /* Neon Green */
            line-height: 1;
            font-feature-settings: "tnum";
        }
        .overall-score-badge-wrap {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .quality-badge {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            border: 1px solid rgba(16, 185, 129, 0.3);
            display: inline-block;
            text-align: center;
            width: fit-content;
            letter-spacing: 0.05em;
        }
        .quality-badge-desc {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        /* Dark Progress Bars */
        .metric-item {
            margin-bottom: 1.25rem;
        }
        .metric-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 6px;
        }
        .metric-info span:last-child {
            color: #10b981;
        }
        .metric-bar-track {
            height: 6px;
            background: #1e293b;
            border-radius: 3px;
            overflow: hidden;
            width: 100%;
        }
        .metric-bar-fill {
            height: 100%;
            background: #10b981;
            border-radius: 3px;
            transition: width 0.8s ease-out;
            width: 0%;
        }

        /* Lab Analysis Notes Box */
        .analysis-notes-box {
            background: #151e33;
            border: 1px solid #27354f;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .analysis-notes-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #27354f;
            padding-bottom: 0.6rem;
        }
        .notes-content-wrap {
            margin-bottom: 1.5rem;
        }
        .notes-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .notes-text {
            font-size: 0.88rem;
            color: #cbd5e1;
            line-height: 1.55;
            background: #090d16;
            padding: 0.85rem 1.1rem;
            border-radius: 8px;
            min-height: 45px;
            border: 1px solid #27354f;
        }

        /* Info Details Grid */
        .info-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem 2.5rem;
            font-size: 0.82rem;
        }
        @media (max-width: 600px) {
            .info-details-grid {
                grid-template-columns: 1fr;
            }
        }
        .info-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            align-items: center;
        }
        .info-detail-label {
            color: #94a3b8;
            font-weight: 600;
        }
        .info-detail-value {
            color: #f1f5f9;
            font-weight: 700;
            text-align: right;
        }

        /* Student chip */
        .student-chip {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid rgba(16, 185, 129, 0.25);
            text-transform: lowercase;
        }
        
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
            #inspectionOverlay .detail-modal {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #111a2e !important;
                color: #f8fafc !important;
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
                            <tr><td colspan="8" style="text-align:center;color:#6c757d;padding:2rem;">No evaluation records found yet. Upload your first fingerprint image to begin evaluation. <a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload now →</a></td></tr>
                        <?php else: ?>
                            <?php foreach ($trials as $i => $t): ?>
                            <tr data-trial-id="<?= $t['id'] ?>">
                                <td><?= $i + 1 ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($t['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($t['surface_type']) ?></td>
                                <td>
                                    <strong>
                                        <?php 
                                        $displayScore = $t['faculty_final_score'] !== null ? $t['faculty_final_score'] : $t['accuracy_score'];
                                        if ($t['status'] === 'approved' && $displayScore !== null): ?>
                                            <?= number_format($displayScore, 1) ?>%
                                        <?php elseif ($t['status'] === 'pending_validation'): ?>
                                            Awaiting Faculty Validation
                                        <?php elseif ($t['status'] === 'needs_revision'): ?>
                                            Needs Revision
                                        <?php elseif ($t['status'] === 'rejected'): ?>
                                            —
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td style="min-width:130px;">
                                    <?php if ($t['status'] === 'approved' && $displayScore !== null): ?>
                                        <div class="score-bar">
                                            <div class="score-bar-track">
                                                <div class="score-bar-fill" style="width:<?= min(100, $displayScore) ?>%"></div>
                                            </div>
                                        </div>
                                    <?php elseif ($t['status'] === 'rejected'): ?>
                                        <span style="font-size:.75rem;color:var(--danger);font-style:italic;">No final score</span>
                                    <?php elseif ($t['status'] === 'needs_revision'): ?>
                                        <span style="font-size:.75rem;color:var(--warning);font-style:italic;">Needs Revision</span>
                                    <?php else: ?>
                                        <span style="font-size:.75rem;color:var(--gray);font-style:italic;">Awaiting Faculty Validation</span>
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
            <h3>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981; margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Detailed Quality Inspection
            </h3>
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="student-chip" id="inspect-student-chip">student123</span>
                <button class="modal-close-btn" onclick="closeInspectionModal()">&times;</button>
            </div>
        </div>
        <div class="detail-modal-body">
            
            <div id="modalLoading" style="text-align:center; padding: 2rem; color: #94a3b8;">
                Loading trial details...
            </div>
            
            <div id="modalContent" style="display:none;">
                <div class="inspect-grid">
                    <!-- Left Column: Minutiae Mapping -->
                    <div>
                        <div class="column-title">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            MINUTIAE MAPPING
                        </div>
                        <div class="inspect-img-box" id="inspect-img-wrapper">
                            <img src="" alt="Fingerprint Preview" id="inspect-img">
                        </div>
                        <div style="text-align:center; color: #ef4444; font-weight:600; margin-bottom:1rem; display:none;" id="inspect-img-missing">
                            Image not found.
                        </div>
                        <div class="inspect-img-caption">
                            Green indicators represent bifurcation/ridge ending coordinate clusters mapped by OpenCV.
                        </div>
                    </div>

                    <!-- Right Column: Evaluation Coefficient -->
                    <div>
                        <div class="column-title">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            EVALUATION COEFFICIENT
                        </div>
                        
                        <div class="coefficient-header">
                            <div class="overall-score-huge" id="inspect-val-accuracy-huge">—</div>
                            <div class="overall-score-badge-wrap">
                                <span class="quality-badge" id="inspect-val-quality-badge">GOOD</span>
                                <span class="quality-badge-desc">Overall Print Quality Standard</span>
                            </div>
                        </div>

                        <!-- Progress Bars -->
                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Ridge Clarity</span>
                                <span id="inspect-val-clarity">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="inspect-fill-clarity"></div>
                            </div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Contrast Quality</span>
                                <span id="inspect-val-contrast">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="inspect-fill-contrast"></div>
                            </div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Minutiae Visibility</span>
                                <span id="inspect-val-visibility">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="inspect-fill-visibility"></div>
                            </div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Fingerprint Sharpness</span>
                                <span id="inspect-val-sharpness">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="inspect-fill-sharpness"></div>
                            </div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Adhesion Quality</span>
                                <span id="inspect-val-adhesion">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="inspect-fill-adhesion"></div>
                            </div>
                        </div>
                        
                        <!-- AI Preliminary Results Container -->
                        <div id="inspect-ai-prelim-container"></div>
                    </div>
                </div>

                <!-- Bottom Section: Lab Analysis Notes -->
                <div class="analysis-notes-box">
                    <div class="analysis-notes-title">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Lab Analysis Notes
                    </div>

                    <div class="notes-content-wrap">
                        <div class="notes-label" id="inspect-remarks-label">Faculty Remarks:</div>
                        <div class="notes-text" id="inspect-remarks"></div>
                    </div>

                    <!-- Details Grid -->
                    <div class="info-details-grid">
                        <div class="info-detail-row">
                            <span class="info-detail-label">Trial ID:</span>
                            <span class="info-detail-value" id="inspect-trial-id"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Powder Type:</span>
                            <span class="info-detail-value" id="inspect-powder" style="text-transform: capitalize;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Surface Type:</span>
                            <span class="info-detail-value" id="inspect-surface" style="text-transform: capitalize;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Image Label:</span>
                            <span class="info-detail-value" id="inspect-label"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Status:</span>
                            <span class="info-detail-value" id="inspect-status"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">AI Preliminary Score:</span>
                            <span class="info-detail-value" id="inspect-ai-score"></span>
                        </div>
                        <div class="info-detail-row" id="row-inspect-faculty-score">
                            <span class="info-detail-label" id="inspect-faculty-score-label">Faculty Final Score:</span>
                            <span class="info-detail-value" id="inspect-faculty-score"></span>
                        </div>
                        <div class="info-detail-row" id="row-inspect-reviewer">
                            <span class="info-detail-label">Faculty Reviewer:</span>
                            <span class="info-detail-value" id="inspect-reviewer"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Image Path:</span>
                            <span class="info-detail-value" id="inspect-image-path" style="font-family: monospace; font-size: 0.75rem; color:#10b981; word-break: break-all;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Evaluation Date:</span>
                            <span class="info-detail-value" id="inspect-evaluation-date"></span>
                        </div>
                        <div class="info-detail-row" id="row-inspect-validated-at">
                            <span class="info-detail-label">Validation Date:</span>
                            <span class="info-detail-value" id="inspect-validated-at"></span>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;" class="no-print">
                    <button type="button" class="btn btn-secondary" onclick="closeInspectionModal()" style="flex:1; background:#334155; border-color:#334155; color:#fff;">Close</button>
                    <a id="printReportBtn" href="#" target="_blank" class="btn btn-primary" style="flex:1; background:#10b981; border-color:#10b981; color:#fff; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Print Report</a>
                    <a id="exportWordBtn" href="#" class="btn btn-primary" style="flex:1; background:#2b6cb0; border-color:#2b6cb0; color:#fff; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Export to Word</a>
                </div>
            </div>

            <div id="modalError" style="display:none; text-align:center; padding: 2rem; color: #ef4444; font-weight: 600;">
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
    // Set Print and Export button links dynamically
    document.getElementById('printReportBtn').href = 'print_fingerprint_report.php?test_id=' + row.id;
    document.getElementById('exportWordBtn').href = 'export_fingerprint_report_word.php?test_id=' + row.id;

    // Fill student chip (username/nickname from email or name)
    const username = row.student_email ? row.student_email.split('@')[0] : (row.student_name ? row.student_name.toLowerCase().replace(/\s+/g, '') : 'student');
    document.getElementById('inspect-student-chip').textContent = username;

    document.getElementById('inspect-trial-id').textContent = row.trial_id || 'TR-' + String(row.id).padStart(4, '0');
    document.getElementById('inspect-powder').textContent = row.powder_type || '';
    document.getElementById('inspect-surface').textContent = row.surface_type || '';
    document.getElementById('inspect-label').textContent = row.image_label || 'Untitled';
    
    // Evaluation Date mapping
    const evalDate = row.ai_evaluated_at ? new Date(row.ai_evaluated_at.replace(/-/g, "/")).toLocaleString() : (row.submitted_at ? new Date(row.submitted_at.replace(/-/g, "/")).toLocaleString() : '—');
    document.getElementById('inspect-evaluation-date').textContent = evalDate;

    // Image path
    document.getElementById('inspect-image-path').textContent = row.image_path ? 'uploads/' + row.image_path : '—';

    // Image viewer logic
    const img = document.getElementById('inspect-img');
    const imgWrapper = document.getElementById('inspect-img-wrapper');
    const imgMissing = document.getElementById('inspect-img-missing');
    
    if (row.image_path && row.image_exists) {
        img.src = '../view_fingerprint.php?test_id=' + row.id;
        imgWrapper.style.display = 'flex';
        imgMissing.style.display = 'none';
    } else {
        imgWrapper.style.display = 'none';
        imgMissing.style.display = 'block';
    }

    // AI Preliminary Result Metrics
    const aiAccuracy = row.ai_accuracy_score !== null ? parseFloat(row.ai_accuracy_score) : (row.accuracy_score !== null ? parseFloat(row.accuracy_score) : 0);
    const aiClarity = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score) : 0;
    const aiVisibility = row.visibility_score !== null ? parseFloat(row.visibility_score) : 0;
    const aiAdhesion = row.adhesion_score !== null ? parseFloat(row.adhesion_score) : 0;
    const aiContrast = row.contrast_score !== null ? parseFloat(row.contrast_score) : 0;

    // Faculty Final Evaluation Metrics (fallback to AI scores for older approved records)
    const hasFacultyScores = row.faculty_final_score !== null;
    const fAccuracy = hasFacultyScores ? parseFloat(row.faculty_final_score) : aiAccuracy;
    const fClarity = hasFacultyScores && row.faculty_ridge_clarity_score !== null ? parseFloat(row.faculty_ridge_clarity_score) : aiClarity;
    const fVisibility = hasFacultyScores && row.faculty_visibility_score !== null ? parseFloat(row.faculty_visibility_score) : aiVisibility;
    const fAdhesion = hasFacultyScores && row.faculty_adhesion_score !== null ? parseFloat(row.faculty_adhesion_score) : aiAdhesion;
    const fContrast = hasFacultyScores && row.faculty_contrast_score !== null ? parseFloat(row.faculty_contrast_score) : aiContrast;

    // Render comparison list or details
    const aiDetailsHtml = `
        <div style="margin-top: 1rem; border-top: 1px solid #27354f; padding-top: 0.85rem;">
            <div style="font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem;">AI Preliminary Results (Read-Only)</div>
            <div style="display: flex; flex-direction: column; gap: 4px; font-size: 0.8rem; color: #cbd5e1;">
                <div style="display: flex; justify-content: space-between;"><span>AI Accuracy:</span> <strong>${aiAccuracy > 0 ? aiAccuracy.toFixed(1) + '%' : '—'}</strong></div>
                <div style="display: flex; justify-content: space-between;"><span>AI Ridge Clarity:</span> <span>${aiClarity > 0 ? aiClarity.toFixed(1) + '%' : '—'}</span></div>
                <div style="display: flex; justify-content: space-between;"><span>AI Visibility:</span> <span>${aiVisibility > 0 ? aiVisibility.toFixed(1) + '%' : '—'}</span></div>
                <div style="display: flex; justify-content: space-between;"><span>AI Adhesion:</span> <span>${aiAdhesion > 0 ? aiAdhesion.toFixed(1) + '%' : '—'}</span></div>
                <div style="display: flex; justify-content: space-between;"><span>AI Contrast:</span> <span>${aiContrast > 0 ? aiContrast.toFixed(1) + '%' : '—'}</span></div>
            </div>
        </div>
    `;

    const extraAiContainer = document.getElementById('inspect-ai-prelim-container');
    if (extraAiContainer) {
        extraAiContainer.innerHTML = aiDetailsHtml;
    }

    // Update main progress bars to show Faculty Final score if approved, otherwise show placeholder or hide
    const overallScoreHuge = document.getElementById('inspect-val-accuracy-huge');
    const badgeEl = document.getElementById('inspect-val-quality-badge');
    const badgeDesc = document.querySelector('.quality-badge-desc');

    if (row.status === 'approved') {
        overallScoreHuge.textContent = Math.round(fAccuracy) + '%';
        badgeEl.textContent = 'APPROVED';
        badgeEl.style.color = '#10b981';
        badgeEl.style.borderColor = 'rgba(16, 185, 129, 0.25)';
        badgeEl.style.background = 'rgba(16, 185, 129, 0.12)';
        if (badgeDesc) badgeDesc.textContent = 'Faculty Approved Official Score';

        // Set text labels
        document.getElementById('inspect-val-clarity').textContent = fClarity > 0 ? fClarity.toFixed(1) + '%' : '—';
        document.getElementById('inspect-val-contrast').textContent = fContrast > 0 ? fContrast.toFixed(1) + '%' : '—';
        document.getElementById('inspect-val-visibility').textContent = fVisibility > 0 ? fVisibility.toFixed(1) + '%' : '—';
        document.getElementById('inspect-val-sharpness').textContent = fClarity > 0 ? fClarity.toFixed(1) + '%' : '—';
        document.getElementById('inspect-val-adhesion').textContent = fAdhesion > 0 ? fAdhesion.toFixed(1) + '%' : '—';

        // Set progress bar widths
        document.getElementById('inspect-fill-clarity').style.width = fClarity + '%';
        document.getElementById('inspect-fill-contrast').style.width = fContrast + '%';
        document.getElementById('inspect-fill-visibility').style.width = fVisibility + '%';
        document.getElementById('inspect-fill-sharpness').style.width = fClarity + '%';
        document.getElementById('inspect-fill-adhesion').style.width = fAdhesion + '%';
        
    } else {
        overallScoreHuge.textContent = '—';
        
        if (row.status === 'pending_validation') {
            badgeEl.textContent = 'AWAITING REVIEW';
            badgeEl.style.color = '#f59e0b';
            badgeEl.style.borderColor = 'rgba(245, 158, 11, 0.25)';
            badgeEl.style.background = 'rgba(245, 158, 11, 0.12)';
            if (badgeDesc) badgeDesc.textContent = 'Awaiting Faculty Validation';
        } else if (row.status === 'rejected') {
            badgeEl.textContent = 'REJECTED';
            badgeEl.style.color = '#ef4444';
            badgeEl.style.borderColor = 'rgba(239, 68, 68, 0.25)';
            badgeEl.style.background = 'rgba(239, 68, 68, 0.12)';
            if (badgeDesc) badgeDesc.textContent = 'Rejected';
        } else if (row.status === 'needs_revision') {
            badgeEl.textContent = 'REVISION NEEDED';
            badgeEl.style.color = '#3b82f6';
            badgeEl.style.borderColor = 'rgba(59, 130, 246, 0.25)';
            badgeEl.style.background = 'rgba(59, 130, 246, 0.12)';
            if (badgeDesc) badgeDesc.textContent = 'Needs Revision';
        }

        // Set progress bars to 0% as they are not approved yet
        document.getElementById('inspect-val-clarity').textContent = '—';
        document.getElementById('inspect-val-contrast').textContent = '—';
        document.getElementById('inspect-val-visibility').textContent = '—';
        document.getElementById('inspect-val-sharpness').textContent = '—';
        document.getElementById('inspect-val-adhesion').textContent = '—';

        document.getElementById('inspect-fill-clarity').style.width = '0%';
        document.getElementById('inspect-fill-contrast').style.width = '0%';
        document.getElementById('inspect-fill-visibility').style.width = '0%';
        document.getElementById('inspect-fill-sharpness').style.width = '0%';
        document.getElementById('inspect-fill-adhesion').style.width = '0%';
    }

    document.getElementById('inspect-ai-score').textContent = aiAccuracy > 0 ? aiAccuracy.toFixed(1) + '%' : 'Awaiting AI Evaluation';

    // Conditional elements based on status
    const statusVal = document.getElementById('inspect-status');
    const reviewerRow = document.getElementById('row-inspect-reviewer');
    const validatedAtRow = document.getElementById('row-inspect-validated-at');
    const remarksRow = document.getElementById('inspect-remarks');
    const remarksLabel = document.getElementById('inspect-remarks-label');
    const facultyScoreRow = document.getElementById('row-inspect-faculty-score');

    if (row.status === 'pending_validation') {
        statusVal.innerHTML = '<span class="badge badge-pending_validation">Pending Validation</span>';
        reviewerRow.style.display = 'none';
        validatedAtRow.style.display = 'none';
        facultyScoreRow.style.display = 'flex';
        
        document.getElementById('inspect-faculty-score-label').textContent = 'Faculty Final Score:';
        document.getElementById('inspect-faculty-score').textContent = 'Awaiting Faculty Validation';
        
        remarksLabel.textContent = 'Notes:';
        remarksRow.innerHTML = 'This record is still awaiting faculty review.';
    } else {
        reviewerRow.style.display = 'flex';
        validatedAtRow.style.display = 'flex';
        
        document.getElementById('inspect-reviewer').textContent = row.faculty_reviewer || 'Faculty Reviewer';
        document.getElementById('inspect-validated-at').textContent = row.validated_at ? new Date(row.validated_at.replace(/-/g, "/")).toLocaleString() : '—';
        
        remarksLabel.textContent = 'Faculty Remarks:';
        remarksRow.innerHTML = row.faculty_remarks ? escapeHtml(row.faculty_remarks).replace(/\n/g, '<br>') : 'No remarks provided.';

        if (row.status === 'approved') {
            statusVal.innerHTML = '<span class="badge badge-approved">Approved</span>';
            facultyScoreRow.style.display = 'flex';
            document.getElementById('inspect-faculty-score-label').textContent = 'Faculty Final Score:';
            document.getElementById('inspect-faculty-score').textContent = fAccuracy.toFixed(1) + '%';
        } else if (row.status === 'rejected') {
            statusVal.innerHTML = '<span class="badge badge-rejected">Rejected</span>';
            facultyScoreRow.style.display = 'none';
            remarksRow.innerHTML += `<div style="margin-top: 12px; padding: 10px 14px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; border-radius: 6px; color: #fca5a5; font-size: 0.82rem;">
                <strong>Action Needed:</strong> Upload a clearer fingerprint image for validation.
            </div>`;
        } else if (row.status === 'needs_revision') {
            statusVal.innerHTML = '<span class="badge badge-needs_revision">Needs Revision</span>';
            facultyScoreRow.style.display = 'none';
            remarksRow.innerHTML += `<div style="margin-top: 12px; padding: 10px 14px; background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; border-radius: 6px; color: #93c5fd; font-size: 0.82rem;">
                <strong>Action Needed:</strong> Revise the details or re-upload a clearer image according to feedback.
            </div>`;
        }
    }
}

// printTrialDetails removed because print is now handled by dedicated printable page
</script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
