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
    $token = $_POST["csrf_token"] ?? "";
    if (empty($token) || !hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        $error = "CSRF token validation failed. Unauthorized request.";
    } else {
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
                        <p>Assign and manage user roles after account approval.</p>
                    </div>
                </div>

                <!-- ALERTS -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>



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
                    <p style="font-size: 0.85rem; color: var(--gray); margin-bottom: 1.25rem; font-style: italic; margin-top: 0;">Super Administrator can assign user roles after approval.</p>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>User Name</th>
                                    <th>Email Address</th>
                                    <th>Current Role</th>
                                    <th>Assign New Role</th>
                                    <th style="text-align: right;">Action</th>
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
                                            <td>
                                                <span style="font-size:0.75rem; font-weight:700; color: #52b788; background: rgba(82, 183, 136, 0.15); padding: 4px 8px; border-radius: 4px;">
                                                    <?php echo role_label($user['role'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form id="role-form-<?php echo $user['id']; ?>" method="POST" action="admin_roles.php"
                                                      onsubmit="return <?php echo ($user['id'] === (int)$_SESSION['user_id']) ? 'confirm(\'Warning: Demoting yourself will lock you out of this panel. Continue?\')' : 'true'; ?>;">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                </form>
                                                <select name="new_role" form="role-form-<?php echo $user['id']; ?>" class="form-control-inline" style="padding: 4px 8px; font-size: 0.8rem;" required>
                                                    <option value="" disabled>Select Role...</option>
                                                    <option value="criminology_student" <?php echo ($user['role'] === 'criminology_student') ? 'selected' : ''; ?>>Criminology Student</option>
                                                    <option value="faculty_researcher" <?php echo ($user['role'] === 'faculty_researcher') ? 'selected' : ''; ?>>Faculty Researcher</option>
                                                    <option value="alumni_police_partner" <?php echo ($user['role'] === 'alumni_police_partner') ? 'selected' : ''; ?>>Alumni / Police Partner</option>
                                                    <option value="super_admin" <?php echo ($user['role'] === 'super_admin') ? 'selected' : ''; ?>>Super Administrator</option>
                                                </select>
                                            </td>
                                            <td style="text-align: right;">
                                                <button type="submit" form="role-form-<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">Assign</button>
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
