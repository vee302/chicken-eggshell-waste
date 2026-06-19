<?php
// faculty/generate_reports.php
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';
$faculty_id   = $_SESSION['user_id']  ?? 0;

$students = [];
try {
    $students = $pdo->query("SELECT id, full_name FROM users WHERE role='criminology_student' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Filters
$f_student = $_GET['student_id'] ?? '';
$f_powder  = $_GET['powder']     ?? '';
$f_surface = $_GET['surface']    ?? '';
$f_from    = $_GET['from']       ?? '';
$f_to      = $_GET['to']         ?? '';

$where = ["ft.status='approved'"]; // Approved records only as required
$params = [];

if ($f_student) { $where[] = "ft.student_id=?";   $params[] = $f_student; }
if ($f_powder)  { $where[] = "ft.powder_type=?";   $params[] = $f_powder; }
if ($f_surface) { $where[] = "ft.surface_type=?";  $params[] = $f_surface; }
if ($f_from)    { $where[] = "DATE(ft.submitted_at)>=?"; $params[] = $f_from; }
if ($f_to)      { $where[] = "DATE(ft.submitted_at)<=?"; $params[] = $f_to; }

$sql = "
    SELECT ft.*, u.full_name AS student_name,
           COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks, fr.decision, fr.created_at AS review_date
    FROM fingerprint_tests ft
    JOIN users u ON u.id = ft.student_id
    LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.decision='approved'
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ft.submitted_at DESC
";

$records = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Handle Save Report Activity (Log when printing)
if (isset($_GET['log_report']) && $_GET['log_report'] === '1' && !empty($records)) {
    try {
        $filter_summary = json_encode([
            'student_id' => $f_student,
            'powder' => $f_powder,
            'surface' => $f_surface,
            'from' => $f_from,
            'to' => $f_to
        ]);
        $stmt = $pdo->prepare("INSERT INTO reports (generated_by, report_title, report_filter) VALUES (?, ?, ?)");
        $stmt->execute([$faculty_id, "Fingerprint Evaluation Performance Report", $filter_summary]);
    } catch (PDOException $e) {}
    // Return simple JSON or exit since it's an AJAX log
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .filter-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(27,67,50,.04);
            margin-bottom: 2rem;
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
        .report-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        .badge-approved { background: rgba(82,183,136,.15); color: #2d6a4f; }
        
        /* Printable Report Layout */
        #printableReport {
            display: none;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #printableReport, #printableReport * {
                visibility: visible;
            }
            #printableReport {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
                color: #000;
                font-family: 'Inter', sans-serif;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
            .report-header {
                text-align: center;
                border-bottom: 2px solid #1b4332;
                padding-bottom: 15px;
                margin-bottom: 30px;
            }
            .report-header h1 {
                font-size: 20pt;
                color: #1b4332;
                margin: 0;
                font-weight: 800;
            }
            .report-header p {
                font-size: 10pt;
                color: #6c757d;
                margin: 5px 0 0 0;
            }
            .meta-section {
                display: flex;
                justify-content: space-between;
                font-size: 10pt;
                margin-bottom: 30px;
                background: #f4f6f0;
                padding: 15px;
                border-radius: 8px;
            }
            .meta-section div p {
                margin: 4px 0;
            }
            .record-card {
                page-break-inside: avoid;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 25px;
            }
            .record-grid {
                display: grid;
                grid-template-columns: 120px 1fr;
                gap: 20px;
            }
            .record-img {
                width: 120px;
                height: 120px;
                object-fit: cover;
                border-radius: 6px;
                border: 1px solid #ddd;
            }
            .record-placeholder {
                width: 120px;
                height: 120px;
                background: #eee;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 9pt;
                color: #777;
                border: 1px dashed #ccc;
            }
            .record-details h3 {
                margin: 0 0 10px 0;
                font-size: 12pt;
                color: #1b4332;
            }
            .record-details table {
                width: 100%;
                border-collapse: collapse;
                font-size: 9.5pt;
                margin-bottom: 10px;
            }
            .record-details table th, .record-details table td {
                padding: 4px 0;
                text-align: left;
            }
            .record-details table th {
                color: #6c757d;
                font-weight: 500;
                width: 150px;
            }
            .remarks-box {
                margin-top: 10px;
                background: #fafafa;
                padding: 10px;
                border-left: 3px solid #2d6a4f;
                font-size: 9.5pt;
            }
        }
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
            <li class="menu-item"><a href="student_records.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>View Student Records</span></a></li>
            <li class="menu-item active"><a href="generate_reports.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Generate Reports</span></a></li>
        </ul>
        <div class="sidebar-footer"><a href="../logout.php" class="menu-link" style="color:#e07a5f;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a></div>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="header-title"><h2>Green Forensics — Generate Reports</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Generate Reports</h1>
                    <p>Select filter criteria and generate official performance validation reports (Approved Records Only).</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <form method="GET">
                    <div class="filter-grid">
                        <div class="filter-item">
                            <label>Student Name</label>
                            <select name="student_id">
                                <option value="">All Students</option>
                                <?php foreach ($students as $stu): ?>
                                    <option value="<?= $stu['id'] ?>" <?= $f_student == $stu['id'] ? 'selected' : '' ?>><?= htmlspecialchars($stu['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label>Powder Type</label>
                            <select name="powder">
                                <option value="">All Powder Types</option>
                                <option value="eggshell"   <?= $f_powder === 'eggshell'   ? 'selected' : '' ?>>Eggshell</option>
                                <option value="commercial" <?= $f_powder === 'commercial' ? 'selected' : '' ?>>Commercial</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label>Surface Type</label>
                            <select name="surface">
                                <option value="">All Surfaces</option>
                                <?php foreach (['glass','paper','wood','plastic','metal'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $f_surface === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label>Date From</label>
                            <input type="date" name="from" value="<?= htmlspecialchars($f_from) ?>">
                        </div>
                        <div class="filter-item">
                            <label>Date To</label>
                            <input type="date" name="to" value="<?= htmlspecialchars($f_to) ?>">
                        </div>
                    </div>
                    <div class="report-actions">
                        <a href="generate_reports.php" class="btn btn-secondary">Reset Filters</a>
                        <button type="submit" class="btn btn-primary">Preview Report</button>
                        <?php if (!empty($records)): ?>
                            <button type="button" class="btn btn-primary" onclick="printReport()" style="background-color: #1b4332;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Print / Save PDF
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Preview Card -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>Report Preview (<?= count($records) ?> Approved Records)</h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Trial ID</th>
                                <th>Student Name</th>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Accuracy Score</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="8" style="text-align:center;padding:2rem;color:#6c757d;">No approved records matched the filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td style="font-weight:600; color:var(--dark-green);"><?= htmlspecialchars($r['trial_id'] ?: 'TR-'.str_pad($r['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                    <td><?= htmlspecialchars($r['student_name']) ?></td>
                                    <td style="text-transform:capitalize;"><?= $r['powder_type'] ?></td>
                                    <td style="text-transform:capitalize;"><?= $r['surface_type'] ?></td>
                                    <td><strong><?= number_format($r['faculty_final_score'] !== null ? $r['faculty_final_score'] : $r['accuracy_score'], 1) ?>%</strong></td>
                                    <td><span class="badge badge-approved"><?= ucfirst($r['status']) ?></span></td>
                                    <td><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
                                    <td style="font-size:.82rem;color:#6c757d;max-width:200px;"><?= htmlspecialchars($r['faculty_remarks'] ?? 'No remarks added.') ?></td>
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

<!-- PRINTABLE TEMPLATE -->
<div id="printableReport">
    <div class="report-header">
        <h1>GREEN FORENSICS EVALUATION REPORT</h1>
        <p>Comparative Study of Eggshell-Based and Commercial Fingerprint Powders</p>
    </div>

    <div class="meta-section">
        <div>
            <p><strong>Generated By:</strong> <?= htmlspecialchars($faculty_name) ?></p>
            <p><strong>Role:</strong> Faculty Researcher / Adviser</p>
            <p><strong>Project Title:</strong> Waste Eggshell Fingerprint Development Evaluation</p>
        </div>
        <div style="text-align: right;">
            <p><strong>Report Date:</strong> <?= date('F d, Y') ?></p>
            <p><strong>Total Validated Records:</strong> <?= count($records) ?></p>
        </div>
    </div>

    <h2>Approved Submissions Detail</h2>
    <?php foreach ($records as $r): ?>
        <div class="record-card">
            <div class="record-grid">
                <div>
                    <?php if ($r['image_path'] && file_exists('../uploads/fingerprints/'.$r['image_path'])): ?>
                        <img src="../uploads/fingerprints/<?= htmlspecialchars($r['image_path']) ?>" class="record-img" alt="Fingerprint">
                    <?php else: ?>
                        <div class="record-placeholder">No Image Available</div>
                    <?php endif; ?>
                </div>
                <div class="record-details">
                    <h3>Trial Record: <?= htmlspecialchars($r['trial_id'] ?: 'TR-'.str_pad($r['id'], 4, '0', STR_PAD_LEFT)) ?> - <?= htmlspecialchars($r['student_name']) ?></h3>
                    <table>
                        <tr>
                            <th>Powder Type:</th>
                            <td style="text-transform:capitalize; font-weight: 600;"><?= htmlspecialchars($r['powder_type']) ?></td>
                            <th>Surface Type:</th>
                            <td style="text-transform:capitalize;"><?= htmlspecialchars($r['surface_type']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" style="padding: 6px 0;">
                                <div style="font-size: 8.5pt; font-weight: 700; color: #1b4332; margin-bottom: 4px; text-transform: uppercase;">Validation Metrics Comparison</div>
                                <table style="width: 100%; border: 1px solid #eee; font-size: 8.5pt;">
                                    <thead>
                                        <tr style="background: #f8faf6;">
                                            <th style="padding: 4px 6px; width: 40%;">Metric</th>
                                            <th style="padding: 4px 6px;">AI Score</th>
                                            <th style="padding: 4px 6px;">Faculty Score (Official)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="padding: 3px 6px;">Ridge Clarity</td>
                                            <td style="padding: 3px 6px;"><?= number_format($r['ridge_clarity_score'], 1) ?>%</td>
                                            <td style="padding: 3px 6px; font-weight: 700;"><?= number_format($r['faculty_ridge_clarity_score'] !== null ? $r['faculty_ridge_clarity_score'] : $r['ridge_clarity_score'], 1) ?>%</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 3px 6px;">Visibility</td>
                                            <td style="padding: 3px 6px;"><?= number_format($r['visibility_score'], 1) ?>%</td>
                                            <td style="padding: 3px 6px; font-weight: 700;"><?= number_format($r['faculty_visibility_score'] !== null ? $r['faculty_visibility_score'] : $r['visibility_score'], 1) ?>%</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 3px 6px;">Adhesion</td>
                                            <td style="padding: 3px 6px;"><?= number_format($r['adhesion_score'], 1) ?>%</td>
                                            <td style="padding: 3px 6px; font-weight: 700;"><?= number_format($r['faculty_adhesion_score'] !== null ? $r['faculty_adhesion_score'] : $r['adhesion_score'], 1) ?>%</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 3px 6px;">Contrast</td>
                                            <td style="padding: 3px 6px;"><?= number_format($r['contrast_score'], 1) ?>%</td>
                                            <td style="padding: 3px 6px; font-weight: 700;"><?= number_format($r['faculty_contrast_score'] !== null ? $r['faculty_contrast_score'] : $r['contrast_score'], 1) ?>%</td>
                                        </tr>
                                        <tr style="border-top: 1px solid #ddd; background: #fbfdfa;">
                                            <td style="padding: 4px 6px; font-weight: 700;">Overall Accuracy</td>
                                            <td style="padding: 4px 6px; font-weight: 700;"><?= number_format($r['ai_accuracy_score'] !== null ? $r['ai_accuracy_score'] : $r['accuracy_score'], 1) ?>%</td>
                                            <td style="padding: 4px 6px; font-weight: 700; color: #1b4332;"><?= number_format($r['faculty_final_score'] !== null ? $r['faculty_final_score'] : $r['accuracy_score'], 1) ?>%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <th>Submitted Date:</th>
                            <td><?= date('M d, Y H:i', strtotime($r['submitted_at'])) ?></td>
                            <th>Validation Status:</th>
                            <td>Approved</td>
                        </tr>
                    </table>
                    <div class="remarks-box">
                        <strong>Faculty Advisor Remarks:</strong><br>
                        <?= htmlspecialchars($r['faculty_remarks'] ?? 'No remarks added.') ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function printReport() {
    // Log the generated report activity via AJAX
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('log_report', '1');
    fetch('generate_reports.php?' + urlParams.toString())
        .then(() => {
            window.print();
        })
        .catch(() => {
            window.print();
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
});
</script>
</body>
</html>
