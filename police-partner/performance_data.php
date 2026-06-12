<?php
// partner/performance_data.php — Alumni / Police Partner Performance Data Analysis
require_once '../config.php';
require_once 'auth.php';
check_partner_auth();

$active_page = 'performance_data';
$partner_name = $_SESSION['user_name'] ?? 'Partner';
$partner_id = $_SESSION['user_id'] ?? 0;

// Filters
$filter_powder = $_GET['powder'] ?? '';
$filter_surface = $_GET['surface'] ?? '';
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';

// Build Query
$where = ["ft.status = 'approved'"];
$params = [];

if (!empty($filter_powder)) {
    $where[] = "ft.powder_type = ?";
    $params[] = $filter_powder;
}
if (!empty($filter_surface)) {
    $where[] = "ft.surface_type = ?";
    $params[] = $filter_surface;
}
if (!empty($filter_from)) {
    $where[] = "DATE(ft.validated_at) >= ?";
    $params[] = $filter_from;
}
if (!empty($filter_to)) {
    $where[] = "DATE(ft.validated_at) <= ?";
    $params[] = $filter_to;
}

$trials = [];
$avg_accuracy = 0;
$avg_clarity = 0;
$avg_visibility = 0;
$avg_adhesion = 0;

try {
    $sql = "
        SELECT 
            ft.*, 
            student.full_name AS student_name, 
            faculty.full_name AS faculty_validator,
            fr.remarks AS validation_remarks
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.decision = 'approved'
        WHERE " . implode(" AND ", $where) . "
        ORDER BY ft.validated_at DESC, ft.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculations
    $total_count = count($trials);
    if ($total_count > 0) {
        $sum_accuracy = 0;
        $sum_clarity = 0;
        $sum_visibility = 0;
        $sum_adhesion = 0;
        
        $count_accuracy = 0;
        $count_clarity = 0;
        $count_visibility = 0;
        $count_adhesion = 0;
        
        foreach ($trials as $t) {
            if ($t['accuracy_score'] !== null) {
                $sum_accuracy += $t['accuracy_score'];
                $count_accuracy++;
            }
            if ($t['ridge_clarity_score'] !== null) {
                $sum_clarity += $t['ridge_clarity_score'];
                $count_clarity++;
            }
            if ($t['visibility_score'] !== null) {
                $sum_visibility += $t['visibility_score'];
                $count_visibility++;
            }
            if ($t['adhesion_score'] !== null) {
                $sum_adhesion += $t['adhesion_score'];
                $count_adhesion++;
            }
        }
        
        $avg_accuracy = $count_accuracy ? ($sum_accuracy / $count_accuracy) : 0;
        $avg_clarity = $count_clarity ? ($sum_clarity / $count_clarity) : 0;
        $avg_visibility = $count_visibility ? ($sum_visibility / $count_visibility) : 0;
        $avg_adhesion = $count_adhesion ? ($sum_adhesion / $count_adhesion) : 0;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Data — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .filter-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(27,67,50,.04);
            margin-bottom: 2rem;
            border: 1px solid rgba(27,67,50,0.05);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .filter-item label {
            font-size: .75rem;
            font-weight: 700;
            color: #1b4332;
            text-transform: uppercase;
            letter-spacing: .3px;
            display: block;
            margin-bottom: .3rem;
        }
        .filter-item select, .filter-item input {
            width: 100%;
            padding: .55rem 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: .85rem;
            background: #fff;
            color: #212529;
            outline: none;
        }
        .filter-item select:focus, .filter-item input:focus {
            border-color: #2d6a4f;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, .12);
        }
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
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
            gap: 8px;
            text-align: center;
        }
        .score-val {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--dark-green);
        }
        .score-lbl {
            font-size: 0.62rem;
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
                    <h2>Alumni / Police Partner Portal</h2>
                </div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Performance Data</h1>
                    <p>Analyze the granular clarity, visibility, and adhesion levels of approved trials.</p>
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

            <!-- FILTER CARD -->
            <div class="filter-card">
                <form method="GET" action="performance_data.php">
                    <div class="filter-grid">
                        <div class="filter-item">
                            <label for="powder">Powder Type</label>
                            <select name="powder" id="powder">
                                <option value="">All Powders</option>
                                <option value="eggshell" <?= $filter_powder === 'eggshell' ? 'selected' : '' ?>>Eggshell-Based Powder</option>
                                <option value="commercial" <?= $filter_powder === 'commercial' ? 'selected' : '' ?>>Commercial Powder</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label for="surface">Surface Material</label>
                            <select name="surface" id="surface">
                                <option value="">All Surfaces</option>
                                <?php foreach (['glass','paper','wood','plastic','metal','ceramic','fabric'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $filter_surface === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label for="from">Validated Date From</label>
                            <input type="date" name="from" id="from" value="<?= htmlspecialchars($filter_from) ?>">
                        </div>
                        <div class="filter-item">
                            <label for="to">Validated Date To</label>
                            <input type="date" name="to" id="to" value="<?= htmlspecialchars($filter_to) ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <?php if (!empty($filter_powder) || !empty($filter_surface) || !empty($filter_from) || !empty($filter_to)): ?>
                            <a href="performance_data.php" class="btn btn-secondary" style="border:none; line-height: 2.2;">Clear Filters</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- OVERALL AVERAGES SECTION -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Matching Records</span>
                    </div>
                    <div class="stat-value"><?= count($trials) ?></div>
                    <div class="stat-desc">Approved trials found</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Average Ridge Clarity</span>
                    </div>
                    <div class="stat-value" style="color: var(--dark-green);"><?= number_format($avg_clarity, 1) ?>%</div>
                    <div class="stat-desc">Biometric detail distinction</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Average Visibility</span>
                    </div>
                    <div class="stat-value" style="color: var(--dark-green);"><?= number_format($avg_visibility, 1) ?>%</div>
                    <div class="stat-desc">Contrast against surface</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Average Adhesion</span>
                    </div>
                    <div class="stat-value" style="color: var(--dark-green);"><?= number_format($avg_adhesion, 1) ?>%</div>
                    <div class="stat-desc">Powder stickiness quality</div>
                </div>
                <div class="stat-card" style="grid-column: span 2; background: var(--cream); border: 1px solid rgba(45,106,79,0.15);">
                    <div class="stat-header">
                        <span class="stat-title" style="color: var(--dark-green); font-weight: 700;">Overall Composite Accuracy Average</span>
                    </div>
                    <div class="stat-value" style="color: #2d6a4f; font-size: 1.8rem; margin-top: 4px;"><?= number_format($avg_accuracy, 1) ?>%</div>
                    <div class="stat-desc">Combined trial score calculation</div>
                </div>
            </div>

            <!-- TABLE OF TRIALS -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <line x1="3" y1="9" x2="21" y2="9"/>
                            <line x1="9" y1="21" x2="9" y2="9"/>
                        </svg>
                        Performance Metrics Reference
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Trial ID</th>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Clarity</th>
                                <th>Visibility</th>
                                <th>Adhesion</th>
                                <th>Accuracy</th>
                                <th>Validated Date</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($trials)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center;color:#6c757d;padding:2rem;">
                                    No approved trials match the selected filter parameters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trials as $row): ?>
                            <tr>
                                <td style="font-weight:700; color:var(--dark-green);"><?= htmlspecialchars($row['trial_id'] ?: 'TR-'.str_pad($row['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td style="text-transform:capitalize;"><?= $row['powder_type'] === 'eggshell' ? 'Eggshell-Based' : 'Commercial' ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['surface_type']) ?></td>
                                <td><?= $row['ridge_clarity_score'] !== null ? number_format($row['ridge_clarity_score'], 1).'%' : '—' ?></td>
                                <td><?= $row['visibility_score'] !== null ? number_format($row['visibility_score'], 1).'%' : '—' ?></td>
                                <td><?= $row['adhesion_score'] !== null ? number_format($row['adhesion_score'], 1).'%' : '—' ?></td>
                                <td><strong><?= number_format($row['accuracy_score'], 1) ?>%</strong></td>
                                <td><?= $row['validated_at'] ? date('M d, Y', strtotime($row['validated_at'])) : '—' ?></td>
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
</body>
</html>
