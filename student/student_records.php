<?php
// student/student_records.php — View My Records & Reports
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'student_records';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

// Filters
$filter_status  = $_GET['status']  ?? '';
$filter_powder  = $_GET['powder']  ?? '';
$filter_surface = $_GET['surface'] ?? '';

// Build query
$where = ['ft.student_id = ?'];
$params = [$student_id];
if ($filter_status)  { $where[] = 'ft.status = ?';       $params[] = $filter_status; }
if ($filter_powder)  { $where[] = 'ft.powder_type = ?';  $params[] = $filter_powder; }
if ($filter_surface) { $where[] = 'ft.surface_type = ?'; $params[] = $filter_surface; }

$records = [];
try {
    $sql = "
        SELECT ft.*, COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks, faculty.full_name AS faculty_validator
        FROM fingerprint_tests ft
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY ft.submitted_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($records as &$row) {
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
    <meta name="description" content="My Records &amp; Reports — Green Forensics">
    <title>Records &amp; Reports — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <style>
        .filter-bar { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1.5rem; align-items:flex-end; }
        .filter-item { display:flex; flex-direction:column; gap:.3rem; }
        .filter-item label { font-size:.72rem; font-weight:700; color:var(--dark-green); text-transform:uppercase; letter-spacing:.4px; }
        .filter-item select { padding:.5rem .9rem; border:1px solid var(--light-gray); border-radius:8px; font-size:.85rem; color:var(--dark); background:var(--white); outline:none; transition:var(--transition); }
        .filter-item select:focus { border-color:var(--medium-green); box-shadow:0 0 0 3px rgba(45,106,79,.1); }
        .badge-pending_validation { background:rgba(244,162,97,.15); color:#c97d2a; border:1px solid rgba(244,162,97,.25); }
        .badge-needs_revision { background:rgba(230,57,70,.12); color:#e63946; border:1px solid rgba(230,57,70,.2); }
        .badge-approved { background:rgba(82,183,136,.15); color:#2d6a4f; border:1px solid rgba(82,183,136,.25); }
        .badge-rejected { background:rgba(224,122,95,.15); color:#c0392b; border:1px solid rgba(224,122,95,.2); }
        
        .custom-table tbody tr { cursor: pointer; transition: background 0.2s; }
        .custom-table tbody tr:hover { background: #f9fbf7; }

        /* Detail Modal styling matching super admin */
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
        .section-divider:first-child { margin-top: 0; }
        
        .score-val { font-size:1.15rem; font-weight:800; color:var(--dark-green); }
        .score-lbl { font-size:0.65rem; color:var(--gray); font-weight:600; text-transform:uppercase; }

        /* Dark theme Detailed Quality Inspection modal scoped under #detailOverlay */
        #detailOverlay .detail-modal {
            background: #111a2e !important; /* Deep dark navy background */
            color: #f8fafc !important;
            border: 1px solid #1e293b !important;
            max-width: 800px !important;
            width: 95% !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            border-radius: 16px !important;
        }
        #detailOverlay .detail-modal-header {
            background: #1e293b !important;
            border-bottom: 1px solid #334155 !important;
            color: #f8fafc !important;
            padding: 1.1rem 1.5rem !important;
            border-top-left-radius: 15px !important;
            border-top-right-radius: 15px !important;
        }
        #detailOverlay .detail-modal-header h3 {
            color: #f8fafc !important;
            font-size: 1.2rem !important;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            margin: 0;
        }
        #detailOverlay .modal-close-btn {
            color: #94a3b8 !important;
            background: none !important;
            border: none !important;
            font-size: 1.6rem !important;
            cursor: pointer !important;
            opacity: 0.8 !important;
        }
        #detailOverlay .modal-close-btn:hover {
            color: #f8fafc !important;
            opacity: 1 !important;
        }
        #detailOverlay .detail-modal-body {
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
            .student-sidebar, .student-header, .filter-bar, .btn, .no-print { display:none !important; }
            .student-main { margin-left:0 !important; }
            .student-content { padding:0; }
            #detailOverlay {
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
            #detailOverlay .detail-modal {
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
                <div class="header-title"><h2>My Records &amp; Reports</h2></div>
            </div>
            <div class="header-right"><div class="header-role-chip">Criminology Student</div></div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>View Records / Reports</h1>
                    <p>All your fingerprint trial submissions — filter and review anytime.</p>
                </div>
                <div style="display:flex;gap:.5rem;" class="no-print">
                    <button onclick="window.print()" class="btn btn-secondary">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print / Export
                    </button>
                    <a href="upload_fingerprint.php" class="btn btn-primary">+ New Submission</a>
                </div>
            </div>

            <!-- Filters -->
            <form id="filterForm" class="filter-bar no-print">
                <div class="filter-item">
                    <label>Status</label>
                    <select name="status" id="filter-status">
                        <option value="">All Statuses</option>
                        <option value="pending_validation" <?= $filter_status==='pending_validation' ? 'selected' : '' ?>>Pending Validation</option>
                        <option value="approved"           <?= $filter_status==='approved'           ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected"           <?= $filter_status==='rejected'           ? 'selected' : '' ?>>Rejected</option>
                        <option value="needs_revision"     <?= $filter_status==='needs_revision'     ? 'selected' : '' ?>>Needs Revision</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Powder Type</label>
                    <select name="powder" id="filter-powder">
                        <option value="">All Powders</option>
                        <option value="eggshell"   <?= $filter_powder==='eggshell'   ? 'selected' : '' ?>>Eggshell</option>
                        <option value="commercial" <?= $filter_powder==='commercial' ? 'selected' : '' ?>>Commercial</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Surface</label>
                    <select name="surface" id="filter-surface">
                        <option value="">All Surfaces</option>
                        <?php foreach (['glass','plastic','metal','paper','wood','ceramic','fabric'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_surface===$s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item" style="justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnClearFilters" style="display: <?= ($filter_status || $filter_powder || $filter_surface) ? 'inline-block' : 'none' ?>;">Clear Filters</button>
                </div>
            </form>

            <!-- Records Table -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        All Submissions
                    </h3>
                    <span id="recordCount" style="font-size:.82rem;color:var(--gray);"><?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Trial ID</th>
                                <th>Fingerprint Image</th>
                                <th>Image Label</th>
                                <th>Powder Type</th>
                                <th>Surface Type</th>
                                <th>Accuracy Score</th>
                                <th>Score Bar</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                                <th>Faculty Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="recordsTableBody">
                        <?php if (empty($records)): ?>
                            <tr class="no-data-row">
                                <td colspan="10" style="text-align:center;color:#6c757d;padding:2.5rem;">
                                    No records found.
                                    <?php if (!$filter_status && !$filter_powder && !$filter_surface): ?>
                                        <a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload your first image →</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $i => $r): ?>
                            <tr data-trial-db-id="<?= $r['id'] ?>" onclick='openDetailModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>
                                <td style="font-weight: 700; color: var(--dark-green);"><?= htmlspecialchars($r['trial_id'] ?: 'TR-'.str_pad($r['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td>
                                    <?php if ($r['image_path']): ?>
                                        <?php if ($r['image_exists']): ?>
                                            <a href="../view_fingerprint.php?test_id=<?= $r['id'] ?>" target="_blank" onclick="event.stopPropagation();">
                                                <img src="../view_fingerprint.php?test_id=<?= $r['id'] ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="FP">
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 0.72rem; color: var(--danger); font-weight:600;">Image not found</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: var(--gray); font-style:italic;">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['image_label'] ?: 'Untitled') ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($r['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($r['surface_type']) ?></td>
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
                                <td style="min-width:120px;">
                                    <?php if ($r['status'] === 'approved' && $displayScore !== null): ?>
                                        <div class="score-bar">
                                            <div class="score-bar-track">
                                                <div class="score-bar-fill" style="width:<?= min(100, $displayScore) ?>%"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 0.75rem; color: var(--gray); font-style:italic;">Awaiting Faculty Validation</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $r['status'] ?>">
                                        <?= $r['status'] === 'pending_validation' ? 'Pending Validation' : ($r['status'] === 'needs_revision' ? 'Needs Revision' : ucfirst($r['status'])) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($r['submitted_at'])) ?></td>
                                <td style="font-size:.82rem; color:#5f5f5f; max-width:180px;"><?= $r['faculty_remarks'] ? htmlspecialchars($r['faculty_remarks']) : '<em>No remarks yet</em>' ?></td>
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

<!-- VIEW DETAILS MODAL -->
<div class="detail-overlay" id="detailOverlay">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981; margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Detailed Quality Inspection
            </h3>
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="student-chip" id="det-student-chip">student123</span>
                <button class="modal-close-btn" onclick="closeDetailModal()">&times;</button>
            </div>
        </div>
        <div class="detail-modal-body">
            
            <div id="modalContent">
                <div class="inspect-grid">
                    <!-- Left Column: Minutiae Mapping -->
                    <div>
                        <div class="column-title">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            MINUTIAE MAPPING
                        </div>
                        <div class="inspect-img-box" id="det-img-wrapper">
                            <img src="" alt="Fingerprint Preview" id="det-img">
                        </div>
                        <div style="text-align:center; color: #ef4444; font-weight:600; margin-bottom:1rem; display:none;" id="det-img-missing">
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
                            <div class="overall-score-huge" id="det-val-accuracy-huge">—</div>
                            <div class="overall-score-badge-wrap">
                                <span class="quality-badge" id="det-val-quality-badge">GOOD</span>
                                <span class="quality-badge-desc">Overall Print Quality Standard</span>
                            </div>
                        </div>

                        <!-- Progress Bars -->
                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Ridge Clarity</span>
                                <span id="det-val-clarity">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="det-fill-clarity"></div>
                            </div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Contrast Quality</span>
                                <span id="det-val-contrast">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="det-fill-contrast"></div>
                            </div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Minutiae Visibility</span>
                                <span id="det-val-visibility">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="det-fill-visibility"></div>
                            </div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Fingerprint Sharpness</span>
                                <span id="det-val-sharpness">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="det-fill-sharpness"></div>
                            </div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-info">
                                <span>Adhesion Quality</span>
                                <span id="det-val-adhesion">—</span>
                            </div>
                            <div class="metric-bar-track">
                                <div class="metric-bar-fill" id="det-fill-adhesion"></div>
                            </div>
                        </div>
                        
                        <!-- AI Preliminary Results Container -->
                        <div id="det-ai-prelim-container"></div>
                    </div>
                </div>

                <!-- Bottom Section: Lab Analysis Notes -->
                <div class="analysis-notes-box">
                    <div class="analysis-notes-title">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Lab Analysis Notes
                    </div>

                    <div class="notes-content-wrap">
                        <div class="notes-label" id="det-remarks-label">Faculty Remarks:</div>
                        <div class="notes-text" id="det-remarks"></div>
                    </div>

                    <!-- Details Grid -->
                    <div class="info-details-grid">
                        <div class="info-detail-row">
                            <span class="info-detail-label">Trial ID:</span>
                            <span class="info-detail-value" id="det-trial-id"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Powder Type:</span>
                            <span class="info-detail-value" id="det-powder" style="text-transform: capitalize;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Surface Type:</span>
                            <span class="info-detail-value" id="det-surface" style="text-transform: capitalize;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Image Label:</span>
                            <span class="info-detail-value" id="det-label"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Status:</span>
                            <span class="info-detail-value" id="det-status"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">AI Preliminary Score:</span>
                            <span class="info-detail-value" id="det-ai-score"></span>
                        </div>
                        <div class="info-detail-row" id="det-faculty-row">
                            <span class="info-detail-label" id="det-faculty-score-label">Faculty Final Score:</span>
                            <span class="info-detail-value" id="det-faculty-score"></span>
                        </div>
                        <div class="info-detail-row" id="det-reviewer-row">
                            <span class="info-detail-label">Faculty Reviewer:</span>
                            <span class="info-detail-value" id="det-reviewer"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Image Path:</span>
                            <span class="info-detail-value" id="det-image-path" style="font-family: monospace; font-size: 0.75rem; color:#10b981; word-break: break-all;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Evaluation Date:</span>
                            <span class="info-detail-value" id="det-evaluation-date"></span>
                        </div>
                        <div class="info-detail-row" id="det-validated-date-row">
                            <span class="info-detail-label">Validation Date:</span>
                            <span class="info-detail-value" id="det-validated-at"></span>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;" class="no-print">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailModal()" style="flex:1; background:#334155; border-color:#334155; color:#fff;">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '_sidebar_js.php'; ?>
<script>
let isFetching = false;

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

function getBadgeClass(status) {
    if (status === 'pending_validation') return 'badge-pending_validation';
    if (status === 'needs_revision') return 'badge-needs_revision';
    if (status === 'approved') return 'badge-approved';
    if (status === 'rejected') return 'badge-rejected';
    return 'badge-' + status;
}

function getStatusLabel(status) {
    if (status === 'pending_validation') return 'Pending Validation';
    if (status === 'needs_revision') return 'Needs Revision';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function fetchFilteredRecords() {
    if (isFetching) return;
    
    const status = document.getElementById('filter-status').value;
    const powder = document.getElementById('filter-powder').value;
    const surface = document.getElementById('filter-surface').value;
    
    // Toggle Clear Filters button visibility
    const clearBtn = document.getElementById('btnClearFilters');
    if (status || powder || surface) {
        clearBtn.style.display = 'inline-block';
    } else {
        clearBtn.style.display = 'none';
    }

    isFetching = true;
    
    // Update Address Bar Query Params
    const url = new URL(window.location);
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    if (powder) url.searchParams.set('powder', powder); else url.searchParams.delete('powder');
    if (surface) url.searchParams.set('surface', surface); else url.searchParams.delete('surface');
    window.history.pushState({}, '', url);

    fetch(`ajax_get_student_records.php?status=${encodeURIComponent(status)}&powder=${encodeURIComponent(powder)}&surface=${encodeURIComponent(surface)}`)
        .then(res => res.json())
        .then(data => {
            isFetching = false;
            if (data.success) {
                renderRecordsTable(data.data.records);
            }
        })
        .catch(err => {
            isFetching = false;
        });
}

function renderRecordsTable(records) {
    const tbody = document.getElementById('recordsTableBody');
    const countSpan = document.getElementById('recordCount');
    
    countSpan.textContent = `${records.length} record${records.length !== 1 ? 's' : ''}`;
    
    if (records.length === 0) {
        const status = document.getElementById('filter-status').value;
        const powder = document.getElementById('filter-powder').value;
        const surface = document.getElementById('filter-surface').value;
        
        let linkHtml = '';
        if (!status && !powder && !surface) {
            linkHtml = '<br><a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload your first image →</a>';
        }
        
        tbody.innerHTML = `
            <tr class="no-data-row">
                <td colspan="10" style="text-align:center;color:#6c757d;padding:2.5rem;">
                    No records found.${linkHtml}
                </td>
            </tr>`;
        return;
    }

    const existingRows = Array.from(tbody.querySelectorAll('tr[data-trial-db-id]'));
    const existingIds = existingRows.map(row => parseInt(row.getAttribute('data-trial-db-id')));
    const newIds = records.map(r => parseInt(r.id));

    // Remove rows no longer matching
    existingRows.forEach(row => {
        const id = parseInt(row.getAttribute('data-trial-db-id'));
        if (!newIds.includes(id)) {
            row.remove();
        }
    });

    // Add or update rows
    records.forEach(r => {
        let row = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
        
        let imageHtml = '<span style="font-size: 0.72rem; color: var(--gray); font-style:italic;">No image</span>';
        if (r.image_path) {
            if (r.image_exists) {
                imageHtml = `
                    <a href="../view_fingerprint.php?test_id=${r.id}" target="_blank" onclick="event.stopPropagation();">
                        <img src="../view_fingerprint.php?test_id=${r.id}" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="FP">
                    </a>`;
            } else {
                imageHtml = '<span style="font-size: 0.72rem; color: var(--danger); font-weight:600;">Image not found</span>';
            }
        }

        const isApproved = r.status === 'approved';
        const scoreText = isApproved ? (r.accuracy_score !== null ? parseFloat(r.accuracy_score).toFixed(1) + '%' : '—') : (r.status === 'pending_validation' ? 'Awaiting Validation' : (r.status === 'needs_revision' ? 'Needs Revision' : (r.status === 'rejected' ? 'Rejected' : 'N/A')));
        const scoreBarHtml = (isApproved && r.accuracy_score !== null) ? `
            <div class="score-bar">
                <div class="score-bar-track">
                    <div class="score-bar-fill" style="width:${Math.min(100, r.accuracy_score)}%"></div>
                </div>
            </div>` : '<div style="font-size: 0.75rem; color: var(--gray); font-style:italic;">Awaiting review</div>';

        const remarksHtml = r.faculty_remarks ? escapeHtml(r.faculty_remarks) : '<em>No remarks yet</em>';

        if (row) {
            // Update row fields
            row.children[1].innerHTML = imageHtml;
            row.children[2].textContent = r.image_label || 'Untitled';
            row.children[3].textContent = r.powder_type || '';
            row.children[4].textContent = r.surface_type || '';
            row.children[5].innerHTML = `<strong>${scoreText}</strong>`;
            row.children[6].innerHTML = scoreBarHtml;
            row.children[7].innerHTML = `<span class="badge ${getBadgeClass(r.status)}">${getStatusLabel(r.status)}</span>`;
            row.children[9].innerHTML = remarksHtml;
        } else {
            // Prepend new row
            const tr = document.createElement('tr');
            tr.setAttribute('data-trial-db-id', r.id);
            
            tr.innerHTML = `
                <td style="font-weight: 700; color: var(--dark-green);">${r.trial_id || 'TR-' + String(r.id).padStart(4, '0')}</td>
                <td>${imageHtml}</td>
                <td>${escapeHtml(r.image_label || 'Untitled')}</td>
                <td style="text-transform:capitalize;">${r.powder_type || ''}</td>
                <td style="text-transform:capitalize;">${r.surface_type || ''}</td>
                <td><strong>${scoreText}</strong></td>
                <td style="min-width:120px;">${scoreBarHtml}</td>
                <td><span class="badge ${getBadgeClass(r.status)}">${getStatusLabel(r.status)}</span></td>
                <td>${new Date(r.submitted_at.replace(/-/g, "/")).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} ${new Date(r.submitted_at.replace(/-/g, "/")).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</td>
                <td style="font-size:.82rem; color:#5f5f5f; max-width:180px;">${remarksHtml}</td>
            `;
            const noData = tbody.querySelector('.no-data-row');
            if (noData) noData.remove();
            tbody.insertBefore(tr, tbody.firstChild);
        }
        
        // Update/bind row click listener
        const trNode = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
        if (trNode) {
            trNode.onclick = () => openDetailModal(r);
        }
    });
}

function openDetailModal(row) {
    // Fill student chip (username/nickname from email or name)
    const username = row.student_email ? row.student_email.split('@')[0] : (row.student_name ? row.student_name.toLowerCase().replace(/\s+/g, '') : 'student');
    document.getElementById('det-student-chip').textContent = username;

    document.getElementById('det-trial-id').textContent = row.trial_id || 'TR-' + String(row.id).padStart(4, '0');
    document.getElementById('det-powder').textContent = row.powder_type || '';
    document.getElementById('det-surface').textContent = row.surface_type || '';
    document.getElementById('det-label').textContent = row.image_label || 'Untitled';
    
    // Evaluation Date mapping
    const evalDate = row.ai_evaluated_at ? new Date(row.ai_evaluated_at.replace(/-/g, "/")).toLocaleString() : (row.submitted_at ? new Date(row.submitted_at.replace(/-/g, "/")).toLocaleString() : '—');
    document.getElementById('det-evaluation-date').textContent = evalDate;

    // Image path
    document.getElementById('det-image-path').textContent = row.image_path ? 'uploads/' + row.image_path : '—';

    // Image viewer logic
    const img = document.getElementById('det-img');
    const imgWrapper = document.getElementById('det-img-wrapper');
    const imgMissing = document.getElementById('det-img-missing');
    
    if (row.image_path && row.image_exists) {
        img.src = '../view_fingerprint.php?test_id=' + row.id;
        imgWrapper.style.display = 'flex';
        if (imgMissing) imgMissing.style.display = 'none';
    } else {
        imgWrapper.style.display = 'none';
        if (imgMissing) imgMissing.style.display = 'block';
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

    const extraAiContainer = document.getElementById('det-ai-prelim-container');
    if (extraAiContainer) {
        extraAiContainer.innerHTML = aiDetailsHtml;
    }

    // Update main progress bars to show Faculty Final score if approved, otherwise show placeholder or hide
    const overallScoreHuge = document.getElementById('det-val-accuracy-huge');
    const badgeEl = document.getElementById('det-val-quality-badge');
    const badgeDesc = document.querySelector('.quality-badge-desc');

    if (row.status === 'approved') {
        overallScoreHuge.textContent = Math.round(fAccuracy) + '%';
        badgeEl.textContent = 'APPROVED';
        badgeEl.style.color = '#10b981';
        badgeEl.style.borderColor = 'rgba(16, 185, 129, 0.25)';
        badgeEl.style.background = 'rgba(16, 185, 129, 0.12)';
        if (badgeDesc) badgeDesc.textContent = 'Faculty Approved Official Score';

        // Set text labels
        document.getElementById('det-val-clarity').textContent = fClarity > 0 ? fClarity.toFixed(1) + '%' : '—';
        document.getElementById('det-val-contrast').textContent = fContrast > 0 ? fContrast.toFixed(1) + '%' : '—';
        document.getElementById('det-val-visibility').textContent = fVisibility > 0 ? fVisibility.toFixed(1) + '%' : '—';
        document.getElementById('det-val-sharpness').textContent = fClarity > 0 ? fClarity.toFixed(1) + '%' : '—';
        document.getElementById('det-val-adhesion').textContent = fAdhesion > 0 ? fAdhesion.toFixed(1) + '%' : '—';

        // Set progress bar widths
        document.getElementById('det-fill-clarity').style.width = fClarity + '%';
        document.getElementById('det-fill-contrast').style.width = fContrast + '%';
        document.getElementById('det-fill-visibility').style.width = fVisibility + '%';
        document.getElementById('det-fill-sharpness').style.width = fClarity + '%';
        document.getElementById('det-fill-adhesion').style.width = fAdhesion + '%';
        
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
        document.getElementById('det-val-clarity').textContent = '—';
        document.getElementById('det-val-contrast').textContent = '—';
        document.getElementById('det-val-visibility').textContent = '—';
        document.getElementById('det-val-sharpness').textContent = '—';
        document.getElementById('det-val-adhesion').textContent = '—';

        document.getElementById('det-fill-clarity').style.width = '0%';
        document.getElementById('det-fill-contrast').style.width = '0%';
        document.getElementById('det-fill-visibility').style.width = '0%';
        document.getElementById('det-fill-sharpness').style.width = '0%';
        document.getElementById('det-fill-adhesion').style.width = '0%';
    }

    document.getElementById('det-ai-score').textContent = aiAccuracy > 0 ? aiAccuracy.toFixed(1) + '%' : 'Awaiting AI Evaluation';

    // Conditional elements based on status
    const statusVal = document.getElementById('det-status');
    const reviewerRow = document.getElementById('det-reviewer-row');
    const validatedAtRow = document.getElementById('det-validated-date-row');
    const remarksRow = document.getElementById('det-remarks');
    const remarksLabel = document.getElementById('det-remarks-label');
    const facultyScoreRow = document.getElementById('det-faculty-row');

    if (row.status === 'pending_validation') {
        statusVal.innerHTML = '<span class="badge badge-pending_validation">Pending Validation</span>';
        reviewerRow.style.display = 'none';
        validatedAtRow.style.display = 'none';
        facultyScoreRow.style.display = 'flex';
        
        document.getElementById('det-faculty-score-label').textContent = 'Faculty Final Score:';
        document.getElementById('det-faculty-score').textContent = 'Awaiting Faculty Validation';
        
        remarksLabel.textContent = 'Notes:';
        remarksRow.innerHTML = 'This record is still awaiting faculty review.';
    } else {
        reviewerRow.style.display = 'flex';
        validatedAtRow.style.display = 'flex';
        
        document.getElementById('det-reviewer').textContent = row.faculty_validator || 'Faculty Reviewer';
        document.getElementById('det-validated-at').textContent = row.validated_at ? new Date(row.validated_at.replace(/-/g, "/")).toLocaleString() : '—';
        
        remarksLabel.textContent = 'Faculty Remarks:';
        remarksRow.innerHTML = row.faculty_remarks ? escapeHtml(row.faculty_remarks).replace(/\n/g, '<br>') : 'No remarks provided.';

        if (row.status === 'approved') {
            statusVal.innerHTML = '<span class="badge badge-approved">Approved</span>';
            facultyScoreRow.style.display = 'flex';
            document.getElementById('det-faculty-score-label').textContent = 'Faculty Final Score:';
            document.getElementById('det-faculty-score').textContent = fAccuracy.toFixed(1) + '%';
        } else if (row.status === 'rejected') {
            statusVal.innerHTML = '<span class="badge badge-rejected">Rejected</span>';
            facultyScoreRow.style.display = 'none';
        } else if (row.status === 'needs_revision') {
            statusVal.innerHTML = '<span class="badge badge-needs_revision">Needs Revision</span>';
            facultyScoreRow.style.display = 'none';
        }
    }

    document.getElementById('detailOverlay').classList.add('open');
}

function closeDetailModal() {
    document.getElementById('detailOverlay').classList.remove('open');
}

// Close modal when clicking outside content
document.getElementById('detailOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('detailOverlay')) closeDetailModal();
});

function isAutoRefreshPaused() {
    const isModalOpen = document.getElementById('detailOverlay').classList.contains('open');
    const isUserTyping = document.activeElement && (
        document.activeElement.tagName === 'INPUT' || 
        document.activeElement.tagName === 'TEXTAREA' || 
        document.activeElement.tagName === 'SELECT'
    );
    return isModalOpen || isUserTyping || isFetching;
}

function autoRefreshStudentRecords() {
    if (isAutoRefreshPaused()) return;
    
    const status = document.getElementById('filter-status').value;
    const powder = document.getElementById('filter-powder').value;
    const surface = document.getElementById('filter-surface').value;

    fetch(`ajax_get_student_records.php?status=${encodeURIComponent(status)}&powder=${encodeURIComponent(powder)}&surface=${encodeURIComponent(surface)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderRecordsTable(data.data.records);
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    // Hook Filter elements changes
    document.getElementById('filter-status').addEventListener('change', fetchFilteredRecords);
    document.getElementById('filter-powder').addEventListener('change', fetchFilteredRecords);
    document.getElementById('filter-surface').addEventListener('change', fetchFilteredRecords);
    
    // Clear Filters button
    document.getElementById('btnClearFilters').addEventListener('click', () => {
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-powder').value = '';
        document.getElementById('filter-surface').value = '';
        fetchFilteredRecords();
    });

    // Initialize click handlers on page load
    const rows = document.querySelectorAll('#recordsTableBody tr[data-trial-db-id]');
    rows.forEach(r => {
        const rowData = <?php echo json_encode($records); ?>;
        const id = parseInt(r.getAttribute('data-trial-db-id'));
        const matchingRec = rowData.find(item => parseInt(item.id) === id);
        if (matchingRec) {
            r.onclick = () => openDetailModal(matchingRec);
        }
    });

    // 10s auto-refresh
    setInterval(autoRefreshStudentRecords, 10000);
});
</script>
</body>
</html>
