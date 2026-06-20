<?php
// partner/approved_reports.php — Alumni / Police Partner Approved Reports & Trial Submissions
require_once '../config.php';
require_once 'auth.php';
check_partner_auth();

$active_page = 'approved_reports';
$partner_name = $_SESSION['user_name'] ?? 'Partner';
$partner_id = $_SESSION['user_id'] ?? 0;

// Handle Print View separately
if (isset($_GET['print']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['id'];
    
    // Fetch report info
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name AS generated_by_name 
        FROM reports r 
        LEFT JOIN users u ON r.generated_by = u.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        die("Report not found.");
    }
    
    // Parse filters
    $powder_filter = '';
    $surface_filter = '';
    $student_filter = '';
    $from_filter = '';
    $to_filter = '';
    
    if (!empty($report['report_filter'])) {
        $filter_data = json_decode($report['report_filter'], true);
        if (is_array($filter_data)) {
            if (isset($filter_data['powder'])) $powder_filter = $filter_data['powder'];
            elseif (isset($filter_data['powder_type'])) $powder_filter = $filter_data['powder_type'];
            
            if (isset($filter_data['surface'])) $surface_filter = $filter_data['surface'];
            elseif (isset($filter_data['surface_type'])) $surface_filter = $filter_data['surface_type'];
            
            if (isset($filter_data['student_id'])) $student_filter = $filter_data['student_id'];
            if (isset($filter_data['from'])) $from_filter = $filter_data['from'];
            if (isset($filter_data['to'])) $to_filter = $filter_data['to'];
        }
    }
    
    // Query approved trials matching filters
    $where = ["ft.status = 'approved'"];
    $params = [];
    
    if (!empty($powder_filter) && $powder_filter !== 'all') {
        $where[] = "ft.powder_type = ?";
        $params[] = $powder_filter;
    }
    if (!empty($surface_filter) && $surface_filter !== 'all') {
        $where[] = "ft.surface_type = ?";
        $params[] = $surface_filter;
    }
    if (!empty($student_filter)) {
        $where[] = "ft.student_id = ?";
        $params[] = $student_filter;
    }
    if (!empty($from_filter)) {
        $where[] = "DATE(ft.submitted_at) >= ?";
        $params[] = $from_filter;
    }
    if (!empty($to_filter)) {
        $where[] = "DATE(ft.submitted_at) <= ?";
        $params[] = $to_filter;
    }
    
    $sql = "
        SELECT 
            ft.*, 
            student.full_name AS student_name, 
            faculty.full_name AS faculty_validator
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY ft.validated_at DESC, ft.id DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $matching_trials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Print Report — Green Forensics</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; color: #111; margin: 40px; background: #fff; font-size: 11pt; }
            .print-header { text-align: center; border-bottom: 3px double #1b4332; padding-bottom: 15px; margin-bottom: 30px; }
            .print-header h1 { color: #1b4332; font-size: 22pt; margin: 0 0 5px 0; font-weight: 800; }
            .print-header p { color: #666; font-size: 10pt; margin: 0; text-transform: uppercase; letter-spacing: 1.5px; }
            .report-title-section { margin-bottom: 25px; }
            .report-title-section h2 { font-size: 15pt; color: #2d6a4f; margin: 0 0 10px 0; font-weight: 700; }
            .metadata-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; background: #f8faf7; padding: 15px; border-radius: 8px; border: 1px solid #e2e8e0; margin-bottom: 30px; }
            .metadata-item { font-size: 10pt; }
            .metadata-label { font-weight: 700; color: #1b4332; }
            .metadata-value { color: #444; }
            .results-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
            .results-table th, .results-table td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; font-size: 9.5pt; }
            .results-table th { background: #1b4332; color: #fff; font-weight: 700; text-transform: uppercase; font-size: 8.5pt; }
            .results-table tr:nth-child(even) { background: #f9f9f9; }
            .print-footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 50px; font-size: 9pt; color: #666; display: flex; justify-content: space-between; }
            @media print {
                body { margin: 20px; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body onload="window.print();">
        <div class="print-header">
            <h1>Green Forensics Evaluating System</h1>
            <p>Official Validation &amp; Comparative Study Report</p>
        </div>

        <div class="report-title-section">
            <h2>Report Title: <?= htmlspecialchars($report['report_title']) ?></h2>
        </div>

        <div class="metadata-grid">
            <div class="metadata-item">
                <span class="metadata-label">Date Generated:</span>
                <span class="metadata-value"><?= date('F d, Y h:i A', strtotime($report['generated_at'])) ?></span>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Compiled By:</span>
                <span class="metadata-value"><?= htmlspecialchars($report['generated_by_name'] ?? 'System') ?></span>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Powder Type Filter:</span>
                <span class="metadata-value" style="text-transform: capitalize;"><?= $powder_filter ? htmlspecialchars($powder_filter) : 'All Powders' ?></span>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Surface Material Filter:</span>
                <span class="metadata-value" style="text-transform: capitalize;"><?= $surface_filter ? htmlspecialchars($surface_filter) : 'All Surfaces' ?></span>
            </div>
        </div>

        <h3>Approved Trials &amp; Performance Metrics</h3>
        <table class="results-table">
            <thead>
                <tr>
                    <th>Trial ID</th>
                    <th>Powder Type</th>
                    <th>Surface Type</th>
                    <th>Accuracy Result</th>
                    <th>Faculty Validator</th>
                    <th>Date Validated</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($matching_trials)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666;">No approved records matching report criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($matching_trials as $trial): ?>
                        <tr>
                            <td style="font-weight: 700; color: #1b4332;"><?= htmlspecialchars($trial['trial_id'] ?: 'TR-'.str_pad($trial['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                            <td style="text-transform: capitalize;"><?= htmlspecialchars($trial['powder_type']) ?></td>
                            <td style="text-transform: capitalize;"><?= htmlspecialchars($trial['surface_type']) ?></td>
                            <td style="font-weight: bold;"><?= number_format($trial['accuracy_score'], 1) ?>%</td>
                            <td><?= htmlspecialchars($trial['faculty_validator'] ?: 'Faculty Validator') ?></td>
                            <td><?= $trial['validated_at'] ? date('M d, Y', strtotime($trial['validated_at'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="print-footer">
            <div>Printed by: <strong><?= htmlspecialchars($partner_name) ?> (Alumni / Police Partner)</strong></div>
            <div>Date Printed: <?= date('F d, Y h:i A') ?></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Otherwise, render full reports page
$reports_list = [];
$all_approved_trials = [];

try {
    // 1. Fetch generated reports
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name AS compiler_name 
        FROM reports r 
        LEFT JOIN users u ON r.generated_by = u.id 
        ORDER BY r.generated_at DESC
    ");
    $stmt->execute();
    $reports_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch all approved trials for reference
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
    ");
    $stmt->execute();
    $all_approved_trials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_approved_trials as &$row) {
        $row['image_exists'] = false;
        if (!empty($row['image_path'])) {
            $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $row['image_path'];
            if (file_exists($filePath)) {
                $row['image_exists'] = true;
            }
        }
    }
    unset($row);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Reports — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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
                    <h2>Alumni / Police Partner Portal</h2>
                </div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Approved Reports</h1>
                    <p>Official comparative analysis and sustainable development study reports.</p>
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

            <!-- REPORTS TABLE -->
            <div class="dashboard-card" style="margin-bottom: 2rem;">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        Archived Validation Reports
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Report Title</th>
                                <th>Compiled By</th>
                                <th>Configurations / Filters</th>
                                <th>Date Generated</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($reports_list)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#6c757d;padding:2rem;">
                                    No compiled reports found in database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports_list as $row): ?>
                            <tr>
                                <td style="font-weight:700; color:var(--dark-green);">RPT-<?= sprintf('%04d', $row['id']) ?></td>
                                <td><strong><?= htmlspecialchars($row['report_title']) ?></strong></td>
                                <td><?= htmlspecialchars($row['compiler_name'] ?? 'System') ?></td>
                                <td>
                                    <span style="font-family: monospace; font-size: 0.78rem; color: #666;">
                                        <?php
                                        $f = json_decode($row['report_filter'], true);
                                        if (is_array($f)) {
                                            $parts = [];
                                            foreach ($f as $k => $v) {
                                                if (!empty($v) && $v !== 'all') {
                                                    $parts[] = htmlspecialchars(ucfirst($k) . ': ' . ucfirst($v));
                                                }
                                            }
                                            echo empty($parts) ? 'No filter filters' : implode(', ', $parts);
                                        } else {
                                            echo 'All records';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($row['generated_at'])) ?></td>
                                <td style="text-align: right;">
                                    <a href="approved_reports.php?print=1&id=<?= $row['id'] ?>" target="_blank" class="btn btn-primary btn-sm">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:2px; vertical-align: middle;">
                                            <polyline points="6 9 6 2 18 2 18 9"/>
                                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                            <rect x="6" y="14" width="12" height="8"/>
                                        </svg>
                                        <span>Print</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ALL APPROVED SUBMISSIONS REFERENCE -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <line x1="9" y1="3" x2="9" y2="21"/>
                        </svg>
                        Reference Database (Approved Trial Records)
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
                                <th>Faculty Validator</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($all_approved_trials)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#6c757d;padding:2rem;">
                                    No approved trials found in database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_approved_trials as $row): ?>
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
        if (row.image_path && row.image_exists) {
            modalImg.src = "../view_fingerprint.php?test_id=" + row.id;
            modalImg.style.display = "inline-block";
            modalFallback.style.display = "none";
        } else {
            modalImg.style.display = "none";
            modalFallback.textContent = "Image not found";
            modalFallback.style.display = "block";
            modalFallback.style.color = "var(--danger)";
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
<?php include '../includes/support_chat_widget.php'; ?>
</body>
</html>
