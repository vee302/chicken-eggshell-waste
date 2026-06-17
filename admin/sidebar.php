<?php
// admin/sidebar.php - Shared Sidebar Template for Super Admin
require_once "../config.php";

$current_page = basename($_SERVER['PHP_SELF']);

// Query pending count dynamically
$sidebar_pending_count = 0;
try {
    if (isset($pdo)) {
        $stmt_pending = $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'");
        $sidebar_pending_count = $stmt_pending ? (int)$stmt_pending->fetchColumn() : 0;
    }
} catch (PDOException $e) {
    // Fail silently
}
?>
<aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-text">
            <span>GREEN</span><span class="brand-accent">FORENSICS</span>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar">SA</div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($_SESSION["user_name"] ?? 'Super Admin'); ?></h4>
                <span>Super Admin</span>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <!-- 1. Dashboard Overview -->
        <li class="menu-item <?php echo ($current_page === 'admin_dashboard.php' || $current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <a href="dashboard.php" class="menu-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="9"></rect>
                    <rect x="14" y="3" width="7" height="5"></rect>
                    <rect x="14" y="12" width="7" height="9"></rect>
                    <rect x="3" y="16" width="7" height="5"></rect>
                </svg>
                <span>Dashboard Overview</span>
            </a>
        </li>

        <!-- 2. Pending User Approvals -->
        <li class="menu-item <?php echo ($current_page === 'admin_pending.php') ? 'active' : ''; ?>">
            <a href="admin_pending.php" class="menu-link" style="position:relative;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Pending Approvals<?php if ($sidebar_pending_count > 0): ?> <span style="background:var(--danger);color:#fff;border-radius:20px;font-size:.65rem;padding:1px 7px;font-weight:700;margin-left:4px;"><?php echo $sidebar_pending_count; ?></span><?php endif; ?></span>
            </a>
        </li>

        <!-- 3. User Management -->
        <li class="menu-item <?php echo ($current_page === 'admin_users.php') ? 'active' : ''; ?>">
            <a href="admin_users.php" class="menu-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>User Management</span>
            </a>
        </li>

        <!-- 4. Role Management -->
        <li class="menu-item <?php echo ($current_page === 'admin_roles.php') ? 'active' : ''; ?>">
            <a href="admin_roles.php" class="menu-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <rect x="9" y="11" width="6" height="4" rx="1"></rect>
                </svg>
                <span>Role Management</span>
            </a>
        </li>

        <!-- 5. Trial Records Monitoring -->
        <li class="menu-item <?php echo ($current_page === 'admin_records.php') ? 'active' : ''; ?>">
            <a href="admin_records.php" class="menu-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <span>Trial Records</span>
            </a>
        </li>

        <!-- 6. Reports Monitoring -->
        <li class="menu-item <?php echo ($current_page === 'admin_reports.php') ? 'active' : ''; ?>">
            <a href="admin_reports.php" class="menu-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                    <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                </svg>
                <span>Reports Monitoring</span>
            </a>
        </li>

        <!-- 7. Activity Logs / Security Audits -->
        <li class="menu-item <?php echo ($current_page === 'admin_security.php') ? 'active' : ''; ?>">
            <a href="admin_security.php" class="menu-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    <line x1="12" y1="15" x2="12" y2="18"></line>
                </svg>
                <span>Activity Logs</span>
            </a>
        </li>

        <!-- 8. System Settings -->
        <li class="menu-item <?php echo ($current_page === 'admin_settings.php') ? 'active' : ''; ?>">
            <a href="admin_settings.php" class="menu-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                <span>System Settings</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="../logout.php" class="menu-link" style="color: var(--danger);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</aside>
