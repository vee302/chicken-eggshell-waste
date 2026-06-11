<?php
// partner/partner_dashboard.php — Alumni / Police Partner Main Dashboard
require_once '../config.php';
require_once 'auth.php';
check_partner_auth();

$partner_name = $_SESSION['user_name'] ?? 'Partner';
$partner_email = $_SESSION['user_email'] ?? 'partner@greenforensics.com';

// Derive initials for avatar
$words = explode(' ', trim($partner_name));
$initials = '';
foreach (array_slice($words, 0, 2) as $w) {
    $initials .= strtoupper($w[0] ?? '');
}
$initials = $initials ?: 'AP';

// Fetch summary metrics
$total_trials = 0;
$pending_trials = 0;
$approved_trials = 0;
try {
    $total_trials = (int)$pdo->query("SELECT COUNT(*) FROM fingerprint_tests")->fetchColumn();
    $pending_trials = (int)$pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='pending'")->fetchColumn();
    $approved_trials = (int)$pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE status='approved'")->fetchColumn();
} catch (PDOException $e) {}

// Fetch recent fingerprint tests for reference
$recent_tests = [];
try {
    $stmt = $pdo->query("SELECT t.*, u.full_name FROM fingerprint_tests t JOIN users u ON t.student_id = u.id ORDER BY t.submitted_at DESC LIMIT 5");
    $recent_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Dashboard — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .role-badge-partner {
            background: rgba(107, 143, 113, 0.12);
            color: var(--dark-green);
            border: 1px solid rgba(107, 143, 113, 0.25);
        }
        .partner-description-card {
            border-left: 4px solid var(--soft-green);
            background: rgba(210, 226, 213, 0.15);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .partner-description-card h3 {
            color: var(--dark-green);
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        .partner-description-card p {
            font-size: 0.92rem;
            line-height: 1.6;
            color: #555;
        }
    </style>
</head>
<body>

<div class="student-wrapper">

    <!-- Mobile overlay -->
    <div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;transition:opacity .3s;"
         onclick="this.style.display='none';document.getElementById('sidebar').classList.remove('active')"></div>

    <!-- SIDEBAR -->
    <aside class="student-sidebar" id="sidebar">
        <!-- Brand Header -->
        <div class="sidebar-brand">
            <div class="brand-logo-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div class="brand-text-block">
                <span class="brand-title">Green Forensics</span>
                <span class="brand-subtitle">Evaluating System</span>
            </div>
        </div>

        <!-- Role Badge -->
        <div class="sidebar-role-badge">
            <span class="role-dot" style="background-color:#6B8F71;"></span>
            <span class="role-label">Alumni / Police Partner</span>
        </div>

        <!-- Navigation Menu -->
        <ul class="sidebar-menu">
            <li class="menu-section-label">Main</li>
            <li class="menu-item active">
                <a href="partner_dashboard.php" class="menu-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9"/>
                        <rect x="14" y="3" width="7" height="5"/>
                        <rect x="14" y="12" width="7" height="9"/>
                        <rect x="3" y="16" width="7" height="5"/>
                    </svg>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- Bottom Profile & Logout -->
        <div class="sidebar-bottom">
            <div class="sidebar-logout">
                <a href="../logout.php" class="logout-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>

            <div class="sidebar-profile">
                <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="profile-info">
                    <div class="profile-name"><?= htmlspecialchars($partner_name) ?></div>
                    <div class="profile-role">Police Partner</div>
                </div>
            </div>
        </div>
    </aside>

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
                    <h2>Green Forensics — Partner Dashboard</h2>
                </div>
            </div>
            <div class="header-right">
                <div class="header-role-chip role-badge-partner">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                    </svg>
                    Alumni / Police Partner
                </div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back, <?= htmlspecialchars($partner_name) ?>. Here is a summary of the forensic evaluation activities.</p>
                </div>
            </div>

            <!-- Partner Role Introduction -->
            <div class="partner-description-card">
                <h3>Authorized Partner Access</h3>
                <p>As an **Alumni / Police Partner**, you have access to monitor and validate the effectiveness of our sustainable fingerprint powder in real-world scenarios. We collaborate with local law enforcement to transition from hazardous carbon black solutions to biodegradable chicken eggshell waste innovations.</p>
            </div>

            <!-- STATS -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">System Trials</span>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $total_trials ?></div>
                    <div class="stat-desc">Total fingerprint trials in system</div>
                </div>

                <div class="stat-card card-pending">
                    <div class="stat-header">
                        <span class="stat-title">Pending Validation</span>
                        <div class="stat-icon" style="background:rgba(244,162,97,.12);color:#c97d2a;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color:#c97d2a;"><?= $pending_trials ?></div>
                    <div class="stat-desc">Trials awaiting review</div>
                </div>

                <div class="stat-card card-approved">
                    <div class="stat-header">
                        <span class="stat-title">Approved Trials</span>
                        <div class="stat-icon" style="background:rgba(82,183,136,.12);color:#2d6a4f;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color:#2d6a4f;"><?= $approved_trials ?></div>
                    <div class="stat-desc">Approved active evaluations</div>
                </div>
            </div>

            <!-- RECENT SUBMISSIONS REFERENCE -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Recent Evaluation Trials
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Powder Type</th>
                                <th>Surface</th>
                                <th>Accuracy</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recent_tests)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#6c757d;padding:2rem;">
                                    No evaluation records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_tests as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($row['surface_type']) ?></td>
                                <td><?= number_format($row['accuracy_score'], 1) ?>%</td>
                                <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
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
</body>
</html>
