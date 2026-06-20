<?php
// admin/admin_security.php - Super Administrator Security Logs & Auditing
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

$filter_action = isset($_GET["action_type"]) ? trim($_GET["action_type"]) : "";
$filter_email = isset($_GET["email"]) ? trim($_GET["email"]) : "";
$filter_date = isset($_GET["date"]) ? trim($_GET["date"]) : "";

// Build SQL Query
$query_str = "SELECT id, user_id, user_email, action, details, ip_address, user_agent, created_at FROM activity_logs WHERE 1=1";
$params = [];

if (!empty($filter_action)) {
    $query_str .= " AND action = :action";
    $params[':action'] = $filter_action;
}

if (!empty($filter_email)) {
    $query_str .= " AND user_email LIKE :email";
    $params[':email'] = '%' . $filter_email . '%';
}

if (!empty($filter_date)) {
    $query_str .= " AND DATE(created_at) = :date";
    $params[':date'] = $filter_date;
}

$query_str .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$security_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique action types for filter dropdown
$action_types = [];
try {
    $act_stmt = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
    $action_types = $act_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Count login security statistics
$total_logins = 0;
$failed_logins = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action IN ('Login Success', 'Super Administrator logged in')");
    $total_logins = $stmt ? (int)$stmt->fetchColumn() : 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%Failed%' OR action LIKE '%Unauthorized%'");
    $failed_logins = $stmt ? (int)$stmt->fetchColumn() : 0;
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs & Security Audits - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .stat-card.total-logins::after { background-color: var(--medium-green); }
        .stat-card.failed-logins::after { background-color: var(--danger); }
        .stat-card.total-logins .stat-icon { background: rgba(82, 183, 136, 0.1); color: var(--medium-green); }
        .stat-card.failed-logins .stat-icon { background: rgba(224, 122, 95, 0.1); color: var(--danger); }

        .security-recommendation-card {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(27, 67, 50, 0.05);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .security-item {
            border-left: 4px solid var(--medium-green);
            padding-left: 1rem;
            background: var(--cream);
            padding: 10px 15px;
            border-radius: 0 8px 8px 0;
            font-size: 0.85rem;
        }

        .security-item.alert-item {
            border-left-color: var(--danger);
            background: rgba(224, 122, 95, 0.05);
        }

        .security-item.warning-item {
            border-left-color: var(--warning);
            background: rgba(244, 162, 97, 0.05);
        }
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
                        <h1>Activity Logs & Security Audits</h1>
                        <p>Track access history, failed login attempts, status changes, role assignments, and system settings modifications.</p>
                    </div>
                </div>

                <div class="dashboard-grid" style="margin-bottom: 2rem;">
                    <!-- Access Stats Card -->
                    <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom:0;">
                        <div class="stat-card total-logins" style="margin-bottom:0;">
                            <div class="stat-header">
                                <span class="stat-title">Successful Logins</span>
                                <div class="stat-icon">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $total_logins; ?></div>
                            <div class="stat-desc">Authorized access actions</div>
                        </div>

                        <div class="stat-card failed-logins" style="margin-bottom:0;">
                            <div class="stat-header">
                                <span class="stat-title">Failed Attemps</span>
                                <div class="stat-icon">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $failed_logins; ?></div>
                            <div class="stat-desc">Blocked unauthorized requests</div>
                        </div>
                    </div>

                    <!-- Security Info Card -->
                    <div class="security-recommendation-card">
                        <div class="card-title-wrap" style="margin-bottom:0; padding-bottom:0.5rem;">
                            <h3 style="font-size:0.95rem;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                </svg>
                                <span>Security Recommendations</span>
                            </h3>
                        </div>
                        <div class="security-item">
                            <strong>1. Auditing Logs Regularly:</strong> Monitor IP addresses and user agents for login anomalies or repeated failures.
                        </div>
                        <div class="security-item alert-item">
                            <strong>2. Failed Login Limit:</strong> The system locks accounts temporarily after multiple failed logins. Set in System Settings.
                        </div>
                    </div>
                </div>

                <!-- SEARCH AND FILTERS -->
                <div class="dashboard-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
                    <form method="GET" action="admin_security.php" class="search-filter-bar">
                        <div class="bar-left">
                            <input type="text" name="email" class="form-control-inline"
                                placeholder="Search by email..."
                                value="<?php echo htmlspecialchars($filter_email); ?>" style="min-width: 220px;">
                            
                            <select name="action_type" class="form-control-inline">
                                <option value="">All Action Types</option>
                                <?php foreach ($action_types as $act): ?>
                                    <option value="<?php echo htmlspecialchars($act); ?>" <?php echo ($filter_action === $act) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($act); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="date" name="date" class="form-control-inline"
                                value="<?php echo htmlspecialchars($filter_date); ?>">

                            <button type="submit" class="btn btn-secondary">Filter Logs</button>
                            <?php if (!empty($filter_action) || !empty($filter_email) || !empty($filter_date)): ?>
                                <a href="admin_security.php" class="btn btn-secondary btn-sm" style="border: none;">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- AUDIT LOGS TABLE -->
                <div class="dashboard-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Email / Account</th>
                                    <th>Action Type</th>
                                    <th>Details / Activity</th>
                                    <th>Timestamp</th>
                                    <th>User Browser Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($security_logs) > 0): ?>
                                    <?php foreach ($security_logs as $log): ?>
                                        <?php 
                                            $is_failed = (strpos(strtolower($log['action']), 'fail') !== false || strpos(strtolower($log['action']), 'reject') !== false || strpos(strtolower($log['action']), 'suspend') !== false);
                                        ?>
                                        <tr>
                                            <td style="font-family: monospace; font-weight:700; color: var(--gray);">
                                                <?php echo htmlspecialchars($log['ip_address']); ?>
                                            </td>
                                            <td style="font-weight: 600; color: var(--dark-green);">
                                                <?php echo htmlspecialchars($log['user_email']); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $is_failed ? 'inactive' : 'success'; ?>" style="font-size:0.65rem;">
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                            <td style="font-size:0.72rem; color: var(--gray); font-family: monospace; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                                <?php echo htmlspecialchars($log['user_agent']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--gray); padding: 2rem;">No system logs match security filters.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JS Toggles -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const sidebar = document.getElementById("sidebar");
            const toggleBtn = document.getElementById("sidebarCollapse");

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener("click", (e) => {
                    e.stopPropagation();
                    sidebar.classList.toggle("active");
                });

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
<?php include '../includes/support_chat_widget.php'; ?>
</body>

</html>