<?php
// faculty/comparison_dashboard.php - Powder Comparison Dashboard
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';

// Filters
$f_powder  = $_GET['powder']  ?? '';
$f_surface = $_GET['surface'] ?? '';
$f_status  = $_GET['status']  ?? '';
$f_from    = $_GET['from']    ?? '';
$f_to      = $_GET['to']      ?? '';

$where = ['1=1'];
$params = [];
if ($f_powder)  { $where[] = 'ft.powder_type=?';  $params[] = $f_powder; }
if ($f_surface) { $where[] = 'ft.surface_type=?'; $params[] = $f_surface; }
if ($f_status)  { $where[] = 'ft.status=?';       $params[] = $f_status; }
if ($f_from)    { $where[] = 'DATE(ft.submitted_at)>=?'; $params[] = $f_from; }
if ($f_to)      { $where[] = 'DATE(ft.submitted_at)<=?'; $params[] = $f_to;   }

$sql = "SELECT ft.*, u.full_name AS student_name FROM fingerprint_tests ft
        JOIN users u ON u.id=ft.student_id
        WHERE ".implode(' AND ', $where)."
        ORDER BY ft.submitted_at DESC";

$rows = [];
try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); }
catch (PDOException $e) {}

// Group by surface for comparison summary
$eggshell_avg = $commercial_avg = 0;
$eg_count = $co_count = 0;
$eg_success = $co_success = 0;
$surface_stats = [];

foreach ($rows as $r) {
    $surf = $r['surface_type'];
    if(!isset($surface_stats[$surf])) {
        $surface_stats[$surf] = ['count'=>0, 'total_acc'=>0, 'success'=>0];
    }
    $surface_stats[$surf]['count']++;
    $surface_stats[$surf]['total_acc'] += $r['accuracy_score'];
    if($r['status'] === 'approved') $surface_stats[$surf]['success']++;

    if ($r['powder_type']==='eggshell')   { 
        $eggshell_avg  += $r['accuracy_score']; 
        $eg_count++; 
        if($r['status'] === 'approved') $eg_success++;
    }
    if ($r['powder_type']==='commercial') { 
        $commercial_avg += $r['accuracy_score']; 
        $co_count++; 
        if($r['status'] === 'approved') $co_success++;
    }
}
$eggshell_avg  = $eg_count ? round($eggshell_avg/$eg_count,1)  : 0;
$commercial_avg= $co_count ? round($commercial_avg/$co_count,1): 0;
$eg_success_rate = $eg_count ? round(($eg_success/$eg_count)*100,1) : 0;
$co_success_rate = $co_count ? round(($co_success/$co_count)*100,1) : 0;
$total_trials = count($rows);

$best_surface = 'N/A';
$highest_acc = 0;
foreach($surface_stats as $surf => $stats) {
    $avg = $stats['count'] ? $stats['total_acc']/$stats['count'] : 0;
    if($avg > $highest_acc) {
        $highest_acc = $avg;
        $best_surface = ucfirst($surf);
    }
}

// Pair extraction for side-by-side
$pairs = [];
foreach($rows as $r) {
    $key = $r['student_id'].'_'.$r['surface_type'];
    if(!isset($pairs[$key])) {
        $pairs[$key] = ['student_name' => $r['student_name'], 'surface_type' => $r['surface_type']];
    }
    $pairs[$key][$r['powder_type']] = $r;
}
$valid_pairs = array_filter($pairs, function($p) { return isset($p['eggshell']) && isset($p['commercial']); });
$selected_pair_key = $_GET['compare_pair'] ?? (count($valid_pairs) > 0 ? array_key_first($valid_pairs) : null);
$selected_pair = $selected_pair_key && isset($valid_pairs[$selected_pair_key]) ? $valid_pairs[$selected_pair_key] : null;

// Chart Data
$chart_surface_labels = json_encode(array_map('ucfirst', array_keys($surface_stats)));
$chart_surface_counts = json_encode(array_column($surface_stats, 'count'));
$chart_surface_success = json_encode(array_map(function($s) { return $s['count'] ? round(($s['success']/$s['count'])*100,1) : 0; }, $surface_stats));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparison Dashboard - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .badge-pending_validation { background:rgba(244,162,97,.15); color:#c97d2a; border:1px solid rgba(244,162,97,.25); }
        .badge-needs_revision { background:rgba(230,57,70,.12); color:#e63946; border:1px solid rgba(230,57,70,.2); }
        .badge-approved { background:rgba(82,183,136,.15); color:#2d6a4f; border:1px solid rgba(82,183,136,.25); }
        .badge-rejected { background:rgba(224,122,95,.15); color:#c0392b; border:1px solid rgba(224,122,95,.2); }
        
        .powder-eggshell{background:rgba(82,183,136,.12);color:#2d6a4f;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:700;}
        .powder-commercial{background:rgba(108,117,125,.12);color:#495057;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:700;}
        
        /* 6 Summary Cards Layout */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .compare-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(27,67,50,.05);
            border: 1px solid rgba(27,67,50,.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .compare-card h3 {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #6c757d;
            margin-bottom: .5rem;
        }
        .compare-big {
            font-size: 2rem;
            font-weight: 800;
            color: #1b4332;
            line-height: 1;
        }
        .compare-sub {
            font-size: .8rem;
            color: #6c757d;
            margin-top: .5rem;
        }
        
        /* Filters */
        .filter-form {
            background: #fff;
            padding: 1.5rem;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(27,67,50,.04);
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        .filter-form > div { flex: 1; min-width: 150px; }
        .filter-form label { font-size:.75rem; font-weight:700; color:#1b4332; text-transform:uppercase; margin-bottom:.4rem; display:block; }
        .filter-form select, .filter-form input { width: 100%; padding:.6rem 1rem; border:1px solid #e9ecef; border-radius:8px; font-size:.85rem; outline:none; }
        .filter-form select:focus, .filter-form input:focus { border-color:#2d6a4f; }
        
        /* Side by Side Viewer */
        .comparison-viewer {
            background: #fff;
            border-radius: 14px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(27,67,50,.04);
            margin-bottom: 2rem;
        }
        .viewer-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .viewer-panes { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .pane { border: 1px solid #e9ecef; border-radius: 12px; padding: 1.5rem; }
        .pane h4 { color: #1b4332; margin-bottom: 1rem; display:flex; justify-content:space-between; align-items:center; }
        .img-placeholder { width:100%; height: 250px; background:#f8f9fa; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#adb5bd; margin-bottom: 1rem; overflow:hidden;}
        .img-placeholder img { width:100%; height:100%; object-fit:cover; }
        .metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .metric-box { background: #f8f9fa; padding: 1rem; border-radius: 8px; text-align: center; }
        .metric-box span { display: block; font-size: .75rem; color:#6c757d; text-transform:uppercase; margin-bottom:.3rem; font-weight:600; }
        .metric-box strong { font-size: 1.25rem; color:#1b4332; }
        
        /* Table & Action Buttons */
        .actions-top { display:flex; justify-content: flex-end; gap: .5rem; margin-bottom: 1rem; }
        .icon-btn-action { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:6px; border:none; cursor:pointer; background:#f4f6f0; color:#2d6a4f; transition:all 0.2s; }
        .icon-btn-action:hover { background:#2d6a4f; color:#fff; }
        
        /* Charts */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        .chart-card {
            background: #fff;
            padding: 1.75rem;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(27,67,50,.04);
            border: 1px solid rgba(27,67,50,.05);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .chart-card.large-chart {
            grid-column: span 2;
        }
        .chart-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1b4332;
            margin-top: 0;
            margin-bottom: 1.25rem;
            border-left: 4px solid #2d6a4f;
            padding-left: 0.75rem;
            text-align: left;
        }
        .chart-container {
            position: relative;
            width: 100%;
            height: 320px;
        }
        
        .empty-state { text-align:center; padding: 4rem 2rem; color: #6c757d; }
        .empty-state svg { width: 48px; height: 48px; opacity: 0.2; margin-bottom: 1rem; }
        
        @media (max-width: 992px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .viewer-panes { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: 1fr; }
            .charts-section { grid-template-columns: 1fr; }
            .chart-card.large-chart { grid-column: span 1; }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="brand-text"><span>GREEN</span><span class="brand-accent">FORENSICS</span></div></div>
        <div class="sidebar-user"><div class="user-info"><div class="user-avatar">FR</div><div class="user-details"><h4><?= htmlspecialchars($faculty_name) ?></h4><span>Faculty Researcher</span></div></div></div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="faculty_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg><span>Dashboard</span></a></li>
            <li class="menu-item active"><a href="comparison_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Comparison Dashboard</span></a></li>
            <li class="menu-item"><a href="validate_accuracy.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Validate Accuracy Scores</span></a></li>
            <li class="menu-item"><a href="surface_performance.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg><span>Surface Performance</span></a></li>
            <li class="menu-item"><a href="safety_climate_log.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Safety &amp; Climate Log</span></a></li>
            <li class="menu-item"><a href="student_records.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>View Student Records</span></a></li>
            <li class="menu-item"><a href="generate_reports.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Generate Reports</span></a></li>
        </ul>
        <div class="sidebar-footer"><a href="../logout.php" class="menu-link" style="color:#e07a5f;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a></div>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="header-title"><h2>Green Forensics — Comparison Dashboard</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Powder Comparison Dashboard</h1>
                    <p>Compare eggshell-based vs. commercial fingerprint powder results.</p>
                </div>
            </div>

            <!-- 6 Summary Cards -->
            <div class="summary-grid">
                <div class="compare-card">
                    <h3>Total Trials</h3>
                    <div class="compare-big"><?= $total_trials ?></div>
                </div>
                <div class="compare-card">
                    <h3>Eggshell Avg Accuracy</h3>
                    <div class="compare-big"><?= $eggshell_avg ?>%</div>
                </div>
                <div class="compare-card">
                    <h3>Commercial Avg Accuracy</h3>
                    <div class="compare-big" style="color:#495057;"><?= $commercial_avg ?>%</div>
                </div>
                <div class="compare-card">
                    <h3>Best Surface Type</h3>
                    <div class="compare-big"><?= $best_surface ?></div>
                </div>
                <div class="compare-card">
                    <h3>Eggshell Success Rate</h3>
                    <div class="compare-big"><?= $eg_success_rate ?>%</div>
                    <div class="compare-sub">Trials marked as Approved</div>
                </div>
                <div class="compare-card">
                    <h3>Commercial Success Rate</h3>
                    <div class="compare-big" style="color:#495057;"><?= $co_success_rate ?>%</div>
                    <div class="compare-sub">Trials marked as Approved</div>
                </div>
            </div>

            <!-- Filter Section -->
            <form method="GET" class="filter-form">
                <div>
                    <label>Powder Type</label>
                    <select name="powder">
                        <option value="">All Powder Types</option>
                        <option value="eggshell"   <?= $f_powder==='eggshell'   ?'selected':'' ?>>Eggshell</option>
                        <option value="commercial" <?= $f_powder==='commercial' ?'selected':'' ?>>Commercial</option>
                    </select>
                </div>
                <div>
                    <label>Surface Type</label>
                    <select name="surface">
                        <option value="">All Surfaces</option>
                        <?php foreach(['glass','plastic','metal','wood'] as $s): ?>
                        <option value="<?=$s?>" <?= $f_surface===$s?'selected':'' ?>><?=ucfirst($s)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="pending_validation" <?= $f_status==='pending_validation' ?'selected':'' ?>>Pending Validation</option>
                        <option value="approved"           <?= $f_status==='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected"           <?= $f_status==='rejected'?'selected':'' ?>>Rejected</option>
                        <option value="needs_revision"     <?= $f_status==='needs_revision'?'selected':'' ?>>Needs Revision</option>
                    </select>
                </div>
                <div>
                    <label>Date From</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($f_from) ?>">
                </div>
                <div>
                    <label>Date To</label>
                    <input type="date" name="to" value="<?= htmlspecialchars($f_to) ?>">
                </div>
                <div style="flex:0; min-width:auto; display:flex; gap:.5rem;">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="comparison_dashboard.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <!-- Analytics Summary Section -->
            <div style="margin-top: 1rem; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; color: #1b4332; margin-bottom: 0.25rem; font-weight: 700;">Analytics Summary</h2>
                <p style="font-size: 0.95rem; color: #6c757d; margin: 0;">Visual comparison of fingerprint evaluation results based on powder type, surface material, and trial distribution.</p>
            </div>

            <div class="charts-section">
                <div class="chart-card large-chart">
                    <h3>Accuracy by Powder Type</h3>
                    <?php if($total_trials > 0): ?>
                        <div class="chart-container">
                            <canvas id="accuracyChart"></canvas>
                        </div>
                    <?php else: ?>
                        <p style="color:#adb5bd; text-align:center; padding:2rem 0; font-size:.85rem;">No chart data available yet.</p>
                    <?php endif; ?>
                </div>
                <div class="chart-card">
                    <h3>Success Rate by Surface Type</h3>
                    <?php if($total_trials > 0): ?>
                        <div class="chart-container">
                            <canvas id="successChart"></canvas>
                        </div>
                    <?php else: ?>
                        <p style="color:#adb5bd; text-align:center; padding:2rem 0; font-size:.85rem;">No chart data available yet.</p>
                    <?php endif; ?>
                </div>
                <div class="chart-card">
                    <h3>Trial Count by Surface</h3>
                    <?php if($total_trials > 0): ?>
                        <div class="chart-container">
                            <canvas id="trialChart"></canvas>
                        </div>
                    <?php else: ?>
                        <p style="color:#adb5bd; text-align:center; padding:2rem 0; font-size:.85rem;">No chart data available yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Side-by-Side Comparison -->
            <div class="comparison-viewer">
                <div class="viewer-header">
                    <h3>Side-by-Side Fingerprint Image Comparison</h3>
                    <form method="GET" style="display:flex; gap:.5rem;">
                        <!-- Keep existing filters in URL when changing pair -->
                        <input type="hidden" name="powder" value="<?=htmlspecialchars($f_powder)?>">
                        <input type="hidden" name="surface" value="<?=htmlspecialchars($f_surface)?>">
                        <input type="hidden" name="status" value="<?=htmlspecialchars($f_status)?>">
                        <input type="hidden" name="from" value="<?=htmlspecialchars($f_from)?>">
                        <input type="hidden" name="to" value="<?=htmlspecialchars($f_to)?>">
                        
                        <select name="compare_pair" class="form-control" style="width:250px; font-size:.85rem;" onchange="this.form.submit()">
                            <option value="">Select Comparison Record...</option>
                            <?php foreach($valid_pairs as $k => $p): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $k === $selected_pair_key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['student_name']) ?> - <?= ucfirst($p['surface_type']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <?php if($selected_pair): ?>
                <div class="viewer-panes">
                    <!-- Left: Eggshell -->
                    <div class="pane">
                        <h4>
                            <span>Eggshell-Based Powder Image</span>
                            <span class="powder-eggshell">Eggshell</span>
                        </h4>
                        <div class="img-placeholder">
                            <?php if(!empty($selected_pair['eggshell']['image_path']) && file_exists(dirname(__DIR__) . '/uploads/fingerprints/' . $selected_pair['eggshell']['image_path'])): ?>
                                <img src="../uploads/fingerprints/<?= htmlspecialchars($selected_pair['eggshell']['image_path']) ?>" alt="Eggshell Print">
                            <?php else: ?>
                                No Image Available
                            <?php endif; ?>
                        </div>
                        <div style="margin-bottom:1rem; font-size:.85rem; color:#555;">
                            <strong>Student:</strong> <?= htmlspecialchars($selected_pair['eggshell']['student_name']) ?><br>
                            <strong>Surface:</strong> <?= ucfirst($selected_pair['eggshell']['surface_type']) ?>
                        </div>
                        <div class="metrics-grid">
                            <div class="metric-box"><span>Accuracy</span><strong><?= $selected_pair['eggshell']['accuracy_score'] ?>%</strong></div>
                            <div class="metric-box"><span>Ridge Clarity</span><strong><?= $selected_pair['eggshell']['ridge_clarity_score'] ?>%</strong></div>
                            <div class="metric-box"><span>Visibility</span><strong><?= $selected_pair['eggshell']['visibility_score'] ?>%</strong></div>
                            <div class="metric-box"><span>Adhesion</span><strong><?= $selected_pair['eggshell']['adhesion_score'] ?>%</strong></div>
                            <div class="metric-box" style="grid-column: span 2;"><span>Contrast</span><strong><?= $selected_pair['eggshell']['contrast_score'] !== null ? $selected_pair['eggshell']['contrast_score'] : '0.00' ?>%</strong></div>
                        </div>
                    </div>
                    
                    <!-- Right: Commercial -->
                    <div class="pane">
                        <h4>
                            <span>Commercial Powder Image</span>
                            <span class="powder-commercial">Commercial</span>
                        </h4>
                        <div class="img-placeholder">
                            <?php if(!empty($selected_pair['commercial']['image_path']) && file_exists(dirname(__DIR__) . '/uploads/fingerprints/' . $selected_pair['commercial']['image_path'])): ?>
                                <img src="../uploads/fingerprints/<?= htmlspecialchars($selected_pair['commercial']['image_path']) ?>" alt="Commercial Print">
                            <?php else: ?>
                                No Image Available
                            <?php endif; ?>
                        </div>
                        <div style="margin-bottom:1rem; font-size:.85rem; color:#555;">
                            <strong>Student:</strong> <?= htmlspecialchars($selected_pair['commercial']['student_name']) ?><br>
                            <strong>Surface:</strong> <?= ucfirst($selected_pair['commercial']['surface_type']) ?>
                        </div>
                        <div class="metrics-grid">
                            <div class="metric-box"><span>Accuracy</span><strong><?= $selected_pair['commercial']['accuracy_score'] ?>%</strong></div>
                            <div class="metric-box"><span>Ridge Clarity</span><strong><?= $selected_pair['commercial']['ridge_clarity_score'] ?>%</strong></div>
                            <div class="metric-box"><span>Visibility</span><strong><?= $selected_pair['commercial']['visibility_score'] ?>%</strong></div>
                            <div class="metric-box"><span>Adhesion</span><strong><?= $selected_pair['commercial']['adhesion_score'] ?>%</strong></div>
                            <div class="metric-box" style="grid-column: span 2;"><span>Contrast</span><strong><?= $selected_pair['commercial']['contrast_score'] !== null ? $selected_pair['commercial']['contrast_score'] : '0.00' ?>%</strong></div>
                        </div>
                    </div>
                </div>
                <?php
                    $eg_rec = $selected_pair['eggshell'];
                    $co_rec = $selected_pair['commercial'];
                    
                    $eg_acc = isset($eg_rec['accuracy_score']) ? floatval($eg_rec['accuracy_score']) : 0.0;
                    $co_acc = isset($co_rec['accuracy_score']) ? floatval($co_rec['accuracy_score']) : 0.0;
                    
                    if ($eg_acc > $co_acc) {
                        $better_powder = "Eggshell-Based Powder";
                    } elseif ($co_acc > $eg_acc) {
                        $better_powder = "Commercial Powder";
                    } else {
                        $better_powder = "Equal Performance";
                    }
                    
                    $score_diff = abs($eg_acc - $co_acc);
                    $surf_type = ucfirst($selected_pair['surface_type']);
                    
                    $status_labels = [
                        'pending_validation' => 'Pending Validation',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'needs_revision' => 'Needs Revision'
                    ];
                    $eg_status = $status_labels[$eg_rec['status']] ?? ucfirst($eg_rec['status']);
                    $co_status = $status_labels[$co_rec['status']] ?? ucfirst($co_rec['status']);
                    $validation_status = "Eggshell: " . $eg_status . " | Commercial: " . $co_status;
                ?>
                <div class="comparison-summary-card" style="margin-top: 1.5rem; background: var(--cream); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(45,106,79,0.15); display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                    <div style="grid-column: 1 / span 3; border-bottom: 1px solid rgba(45,106,79,0.2); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
                        <h4 style="margin:0; color:var(--dark-green); text-transform:uppercase; font-size:0.8rem; letter-spacing:0.8px;">Powder Performance Comparison Analysis</h4>
                    </div>
                    <div>
                        <span style="font-size:0.75rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Better Performing Powder</span>
                        <strong style="font-size:1.1rem; color:var(--dark-green);"><?= htmlspecialchars($better_powder) ?></strong>
                    </div>
                    <div>
                        <span style="font-size:0.75rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Difference in Score</span>
                        <strong style="font-size:1.1rem; color:var(--dark-green);"><?= number_format((float)($score_diff ?? 0), 2) ?>%</strong>
                    </div>
                    <div>
                        <span style="font-size:0.75rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Surface Material</span>
                        <strong style="font-size:1.1rem; color:var(--dark-green);"><?= htmlspecialchars($surf_type) ?></strong>
                    </div>
                    <div>
                        <span style="font-size:0.75rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Eggshell Powder Accuracy</span>
                        <strong style="font-size:1.1rem; color:var(--dark-green);"><?= number_format((float)($eg_acc ?? 0), 2) ?>%</strong>
                    </div>
                    <div>
                        <span style="font-size:0.75rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Commercial Powder Accuracy</span>
                        <strong style="font-size:1.1rem; color:var(--dark-green);"><?= number_format((float)($co_acc ?? 0), 2) ?>%</strong>
                    </div>
                    <div>
                        <span style="font-size:0.75rem; color:var(--gray); display:block; text-transform:uppercase; font-weight:600;">Faculty Validation Status</span>
                        <strong style="font-size:0.85rem; color:var(--dark-green);"><?= htmlspecialchars($validation_status) ?></strong>
                    </div>
                </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 2rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <p>No valid pairs available for comparison based on current filters.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Export Buttons -->
            <div class="actions-top">
                <button class="btn btn-secondary btn-sm"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> Export PDF</button>
                <button class="btn btn-secondary btn-sm"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h2v4H8z"/><path d="M14 13h2v4h-2z"/></svg> Export Excel</button>
                <button class="btn btn-secondary btn-sm"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print Report</button>
            </div>

            <!-- Comparison Table -->
            <div class="dashboard-card">
                <div class="card-title-wrap"><h3>Comparison Records (<?= count($rows) ?> entries)</h3></div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Ridge Clarity</th>
                                <th>Visibility</th>
                                <th>Adhesion</th>
                                <th>Contrast</th>
                                <th>Accuracy</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                                        <p>No comparison records available yet.<br>Student submissions will appear here after validation.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                            <tr>
                                <td style="font-weight:600; color:#1b4332;"><?= htmlspecialchars($r['student_name']) ?></td>
                                <td><span class="powder-<?= $r['powder_type'] ?>"><?= ucfirst($r['powder_type']) ?></span></td>
                                <td style="text-transform:capitalize;"><?= $r['surface_type'] ?></td>
                                <td><?= number_format((float)($r['ridge_clarity_score'] ?? 0), 1) ?>%</td>
                                <td><?= number_format((float)($r['visibility_score'] ?? 0), 1) ?>%</td>
                                <td><?= number_format((float)($r['adhesion_score'] ?? 0), 1) ?>%</td>
                                <td><?= $r['contrast_score'] !== null ? number_format((float)$r['contrast_score'], 1) . '%' : '—' ?></td>
                                <td><strong><?= number_format((float)($r['accuracy_score'] ?? 0), 1) ?>%</strong></td>
                                <td><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $r['status'] ?>">
                                        <?= $r['status'] === 'pending_validation' ? 'Pending Validation' : ($r['status'] === 'needs_revision' ? 'Needs Revision' : ucfirst($r['status'])) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="student_records.php?view=<?= $r['id'] ?>" class="icon-btn-action" title="View Details"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));

    <?php if($total_trials > 0): ?>
    // Chart.js Implementations
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.color = '#6c757d';

    // 1. Accuracy by Powder Type (Bar)
    new Chart(document.getElementById('accuracyChart'), {
        type: 'bar',
        data: {
            labels: ['Eggshell', 'Commercial'],
            datasets: [{
                label: 'Avg Accuracy (%)',
                data: [<?= $eggshell_avg ?>, <?= $commercial_avg ?>],
                backgroundColor: ['rgba(45,106,79,0.8)', 'rgba(108,117,125,0.8)'],
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });

    // 2. Success Rate by Surface Type (Line)
    new Chart(document.getElementById('successChart'), {
        type: 'line',
        data: {
            labels: <?= $chart_surface_labels ?>,
            datasets: [{
                label: 'Success Rate (%)',
                data: <?= $chart_surface_success ?>,
                borderColor: '#2d6a4f',
                backgroundColor: 'rgba(45,106,79,0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });

    // 3. Trial Count by Surface (Doughnut)
    new Chart(document.getElementById('trialChart'), {
        type: 'doughnut',
        data: {
            labels: <?= $chart_surface_labels ?>,
            datasets: [{
                data: <?= $chart_surface_counts ?>,
                backgroundColor: ['#1b4332', '#2d6a4f', '#40916c', '#52b788', '#74c69d']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            cutout: '60%'
        }
    });
    <?php endif; ?>
});
</script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
