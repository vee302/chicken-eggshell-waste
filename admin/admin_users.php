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
        $role = $_POST["role"] ?? "criminology_student";
        $status = $_POST["status"];
        $department = trim($_POST["department"] ?? "");
        $affiliation = trim($_POST["affiliation"] ?? "");

        if (empty($name) || empty($email) || empty($password)) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (!in_array($role, ['super_admin','faculty_researcher','criminology_student','alumni_police_partner'], true)) {
            $error = "Invalid role selected.";
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
                    $insertStmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, department, affiliation, status) VALUES (:full_name, :email, :password, :role, :department, :affiliation, :status)");
                    $insertStmt->execute([
                        ':full_name' => $name,
                        ':email' => $email,
                        ':password' => $hashed_password,
                        ':role' => $role,
                        ':department' => $department,
                        ':affiliation' => $affiliation,
                        ':status' => $status
                    ]);
                    log_activity("Add User", "Created user account: $email with role: $role");
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
        $role = $_POST["role"] ?? "";
        $department = trim($_POST["department"] ?? "");
        $affiliation = trim($_POST["affiliation"] ?? "");

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
                    // Fetch current user details to see if role changed (prevent public self-promotion is managed by only super_admin editing)
                    $curr_stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
                    $curr_stmt->execute([':id' => $id]);
                    $curr_role = $curr_stmt->fetchColumn();

                    // Update user
                    $updateStmt = $pdo->prepare("UPDATE users SET full_name = :full_name, email = :email, role = :role, department = :department, affiliation = :affiliation, status = :status WHERE id = :id");
                    $updateStmt->execute([
                        ':full_name' => $name,
                        ':email' => $email,
                        ':role' => !empty($role) ? $role : $curr_role,
                        ':department' => $department,
                        ':affiliation' => $affiliation,
                        ':status' => $status,
                        ':id' => $id
                    ]);
                    log_activity("Edit User", "Updated user details for $email (status: $status, role: " . (!empty($role) ? $role : $curr_role) . ")");
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
                
                // Fetch email for logging
                $u_stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
                $u_stmt->execute([':id' => $id]);
                $u_email = $u_stmt->fetchColumn();

                log_activity("Reset Password", "Reset password for user: $u_email");
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

            // Fetch email for logging
            $u_stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
            $u_stmt->execute([':id' => $id]);
            $u_email = $u_stmt->fetchColumn();

            log_activity("Toggle Status", "Toggled status of $u_email to $new_status");
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
                // Fetch email for logging
                $u_stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
                $u_stmt->execute([':id' => $id]);
                $u_email = $u_stmt->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);
                
                log_activity("Delete User", "Deleted user account: $u_email");
                $success = "User account deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error deleting user: " . $e->getMessage();
            }
        }
    }
}

// Fetch all users with search and filters
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$filter_status = isset($_GET["status"]) ? trim($_GET["status"]) : "";
$filter_role = isset($_GET["role"]) ? trim($_GET["role"]) : "";

$query_str = "SELECT id, full_name, email, role, department, affiliation, status, created_at FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query_str .= " AND (full_name LIKE :search OR email LIKE :search OR department LIKE :search OR affiliation LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_status)) {
    $query_str .= " AND status = :status";
    $params[':status'] = $filter_status;
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
    <title>User Management - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
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
                        <h1>User Management</h1>
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
                                placeholder="Search by name, email, department..."
                                value="<?php echo htmlspecialchars($search); ?>" style="min-width: 250px;">
                            
                            <select name="role" class="form-control-inline">
                                <option value="">All Roles</option>
                                <option value="super_admin" <?php echo $filter_role === 'super_admin' ? 'selected' : ''; ?>>Super Administrator</option>
                                <option value="faculty_researcher" <?php echo $filter_role === 'faculty_researcher' ? 'selected' : ''; ?>>Faculty Researcher</option>
                                <option value="criminology_student" <?php echo $filter_role === 'criminology_student' ? 'selected' : ''; ?>>Criminology Student</option>
                                <option value="alumni_police_partner" <?php echo $filter_role === 'alumni_police_partner' ? 'selected' : ''; ?>>Alumni / Police Partner</option>
                            </select>

                            <select name="status" class="form-control-inline">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>

                            <button type="submit" class="btn btn-secondary">Filter</button>
                            <?php if (!empty($search) || !empty($filter_status) || !empty($filter_role)): ?>
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
                                    <th>Name</th>
                                    <th>Email Address</th>
                                    <th>Role</th>
                                    <th>Department / Affiliation</th>
                                    <th>Status</th>
                                    <th>Date Created</th>
                                    <th style="text-align: right;">Actions</th>
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
                                                <span style="font-size:.75rem; font-weight:700; color:#6B8F71;">
                                                    <?php echo role_label($user['role'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="display:block; font-weight:500;"><?php echo htmlspecialchars($user['department'] ?: '—'); ?></span>
                                                <span style="font-size:.75rem; color:#888;"><?php echo htmlspecialchars($user['affiliation'] ?: '—'); ?></span>
                                            </td>
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
                                                        onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['department'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['affiliation'] ?? '', ENT_QUOTES); ?>', '<?php echo $user['status']; ?>')">
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
                                        <td colspan="7" style="text-align: center; color: var(--gray); padding: 2rem;">No
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
                        <label for="add_role">Role</label>
                        <select name="role" id="add_role" class="form-control">
                            <option value="criminology_student">Criminology Student</option>
                            <option value="faculty_researcher">Faculty Researcher</option>
                            <option value="alumni_police_partner">Alumni / Police Partner</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_department">Department</label>
                        <input type="text" name="department" id="add_department" class="form-control"
                            placeholder="e.g. Criminology Dept">
                    </div>
                    <div class="form-group">
                        <label for="add_affiliation">Affiliation</label>
                        <input type="text" name="affiliation" id="add_affiliation" class="form-control"
                            placeholder="e.g. LSPU CCJE / Police Force">
                    </div>
                    <div class="form-group">
                        <label for="add_status">Account Status</label>
                        <select name="status" id="add_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
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
                        <label for="edit_role">Role</label>
                        <select name="role" id="edit_role" class="form-control">
                            <option value="criminology_student">Criminology Student</option>
                            <option value="faculty_researcher">Faculty Researcher</option>
                            <option value="alumni_police_partner">Alumni / Police Partner</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_department">Department</label>
                        <input type="text" name="department" id="edit_department" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_affiliation">Affiliation</label>
                        <input type="text" name="affiliation" id="edit_affiliation" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Account Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                            <option value="rejected">Rejected</option>
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

        function openEditModal(id, name, email, role, department, affiliation, status) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_email").value = email;
            document.getElementById("edit_role").value = role;
            document.getElementById("edit_department").value = department;
            document.getElementById("edit_affiliation").value = affiliation;
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
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>

</html>
