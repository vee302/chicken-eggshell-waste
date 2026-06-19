<?php
// faculty/validate_accuracy.php - Validate Student Fingerprint Submissions
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';
$faculty_id   = $_SESSION['user_id']  ?? 0;
$message = $error = '';

// Summary counts
$total_submissions = $pending = $approved = $rejected = 0;
try {
    $total_submissions = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests")->fetchColumn();
    $pending           = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='pending_validation'")->fetchColumn();
    $approved          = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='approved'")->fetchColumn();
    $rejected          = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='rejected'")->fetchColumn();
} catch (PDOException $e) {}

// Retrieve all pending submissions (and handle optional assigned_faculty_id column)
$submissions = [];
try {
    $where_clause = "WHERE ft.status = 'pending_validation'";
    $params = [];
    
    // Dynamic check for assigned_faculty_id column to restrict visibility
    $check_cols = $pdo->query("SHOW COLUMNS FROM `fingerprint_tests` LIKE 'assigned_faculty_id'")->fetch();
    if ($check_cols) {
        $where_clause .= " AND ft.assigned_faculty_id = :faculty_id";
        $params[':faculty_id'] = $faculty_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
          ft.*,
          student.full_name AS student_name
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        $where_clause
        ORDER BY ft.submitted_at DESC
    ");
    $stmt->execute($params);
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

// Pre-loaded submission if id is passed in URL
$selected_trial = null;
if (isset($_GET['id'])) {
    $sel_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("
            SELECT 
              ft.*,
              student.full_name AS student_name
            FROM fingerprint_tests ft
            LEFT JOIN users student ON ft.student_id = student.id
            WHERE ft.id = ?
            LIMIT 1
        ");
        $stmt->execute([$sel_id]);
        $selected_trial = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($selected_trial) {
            $selected_trial['image_exists'] = false;
            if (!empty($selected_trial['image_path'])) {
                $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $selected_trial['image_path'];
                if (file_exists($filePath)) {
                    $selected_trial['image_exists'] = true;
                }
            }
        }
    } catch (PDOException $e) {}
}
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
        .badge-needs_revision { background:rgba(230,57,70,.12); color:#e63946; }
        
        .alert-msg { padding:.85rem 1.2rem; border-radius:10px; margin-bottom:1.5rem; font-weight:600; font-size:.9rem; }
        .alert-success { background:rgba(82,183,136,.12); color:#2d6a4f; border:1px solid rgba(82,183,136,.3); }
        .alert-error   { background:rgba(224,122,95,.12);  color:#c0392b; border:1px solid rgba(224,122,95,.3); }
        
        .stat-card.pending-card::after  { background: #f4a261; }
        .stat-card.approved-card::after { background: #52b788; }
        .stat-card.rejected-card::after { background: #e07a5f; }

        /* Workspace Grid Layout */
        .validate-workspace-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 1.5rem;
            margin-top: 1rem;
            align-items: start;
        }
        @media (max-width: 992px) {
            .validate-workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar Submissions Card */
        .workspace-sidebar-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid rgba(27,67,50,0.1);
            padding: 1.25rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            max-height: calc(100vh - 180px);
            display: flex;
            flex-direction: column;
        }
        .panel-heading {
            font-size: 1.1rem;
            color: #1b4332;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 1rem;
            border-bottom: 2px solid rgba(27,67,50,0.06);
            padding-bottom: 0.5rem;
        }
        .search-box-container {
            margin-bottom: 1rem;
        }
        .search-box-container input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
        }
        .search-box-container input:focus {
            border-color: #52b788;
            box-shadow: 0 0 0 3px rgba(82, 183, 136, 0.15);
        }
        .trials-list {
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-height: 500px;
            padding-right: 4px;
        }
        
        /* Custom Scrollbar */
        .trials-list::-webkit-scrollbar {
            width: 5px;
        }
        .trials-list::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .trials-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        
        /* List Items */
        .trial-list-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem;
            border-radius: 12px;
            background: #fdfdfd;
            border: 1px solid #f0f4f1;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .trial-list-item:hover {
            background: #f3f8f4;
            border-color: rgba(82, 183, 136, 0.25);
            transform: translateY(-1px);
        }
        .trial-list-item.active {
            background: #eaf4ed;
            border-color: #2d6a4f;
            box-shadow: 0 4px 12px rgba(45, 106, 79, 0.08);
        }
        .trial-item-thumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .trial-item-details {
            flex: 1;
            min-width: 0;
        }
        .trial-item-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #1b4332;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .trial-item-student {
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .trial-item-date {
            font-size: 0.7rem;
            color: #94a3b8;
        }
        .trial-item-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #f4a261;
            flex-shrink: 0;
        }

        /* Workspace Card */
        .workspace-main-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid rgba(27,67,50,0.1);
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            min-height: 450px;
        }
        .workspace-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-height: 400px;
            color: #94a3b8;
            text-align: center;
        }
        .workspace-empty-state svg {
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
        .workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid rgba(27,67,50,0.06);
            padding-bottom: 0.85rem;
            margin-bottom: 1.5rem;
        }
        .workspace-header h2 {
            font-size: 1.3rem;
            color: #1b4332;
            font-weight: 800;
            margin: 0;
        }
        .workspace-body-split {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 1.75rem;
            margin-bottom: 1.75rem;
        }
        @media (max-width: 768px) {
            .workspace-body-split {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }
        }
        .workspace-image-preview-container {
            background: #fafafa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 250px;
            margin-bottom: 1rem;
        }
        .workspace-image-preview-container img {
            max-height: 260px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        .workspace-image-preview-container img:hover {
            transform: scale(1.02);
        }
        .image-preview-overlay-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1b4332;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .meta-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            font-size: 0.85rem;
            margin-top: 1rem;
        }
        .meta-info-item {
            background: #fdfdfd;
            border: 1px solid #f1f5f2;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }
        .meta-info-label {
            font-size: 0.72rem;
            color: #8c9b90;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .meta-info-value {
            font-weight: 700;
            color: #2c4a3e;
            word-break: break-all;
        }
        .quality-metrics-container {
            background: #fcfdfe;
            border: 1px solid #eef3ef;
            border-radius: 12px;
            padding: 1.25rem;
        }
        .metrics-heading {
            font-size: 0.85rem;
            font-weight: 800;
            color: #1b4332;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 0;
            margin-bottom: 1rem;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(27,67,50,0.08);
        }
        .metric-score-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.85rem;
            background: #ffffff;
            border: 1px solid #f1f5f2;
            padding: 0.65rem 0.85rem;
            border-radius: 8px;
            gap: 1rem;
        }
        .metric-score-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            min-width: 100px;
        }
        .metric-score-progress {
            flex: 1;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
        }
        .metric-score-fill {
            height: 100%;
            background: #52b788;
            border-radius: 4px;
            transition: width 0.5s ease-out;
        }
        .metric-score-value {
            font-size: 0.8rem;
            font-weight: 700;
            color: #2d6a4f;
            width: 45px;
            text-align: right;
        }
        .metric-score-card.overall-card {
            background: rgba(82, 183, 136, 0.08);
            border: 1px dashed rgba(82, 183, 136, 0.4);
            margin-bottom: 1.25rem;
        }
        .metric-score-card.overall-card .metric-score-label {
            font-weight: 800;
            color: #1b4332;
        }
        .metric-score-card.overall-card .metric-score-fill {
            background: #2d6a4f;
        }
        .metric-score-card.overall-card .metric-score-value {
            color: #1b4332;
            font-size: 0.9rem;
        }
        .faculty-validation-box {
            background: #fbfcfb;
            border: 1.5px solid #d8ebd9;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        .validation-heading {
            font-size: 0.95rem;
            font-weight: 800;
            color: #1b4332;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 0;
            margin-bottom: 1rem;
            padding-bottom: 4px;
            border-bottom: 1.5px solid rgba(27,67,50,0.12);
        }
        .faculty-evaluation-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.25rem;
        }
        @media (max-width: 768px) {
            .faculty-evaluation-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        .form-group-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #1b4332;
            margin-bottom: 0.4rem;
            display: block;
        }
        .validation-btn-group {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1.2fr;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        @media (max-width: 576px) {
            .validation-btn-group {
                grid-template-columns: 1fr;
            }
        }
        .btn-validation {
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #ffffff;
            position: relative;
        }
        .btn-validation:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-approve-workspace { background: #2d6a4f; box-shadow: 0 4px 12px rgba(45, 106, 79, 0.15); }
        .btn-approve-workspace:hover:not(:disabled) { background: #1b4332; transform: translateY(-1px); }
        .btn-reject-workspace { background: #e07a5f; box-shadow: 0 4px 12px rgba(224, 122, 95, 0.15); }
        .btn-reject-workspace:hover:not(:disabled) { background: #c0392b; transform: translateY(-1px); }
        .btn-revision-workspace { background: #f4a261; box-shadow: 0 4px 12px rgba(244, 162, 97, 0.15); }
        .btn-revision-workspace:hover:not(:disabled) { background: #e76f51; transform: translateY(-1px); }
        .spinner-loader {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
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
                    <p>Review student fingerprint trial submissions, verify quality metrics, and input final decisions.</p>
                </div>
            </div>

            <!-- Toast alert box -->
            <div id="alertContainer"></div>

            <!-- SUMMARY STATS CARDS -->
            <div class="stats-grid" style="margin-bottom: 1.5rem;">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Submissions</span>
                        <div class="stat-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                    </div>
                    <div class="stat-value" id="val-total-submissions"><?= $total_submissions ?></div>
                    <div class="stat-desc">Student trial submissions</div>
                </div>
                <div class="stat-card pending-card">
                    <div class="stat-header">
                        <span class="stat-title">Pending Validation</span>
                        <div class="stat-icon" style="background:rgba(244,162,97,.12);color:#c97d2a;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                    </div>
                    <div class="stat-value" id="val-pending" style="color:#c97d2a;"><?= $pending ?></div>
                    <div class="stat-desc">Awaiting faculty review</div>
                </div>
                <div class="stat-card approved-card">
                    <div class="stat-header">
                        <span class="stat-title">Approved Records</span>
                        <div class="stat-icon" style="background:rgba(82,183,136,.12);color:#2d6a4f;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
                    </div>
                    <div class="stat-value" id="val-approved" style="color:#2d6a4f;"><?= $approved ?></div>
                    <div class="stat-desc">Validated and confirmed</div>
                </div>
                <div class="stat-card rejected-card">
                    <div class="stat-header">
                        <span class="stat-title">Rejected Records</span>
                        <div class="stat-icon" style="background:rgba(224,122,95,.12);color:#c0392b;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
                    </div>
                    <div class="stat-value" id="val-rejected" style="color:#c0392b;"><?= $rejected ?></div>
                    <div class="stat-desc">Returned for revision</div>
                </div>
            </div>

            <!-- WORKSPACE LAYOUT -->
            <div class="validate-workspace-grid">
                
                <!-- Left Sidebar: Submissions List -->
                <div class="workspace-sidebar-card">
                    <h3 class="panel-heading">Pending Trials</h3>
                    <div class="search-box-container">
                        <input type="text" id="trialSearch" placeholder="Search student or trial ID..." oninput="filterTrials()">
                    </div>
                    <div class="trials-list" id="trialsListContainer">
                        <!-- Loaded dynamically -->
                    </div>
                </div>

                <!-- Right Sidebar: Workspace Panel -->
                <div class="workspace-main-card" id="workspaceMainCard">
                    <!-- Selected trial workspace details loaded dynamically -->
                </div>

            </div>
        </div>
    </main>
</div>

<script>
const trialsData = <?php echo json_encode($submissions); ?>;
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let selectedTrial = <?php echo $selected_trial ? json_encode($selected_trial) : 'null'; ?>;
let activeTrialId = selectedTrial ? selectedTrial.id : null;
let isSubmitting = false;

// Auto select first trial if none is active but pending trials exist
if (!activeTrialId && trialsData.length > 0) {
    activeTrialId = trialsData[0].id;
    selectedTrial = trialsData[0];
}

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

function renderTrialsList() {
    const listContainer = document.getElementById('trialsListContainer');
    if (trialsData.length === 0) {
        listContainer.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8;font-size:0.85rem;">No pending submissions assigned for validation.</div>';
        return;
    }

    const query = document.getElementById('trialSearch').value.toLowerCase().trim();
    
    // Filter trials based on search query
    const filtered = trialsData.filter(t => {
        const student = (t.student_name || '').toLowerCase();
        const trialId = (t.trial_id || 'TR-' + String(t.id).padStart(4, '0')).toLowerCase();
        return student.includes(query) || trialId.includes(query);
    });

    if (filtered.length === 0) {
        listContainer.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8;font-size:0.85rem;">No matching submissions found</div>';
        return;
    }

    listContainer.innerHTML = filtered.map(t => {
        const isActive = t.id == activeTrialId ? 'active' : '';
        const trialLabel = t.trial_id || 'TR-' + String(t.id).padStart(4, '0');
        const studentName = escapeHtml(t.student_name || 'Unknown');
        const formattedDate = new Date(t.submitted_at.replace(/-/g, "/")).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        
        let imgHtml = '<div class="trial-item-thumb"><span style="font-size:0.6rem;color:#94a3b8;font-weight:600;">No Image</span></div>';
        if (t.image_path && t.image_exists) {
            imgHtml = `<img class="trial-item-thumb" src="../view_fingerprint.php?test_id=${t.id}" alt="Thumb">`;
        }

        return `
            <div class="trial-list-item ${isActive}" onclick="selectTrial(${t.id})" data-id="${t.id}">
                ${imgHtml}
                <div class="trial-item-details">
                    <div class="trial-item-title">${trialLabel}</div>
                    <div class="trial-item-student">${studentName}</div>
                    <div class="trial-item-date">${formattedDate}</div>
                </div>
                <div class="trial-item-status-dot"></div>
            </div>
        `;
    }).join('');
}

function selectTrial(id) {
    if (isSubmitting) return;
    activeTrialId = id;
    
    // Check local arrays
    const trial = trialsData.find(t => t.id == id);
    if (trial) {
        selectedTrial = trial;
        renderWorkspace();
    } else if (selectedTrial && selectedTrial.id == id) {
        renderWorkspace();
    }
    
    // Update active UI classes
    document.querySelectorAll('.trial-list-item').forEach(item => {
        if (item.getAttribute('data-id') == id) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

function filterTrials() {
    renderTrialsList();
}

function renderWorkspace() {
    const card = document.getElementById('workspaceMainCard');
    if (!selectedTrial) {
        card.innerHTML = `
            <div class="workspace-empty-state">
                <svg viewBox="0 0 24 24" width="60" height="60" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h3>No pending submissions assigned for validation.</h3>
                <p>All assigned submissions have been validated successfully.</p>
            </div>
        `;
        return;
    }

    const t = selectedTrial;
    const trialLabel = t.trial_id || 'TR-' + String(t.id).padStart(4, '0');
    const studentName = escapeHtml(t.student_name || 'Unknown');
    const formattedDate = new Date(t.submitted_at.replace(/-/g, "/")).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    const imageLabel = escapeHtml(t.image_label || 'Untitled');
    const powderType = escapeHtml(t.powder_type || '');
    const surfaceType = escapeHtml(t.surface_type || '');
    
    // Metrics values
    const clarity = t.ridge_clarity_score !== null ? parseFloat(t.ridge_clarity_score) : null;
    const visibility = t.visibility_score !== null ? parseFloat(t.visibility_score) : null;
    const adhesion = t.adhesion_score !== null ? parseFloat(t.adhesion_score) : null;
    const contrast = t.contrast_score !== null ? parseFloat(t.contrast_score) : null;
    const accuracy = t.ai_accuracy_score !== null ? parseFloat(t.ai_accuracy_score) : (t.accuracy_score !== null ? parseFloat(t.accuracy_score) : null);

    // Displays
    const displayClarity = clarity !== null ? clarity.toFixed(1) + '%' : 'N/A';
    const displayVisibility = visibility !== null ? visibility.toFixed(1) + '%' : 'N/A';
    const displayAdhesion = adhesion !== null ? adhesion.toFixed(1) + '%' : 'N/A';
    const displayContrast = contrast !== null ? contrast.toFixed(1) + '%' : 'N/A';
    const displayAccuracy = accuracy !== null ? accuracy.toFixed(1) + '%' : 'N/A';

    // Width styles
    const widthClarity = clarity !== null ? clarity + '%' : '0%';
    const widthVisibility = visibility !== null ? visibility + '%' : '0%';
    const widthAdhesion = adhesion !== null ? adhesion + '%' : '0%';
    const widthContrast = contrast !== null ? contrast + '%' : '0%';
    const widthAccuracy = accuracy !== null ? accuracy + '%' : '0%';

    // Status Badge
    let statusClass = 'badge-pending';
    let statusText = 'Pending Validation';
    if (t.status === 'approved') {
        statusClass = 'badge-approved';
        statusText = 'Approved';
    } else if (t.status === 'rejected') {
        statusClass = 'badge-rejected';
        statusText = 'Rejected';
    } else if (t.status === 'needs_revision') {
        statusClass = 'badge-needs_revision';
        statusText = 'Needs Revision';
    }

    // Use secure view_fingerprint.php script for previews
    let imgHtml = `
        <div style="background:#f1f5f9;width:100%;height:220px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ef4444;font-weight:600;">
            Image not found
        </div>`;
    if (t.image_path && t.image_exists) {
        imgHtml = `
            <img src="../view_fingerprint.php?test_id=${t.id}" alt="Fingerprint Preview Image">
            <a href="../view_fingerprint.php?test_id=${t.id}" target="_blank" class="image-preview-overlay-btn">View Original</a>
        `;
    }

    // Default Faculty Final Score input to the AI overall accuracy
    const finalScoreDefault = accuracy !== null ? accuracy.toFixed(1) : '80.0';

    card.innerHTML = `
        <div class="workspace-header">
            <h2>Validation Workspace — ${trialLabel}</h2>
            <span class="badge ${statusClass}">${statusText}</span>
        </div>
        
        <div class="workspace-body-split">
            <!-- Left Column: Previews & Info -->
            <div>
                <div class="workspace-image-preview-container">
                    ${imgHtml}
                </div>
                
                <div class="meta-info-grid">
                    <div class="meta-info-item">
                        <div class="meta-info-label">Student Name</div>
                        <div class="meta-info-value">${studentName}</div>
                    </div>
                    <div class="meta-info-item">
                        <div class="meta-info-label">Trial ID</div>
                        <div class="meta-info-value">${trialLabel}</div>
                    </div>
                    <div class="meta-info-item">
                        <div class="meta-info-label">Powder Type</div>
                        <div class="meta-info-value" style="text-transform:capitalize;">${powderType}</div>
                    </div>
                    <div class="meta-info-item">
                        <div class="meta-info-label">Surface Type</div>
                        <div class="meta-info-value" style="text-transform:capitalize;">${surfaceType}</div>
                    </div>
                    <div class="meta-info-item">
                        <div class="meta-info-label">Image Label</div>
                        <div class="meta-info-value">${imageLabel}</div>
                    </div>
                    <div class="meta-info-item">
                        <div class="meta-info-label">Date Submitted</div>
                        <div class="meta-info-value">${formattedDate}</div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Read-only AI Quality Metrics -->
            <div>
                <div class="quality-metrics-container">
                    <h3 class="metrics-heading">AI Preliminary Quality Metrics (Read-Only)</h3>
                    
                    <div class="metric-score-card overall-card">
                        <div class="metric-score-label">AI Accuracy</div>
                        <div class="metric-score-progress">
                            <div class="metric-score-fill" style="width: ${widthAccuracy}"></div>
                        </div>
                        <div class="metric-score-value">${displayAccuracy}</div>
                    </div>
                    
                    <div class="metric-score-card">
                        <div class="metric-score-label">AI Ridge Clarity</div>
                        <div class="metric-score-progress">
                            <div class="metric-score-fill" style="width: ${widthClarity}"></div>
                        </div>
                        <div class="metric-score-value">${displayClarity}</div>
                    </div>
                    
                    <div class="metric-score-card">
                        <div class="metric-score-label">AI Visibility</div>
                        <div class="metric-score-progress">
                            <div class="metric-score-fill" style="width: ${widthVisibility}"></div>
                        </div>
                        <div class="metric-score-value">${displayVisibility}</div>
                    </div>
                    
                    <div class="metric-score-card">
                        <div class="metric-score-label">AI Adhesion</div>
                        <div class="metric-score-progress">
                            <div class="metric-score-fill" style="width: ${widthAdhesion}"></div>
                        </div>
                        <div class="metric-score-value">${displayAdhesion}</div>
                    </div>
                    
                    <div class="metric-score-card">
                        <div class="metric-score-label">AI Contrast</div>
                        <div class="metric-score-progress">
                            <div class="metric-score-fill" style="width: ${widthContrast}"></div>
                        </div>
                        <div class="metric-score-value">${displayContrast}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Faculty Validation Panel -->
        <div class="faculty-validation-box">
            <h3 class="validation-heading">Faculty Final Evaluation</h3>
            
            <div class="faculty-evaluation-grid">
                <!-- Left column of scores -->
                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                        <label class="form-group-label" for="faculty_accuracy_input" style="margin: 0; font-weight: 600; color: #1b4332;">Final Accuracy Score (%)</label>
                        <input type="number" id="faculty_accuracy_input" class="form-control-plain" min="0" max="100" step="0.01" value="${accuracy !== null ? accuracy.toFixed(1) : ''}" style="width: 100px; text-align: right; font-weight: 700; color: #1b4332; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                        <label class="form-group-label" for="faculty_clarity_input" style="margin: 0; font-weight: 600; color: #1b4332;">Final Ridge Clarity (%)</label>
                        <input type="number" id="faculty_clarity_input" class="form-control-plain" min="0" max="100" step="0.01" value="${clarity !== null ? clarity.toFixed(1) : ''}" style="width: 100px; text-align: right; font-weight: 700; color: #1b4332; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                        <label class="form-group-label" for="faculty_visibility_input" style="margin: 0; font-weight: 600; color: #1b4332;">Final Visibility (%)</label>
                        <input type="number" id="faculty_visibility_input" class="form-control-plain" min="0" max="100" step="0.01" value="${visibility !== null ? visibility.toFixed(1) : ''}" style="width: 100px; text-align: right; font-weight: 700; color: #1b4332; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                        <label class="form-group-label" for="faculty_adhesion_input" style="margin: 0; font-weight: 600; color: #1b4332;">Final Adhesion (%)</label>
                        <input type="number" id="faculty_adhesion_input" class="form-control-plain" min="0" max="100" step="0.01" value="${adhesion !== null ? adhesion.toFixed(1) : ''}" style="width: 100px; text-align: right; font-weight: 700; color: #1b4332; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                        <label class="form-group-label" for="faculty_contrast_input" style="margin: 0; font-weight: 600; color: #1b4332;">Final Contrast (%)</label>
                        <input type="number" id="faculty_contrast_input" class="form-control-plain" min="0" max="100" step="0.01" value="${contrast !== null ? contrast.toFixed(1) : ''}" style="width: 100px; text-align: right; font-weight: 700; color: #1b4332; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                    </div>
                </div>
                
                <!-- Right column for Remarks -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label class="form-group-label" for="faculty_remarks_input" style="margin: 0;">Faculty Remarks / Evaluation Feedback</label>
                    <textarea id="faculty_remarks_input" class="form-control-plain" rows="6" placeholder="Provide evaluation remarks here..." style="flex: 1; min-height: 120px; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.9rem; font-family: inherit; resize: none; outline: none;"></textarea>
                    <p id="workspaceRemarksWarning" style="color:#c0392b; font-size:0.75rem; font-weight:600; margin-top:4px; margin-bottom:0; display:none;">Remarks are required to reject or request revision.</p>
                </div>
            </div>
            
            <div class="validation-btn-group">
                <button type="button" class="btn-validation btn-approve-workspace" id="btnApprove" onclick="performValidation('approve')">
                    <span>Approve Submission</span>
                </button>
                <button type="button" class="btn-validation btn-reject-workspace" id="btnReject" onclick="performValidation('reject')">
                    <span>Reject</span>
                </button>
                <button type="button" class="btn-validation btn-revision-workspace" id="btnRevision" onclick="performValidation('needs_revision')">
                    <span>Needs Revision</span>
                </button>
            </div>
        </div>
    `;
}

function performValidation(action) {
    if (isSubmitting || !selectedTrial) return;
    
    const remarks = document.getElementById('faculty_remarks_input').value.trim();
    const warning = document.getElementById('workspaceRemarksWarning');
    
    // Remarks is strictly required for Reject and Needs Revision
    if ((action === 'reject' || action === 'needs_revision') && !remarks) {
        warning.style.display = 'block';
        document.getElementById('faculty_remarks_input').focus();
        return;
    }
    warning.style.display = 'none';

    let accuracy = 0, clarity = 0, visibility = 0, adhesion = 0, contrast = 0;
    
    // Validation final score check (0 to 100) for all 5 fields
    if (action === 'approve') {
        accuracy = parseFloat(document.getElementById('faculty_accuracy_input').value);
        clarity = parseFloat(document.getElementById('faculty_clarity_input').value);
        visibility = parseFloat(document.getElementById('faculty_visibility_input').value);
        adhesion = parseFloat(document.getElementById('faculty_adhesion_input').value);
        contrast = parseFloat(document.getElementById('faculty_contrast_input').value);
        
        if (
            isNaN(accuracy) || accuracy < 0 || accuracy > 100 ||
            isNaN(clarity) || clarity < 0 || clarity > 100 ||
            isNaN(visibility) || visibility < 0 || visibility > 100 ||
            isNaN(adhesion) || adhesion < 0 || adhesion > 100 ||
            isNaN(contrast) || contrast < 0 || contrast > 100
        ) {
            alert("Please enter a valid score between 0 and 100 for all 5 evaluation metrics.");
            return;
        }
    }

    const btnApprove = document.getElementById('btnApprove');
    const btnReject = document.getElementById('btnReject');
    const btnRevision = document.getElementById('btnRevision');
    
    let originalText = '';
    let activeBtn = null;
    
    if (action === 'approve') {
        activeBtn = btnApprove;
        originalText = 'Approve Submission';
    } else if (action === 'reject') {
        activeBtn = btnReject;
        originalText = 'Reject';
    } else if (action === 'needs_revision') {
        activeBtn = btnRevision;
        originalText = 'Needs Revision';
    }
    
    btnApprove.disabled = true;
    btnReject.disabled = true;
    btnRevision.disabled = true;
    
    activeBtn.innerHTML = '<span class="spinner-loader"></span> Processing...';
    isSubmitting = true;

    const formData = new FormData();
    formData.append('test_id', selectedTrial.id);
    formData.append('remarks', remarks);
    formData.append('csrf_token', csrfToken);

    let endpoint = '';
    if (action === 'approve') {
        endpoint = 'ajax_approve_trial.php';
        formData.append('faculty_accuracy_score', accuracy);
        formData.append('faculty_ridge_clarity_score', clarity);
        formData.append('faculty_visibility_score', visibility);
        formData.append('faculty_adhesion_score', adhesion);
        formData.append('faculty_contrast_score', contrast);
    } else if (action === 'reject') {
        endpoint = 'ajax_reject_trial.php';
    } else if (action === 'needs_revision') {
        endpoint = 'ajax_needs_revision.php';
    }

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
        btnApprove.disabled = false;
        btnReject.disabled = false;
        btnRevision.disabled = false;
        
        if (action === 'approve') btnApprove.textContent = originalText;
        else if (action === 'reject') btnReject.textContent = originalText;
        else if (action === 'needs_revision') btnRevision.textContent = originalText;

        if (data.success) {
            showNotification('success', data.message);
            
            // Remove validated trial from local state array
            const index = trialsData.findIndex(t => t.id == selectedTrial.id);
            if (index !== -1) {
                trialsData.splice(index, 1);
            }
            
            // Select next pending trial
            if (trialsData.length > 0) {
                const nextIndex = index < trialsData.length ? index : trialsData.length - 1;
                activeTrialId = trialsData[nextIndex].id;
                selectedTrial = trialsData[nextIndex];
            } else {
                activeTrialId = null;
                selectedTrial = null;
            }
            
            renderTrialsList();
            renderWorkspace();
            refreshFacultyStats();
        } else {
            showNotification('danger', data.message);
        }
    })
    .catch(err => {
        isSubmitting = false;
        btnApprove.disabled = false;
        btnReject.disabled = false;
        btnRevision.disabled = false;
        
        if (action === 'approve') btnApprove.textContent = originalText;
        else if (action === 'reject') btnReject.textContent = originalText;
        else if (action === 'needs_revision') btnRevision.textContent = originalText;
        
        showNotification('danger', 'An error occurred during submission.');
    });
}

function showNotification(type, message) {
    const container = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    container.innerHTML = `<div class="alert-msg ${alertClass}">${message}</div>`;
    setTimeout(() => { container.innerHTML = ''; }, 6000);
}

function refreshFacultyStats() {
    fetch('ajax_get_faculty_dashboard_stats.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const stats = data.data;
                const totalVal = document.getElementById('val-total-submissions');
                const pendingVal = document.getElementById('val-pending');
                const approvedVal = document.getElementById('val-approved');
                const rejectedVal = document.getElementById('val-rejected');
                
                if (totalVal) totalVal.textContent = stats.total_submissions;
                if (pendingVal) pendingVal.textContent = stats.pending;
                if (approvedVal) approvedVal.textContent = stats.approved;
                if (rejectedVal) rejectedVal.textContent = stats.rejected;
            }
        })
        .catch(err => console.error("Error refreshing stats:", err));
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    
    // Initial Render of List & Workspace
    renderTrialsList();
    renderWorkspace();
});
</script>
</body>
</html>
