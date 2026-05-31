<?php
// admin/admin_users.php - Super Administrator User Management CRUD
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

$error = "";
$success = "";

// Handle user actions (Add, Edit, Delete, Toggle Status, Reset Password)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];

    // 1. ADD USER
    if ($action === "add") {
        $name = trim($_POST["name"]);
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);
        $status = $_POST["status"];

        if (empty($name) || empty($email) || empty($password)) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                if ($stmt->rowCount() > 0) {
                    $error = "Email address is already registered.";
                } else {
                    // Insert new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insertStmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (:full_name, :email, :password, :role, :status)");
                    $insertStmt->execute([
                        ':full_name' => $name,
                        ':role' => 'criminology_student',
                        ':email' => $email,
                        ':password' => $hashed_password,
                        ':status' => $status
                    ]);
                    $success = "User account created successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error adding user: " . $e->getMessage();
            }
        }
    }

    // 2. EDIT USER
    if ($action === "edit") {
        $id = intval($_POST["id"]);
        $name = trim($_POST["name"]);
        $email = trim($_POST["email"]);
        $status = $_POST["status"];

        if (empty($name) || empty($email)) {
            $error = "Name and email cannot be empty.";
        } else {
            try {
                // Check if email is already taken by someone else
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $stmt->execute([':email' => $email, ':id' => $id]);
                if ($stmt->rowCount() > 0) {
                    $error = "Email address is already in use by another account.";
                } else {
                    // Update user
                    $updateStmt = $pdo->prepare("UPDATE users SET full_name = :full_name, email = :email, status = :status WHERE id = :id");
                    $updateStmt->execute([
                        ':full_name' => $name,
                        ':email' => $email,
                        ':status' => $status,
                        ':id' => $id
                    ]);
                    $success = "User account updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error updating user: " . $e->getMessage();
            }
        }
    }

    // 3. RESET PASSWORD
    if ($action === "reset_password") {
        $id = intval($_POST["id"]);
        $new_password = trim($_POST["new_password"]);

        if (empty($new_password)) {
            $error = "Password cannot be empty.";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->execute([':password' => $hashed_password, ':id' => $id]);
                $success = "Password reset successfully!";
            } catch (PDOException $e) {
                $error = "Error resetting password: " . $e->getMessage();
            }
        }
    }

    // 4. TOGGLE STATUS
    if ($action === "toggle_status") {
        $id = intval($_POST["id"]);
        $current_status = $_POST["current_status"];
        $new_status = ($current_status === "active") ? "inactive" : "active";

        try {
            $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $new_status, ':id' => $id]);
            $success = "Account status updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }

    // 5. DELETE USER
    if ($action === "delete") {
        $id = intval($_POST["id"]);
        // Protect from deleting self
        if ($id == $_SESSION["user_id"]) {
            $error = "You cannot delete your own account.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $success = "User account deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error deleting user: " . $e->getMessage();
            }
        }
    }
}

// Fetch all users with search and filter
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$filter_status = isset($_GET["status"]) ? trim($_GET["status"]) : "";

$query_str = "SELECT id, full_name, email, role, status, created_at FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query_str .= " AND (full_name LIKE :search OR email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_status)) {
    $query_str .= " AND status = :status";
    $params[':status'] = $filter_status;
}

$query_str .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="../css/admin_style.css?v=1.5">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .alert {
            padding: 0.85rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .alert-danger {
            background-color: rgba(224, 122, 95, 0.15);
            color: var(--danger);
            border: 1px solid rgba(224, 122, 95, 0.2);
        }

        .alert-success {
            background-color: rgba(82, 183, 136, 0.15);
            color: var(--medium-green);
            border: 1px solid rgba(82, 183, 136, 0.2);
        }
    </style>
</head>

<body>

    <div class="admin-wrapper">
        <!-- SIDEBAR NAVIGATION -->
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
                        <h4><?php echo htmlspecialchars($_SESSION["user_name"]); ?></h4>
                        <span>Super Admin</span>
                    </div>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="admin_dashboard.php" class="menu-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="9"></rect>
                            <rect x="14" y="3" width="7" height="5"></rect>
                            <rect x="14" y="12" width="7" height="9"></rect>
                            <rect x="3" y="16" width="7" height="5"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="admin_users.php" class="menu-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>User Management</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="admin_records.php" class="menu-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>Records</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="admin_reports.php" class="menu-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                            <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                        </svg>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="admin_security.php" class="menu-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Security / Backup</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="../logout.php" class="menu-link" style="color: #e07a5f;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

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
                        <h2>Green Forensics Evaluating System</h2>
                    </div>
                </div>

                <div class="header-right">
                    <a href="../logout.php" class="header-logout">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Sign Out</span>
                    </a>
                </div>
            </header>

            <!-- Main Content Area -->
            <div class="admin-content">
                <div class="page-header-wrap">
                    <div class="page-title">
                        <h1>User Account Management</h1>
                        <p>Create, update, activate, and manage all Green Forensics system user credentials.</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Add New User</span>
                        </button>
                    </div>
                </div>

                <!-- ALERTS -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- SEARCH AND FILTERS -->
                <div class="dashboard-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
                    <form method="GET" action="admin_users.php" class="search-filter-bar">
                        <div class="bar-left">
                            <input type="text" name="search" class="form-control-inline"
                                placeholder="Search by name or email..."
                                value="<?php echo htmlspecialchars($search); ?>" style="min-width: 280px;">
                            <select name="status" class="form-control-inline">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active
                                    Only</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>
                                    Inactive Only</option>
                            </select>
                            <button type="submit" class="btn btn-secondary">Filter</button>
                            <?php if (!empty($search) || !empty($filter_status)): ?>
                                <a href="admin_users.php" class="btn btn-secondary btn-sm" style="border: none;">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- USERS TABLE -->
                <div class="dashboard-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Email Address</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td style="font-weight: 600; color: var(--dark-green);">
                                                <?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['status']; ?>">
                                                    <?php echo $user['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td style="text-align: right;">
                                                <div class="btn-group" style="justify-content: flex-end;">
                                                    <!-- Toggle Active/Inactive -->
                                                    <form method="POST" action="admin_users.php" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="current_status"
                                                            value="<?php echo $user['status']; ?>">
                                                        <button type="submit" class="icon-btn"
                                                            title="Toggle active/inactive status">
                                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                                                stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                                stroke-linejoin="round">
                                                                <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                                                <line x1="12" y1="2" x2="12" y2="12"></line>
                                                            </svg>
                                                        </button>
                                                    </form>

                                                    <!-- Edit User Info Button -->
                                                    <button class="icon-btn" title="Edit account details"
                                                        onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo $user['status']; ?>')">
                                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path
                                                                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                            </path>
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                            </path>
                                                        </svg>
                                                    </button>

                                                    <!-- Reset Password Button -->
                                                    <button class="icon-btn" title="Reset password"
                                                        onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>')">
                                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                                        </svg>
                                                    </button>

                                                    <!-- Delete Button -->
                                                    <?php if ($user['id'] != $_SESSION["user_id"]): ?>
                                                        <form method="POST" action="admin_users.php" style="display:inline;"
                                                            onsubmit="return confirm('Are you sure you want to permanently delete this user?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="icon-btn danger-hover"
                                                                title="Delete account">
                                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                                                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                                    stroke-linejoin="round">
                                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                                    <path
                                                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                                    </path>
                                                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--gray); padding: 2rem;">No
                                            user accounts found matching query.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ADD USER MODAL -->
    <div class="action-modal-overlay" id="addModal">
        <div class="action-modal">
            <div class="modal-header">
                <h3>Add New User Account</h3>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="add_name">Full Name</label>
                        <input type="text" name="name" id="add_name" class="form-control"
                            placeholder="e.g. Juan dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label for="add_email">Email Address</label>
                        <input type="email" name="email" id="add_email" class="form-control"
                            placeholder="e.g. user@domain.com" required>
                    </div>
                    <div class="form-group">
                        <label for="add_password">Initial Password</label>
                        <input type="password" name="password" id="add_password" class="form-control"
                            placeholder="Minimum 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label for="add_status">Account Status</label>
                        <select name="status" id="add_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT USER MODAL -->
    <div class="action-modal-overlay" id="editModal">
        <div class="action-modal">
            <div class="modal-header">
                <h3>Edit User Details</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">Full Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Account Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RESET PASSWORD MODAL -->
    <div class="action-modal-overlay" id="resetModal">
        <div class="action-modal">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <button class="modal-close" onclick="closeResetModal()">&times;</button>
            </div>
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="reset_id">
                <div class="modal-body">
                    <p style="font-size: 0.85rem; color: var(--gray); margin-bottom: 1rem;">
                        Resetting password for user: <strong id="reset_user_name"
                            style="color: var(--dark-green);"></strong>
                    </p>
                    <div class="form-group">
                        <label for="reset_new_password">New Password</label>
                        <input type="password" name="new_password" id="reset_new_password" class="form-control"
                            placeholder="Minimum 6 characters" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
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

                document.addEventListener("click", (e) => {
                    if (window.innerWidth <= 768 && sidebar.classList.contains("active")) {
                        if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                            sidebar.classList.remove("active");
                        }
                    }
                });
            }
        });

        // Modal triggers
        function openAddModal() {
            document.getElementById("addModal").classList.add("active");
        }
        function closeAddModal() {
            document.getElementById("addModal").classList.remove("active");
        }

        function openEditModal(id, name, email, status) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_email").value = email;
            document.getElementById("edit_status").value = status;
            document.getElementById("editModal").classList.add("active");
        }
        function closeEditModal() {
            document.getElementById("editModal").classList.remove("active");
        }

        function openResetModal(id, name) {
            document.getElementById("reset_id").value = id;
            document.getElementById("reset_user_name").textContent = name;
            document.getElementById("reset_new_password").value = "";
            document.getElementById("resetModal").classList.add("active");
        }
        function closeResetModal() {
            document.getElementById("resetModal").classList.remove("active");
        }
    </script>
</body>

</html>