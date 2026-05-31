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
foreach ($rows as $r) {
    if ($r['powder_type']==='eggshell')   { $eggshell_avg  += $r['accuracy_score']; $eg_count++; }
    if ($r['powder_type']==='commercial') { $commercial_avg += $r['accuracy_score']; $co_count++; }
}
$eggshell_avg  = $eg_count ? round($eggshell_avg/$eg_count,1)  : 0;
$commercial_avg= $co_count ? round($commercial_avg/$co_count,1): 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparison Dashboard - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .badge-pending{background:rgba(244,162,97,.15);color:#c97d2a;}
        .badge-approved{background:rgba(82,183,136,.15);color:#2d6a4f;}
        .badge-rejected{background:rgba(224,122,95,.15);color:#c0392b;}
        .powder-eggshell{background:rgba(82,183,136,.12);color:#2d6a4f;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;}
        .powder-commercial{background:rgba(108,117,125,.12);color:#495057;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;}
        .compare-summary{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;}
        .compare-card{background:#fff;border-radius:14px;padding:2rem;text-align:center;box-shadow:0 4px 20px rgba(27,67,50,.05);border:1px solid rgba(27,67,50,.05);}
        .compare-card h3{font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:.5rem;}
        .compare-big{font-size:2.5rem;font-weight:800;color:#1b4332;line-height:1;}
        .compare-sub{font-size:.85rem;color:#6c757d;margin-top:.5rem;}
        .bar-h{display:flex;align-items:center;gap:8px;margin-top:.75rem;}
        .bar-h-track{flex:1;height:8px;background:#f4f6f0;border-radius:4px;overflow:hidden;}
        .bar-h-fill{height:100%;border-radius:4px;}
        .filter-form{background:#fff;padding:1.5rem;border-radius:14px;box-shadow:0 4px 20px rgba(27,67,50,.04);margin-bottom:2rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;}
        .filter-form label{font-size:.75rem;font-weight:700;color:#1b4332;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:.3rem;}
        .filter-form select,.filter-form input{padding:.55rem 1rem;border:1px solid #e9ecef;border-radius:8px;font-size:.85rem;background:#fff;color:#212529;outline:none;}
        .filter-form select:focus,.filter-form input:focus{border-color:#2d6a4f;box-shadow:0 0 0 3px rgba(45,106,79,.12);}
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- SIDEBAR (same as dashboard) -->
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

            <!-- Summary comparison -->
            <div class="compare-summary">
                <div class="compare-card">
                    <h3>Eggshell-Based Powder</h3>
                    <div class="compare-big"><?= $eggshell_avg ?>%</div>
                    <div class="compare-sub">Average Accuracy Score (<?= $eg_count ?> trials)</div>
                    <div class="bar-h"><div class="bar-h-track"><div class="bar-h-fill" style="width:<?= $eggshell_avg ?>%;background:#2d6a4f;"></div></div></div>
                </div>
                <div class="compare-card">
                    <h3>Commercial Powder</h3>
                    <div class="compare-big" style="color:#495057;"><?= $commercial_avg ?>%</div>
                    <div class="compare-sub">Average Accuracy Score (<?= $co_count ?> trials)</div>
                    <div class="bar-h"><div class="bar-h-track"><div class="bar-h-fill" style="width:<?= $commercial_avg ?>%;background:#6c757d;"></div></div></div>
                </div>
            </div>

            <!-- Filter Form -->
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
                        <?php foreach(['glass','paper','wood','plastic','metal'] as $s): ?>
                        <option value="<?=$s?>" <?= $f_surface===$s?'selected':'' ?>><?=ucfirst($s)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="pending"  <?= $f_status==='pending' ?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $f_status==='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $f_status==='rejected'?'selected':'' ?>>Rejected</option>
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
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <a href="comparison_dashboard.php" class="btn btn-secondary">Reset</a>
            </form>

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
                                <th>Accuracy</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="9" style="text-align:center;padding:2rem;color:#6c757d;">No records match the selected filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['student_name']) ?></td>
                                <td><span class="powder-<?= $r['powder_type'] ?>"><?= ucfirst($r['powder_type']) ?></span></td>
                                <td style="text-transform:capitalize;"><?= $r['surface_type'] ?></td>
                                <td><?= number_format($r['ridge_clarity_score'],1) ?>%</td>
                                <td><?= number_format($r['visibility_score'],1) ?>%</td>
                                <td><?= number_format($r['adhesion_score'],1) ?>%</td>
                                <td><strong><?= number_format($r['accuracy_score'],1) ?>%</strong></td>
                                <td><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
                                <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
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
});
</script>
</body>
</html>
