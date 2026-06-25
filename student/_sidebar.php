<?php
/**
 * student/_sidebar.php
 * Reusable sidebar partial for all Criminology Student pages.
 * 
 * Usage: require_once '_sidebar.php';
 * Before including, define $active_page to match one of the keys below
 * so the correct menu item receives the 'active' class.
 * 
 * Example:  $active_page = 'dashboard';
 */

// Derive initials from full name stored in session
$s_name = $_SESSION['user_name'] ?? 'Student';
$s_words = explode(' ', trim($s_name));
$initials = '';
foreach (array_slice($s_words, 0, 2) as $w) {
    $initials .= strtoupper($w[0] ?? '');
}
$initials = $initials ?: 'CS';

// Helper: return 'active' class if page matches
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
        <span class="role-dot"></span>
        <span class="role-label">Criminology Student</span>
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <li class="menu-section-label">Main</li>

        <li class="menu-item<?= nav_active('dashboard', $active_page ?? '') ?>">
            <a href="student_dashboard.php" class="menu-link" id="nav-dashboard">
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

        <li class="menu-section-label">Submit Data</li>


        <li class="menu-item<?= nav_active('upload_fingerprint', $active_page ?? '') ?>">
            <a href="upload_fingerprint.php" class="menu-link" id="nav-upload-fingerprint">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <span class="menu-text">Upload Fingerprint Images</span>
            </a>
        </li>

        <li class="menu-section-label">View Results</li>

        <li class="menu-item<?= nav_active('surface_performance', $active_page ?? '') ?>">
            <a href="surface_performance.php" class="menu-link" id="nav-surface">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                    <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                </svg>
                <span class="menu-text">View Surface Performance</span>
            </a>
        </li>

        <li class="menu-item<?= nav_active('accuracy_rating', $active_page ?? '') ?>">
            <a href="accuracy_rating.php" class="menu-link" id="nav-accuracy">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                    <polyline points="16 7 22 7 22 13"/>
                </svg>
                <span class="menu-text">View Accuracy Rating</span>
            </a>
        </li>

        <li class="menu-section-label">Logs &amp; Reports</li>

        <li class="menu-item<?= nav_active('safety_climate_log', $active_page ?? '') ?>">
            <a href="safety_climate_log.php" class="menu-link" id="nav-safety">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <span class="menu-text">Safety &amp; Climate Log</span>
            </a>
        </li>

        <li class="menu-item<?= nav_active('student_records', $active_page ?? '') ?>">
            <a href="student_records.php" class="menu-link" id="nav-records">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                <span class="menu-text">View Records / Reports</span>
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
                <div class="profile-name"><?= htmlspecialchars($s_name) ?></div>
                <div class="profile-role">Criminology Student</div>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Top App Bar -->
<header class="student-mobile-topbar">
    <div class="mobile-topbar-brand">
        <span class="mobile-brand-title">Crim Student</span>
    </div>
    <div class="mobile-topbar-actions">
        <!-- New Submission (+) Action -->
        <a href="upload_fingerprint.php" class="mobile-action-btn" id="mobile-action-upload" title="New Submission">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
        </a>
        <!-- Chat Assistant Action -->
        <button type="button" class="mobile-action-btn" id="mobile-action-chat" onclick="toggleSupportChat()" title="Support Assistant">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </button>
        <!-- Profile Initial Avatar with Dropdown -->
        <div class="mobile-profile-container">
            <div class="mobile-profile-avatar" id="mobileProfileBtn" title="<?= htmlspecialchars($s_name) ?>">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div class="mobile-profile-dropdown" id="mobileProfileDropdown">
                <a href="../logout.php" class="mobile-dropdown-item logout" id="mobile-nav-logout-btn">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Bottom Navigation Bar -->
<nav class="student-mobile-nav">
    <a href="student_dashboard.php" class="student-mobile-nav-item<?= nav_active('dashboard', $active_page ?? '') ?>" id="mobile-nav-dashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="9"/>
            <rect x="14" y="3" width="7" height="5"/>
            <rect x="14" y="12" width="7" height="9"/>
            <rect x="3" y="16" width="7" height="5"/>
        </svg>
        <span class="student-mobile-nav-label">Dashboard</span>
    </a>
    <a href="upload_fingerprint.php" class="student-mobile-nav-item<?= nav_active('upload_fingerprint', $active_page ?? '') ?>" id="mobile-nav-upload">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21 15 16 10 5 21"/>
        </svg>
        <span class="student-mobile-nav-label">Upload</span>
    </a>
    <a href="surface_performance.php" class="student-mobile-nav-item<?= nav_active('surface_performance', $active_page ?? '') ?>" id="mobile-nav-surface">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
            <path d="M22 12A10 10 0 0 0 12 2v10z"/>
        </svg>
        <span class="student-mobile-nav-label">Surface</span>
    </a>
    <a href="accuracy_rating.php" class="student-mobile-nav-item<?= nav_active('accuracy_rating', $active_page ?? '') ?>" id="mobile-nav-accuracy">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
            <polyline points="16 7 22 7 22 13"/>
        </svg>
        <span class="student-mobile-nav-label">Accuracy</span>
    </a>
    <a href="safety_climate_log.php" class="student-mobile-nav-item<?= nav_active('safety_climate_log', $active_page ?? '') ?>" id="mobile-nav-safety">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        <span class="student-mobile-nav-label">Safety</span>
    </a>
    <a href="student_records.php" class="student-mobile-nav-item<?= nav_active('student_records', $active_page ?? '') ?>" id="mobile-nav-records">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        <span class="student-mobile-nav-label">Records</span>
    </a>
</nav>
