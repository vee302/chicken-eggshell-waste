<?php
// partner/surface_compatibility.php — Alumni / Police Partner Surface Compatibility Matrix
require_once '../config.php';
require_once 'auth.php';
check_partner_auth();

$active_page = 'surface_compatibility';
$partner_name = $_SESSION['user_name'] ?? 'Partner';
$partner_id = $_SESSION['user_id'] ?? 0;

$surfaces = ['glass', 'plastic', 'metal', 'wood'];
$compatibility_data = [];

try {
    foreach ($surfaces as $s) {
        // 1. Get counts and average scores using prepared statements
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as trial_count,
                AVG(ridge_clarity_score) as avg_clarity,
                AVG(visibility_score) as avg_visibility,
                AVG(adhesion_score) as avg_adhesion,
                AVG(contrast_score) as avg_contrast,
                AVG(accuracy_score) as avg_accuracy
            FROM fingerprint_tests 
            WHERE status = 'approved' AND surface_type = ?
        ");
        $stmt->execute([$s]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($metrics['trial_count'] == 0) {
            $compatibility_data[$s] = [
                'trial_count' => 0,
                'avg_clarity' => 0,
                'avg_visibility' => 0,
                'avg_adhesion' => 0,
                'avg_contrast' => 0,
                'avg_accuracy' => 0,
                'best_powder' => 'N/A',
                'compatibility_rating' => 'Not Tested'
            ];
            continue;
        }
        
        // 2. Find best powder on this surface
        $stmt_eggshell = $pdo->prepare("
            SELECT AVG(accuracy_score) as avg_acc 
            FROM fingerprint_tests 
            WHERE status = 'approved' AND surface_type = ? AND powder_type = 'eggshell'
        ");
        $stmt_eggshell->execute([$s]);
        $eggshell_avg = $stmt_eggshell->fetchColumn() ?? 0;

        $stmt_commercial = $pdo->prepare("
            SELECT AVG(accuracy_score) as avg_acc 
            FROM fingerprint_tests 
            WHERE status = 'approved' AND surface_type = ? AND powder_type = 'commercial'
        ");
        $stmt_commercial->execute([$s]);
        $commercial_avg = $stmt_commercial->fetchColumn() ?? 0;
        
        $best_powder = 'N/A';
        if ($eggshell_avg > 0 || $commercial_avg > 0) {
            if ($eggshell_avg > $commercial_avg) {
                $best_powder = 'Eggshell-Based (' . number_format($eggshell_avg, 1) . '%)';
            } elseif ($commercial_avg > $eggshell_avg) {
                $best_powder = 'Commercial (' . number_format($commercial_avg, 1) . '%)';
            } else {
                $best_powder = 'Equal (' . number_format($eggshell_avg, 1) . '%)';
            }
        }
        
        // Determine compatibility level text
        $acc = $metrics['avg_accuracy'];
        if ($acc >= 85) {
            $rating = 'Excellent Compatibility';
        } elseif ($acc >= 70) {
            $rating = 'Good Compatibility';
        } elseif ($acc >= 50) {
            $rating = 'Moderate Compatibility';
        } else {
            $rating = 'Low Compatibility';
        }
        
        $compatibility_data[$s] = [
            'trial_count' => $metrics['trial_count'],
            'avg_clarity' => $metrics['avg_clarity'] ?? 0,
            'avg_visibility' => $metrics['avg_visibility'] ?? 0,
            'avg_adhesion' => $metrics['avg_adhesion'] ?? 0,
            'avg_contrast' => $metrics['avg_contrast'] ?? 0,
            'avg_accuracy' => $acc ?? 0,
            'best_powder' => $best_powder,
            'compatibility_rating' => $rating
        ];
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
    <title>Surface Compatibility Matrix — Green Forensics</title>
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
        .badge-compatibility {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-block;
            text-align: center;
        }
        .badge-excellent {
            background: rgba(82, 183, 136, 0.15);
            color: #2d6a4f;
            border: 1px solid rgba(82, 183, 136, 0.25);
        }
        .badge-good {
            background: rgba(107, 143, 113, 0.15);
            color: #1b4332;
            border: 1px solid rgba(107, 143, 113, 0.25);
        }
        .badge-moderate {
            background: rgba(244, 241, 222, 0.5);
            color: #e07a5f;
            border: 1px solid rgba(224, 122, 95, 0.25);
        }
        .badge-low {
            background: rgba(224, 122, 95, 0.15);
            color: #d90429;
            border: 1px solid rgba(224, 122, 95, 0.25);
        }
        .badge-none {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #ddd;
        }
        .highlight-best-powder {
            background: var(--cream);
            color: var(--dark-green);
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px dashed rgba(45,106,79,0.2);
            display: inline-block;
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
                    <h1>Surface Compatibility</h1>
                    <p>Compare the efficacy of developed powders on different surface substrates.</p>
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

            <!-- MATRIX CARD -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <line x1="3" y1="9" x2="21" y2="9"/>
                            <line x1="9" y1="21" x2="9" y2="9"/>
                        </svg>
                        Surface Compatibility Analysis Matrix
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Surface Substrate</th>
                                <th>Total Trials</th>
                                <th>Avg Ridge Clarity</th>
                                <th>Avg Visibility</th>
                                <th>Avg Adhesion</th>
                                <th>Avg Contrast</th>
                                <th>Avg Composite Accuracy</th>
                                <th>Best Performing Powder Type</th>
                                <th style="text-align: right;">Compatibility Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($surfaces as $s): 
                            $data = $compatibility_data[$s];
                            $badge_class = 'badge-none';
                            if ($data['trial_count'] > 0) {
                                if ($data['avg_accuracy'] >= 85) $badge_class = 'badge-excellent';
                                elseif ($data['avg_accuracy'] >= 70) $badge_class = 'badge-good';
                                elseif ($data['avg_accuracy'] >= 50) $badge_class = 'badge-moderate';
                                else $badge_class = 'badge-low';
                            }
                        ?>
                            <tr>
                                <td style="font-weight:700; color:var(--dark-green); text-transform:capitalize;"><?= htmlspecialchars($s) ?></td>
                                <td><strong><?= $data['trial_count'] ?></strong></td>
                                <td><?= $data['trial_count'] > 0 ? number_format($data['avg_clarity'], 1).'%' : '—' ?></td>
                                <td><?= $data['trial_count'] > 0 ? number_format($data['avg_visibility'], 1).'%' : '—' ?></td>
                                <td><?= $data['trial_count'] > 0 ? number_format($data['avg_adhesion'], 1).'%' : '—' ?></td>
                                <td><?= $data['trial_count'] > 0 ? number_format($data['avg_contrast'], 1).'%' : '—' ?></td>
                                <td><strong><?= $data['trial_count'] > 0 ? number_format($data['avg_accuracy'], 1).'%' : '—' ?></strong></td>
                                <td>
                                    <?php if ($data['trial_count'] > 0): ?>
                                        <span class="highlight-best-powder"><?= htmlspecialchars($data['best_powder']) ?></span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <span class="badge-compatibility <?= $badge_class ?>"><?= htmlspecialchars($data['compatibility_rating']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- end student-content -->
    </main>
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
    });
</script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
