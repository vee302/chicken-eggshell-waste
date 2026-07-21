<?php
// faculty/surface_performance.php
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';
$surfaces = ['glass','plastic','metal','wood'];
$perf = [];

foreach ($surfaces as $s) {
    $data = ['surface'=>$s,'trials'=>0,'success_rate'=>0,'avg_clarity'=>0,'avg_visibility'=>0,'avg_adhesion'=>0,'best_powder'=>'—','compat'=>'—'];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved,
            ROUND(AVG(ridge_clarity_score),1) AS avg_clarity,
            ROUND(AVG(visibility_score),1) AS avg_vis,
            ROUND(AVG(adhesion_score),1) AS avg_adh
            FROM fingerprint_tests WHERE surface_type=?");
        $stmt->execute([$s]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['total'] > 0) {
            $data['trials']         = $row['total'];
            $data['success_rate']   = round(($row['approved']/$row['total'])*100,1);
            $data['avg_clarity']    = $row['avg_clarity'] ?? 0;
            $data['avg_visibility'] = $row['avg_vis']     ?? 0;
            $data['avg_adhesion']   = $row['avg_adh']     ?? 0;
        }
        // Best powder on this surface
        $stmt2 = $pdo->prepare("SELECT powder_type, ROUND(AVG(accuracy_score),1) AS avg_score
            FROM fingerprint_tests WHERE surface_type=? GROUP BY powder_type ORDER BY avg_score DESC LIMIT 1");
        $stmt2->execute([$s]);
        $best = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($best) {
            $data['best_powder'] = ucfirst($best['powder_type']).' ('.$best['avg_score'].'%)';
        }
        $rate = $data['success_rate'];
        $data['compat'] = $rate >= 80 ? 'Highly Compatible' : ($rate >= 60 ? 'Moderately Compatible' : 'Low Compatibility');
    } catch (PDOException $e) {}
    $perf[] = $data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surface Performance - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .surface-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;}
        .surface-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(27,67,50,.05);border:1px solid rgba(27,67,50,.05);}
        .surface-card-header{padding:1.25rem 1.5rem;background:var(--dark-green);color:#fff;}
        .surface-card-header h3{font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
        .surface-card-header span{font-size:.8rem;opacity:.75;}
        .surface-card-body{padding:1.5rem;}
        .metric-row{display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid #f4f6f0;}
        .metric-row:last-child{border-bottom:none;}
        .metric-label{font-size:.82rem;color:#6c757d;}
        .metric-value{font-size:.9rem;font-weight:700;color:#1b4332;}
        .compat-badge{padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;}
        .compat-high{background:rgba(82,183,136,.15);color:#2d6a4f;}
        .compat-mid {background:rgba(244,162,97,.15);color:#c97d2a;}
        .compat-low {background:rgba(224,122,95,.15);color:#c0392b;}
        .mini-bar{display:flex;align-items:center;gap:8px;}
        .mini-bar-track{flex:1;height:6px;background:#f4f6f0;border-radius:3px;overflow:hidden;}
        .mini-bar-fill{height:100%;background:#2d6a4f;border-radius:3px;}
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
            <li class="menu-item active"><a href="surface_performance.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg><span>Surface Performance</span></a></li>
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
                <div class="header-title"><h2>Green Forensics — Surface Performance</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Surface Performance</h1>
                    <p>Powder performance metrics broken down by test surface type.</p>
                </div>
            </div>

            <div class="surface-grid">
            <?php foreach ($perf as $p):
                $compatClass = str_contains($p['compat'],'High') ? 'compat-high' : (str_contains($p['compat'],'Mid') ? 'compat-mid' : 'compat-low');
            ?>
                <div class="surface-card">
                    <div class="surface-card-header">
                        <h3><?= ucfirst($p['surface']) ?></h3>
                        <span><?= $p['trials'] ?> trial<?= $p['trials']!=1?'s':'' ?> recorded</span>
                    </div>
                    <div class="surface-card-body">
                        <div class="metric-row">
                            <span class="metric-label">Success Rate</span>
                            <div class="mini-bar" style="width:55%;">
                                <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?= min($p['success_rate'],100) ?>%"></div></div>
                                <span class="metric-value"><?= $p['success_rate'] ?>%</span>
                            </div>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">Avg. Ridge Clarity</span>
                            <span class="metric-value"><?= $p['avg_clarity'] ?>%</span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">Avg. Visibility</span>
                            <span class="metric-value"><?= $p['avg_visibility'] ?>%</span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">Avg. Adhesion</span>
                            <span class="metric-value"><?= $p['avg_adhesion'] ?>%</span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">Best Performing Powder</span>
                            <span class="metric-value"><?= $p['best_powder'] ?></span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">Compatibility</span>
                            <span class="compat-badge <?= $compatClass ?>"><?= $p['compat'] ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
