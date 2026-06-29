<?php
// student/student_dashboard.php — Criminology Student Main Dashboard
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page = 'dashboard';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

// Summary stats
$total = $pending = $approved = $rejected = 0;
$avg_display = 'N/A';
try {
    $total    = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id")->fetchColumn();
    $pending  = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='pending_validation'")->fetchColumn();
    $approved = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='approved'")->fetchColumn();
    $rejected = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE student_id = $student_id AND status='rejected'")->fetchColumn();
    
    // Only calculate average accuracy from approved records
    $avg_score = $pdo->query("SELECT ROUND(AVG(COALESCE(faculty_final_score, accuracy_score)),1) FROM fingerprint_tests WHERE student_id = $student_id AND status='approved'")->fetchColumn();
    
    if ($avg_score !== null) {
        $avg_display = $avg_score . '%';
    } else {
        $avg_display = ($pending > 0) ? 'Awaiting Validation' : 'N/A';
    }
} catch (PDOException $e) {}

// Recent 5 submissions
$recent = [];
try {
    $stmt = $pdo->prepare("
        SELECT ft.*, COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks, faculty.full_name AS faculty_validator
        FROM fingerprint_tests ft
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE ft.student_id = ? 
        ORDER BY ft.submitted_at DESC LIMIT 5
    ");
    $stmt->execute([$student_id]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recent as &$r) {
        $r['image_exists'] = false;
        if (!empty($r['image_path'])) {
            $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $r['image_path'];
            if (file_exists($filePath)) {
                $r['image_exists'] = true;
            }
        }
    }
    unset($r);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Criminology Student Dashboard — Green Forensics Evaluating System">
    <title>Student Dashboard — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
    <style>
        .badge-pending_validation  { background: rgba(244,162,97,.15); color: #c97d2a; border: 1px solid rgba(244,162,97,.25); }
        .badge-approved { background: rgba(82,183,136,.15);  color: #2d6a4f; border: 1px solid rgba(82,183,136,.25); }
        .badge-rejected { background: rgba(224,122,95,.15);  color: #c0392b; border: 1px solid rgba(224,122,95,.2); }
        .badge-needs_revision { background: rgba(230,57,70,.12); color: #e63946; border: 1px solid rgba(230,57,70,.2); }
        
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
        
        .score-box { background: var(--cream); border-radius:8px; padding:10px 15px; margin-bottom:1rem; border:1px solid rgba(45,106,79,0.08); }
        .score-title { font-size:0.75rem; font-weight:700; color:var(--medium-green); margin-bottom:6px; text-transform:uppercase; }
        .score-values { display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; text-align:center; }
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
    </style>
</head>
<body>

<div class="student-wrapper">

    <!-- Mobile overlay -->
    <div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;transition:opacity .3s;"
         onclick="this.style.display='none';document.getElementById('sidebar').classList.remove('active')"></div>

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
                    <h2>Green Forensics — Student Dashboard</h2>
                </div>
            </div>
            <div class="header-right">
                <div class="header-role-chip">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                    </svg>
                    Criminology Student
                </div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back, <?= htmlspecialchars($student_name) ?>. Here is a summary of your forensic submissions.</p>
                </div>
                <a href="upload_fingerprint.php" class="btn btn-primary btn-new-submission" id="btn-submit-new">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Submission
                </a>
            </div>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Submissions</span>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="val-total"><?= $total ?></div>
                    <div class="stat-desc">Fingerprint trial records submitted</div>
                </div>

                <div class="stat-card card-pending">
                    <div class="stat-header">
                        <span class="stat-title">Pending Review</span>
                        <div class="stat-icon" style="background:rgba(244,162,97,.12);color:#c97d2a;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="val-pending" style="color:#c97d2a;"><?= $pending ?></div>
                    <div class="stat-desc">Awaiting faculty validation</div>
                </div>

                <div class="stat-card card-approved">
                    <div class="stat-header">
                        <span class="stat-title">Approved</span>
                        <div class="stat-icon" style="background:rgba(82,183,136,.12);color:#2d6a4f;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="val-approved" style="color:#2d6a4f;"><?= $approved ?></div>
                    <div class="stat-desc">Validated and confirmed records</div>
                </div>

                <div class="stat-card card-rejected">
                    <div class="stat-header">
                        <span class="stat-title">Rejected</span>
                        <div class="stat-icon" style="background:rgba(224,122,95,.12);color:#c0392b;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="val-rejected" style="color:#c0392b;"><?= $rejected ?></div>
                    <div class="stat-desc">Returned for revision</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Avg. Accuracy Score</span>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                                <polyline points="16 7 22 7 22 13"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="val-avg" style="font-size: <?= (strlen($avg_display) > 6) ? '1.25rem' : '2rem' ?>;"><?= htmlspecialchars($avg_display) ?></div>
                    <div class="stat-desc">Average across all your submissions</div>
                </div>
            </div>

            <!-- QUICK LINKS -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        Quick Actions
                    </h3>
                </div>
                <div class="quicklinks-grid">
                    <a href="upload_fingerprint.php" class="quicklink-card" id="ql-upload">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Upload Fingerprint Images</span>
                    </a>
                    <a href="surface_performance.php" class="quicklink-card" id="ql-surface">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Surface Performance</span>
                    </a>
                    <a href="accuracy_rating.php" class="quicklink-card" id="ql-accuracy">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                                <polyline points="16 7 22 7 22 13"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Accuracy Rating</span>
                    </a>
                    <a href="safety_climate_log.php" class="quicklink-card" id="ql-safety">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Safety &amp; Climate Log</span>
                    </a>
                    <a href="student_records.php" class="quicklink-card" id="ql-records">
                        <div class="quicklink-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        </div>
                        <span class="quicklink-label">Records / Reports</span>
                    </a>
                </div>
            </div>

            <!-- RECENT SUBMISSIONS -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Recent Submissions
                    </h3>
                    <a href="student_records.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Accuracy</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentSubmissionsBody">
                        <?php if (empty($recent)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#6c757d;padding:2rem;">
                                    No submissions yet. Upload your first fingerprint image to begin evaluation. <a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload now →</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent as $row): ?>
                            <tr data-trial-db-id="<?= $row['id'] ?>" onclick='openDetailModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['surface_type']) ?></td>
                                <td>
                                    <?php 
                                        $displayScore = $row['faculty_final_score'] !== null ? $row['faculty_final_score'] : $row['accuracy_score'];
                                        if ($row['status'] === 'approved' && $displayScore !== null) {
                                            echo number_format($displayScore, 1) . '%';
                                        } elseif ($row['status'] === 'pending_validation') {
                                            echo 'Awaiting Faculty Validation';
                                        } elseif ($row['status'] === 'needs_revision') {
                                            echo 'Needs Revision';
                                        } elseif ($row['status'] === 'rejected') {
                                            echo '—';
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $row['status'] ?>">
                                        <?php
                                            $status_labels = [
                                                'pending_validation' => 'Pending Validation',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                                'needs_revision' => 'Needs Revision'
                                            ];
                                            echo htmlspecialchars($status_labels[$row['status']] ?? ucfirst($row['status']));
                                        ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
                                <td style="text-align:right;">
                                    <button class="btn btn-secondary btn-sm view-details-btn" onclick='event.stopPropagation(); openDetailModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;">View Details</button>
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
let isFetchingStats = false;
let isFetchingRecords = false;

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

function isPollingPaused() {
    const isModalOpen = document.getElementById('detailOverlay').classList.contains('open');
    const isUserTyping = document.activeElement && (
        document.activeElement.tagName === 'INPUT' || 
        document.activeElement.tagName === 'TEXTAREA' || 
        document.activeElement.tagName === 'SELECT'
    );
    return isModalOpen || isUserTyping || isFetchingStats || isFetchingRecords;
}

function refreshStudentDashboardStats() {
    if (isPollingPaused()) return;
    
    isFetchingStats = true;
    fetch('ajax_get_student_dashboard_stats.php')
        .then(res => res.json())
        .then(data => {
            isFetchingStats = false;
            if (data.success) {
                const s = data.data;
                document.getElementById('val-total').textContent = s.total;
                document.getElementById('val-pending').textContent = s.pending;
                document.getElementById('val-approved').textContent = s.approved;
                document.getElementById('val-rejected').textContent = s.rejected;
                
                const avgEl = document.getElementById('val-avg');
                avgEl.textContent = s.avg_score;
                if (s.avg_score.length > 6) {
                    avgEl.style.fontSize = '1.25rem';
                } else {
                    avgEl.style.fontSize = '2rem';
                }
            }
        })
        .catch(err => {
            isFetchingStats = false;
        });
}

function refreshRecentSubmissions() {
    if (isPollingPaused()) return;
    
    isFetchingRecords = true;
    fetch('ajax_get_student_records.php')
        .then(res => res.json())
        .then(data => {
            isFetchingRecords = false;
            if (data.success) {
                const records = data.data.records.slice(0, 5); // Limit 5 on dashboard
                renderRecentTable(records);
            }
        })
        .catch(err => {
            isFetchingRecords = false;
        });
}

function renderRecentTable(records) {
    const tbody = document.getElementById('recentSubmissionsBody');
    if (records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center;color:#6c757d;padding:2rem;">
                    No submissions yet. Upload your first fingerprint image to begin evaluation. <a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload now →</a>
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

    records.forEach(r => {
        let row = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
        
        const isApproved = r.status === 'approved';
        const displayScore = r.faculty_final_score !== null ? parseFloat(r.faculty_final_score) : (r.accuracy_score !== null ? parseFloat(r.accuracy_score) : null);
        const scoreText = isApproved ? (displayScore !== null ? displayScore.toFixed(1) + '%' : '—') : (r.status === 'pending_validation' ? 'Awaiting Faculty Validation' : (r.status === 'needs_revision' ? 'Needs Revision' : (r.status === 'rejected' ? '—' : 'N/A')));
        
        const rowHtml = `
            <td style="text-transform:capitalize;">${escapeHtml(r.powder_type)}</td>
            <td style="text-transform:capitalize;">${escapeHtml(r.surface_type)}</td>
            <td>${scoreText}</td>
            <td>
                <span class="badge ${getBadgeClass(r.status)}">${getStatusLabel(r.status)}</span>
            </td>
            <td>${new Date(r.submitted_at.replace(/-/g, "/")).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
            <td style="text-align:right;">
                <button class="btn btn-secondary btn-sm view-details-btn" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;">View Details</button>
            </td>
        `;

        if (row) {
            // Update
            row.innerHTML = rowHtml;
        } else {
            // Prepend new row
            const tr = document.createElement('tr');
            tr.setAttribute('data-trial-db-id', r.id);
            tr.innerHTML = rowHtml;
            
            const noData = tbody.querySelector('.no-data-row');
            if (noData) noData.remove();
            tbody.insertBefore(tr, tbody.firstChild);
        }
        
        // Update/bind row click listener
        const trNode = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
        if (trNode) {
            trNode.onclick = () => openDetailModal(r);
            const btnNode = trNode.querySelector('.view-details-btn');
            if (btnNode) {
                btnNode.onclick = (e) => {
                    e.stopPropagation();
                    openDetailModal(r);
                };
            }
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
}

function closeDetailModal() {
    document.getElementById('detailOverlay').classList.remove('open');
}

// Close modal when clicking outside content
document.getElementById('detailOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('detailOverlay')) closeDetailModal();
});

document.addEventListener('DOMContentLoaded', () => {
    // 10s auto-refresh
    setInterval(refreshStudentDashboardStats, 10000);
    setInterval(refreshRecentSubmissions, 10000);
});
</script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
