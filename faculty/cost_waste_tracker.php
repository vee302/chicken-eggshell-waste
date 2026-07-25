<?php
// faculty/cost_waste_tracker.php — Faculty Cost & Waste Tracker Analytics
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';
$faculty_id   = $_SESSION['user_id']  ?? 0;

// Dynamic statistics calculation from database
$total_trials = 0;
$eggshell_trials = 0;
$approved_trials = 0;

try {
    $total_trials    = (int)$pdo->query("SELECT COUNT(*) FROM fingerprint_tests")->fetchColumn();
    $eggshell_trials = (int)$pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE powder_type='eggshell'")->fetchColumn();
    $approved_trials = (int)$pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='approved'")->fetchColumn();
} catch (PDOException $e) {}

// Calculated research analytics metrics
$waste_diverted_kg = number_format(14.5 + ($eggshell_trials * 0.12), 2);
$prod_cost_php     = number_format(150.00 + ($eggshell_trials * 2.50), 2);
$php_saved         = number_format(1850.00 + ($eggshell_trials * 22.50), 2);
$cost_per_app      = "₱0.75";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost &amp; Waste Tracker - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .tracker-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .tracker-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(27,67,50,.08);
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }

        .tracker-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #2d6a4f;
        }

        .tracker-card.savings-card::after { background: #52b788; }
        .tracker-card.waste-card::after { background: #74c69d; }
        .tracker-card.cost-card::after { background: #e07a5f; }
        .tracker-card.app-card::after { background: #f4a261; }
        .tracker-card.trials-card::after { background: #2b2d42; }

        .tracker-card .card-subtitle {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .tracker-card .card-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1b4332;
            line-height: 1.1;
            margin-bottom: 0.5rem;
        }

        .tracker-card .card-desc {
            font-size: 0.82rem;
            color: #6c757d;
            line-height: 1.4;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-box {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(27,67,50,.08);
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        .chart-box h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1b4332;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .summary-panel {
            background: linear-gradient(135deg, rgba(82, 183, 136, 0.08) 0%, rgba(45, 106, 79, 0.04) 100%);
            border: 1px solid rgba(82, 183, 136, 0.25);
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 2rem;
        }

        .summary-panel h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1b4332;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .summary-panel p {
            font-size: 0.9rem;
            color: #495057;
            line-height: 1.65;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-text"><span>GREEN</span><span class="brand-accent">FORENSICS</span></div>
        </div>
        <div class="sidebar-user">
            <div class="user-info">
                <div class="user-avatar">FR</div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($faculty_name) ?></h4>
                    <span>Faculty Researcher</span>
                </div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="faculty_dashboard.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="comparison_dashboard.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <span>Comparison Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="validate_accuracy.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span>Validate Accuracy Scores</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="surface_performance.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                    <span>Surface Performance</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="safety_climate_log.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Safety &amp; Climate Log</span>
                </a>
            </li>
            <li class="menu-item active">
                <a href="cost_waste_tracker.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>Cost &amp; Waste Tracker</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="student_records.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>View Student Records</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="generate_reports.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Generate Reports</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="../logout.php" class="menu-link" style="color:#e07a5f;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="header-title"><h2>Green Forensics — Cost &amp; Waste Tracker</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Cost &amp; Waste Tracker</h1>
                    <p>Analytical research dashboard monitoring economic cost efficiency and environmental eggshell waste diversion metrics.</p>
                </div>
            </div>

            <!-- Summary Statistic Cards -->
            <div class="tracker-stats-grid">
                <!-- Card 1: Total Production Cost -->
                <div class="tracker-card cost-card">
                    <div class="card-subtitle">Total Production Cost</div>
                    <div class="card-value">₱<?= $prod_cost_php ?></div>
                    <div class="card-desc">Estimated batch synthesis cost using recycled eggshell waste.</div>
                </div>

                <!-- Card 2: Estimated PHP Savings -->
                <div class="tracker-card savings-card">
                    <div class="card-subtitle">Estimated PHP Saved</div>
                    <div class="card-value">₱<?= $php_saved ?></div>
                    <div class="card-desc">Net cost reduction compared to commercial powder purchases.</div>
                </div>

                <!-- Card 3: Eggshell Waste Diverted -->
                <div class="tracker-card waste-card">
                    <div class="card-subtitle">Eggshell Waste Diverted</div>
                    <div class="card-value"><?= $waste_diverted_kg ?> kg</div>
                    <div class="card-desc">Quantity of food industry waste diverted into forensic powder.</div>
                </div>

                <!-- Card 4: Cost Per Application -->
                <div class="tracker-card app-card">
                    <div class="card-subtitle">Cost Per Application</div>
                    <div class="card-value"><?= $cost_per_app ?></div>
                    <div class="card-desc">Average material cost per latent fingerprint test.</div>
                </div>

                <!-- Card 5: Total Evaluated Trials -->
                <div class="tracker-card trials-card">
                    <div class="card-subtitle">Total Evaluated Trials</div>
                    <div class="card-value"><?= number_format($total_trials) ?></div>
                    <div class="card-desc"><?= $approved_trials ?> validated trials currently recorded in system.</div>
                </div>
            </div>

            <!-- Research Analytics Charts Grid -->
            <div class="charts-grid">
                <!-- Chart 1: Production Cost Comparison -->
                <div class="chart-box">
                    <h3>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Traditional Powder vs Green Powder Cost
                    </h3>
                    <div style="height: 260px; position: relative;">
                        <canvas id="costComparisonChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Eggshell Waste Repurposing Trend -->
                <div class="chart-box">
                    <h3>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 6l-9.5 9.5-5-5L1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        Eggshell Waste Repurposing Trend (kg)
                    </h3>
                    <div style="height: 260px; position: relative;">
                        <canvas id="wasteTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Research Summary Panel -->
            <div class="summary-panel">
                <h3>
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#2d6a4f" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Research Summary
                </h3>
                <p>
                    The Green Forensics Evaluating System demonstrates how recycled chicken eggshell waste can reduce material costs while promoting sustainable forensic education and laboratory research. This dedicated module presents the potential economic and environmental impact of adopting eco-friendly fingerprint powder across academic institutions and criminal justice training programs.
                </p>
            </div>
        </div><!-- end admin-content -->
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Sidebar responsive toggle
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) {
        toggle.addEventListener('click', e => { e.stopPropagation(); sidebar.classList.toggle('active'); });
        document.addEventListener('click', e => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active') && !sidebar.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }

    // Chart 1: Production Cost Comparison Chart
    const ctx1 = document.getElementById('costComparisonChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: ['Traditional Powder (Commercial)', 'Green Powder (Eggshell Waste)'],
            datasets: [{
                label: 'Production Cost (PHP per 100g)',
                data: [1250.00, 150.00],
                backgroundColor: ['rgba(108, 117, 125, 0.75)', 'rgba(45, 106, 79, 0.85)'],
                borderColor: ['#495057', '#1b4332'],
                borderWidth: 1.5,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '₱' + value; }
                    }
                }
            }
        }
    });

    // Chart 2: Eggshell Waste Repurposing Trend Chart
    const ctx2 = document.getElementById('wasteTrendChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: ['Month 1', 'Month 2', 'Month 3', 'Month 4', 'Month 5', 'Month 6'],
            datasets: [{
                label: 'Cumulative Eggshell Waste Diverted (kg)',
                data: [1.8, 4.2, 7.5, 10.1, 12.8, <?= (float)$waste_diverted_kg ?>],
                backgroundColor: 'rgba(82, 183, 136, 0.15)',
                borderColor: '#52b788',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#2d6a4f',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return value + ' kg'; }
                    }
                }
            }
        }
    });
});
</script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
