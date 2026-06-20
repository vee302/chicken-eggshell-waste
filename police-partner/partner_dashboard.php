<?php
// partner/partner_dashboard.php — Alumni / Police Partner Main Dashboard
require_once '../config.php';
require_once 'auth.php';
check_partner_auth();

$active_page = 'dashboard';
$partner_name = $_SESSION['user_name'] ?? 'Partner';
$partner_id = $_SESSION['user_id'] ?? 0;

// Metrics Calculations
$total_approved_reports = 0;
$total_trials = 0;
$approved_trials = 0;
$success_rate = 0;
$best_surface = 'N/A';
$eggshell_avg = 0;
$commercial_avg = 0;

try {
    // 1. Total Approved Reports
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports");
    $stmt->execute();
    $total_approved_reports = (int)$stmt->fetchColumn();

    // 2. Total fingerprint trials (all trials)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests");
    $stmt->execute();
    $total_trials = (int)$stmt->fetchColumn();

    // 3. Approved trials
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fingerprint_tests WHERE status='approved'");
    $stmt->execute();
    $approved_trials = (int)$stmt->fetchColumn();

    // 4. Overall Success Rate
    $success_rate = $total_trials ? round(($approved_trials / $total_trials) * 100, 1) : 0;

    // 5. Best Performing Surface (among approved trials)
    $stmt = $pdo->prepare("
        SELECT surface_type, AVG(accuracy_score) as avg_acc 
        FROM fingerprint_tests 
        WHERE status='approved' AND accuracy_score IS NOT NULL
        GROUP BY surface_type 
        ORDER BY avg_acc DESC LIMIT 1
    ");
    $stmt->execute();
    $best_surface_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($best_surface_row) {
        $best_surface = ucfirst($best_surface_row['surface_type']);
    }

    // 6. Eggshell Powder Average Accuracy
    $stmt = $pdo->prepare("SELECT ROUND(AVG(accuracy_score),1) FROM fingerprint_tests WHERE powder_type='eggshell' AND status='approved'");
    $stmt->execute();
    $eggshell_avg = $stmt->fetchColumn() ?? 0;

    // 7. Commercial Powder Average Accuracy
    $stmt = $pdo->prepare("SELECT ROUND(AVG(accuracy_score),1) FROM fingerprint_tests WHERE powder_type='commercial' AND status='approved'");
    $stmt->execute();
    $commercial_avg = $stmt->fetchColumn() ?? 0;

} catch (PDOException $e) {}

// Fetch 5 most recent approved trials with joins for student name and validator name
$recent_tests = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            ft.*, 
            student.full_name AS student_name, 
            faculty.full_name AS faculty_validator,
            fr.remarks AS validation_remarks
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.decision = 'approved'
        WHERE ft.status = 'approved' 
        ORDER BY ft.validated_at DESC, ft.id DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Dashboard — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .role-badge-partner {
            background: rgba(107, 143, 113, 0.12);
            color: var(--dark-green);
            border: 1px solid rgba(107, 143, 113, 0.25);
        }
        .partner-description-card {
            border-left: 4px solid var(--soft-green);
            background: rgba(210, 226, 213, 0.15);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .partner-description-card h3 {
            color: var(--dark-green);
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        .partner-description-card p {
            font-size: 0.92rem;
            line-height: 1.6;
            color: #555;
        }
        .notice-banner {
            background-color: rgba(45, 106, 79, 0.08);
            border-left: 4px solid var(--medium-green);
            color: var(--dark-green);
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .badge-approved {
            background: rgba(82, 183, 136, 0.15);
            color: #2d6a4f;
            border: 1px solid rgba(82, 183, 136, 0.25);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-block;
        }
        .detail-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(27, 67, 50, 0.45);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .detail-overlay.open {
            display: flex;
        }
        .detail-modal {
            background: #fff;
            border-radius: 16px;
            max-width: 600px;
            width: 92%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
            border: 1px solid rgba(27,67,50,0.1);
        }
        .detail-modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--dark-green);
            color: #fff;
        }
        .detail-modal-header h3 {
            color: #fff;
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
        }
        .detail-modal-body {
            padding: 1.5rem;
        }
        .detail-row {
            display: flex;
            gap: .5rem;
            margin-bottom: .75rem;
            font-size: .85rem;
        }
        .detail-label {
            min-width: 160px;
            font-weight: 600;
            color: var(--dark-green);
        }
        .detail-value {
            color: #5f5f5f;
            flex: 1;
        }
        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: #fff;
            opacity: 0.8;
            line-height: 1;
        }
        .modal-close-btn:hover {
            opacity: 1;
        }
        .section-divider {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6B8F71;
            border-bottom: 1px solid #D2E2D5;
            padding-bottom: .35rem;
            margin: 1.25rem 0 .6rem;
        }
        .score-box {
            background: var(--cream);
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 1rem;
            border: 1px solid rgba(45,106,79,0.08);
        }
        .score-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--medium-green);
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .score-values {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            text-align: center;
        }
        .score-val {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--dark-green);
        }
        .score-lbl {
            font-size: 0.65rem;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="student-wrapper">
    <!-- Mobile overlay -->
    <div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;transition:opacity .3s;"
         onclick="this.style.display='none';document.getElementById('sidebar').classList.remove('active')"></div>

    <!-- SIDEBAR -->
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
                    <h2>Alumni / Police Partner Dashboard</h2>
                </div>
            </div>
            <div class="header-right">
                <div class="header-role-chip role-badge-partner">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                    </svg>
                    Alumni / Police Partner
                </div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Dashboard Overview</h1>
                    <p>Approved Performance Data, Reports, and Field Feedback</p>
                </div>
            </div>

            <!-- Notice Banner -->
            <div class="notice-banner">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>Only faculty-approved records are visible in this portal.</span>
            </div>

            <!-- Partner Role Introduction -->
            <div class="partner-description-card">
                <h3>Stakeholder Monitoring Portal</h3>
                <p>Welcome, <?= htmlspecialchars($partner_name) ?>. This portal provides secure, read-only monitoring access to approved fingerprint powder development reports, comparative evaluation statistics, and surface compatibility trials. Use this interface to evaluate research data and log observations directly from active field usage.</p>
            </div>

            <!-- STATS GRID -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Approved Reports</span>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $total_approved_reports ?></div>
                    <div class="stat-desc">Faculty validated report sheets</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Approved Trials</span>
                        <div class="stat-icon" style="background:rgba(82,183,136,.12);color:#2d6a4f;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color:#2d6a4f;"><?= $approved_trials ?></div>
                    <div class="stat-desc">Approved forensic tests logged</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Overall Success Rate</span>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $success_rate ?>%</div>
                    <div class="stat-desc">Approved out of total trials</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Best Surface</span>
                        <div class="stat-icon" style="background:rgba(42,111,151,.1);color:#2a6f97;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color:#2a6f97;"><?= htmlspecialchars($best_surface) ?></div>
                    <div class="stat-desc">Highest average accuracy</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Eggshell Avg Accuracy</span>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="12" cy="11" r="3"></circle>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $eggshell_avg ?>%</div>
                    <div class="stat-desc">Eggshell-Based Powder trials</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Commercial Avg Accuracy</span>
                        <div class="stat-icon" style="color:var(--gray);">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="12" cy="11" r="3"></circle>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color:var(--gray);"><?= $commercial_avg ?>%</div>
                    <div class="stat-desc">Commercial Powder trials</div>
                </div>
            </div>

            <!-- RECENT SUBMISSIONS REFERENCE -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Recent Approved Trials
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Trial ID</th>
                                <th>Student Submitter</th>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Accuracy</th>
                                <th>Status</th>
                                <th>Faculty Reviewer</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recent_tests)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#6c757d;padding:2rem;">
                                    No approved trials found in system.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_tests as $row): ?>
                            <tr>
                                <td style="font-weight:700; color:var(--dark-green);"><?= htmlspecialchars($row['trial_id'] ?: 'TR-'.str_pad($row['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td><?= htmlspecialchars($row['student_name']) ?></td>
                                <td style="text-transform:capitalize;"><?= $row['powder_type'] === 'eggshell' ? 'Eggshell-Based' : 'Commercial' ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['surface_type']) ?></td>
                                <td><strong><?= number_format($row['accuracy_score'], 1) ?>%</strong></td>
                                <td><span class="badge-approved">Approved</span></td>
                                <td><?= htmlspecialchars($row['faculty_validator'] ?: 'Faculty Validator') ?></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-secondary btn-sm" onclick="openDetailsModal(<?= htmlspecialchars(json_encode($row)) ?>)">View Details</button>
                                </td>
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
<div class="detail-overlay" id="detailsModal">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3>Trial Record Details: <span id="modalTrialId">TR-0000</span></h3>
            <button class="modal-close-btn" onclick="closeDetailsModal()">&times;</button>
        </div>
        <div class="detail-modal-body">
            <p class="section-divider">Forensic Submission Details</p>
            <div class="detail-row"><span class="detail-label">Student Submitter</span><span class="detail-value" id="modalStudent"></span></div>
            <div class="detail-row"><span class="detail-label">Powder Type Used</span><span class="detail-value" id="modalPowder" style="text-transform: capitalize; font-weight: 600;"></span></div>
            <div class="detail-row"><span class="detail-label">Surface Material Type</span><span class="detail-value" id="modalSurface" style="text-transform: capitalize; font-weight: 600;"></span></div>
            <div class="detail-row"><span class="detail-label">Image Label</span><span class="detail-value" id="modalLabel"></span></div>
            <div class="detail-row"><span class="detail-label">Notes from Submission</span><span class="detail-value" id="modalNotes"></span></div>
            <div class="detail-row"><span class="detail-label">Date Submitted</span><span class="detail-value" id="modalDateSubmitted"></span></div>

            <p class="section-divider">Fingerprint Image Asset</p>
            <div style="text-align:center; margin-bottom:1rem; border:1px solid #e9ecef; padding:10px; border-radius:8px; background:#fafafa;">
                <img id="modalImage" src="" style="max-height:220px; max-width:100%; object-fit:contain; border-radius:6px; border:1px solid #ddd;" alt="Fingerprint Image Asset">
                <div id="modalImageFallback" style="padding:2rem; background:#f4f6f0; border-radius:6px; font-weight:600; color:var(--gray); display:none;">No Image Uploaded</div>
            </div>

            <p class="section-divider">Clarity & Adhesion Scores</p>
            <div class="score-box">
                <div class="score-title">Individual Forensic Performance Metrics</div>
                <div class="score-values">
                    <div>
                        <div class="score-val" id="modalClarity">—</div>
                        <div class="score-lbl">Clarity</div>
                    </div>
                    <div>
                        <div class="score-val" id="modalVisibility">—</div>
                        <div class="score-lbl">Visibility</div>
                    </div>
                    <div>
                        <div class="score-val" id="modalAdhesion">—</div>
                        <div class="score-lbl">Adhesion</div>
                    </div>
                    <div>
                        <div class="score-val" id="modalContrast">—</div>
                        <div class="score-lbl">Contrast</div>
                    </div>
                </div>
            </div>
            <div class="detail-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green);">
                <span class="detail-label" style="font-weight: 700;">Composite Accuracy Score</span>
                <span class="detail-value" id="modalAccuracy" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;">—</span>
            </div>

            <p class="section-divider">Validation Details</p>
            <div class="detail-row"><span class="detail-label">Validation Status</span><span class="detail-value"><span class="badge-approved">Approved</span></span></div>
            <div class="detail-row"><span class="detail-label">Faculty Validator</span><span class="detail-value" id="modalValidator" style="font-weight: 600;"></span></div>
            <div class="detail-row"><span class="detail-label">Validation Date</span><span class="detail-value" id="modalDateValidated"></span></div>
            <div class="detail-row"><span class="detail-label">Advisor Remarks</span><span class="detail-value" id="modalRemarks" style="font-style: italic;"></span></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarCollapse");
        const overlay = document.getElementById("sidebarOverlay");

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                sidebar.classList.toggle("active");
                if (overlay) overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";
            });

            document.addEventListener("click", (e) => {
                if (window.innerWidth <= 992 && sidebar.classList.contains("active")) {
                    if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                        sidebar.classList.remove("active");
                        if (overlay) overlay.style.display = "none";
                    }
                }
            });
        }

        const detailsModal = document.getElementById("detailsModal");
        if (detailsModal) {
            detailsModal.addEventListener("click", e => {
                if (e.target === detailsModal) {
                    closeDetailsModal();
                }
            });
        }
    });

    function openDetailsModal(row) {
        document.getElementById("modalTrialId").textContent = row.trial_id || ('TR-' + row.id.toString().padStart(4, '0'));
        document.getElementById("modalStudent").textContent = row.student_name || 'N/A';
        document.getElementById("modalPowder").textContent = row.powder_type === 'eggshell' ? 'Eggshell-Based Powder' : 'Commercial Powder';
        document.getElementById("modalSurface").textContent = row.surface_type || 'N/A';
        document.getElementById("modalLabel").textContent = row.image_label || 'Untitled';
        document.getElementById("modalNotes").textContent = row.notes || 'No observations recorded.';
        document.getElementById("modalDateSubmitted").textContent = row.submitted_at || '—';

        // Image loading
        const modalImg = document.getElementById("modalImage");
        const modalFallback = document.getElementById("modalImageFallback");
        if (row.image_path) {
            modalImg.src = "../uploads/fingerprints/" + row.image_path;
            modalImg.style.display = "inline-block";
            modalFallback.style.display = "none";
        } else {
            modalImg.style.display = "none";
            modalFallback.style.display = "block";
        }

        // Scores
        document.getElementById("modalClarity").textContent = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score).toFixed(1) + '%' : '—';
        document.getElementById("modalVisibility").textContent = row.visibility_score !== null ? parseFloat(row.visibility_score).toFixed(1) + '%' : '—';
        document.getElementById("modalAdhesion").textContent = row.adhesion_score !== null ? parseFloat(row.adhesion_score).toFixed(1) + '%' : '—';
        document.getElementById("modalContrast").textContent = row.contrast_score !== null ? parseFloat(row.contrast_score).toFixed(1) + '%' : '—';
        document.getElementById("modalAccuracy").textContent = row.accuracy_score !== null ? parseFloat(row.accuracy_score).toFixed(1) + '%' : '—';

        // Validator details
        document.getElementById("modalValidator").textContent = row.faculty_validator || 'Awaiting validation';
        document.getElementById("modalDateValidated").textContent = row.validated_at || '—';
        document.getElementById("modalRemarks").textContent = row.validation_remarks || 'No advisor remarks recorded.';

        document.getElementById("detailsModal").classList.add("open");
    }

    function closeDetailsModal() {
        document.getElementById("detailsModal").classList.remove("open");
    }
</script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
