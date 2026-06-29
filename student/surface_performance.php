<?php
// student/surface_performance.php — View Surface Performance Results
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'surface_performance';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

// Group by surface and powder type for this student
$surface_data = [];
try {
    $stmt = $pdo->prepare("
        SELECT surface_type, powder_type,
               COUNT(*) AS trial_count,
               ROUND(AVG(accuracy_score), 1) AS avg_score,
               ROUND(MAX(accuracy_score), 1) AS max_score,
               ROUND(MIN(accuracy_score), 1) AS min_score
        FROM fingerprint_tests
        WHERE student_id = ? AND status = 'approved' AND accuracy_score IS NOT NULL
        GROUP BY surface_type, powder_type
        ORDER BY surface_type, powder_type
    ");
    $stmt->execute([$student_id]);
    $surface_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Surface Performance — Green Forensics">
    <title>Surface Performance — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
    <style>
        .surface-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .surface-card { background: var(--white); border-radius: 14px; overflow: hidden; box-shadow: var(--box-shadow); border: 1px solid rgba(27,67,50,.05); }
        .surface-card-head { background: var(--dark-green); color: var(--white); padding: 1.1rem 1.4rem; }
        .surface-card-head h3 { font-size: .95rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
        .surface-card-head span { font-size: .75rem; opacity: .75; }
        .surface-card-body { padding: 1.25rem 1.4rem; }
        .metric-row { display: flex; justify-content: space-between; align-items: center; padding: .55rem 0; border-bottom: 1px solid var(--cream); }
        .metric-row:last-child { border-bottom: none; }
        .metric-label { font-size: .8rem; color: var(--gray); }
        .metric-value { font-size: .9rem; font-weight: 700; color: var(--dark-green); }
        .powder-pill { padding: 2px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; }
        .powder-eggshell  { background: rgba(82,183,136,.12); color: var(--medium-green); }
        .powder-commercial{ background: rgba(108,117,125,.12); color: #495057; }
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
                <div class="header-title"><h2>Surface Performance</h2></div>
            </div>
            <div class="header-right"><div class="header-role-chip">Criminology Student</div></div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>View Surface Performance</h1>
                    <p>Breakdown of your fingerprint accuracy scores by surface type and powder used.</p>
                </div>
                <a href="upload_fingerprint.php" class="btn btn-primary">+ New Trial</a>
            </div>

            <?php if (empty($surface_data)): ?>
                <div class="dashboard-card" style="text-align:center;padding:3rem;">
                    <p style="color:var(--gray);">No evaluation records found yet. Upload your first fingerprint image to begin evaluation. <a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload now →</a></p>
                </div>
            <?php else: ?>
                <div class="surface-grid">
                    <?php foreach ($surface_data as $row): ?>
                    <div class="surface-card">
                        <div class="surface-card-head">
                            <h3><?= ucfirst(htmlspecialchars($row['surface_type'])) ?></h3>
                            <span><span class="powder-pill powder-<?= $row['powder_type'] ?>"><?= ucfirst($row['powder_type']) ?> Powder</span></span>
                        </div>
                        <div class="surface-card-body">
                            <div class="metric-row">
                                <span class="metric-label">Avg Accuracy</span>
                                <span class="metric-value"><?= $row['avg_score'] ?>%</span>
                            </div>
                            <div class="metric-row">
                                <span class="metric-label">Score Bar</span>
                                <div class="score-bar" style="width:55%;">
                                    <div class="score-bar-track">
                                        <div class="score-bar-fill" style="width:<?= min(100, $row['avg_score']) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="metric-row">
                                <span class="metric-label">Highest Score</span>
                                <span class="metric-value" style="color:var(--soft-green);"><?= $row['max_score'] ?>%</span>
                            </div>
                            <div class="metric-row">
                                <span class="metric-label">Lowest Score</span>
                                <span class="metric-value" style="color:var(--danger);"><?= $row['min_score'] ?>%</span>
                            </div>
                            <div class="metric-row">
                                <span class="metric-label">Total Trials</span>
                                <span class="metric-value"><?= $row['trial_count'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require_once '_sidebar_js.php'; ?>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
