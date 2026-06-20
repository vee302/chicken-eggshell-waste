<?php
// admin/admin_roles.php - Role Management & Permissions Panel
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

$error = "";
$success = "";

// Handle role change requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "change_role") {
    $uid = intval($_POST["user_id"] ?? 0);
    $new_role = trim($_POST["new_role"] ?? "");

    if (!in_array($new_role, ['super_admin','faculty_researcher','criminology_student','alumni_police_partner'])) {
        $error = "Invalid role selection.";
    } elseif ($uid === (int)$_SESSION["user_id"] && $new_role !== 'super_admin') {
        $error = "For security reasons, you cannot demote yourself from Super Administrator.";
    } else {
        try {
            // Get user's current role and email
            $stmt = $pdo->prepare("SELECT email, role FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $uid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $email = $user['email'];
                $old_role = $user['role'];

                // Update role
                $upd = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
                $upd->execute([':role' => $new_role, ':id' => $uid]);

                log_activity("Change Role", "Changed role of $email from " . ($old_role ?: 'unassigned') . " to $new_role");
                $success = "Successfully changed role of " . htmlspecialchars($email) . " to " . str_replace('_', ' ', $new_role) . ".";
            } else {
                $error = "User not found.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch users
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$filter_role = isset($_GET["role"]) ? trim($_GET["role"]) : "";

$query_str = "SELECT id, full_name, email, role, department, status FROM users WHERE status='active'";
$params = [];

if (!empty($search)) {
    $query_str .= " AND (full_name LIKE :search OR email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_role)) {
    $query_str .= " AND role = :role";
    $params[':role'] = $filter_role;
}

$query_str .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

function role_label($r) {
    $map = [
        'criminology_student' => 'Criminology Student',
        'faculty_researcher' => 'Faculty Researcher',
        'alumni_police_partner' => 'Alumni / Police Partner',
        'super_admin' => 'Super Administrator'
    ];
    return $map[$r] ?? str_replace('_', ' ', $r);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; }
        .alert-danger { background-color: rgba(224, 122, 95, 0.15); color: var(--danger); border: 1px solid rgba(224, 122, 95, 0.2); }
        .alert-success { background-color: rgba(82, 183, 136, 0.15); color: var(--medium-green); border: 1px solid rgba(82, 183, 136, 0.2); }
        
        /* Permissions block */
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-top: 1.5rem;
        }
        
        .perm-card {
            background:#fff;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            border: 1px solid rgba(27, 67, 50, 0.08);
            box-shadow: var(--box-shadow);
        }
        
        .perm-header {
            font-weight: 700;
            color: var(--dark-green);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1.5px solid var(--light-gray);
            padding-bottom: 0.5rem;
        }

        .perm-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .perm-item {
            font-size: 0.8rem;
            color: #5f5f5f;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        .perm-item::before {
            content: '✓';
            color: var(--medium-green);
            font-weight: bold;
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
                        <h1>Role Management & Access Permissions</h1>
                        <p>Assign final security clearance roles, manage user access scopes, and audit portal permissions matrix.</p>
                    </div>
                </div>

                <!-- ALERTS -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- ROLE MATRIX / PERMISSIONS MATRIX CARD -->
                <div class="dashboard-card" style="margin-bottom: 2rem;">
                    <div class="card-title-wrap">
                        <h3>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <span>Security Roles Permission Matrix</span>
                        </h3>
                    </div>
                    
                    <div class="permissions-grid">
                        <!-- Super Admin -->
                        <div class="perm-card" style="border-left: 4px solid var(--medium-green);">
                            <div class="perm-header">
                                <span>Super Administrator</span>
                            </div>
                            <ul class="perm-list">
                                <li class="perm-item">Full system access controls</li>
                                <li class="perm-item">User approval & account management</li>
                                <li class="perm-item">Role configuration reassignments</li>
                                <li class="perm-item">Reports monitoring & activity logs</li>
                                <li class="perm-item">Database backup & restoration</li>
                                <li class="perm-item">General system configurations</li>
                            </ul>
                        </div>

                        <!-- Faculty Researcher -->
                        <div class="perm-card" style="border-left: 4px solid #2a6f97;">
                            <div class="perm-header" style="color: #2a6f97;">
                                <span>Faculty Researcher</span>
                            </div>
                            <ul class="perm-list">
                                <li class="perm-item">View student fingerprint submissions</li>
                                <li class="perm-item">Validate accuracy scores & submit decisions</li>
                                <li class="perm-item">View comparative metrics & success data</li>
                                <li class="perm-item">View safety & climate logger metrics</li>
                                <li class="perm-item">Compile evaluation reports</li>
                            </ul>
                        </div>

                        <!-- Criminology Student -->
                        <div class="perm-card" style="border-left: 4px solid #f4a261;">
                            <div class="perm-header" style="color: #f4a261;">
                                <span>Criminology Student</span>
                            </div>
                            <ul class="perm-list">
                                <li class="perm-item">Submit latent fingerprint evaluation data</li>
                                <li class="perm-item">Upload fingerprint photography files</li>
                                <li class="perm-item">View own records & historical data</li>
                                <li class="perm-item">View automated image evaluation scores</li>
                                <li class="perm-item">Submit safety and climate logs</li>
                            </ul>
                        </div>

                        <!-- Alumni Police Partner -->
                        <div class="perm-card" style="border-left: 4px solid #7251b5;">
                            <div class="perm-header" style="color: #7251b5;">
                                <span>Alumni / Police Partner</span>
                            </div>
                            <ul class="perm-list">
                                <li class="perm-item">View approved forensic studies & reports</li>
                                <li class="perm-item">View sustainable powder performance logs</li>
                                <li class="perm-item">Submit partner feedback on trials</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- SEARCH & FILTERS -->
                <div class="dashboard-card" style="margin-bottom:1.5rem; padding:1.25rem;">
                    <form method="GET" action="admin_roles.php" class="search-filter-bar">
                        <div class="bar-left">
                            <input type="text" name="search" class="form-control-inline"
                                placeholder="Search active users..."
                                value="<?php echo htmlspecialchars($search); ?>" style="min-width: 250px;">
                            
                            <select name="role" class="form-control-inline">
                                <option value="">All Roles</option>
                                <option value="super_admin" <?php echo $filter_role === 'super_admin' ? 'selected' : ''; ?>>Super Administrator</option>
                                <option value="faculty_researcher" <?php echo $filter_role === 'faculty_researcher' ? 'selected' : ''; ?>>Faculty Researcher</option>
                                <option value="criminology_student" <?php echo $filter_role === 'criminology_student' ? 'selected' : ''; ?>>Criminology Student</option>
                                <option value="alumni_police_partner" <?php echo $filter_role === 'alumni_police_partner' ? 'selected' : ''; ?>>Alumni / Police Partner</option>
                            </select>

                            <button type="submit" class="btn btn-secondary">Filter</button>
                            <?php if (!empty($search) || !empty($filter_role)): ?>
                                <a href="admin_roles.php" class="btn btn-secondary btn-sm" style="border: none;">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- USERS ROLE TABLE -->
                <div class="dashboard-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>User Name</th>
                                    <th>Email Address</th>
                                    <th>Department</th>
                                    <th>Current Role</th>
                                    <th style="text-align: right;">Assign Role Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--dark-green);">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['department'] ?: '—'); ?></td>
                                            <td>
                                                <span style="font-size:0.75rem; font-weight:700; color: #52b788; background: rgba(82, 183, 136, 0.15); padding: 4px 8px; border-radius: 4px;">
                                                    <?php echo role_label($user['role'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <form method="POST" action="admin_roles.php" style="display:inline-flex; gap:6px; align-items:center;"
                                                      onsubmit="return <?php echo ($user['id'] === (int)$_SESSION['user_id']) ? 'confirm(\'Warning: Demoting yourself will lock you out of this panel. Continue?\')' : 'true'; ?>;">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <select name="new_role" class="form-control-inline" style="padding: 4px 8px; font-size: 0.8rem;" required>
                                                        <option value="" disabled>Select Role...</option>
                                                        <option value="criminology_student" <?php echo ($user['role'] === 'criminology_student') ? 'selected' : ''; ?>>Criminology Student</option>
                                                        <option value="faculty_researcher" <?php echo ($user['role'] === 'faculty_researcher') ? 'selected' : ''; ?>>Faculty Researcher</option>
                                                        <option value="alumni_police_partner" <?php echo ($user['role'] === 'alumni_police_partner') ? 'selected' : ''; ?>>Alumni / Police Partner</option>
                                                        <option value="super_admin" <?php echo ($user['role'] === 'super_admin') ? 'selected' : ''; ?>>Super Administrator</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-sm">Assign</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--gray); padding: 2rem;">No active users found.</td>
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
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>

</html>
