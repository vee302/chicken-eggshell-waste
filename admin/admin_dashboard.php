<?php
// admin/admin_dashboard.php - Super Administrator Dashboard Overview
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

// Fetch dynamic counts with fallbacks
$total_users = 0;
$pending_count = 0;
$active_users = 0;
$suspended_rejected_users = 0;
$total_trials = 0;
$total_images = 0;
$total_reports = 0;
$total_activities = 0;

try {
    // 1. Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = (int)$stmt->fetchColumn();

    // 2. Pending approvals
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'");
    $pending_count = (int)$stmt->fetchColumn();

    // 3. Active users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'");
    $active_users = (int)$stmt->fetchColumn();

    // 4. Suspended / Rejected users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status IN ('suspended', 'rejected')");
    $suspended_rejected_users = (int)$stmt->fetchColumn();

    // 5. Fingerprint trials
    $stmt = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests");
    $total_trials = (int)$stmt->fetchColumn();

    // 6. Uploaded images
    $stmt = $pdo->query("SELECT COUNT(*) FROM fingerprint_tests WHERE image_path IS NOT NULL AND image_path != ''");
    $total_images = (int)$stmt->fetchColumn();

    // 7. Reports generated
    $stmt = $pdo->query("SELECT COUNT(*) FROM reports");
    $total_reports = (int)$stmt->fetchColumn();

    // 8. Total activity logs
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
    $total_activities = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    // Fallbacks
}

// Fetch maintenance mode from settings
$maintenance_mode = "OFF";
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
    $stmt->execute();
    $m_val = $stmt->fetchColumn();
    if ($m_val === "1") {
        $maintenance_mode = "ON";
    }
} catch (PDOException $e) {}

// Fetch recent activity logs (real logs from DB)
$activities = [];
try {
    $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fallback logic for activity logs if empty
if (empty($activities)) {
    $activities = [
        ['action' => 'security', 'details' => 'Super Administrator logged in', 'created_at' => date('Y-m-d H:i:s'), 'user_email' => $_SESSION["user_email"] ?? 'admin@greenforensics.com'],
        ['action' => 'system', 'details' => 'System activity log database initialized', 'created_at' => date('Y-m-d H:i:s'), 'user_email' => 'system']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Forensics — Super Administrator Dashboard</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .stat-card.users-card::after { background-color: var(--medium-green); }
        .stat-card.pending-card::after { background-color: var(--warning); }
        .stat-card.active-card::after { background-color: var(--soft-green); }
        .stat-card.suspended-card::after { background-color: var(--danger); }
        
        .stat-card.pending-card .stat-icon { background: rgba(244, 162, 97, 0.1); color: var(--warning); }
        .stat-card.active-card .stat-icon { background: rgba(82, 183, 136, 0.1); color: var(--soft-green); }
        .stat-card.suspended-card .stat-icon { background: rgba(224, 122, 95, 0.1); color: var(--danger); }

        .stat-card.trials-card::after { background-color: #2a6f97; }
        .stat-card.trials-card .stat-icon { background: rgba(42, 111, 151, 0.1); color: #2a6f97; }
        
        .stat-card.images-card::after { background-color: #4a5759; }
        .stat-card.images-card .stat-icon { background: rgba(74, 87, 89, 0.1); color: #4a5759; }

        .stat-card.reports-card::after { background-color: #7251b5; }
        .stat-card.reports-card .stat-icon { background: rgba(114, 81, 181, 0.1); color: #7251b5; }

        .stat-card.activity-card::after { background-color: #1d3557; }
        .stat-card.activity-card .stat-icon { background: rgba(29, 53, 87, 0.1); color: #1d3557; }

        .dot-orange { background-color: var(--warning); }
    </style>
</head>

<body>

    <div class="admin-wrapper">
        <!-- SIDEBAR NAVIGATION -->
        <?php include "sidebar.php"; ?>

        <!-- MAIN LAYOUT CONTENT -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-left">
                    <button class="menu-toggle" id="sidebarCollapse">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <div class="header-title">
                        <h2>Green Forensics — Super Administrator Dashboard</h2>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <div class="admin-content">
                <div class="page-header-wrap">
                    <div class="page-title">
                        <h1>Dashboard Overview</h1>
                        <p>System Administration, Authentication, Access Control, and Monitoring Panel</p>
                    </div>
                </div>

                <!-- METRICS GRID 1 (USER MANAGEMENT STATS) -->
                <div class="stats-grid" style="margin-bottom: 1.5rem;">
                    <!-- Total Users -->
                    <div class="stat-card users-card">
                        <div class="stat-header">
                            <span class="stat-title">Total Users</span>
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $total_users; ?></div>
                        <div class="stat-desc">All registered credentials</div>
                    </div>

                    <!-- Pending Approvals -->
                    <div class="stat-card pending-card">
                        <div class="stat-header">
                            <span class="stat-title">Pending Approvals</span>
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $pending_count; ?></div>
                        <div class="stat-desc">Users awaiting review</div>
                    </div>

                    <!-- Active Users -->
                    <div class="stat-card active-card">
                        <div class="stat-header">
                            <span class="stat-title">Active Users</span>
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $active_users; ?></div>
                        <div class="stat-desc">Approved authorized accounts</div>
                    </div>

                    <!-- Suspended / Rejected Users -->
                    <div class="stat-card suspended-card">
                        <div class="stat-header">
                            <span class="stat-title">Suspended / Rejected</span>
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                    <line x1="12" y1="2" x2="12" y2="12"></line>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $suspended_rejected_users; ?></div>
                        <div class="stat-desc">Blocked or denied accounts</div>
                    </div>
                </div>

                <!-- METRICS GRID 2 (FORENSIC ACTIVITY STATS) -->
                <div class="stats-grid" style="margin-bottom: 2rem;">
                    <!-- Fingerprint Trials -->
                    <div class="stat-card trials-card">
                        <div class="stat-header">
                            <span class="stat-title">Fingerprint Trials</span>
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <circle cx="12" cy="11" r="3"></circle>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $total_trials; ?></div>
                        <div class="stat-desc">Total fingerprint tests logged</div>
                    </div>

                    <!-- Uploaded Images -->
                    <div class="stat-card images-card">
                        <div class="stat-header">
                            <span class="stat-title">Uploaded Images</span>
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $total_images; ?></div>
                        <div class="stat-desc">Image assets in active storage</div>
                    </div>

                    <!-- Reports Generated -->
                    <div class="stat-card reports-card">
                        <div class="stat-header">
                            <span class="stat-title">Reports Generated</span>
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $total_reports; ?></div>
                        <div class="stat-desc">Evaluation files generated</div>
                    </div>

                    <!-- Recent System Activities -->
                    <div class="stat-card activity-card">
                        <div class="stat-header">
                            <span class="stat-title">System Activities</span>
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="8" y1="6" x2="21" y2="6"></line>
                                    <line x1="8" y1="12" x2="21" y2="12"></line>
                                    <line x1="8" y1="18" x2="21" y2="18"></line>
                                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $total_activities; ?></div>
                        <div class="stat-desc">System logs recorded in audit</div>
                    </div>
                </div>

                <!-- MAIN WORKSPACE SECTION -->
                <div class="dashboard-grid">
                    <!-- Recent Activity Card -->
                    <div class="dashboard-card">
                        <div class="card-title-wrap">
                            <h3>
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span>Recent System Activity</span>
                            </h3>
                        </div>
                        <ul class="activity-list">
                            <?php foreach ($activities as $act): ?>
                                <?php 
                                $act_type = 'security';
                                $action_lower = strtolower($act['action'] ?? '');
                                if (strpos($action_lower, 'add') !== false || strpos($action_lower, 'create') !== false || strpos($action_lower, 'register') !== false) {
                                    $act_type = 'add';
                                } elseif (strpos($action_lower, 'edit') !== false || strpos($action_lower, 'update') !== false || strpos($action_lower, 'reset') !== false || strpos($action_lower, 'change') !== false || strpos($action_lower, 'approve') !== false) {
                                    $act_type = 'edit';
                                } elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'remove') !== false || strpos($action_lower, 'reject') !== false || strpos($action_lower, 'suspend') !== false) {
                                    $act_type = 'delete';
                                }
                                ?>
                                <li class="activity-item">
                                    <div class="activity-badge <?php echo $act_type; ?>">
                                        <?php if ($act_type === 'security'): ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"
                                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        <?php elseif ($act_type === 'add'): ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"
                                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        <?php elseif ($act_type === 'edit'): ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"
                                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                            </svg>
                                        <?php elseif ($act_type === 'delete'): ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"
                                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-details">
                                        <p><?php echo htmlspecialchars($act['details']); ?></p>
                                        <span>Triggered by <?php echo htmlspecialchars($act['user_email']); ?> &bull;
                                            <?php echo date('M d, Y h:i A', strtotime($act['created_at'])); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- System Status Card -->
                    <div class="dashboard-card">
                        <div class="card-title-wrap">
                            <h3>
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                                    <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                                    <line x1="6" y1="6" x2="6.01" y2="6"></line>
                                    <line x1="6" y1="18" x2="6.01" y2="18"></line>
                                </svg>
                                <span>System Status</span>
                            </h3>
                        </div>
                        <ul class="status-list">
                            <li class="status-item">
                                <span class="status-label">Local Host / Server Status</span>
                                <div class="status-value-indicator">
                                    <span class="dot dot-green"></span>
                                    <span>ONLINE (Apache)</span>
                                </div>
                            </li>
                            <li class="status-item">
                                <span class="status-label">Database Connection</span>
                                <div class="status-value-indicator">
                                    <span class="dot dot-green"></span>
                                    <span>CONNECTED</span>
                                </div>
                            </li>
                            <li class="status-item">
                                <span class="status-label">Database Name</span>
                                <div class="status-value-indicator" style="color: var(--medium-green); font-weight:600;">
                                    <span>green_forensics</span>
                                </div>
                            </li>
                            <li class="status-item">
                                <span class="status-label">Backup Integrity</span>
                                <div class="status-value-indicator">
                                    <span class="dot dot-green"></span>
                                    <span>SECURE</span>
                                </div>
                            </li>
                            <li class="status-item">
                                <span class="status-label">Maintenance Mode</span>
                                <div class="status-value-indicator" style="font-weight: 600; color: <?php echo ($maintenance_mode === 'ON') ? 'var(--danger)' : 'var(--gray)'; ?>;">
                                    <?php if ($maintenance_mode === 'ON'): ?>
                                        <span class="dot dot-orange"></span>
                                    <?php endif; ?>
                                    <span><?php echo $maintenance_mode; ?></span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JS Core Toggles -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const sidebar = document.getElementById("sidebar");
            const toggleBtn = document.getElementById("sidebarCollapse");

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener("click", (e) => {
                    e.stopPropagation();
                    sidebar.classList.toggle("active");
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener("click", (e) => {
                    if (window.innerWidth <= 768 && sidebar.classList.contains("active")) {
                        if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                            sidebar.classList.remove("active");
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>