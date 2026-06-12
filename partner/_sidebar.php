<?php
// partner/_sidebar.php - Reusable Sidebar for Alumni / Police Partner Portal

$p_name = $_SESSION['user_name'] ?? 'Partner';
$p_words = explode(' ', trim($p_name));
$initials = '';
foreach (array_slice($p_words, 0, 2) as $w) {
    $initials .= strtoupper($w[0] ?? '');
}
$initials = $initials ?: 'AP';

function nav_active(string $page, string $current): string {
    return $page === $current ? ' active' : '';
}
?>
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

        <li class="menu-item<?= nav_active('dashboard', $active_page ?? '') ?>">
            <a href="partner_dashboard.php" class="menu-link" id="nav-dashboard">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="9"/>
                    <rect x="14" y="3" width="7" height="5"/>
                    <rect x="14" y="12" width="7" height="9"/>
                    <rect x="3" y="16" width="7" height="5"/>
                </svg>
                <span class="menu-text">Dashboard Overview</span>
            </a>
        </li>

        <li class="menu-section-label">Scientific Results</li>

        <li class="menu-item<?= nav_active('approved_reports', $active_page ?? '') ?>">
            <a href="approved_reports.php" class="menu-link" id="nav-reports">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                <span class="menu-text">Approved Reports</span>
            </a>
        </li>

        <li class="menu-item<?= nav_active('performance_data', $active_page ?? '') ?>">
            <a href="performance_data.php" class="menu-link" id="nav-performance">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
                <span class="menu-text">Performance Data</span>
            </a>
        </li>

        <li class="menu-item<?= nav_active('surface_compatibility', $active_page ?? '') ?>">
            <a href="surface_compatibility.php" class="menu-link" id="nav-surface">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <line x1="3" y1="9" x2="21" y2="9"/>
                    <line x1="9" y1="21" x2="9" y2="9"/>
                </svg>
                <span class="menu-text">Surface Compatibility</span>
            </a>
        </li>

        <li class="menu-section-label">Observations</li>

        <li class="menu-item<?= nav_active('field_feedback', $active_page ?? '') ?>">
            <a href="field_feedback.php" class="menu-link" id="nav-feedback">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span class="menu-text">Field Feedback</span>
            </a>
        </li>

        <li class="menu-section-label">Account</li>

        <li class="menu-item<?= nav_active('profile', $active_page ?? '') ?>">
            <a href="profile.php" class="menu-link" id="nav-profile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="menu-text">My Profile</span>
            </a>
        </li>
    </ul>

    <!-- Bottom: Logout + Profile -->
    <div class="sidebar-bottom">
        <div class="sidebar-logout">
            <a href="../logout.php" class="logout-link" id="nav-logout">
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
                <div class="profile-name" title="<?= htmlspecialchars($p_name) ?>"><?= htmlspecialchars($p_name) ?></div>
                <div class="profile-role">Police Partner</div>
            </div>
        </div>
    </div>
</aside>
