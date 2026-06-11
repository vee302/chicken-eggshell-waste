<?php
// admin/admin_pending.php - Pending Registration Approvals
require_once "../config.php";
require_once "auth.php";
check_admin_auth();

$error = "";
$success = "";

// Handle Approve / Reject actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];
    $uid    = intval($_POST["user_id"] ?? 0);

    if ($action === "approve") {
        $approved_role = trim($_POST["approved_role"] ?? "");
        if (empty($approved_role) || !in_array($approved_role, ['criminology_student','faculty_researcher','alumni_police_partner','super_admin'])) {
            $error = "Please select a valid role to assign before approving.";
        } else {
            try {
                // Get user details for logging
                $u_stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = :id LIMIT 1");
                $u_stmt->execute([':id' => $uid]);
                $user_info = $u_stmt->fetch(PDO::FETCH_ASSOC);
                $u_email = $user_info ? $user_info['email'] : "ID $uid";

                $stmt = $pdo->prepare("UPDATE users SET status='active', role=:role WHERE id=:id");
                $stmt->execute([':role' => $approved_role, ':id' => $uid]);
                
                log_activity("Approve User", "Approved user registration for $u_email (assigned role: $approved_role)");
                $success = "User account approved and role assigned successfully.";
            } catch (PDOException $e) { 
                $error = "Error: " . $e->getMessage(); 
            }
        }
    } elseif ($action === "reject") {
        try {
            // Get user details for logging
            $u_stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
            $u_stmt->execute([':id' => $uid]);
            $u_email = $u_stmt->fetchColumn() ?: "ID $uid";

            $stmt = $pdo->prepare("UPDATE users SET status='rejected' WHERE id=:id");
            $stmt->execute([':id' => $uid]);

            log_activity("Reject User", "Rejected user registration for $u_email");
            $success = "Registration request rejected successfully.";
        } catch (PDOException $e) { 
            $error = "Error: " . $e->getMessage(); 
        }
    }
}

// View detail of a single user
$view_user = null;
if (isset($_GET['view'])) {
    $vid = intval($_GET['view']);
    try {
        $vs = $pdo->prepare("SELECT * FROM users WHERE id=:id LIMIT 1");
        $vs->execute([':id' => $vid]);
        $view_user = $vs->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Fetch pending users
$search = trim($_GET['search'] ?? '');
$qStr = "SELECT id, first_name, middle_name, last_name, full_name, email, contact_number, id_number, department, requested_role, reason_for_access, status, created_at FROM users WHERE status='pending'";
$params = [];
if (!empty($search)) {
    $qStr .= " AND (full_name LIKE :s OR email LIKE :s OR id_number LIKE :s)";
    $params[':s'] = "%$search%";
}
$qStr .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($qStr);
$stmt->execute($params);
$pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Pending Approvals - Green Forensics Admin</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .alert { padding:.85rem 1.25rem; margin-bottom:1.5rem; border-radius:8px; font-size:.85rem; font-weight:500; }
        .alert-danger  { background:rgba(224,122,95,.15); color:var(--danger); border:1px solid rgba(224,122,95,.2); }
        .alert-success { background:rgba(82,183,136,.15); color:var(--medium-green); border:1px solid rgba(82,183,136,.2); }
        .badge-pending  { background:rgba(243,156,18,.15); color:#b7770d; border:1px solid rgba(243,156,18,.25); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-active   { background:rgba(82,183,136,.15); color:#1e7e34; border:1px solid rgba(82,183,136,.25); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-rejected { background:rgba(220,53,69,.12); color:#c0392b; border:1px solid rgba(220,53,69,.2); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .approve-form { display:inline-flex; gap:6px; align-items:center; }
        .role-select-sm { padding:6px 10px; border-radius:6px; border:1.5px solid #ccc; font-size:.75rem; font-family:inherit; color:#2F4F3A; cursor:pointer; background:#fff; }
        .role-select-sm:focus { border-color:#6B8F71; outline:none; }
        .btn-approve { background:var(--medium-green); color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:.75rem; font-weight:700; cursor:pointer; transition:background .2s; }
        .btn-approve:hover { background:var(--dark-green); }
        .btn-reject  { background:transparent; color:var(--danger); border:1.5px solid var(--danger); padding:6px 10px; border-radius:6px; font-size:.75rem; font-weight:700; cursor:pointer; transition:all .2s; }
        .btn-reject:hover { background:var(--danger); color:#fff; }
        .btn-view    { background:transparent; color:#6B8F71; border:1.5px solid #6B8F71; padding:6px 10px; border-radius:6px; font-size:.75rem; font-weight:700; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-block; }
        .btn-view:hover { background:#6B8F71; color:#fff; }
        .count-badge { background:rgba(47,79,58,.1); color:#2F4F3A; border-radius:20px; font-size:.75rem; font-weight:700; padding:2px 10px; margin-left:8px; }

        /* Detail modal */
        .detail-overlay { display:none; position:fixed; inset:0; background:rgba(27, 67, 50, 0.45); backdrop-filter: blur(4px); z-index:9999; align-items:center; justify-content:center; }
        .detail-overlay.open { display:flex; }
        .detail-modal { background:#fff; border-radius:16px; max-width:540px; width:92%; max-height:88vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); border: 1px solid rgba(27,67,50,0.1); }
        .detail-modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:var(--dark-green); color:#fff; }
        .detail-modal-header h3 { color:#fff; font-size:1.05rem; font-weight:700; margin:0; }
        .detail-modal-body { padding:1.5rem; }
        .detail-row { display:flex; gap:.5rem; margin-bottom:.75rem; font-size:.85rem; }
        .detail-label { min-width:140px; font-weight:600; color:var(--dark-green); }
        .detail-value { color:#5f5f5f; flex:1; }
        .modal-close-btn { background:none; border:none; font-size:1.4rem; cursor:pointer; color:#fff; opacity:0.8; line-height:1; }
        .modal-close-btn:hover { opacity:1; }
        .section-divider { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#6B8F71; border-bottom:1px solid #D2E2D5; padding-bottom:.35rem; margin:.75rem 0 .6rem; }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- SIDEBAR -->
    <?php include "sidebar.php"; ?>

    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <div class="header-title"><h2>Green Forensics — Super Administrator Dashboard</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Pending User Approvals <span class="count-badge"><?php echo count($pending_users); ?> pending</span></h1>
                    <p>Review registration requests and assign proper authentication roles to new users.</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Search -->
            <div class="dashboard-card" style="margin-bottom:1.5rem;padding:1.25rem;">
                <form method="GET" action="admin_pending.php" class="search-filter-bar">
                    <div class="bar-left">
                        <input type="text" name="search" class="form-control-inline"
                            placeholder="Search pending users by name, email, or ID..."
                            value="<?php echo htmlspecialchars($search); ?>" style="min-width:320px;">
                        <button type="submit" class="btn btn-secondary">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="admin_pending.php" class="btn btn-secondary btn-sm" style="border:none;">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Pending Table -->
            <div class="dashboard-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Name / ID</th>
                                <th>Email / Contact</th>
                                <th>Requested Role</th>
                                <th>Reason for Access</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($pending_users) > 0): ?>
                            <?php foreach ($pending_users as $u): ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--dark-green);display:block;">
                                        <?php echo htmlspecialchars($u['full_name'] ?: trim($u['first_name'].' '.$u['last_name'])); ?>
                                    </strong>
                                    <span style="font-size:.75rem;color:#888;"><?php echo htmlspecialchars($u['id_number'] ?? '—'); ?></span>
                                </td>
                                <td>
                                    <span style="display:block;"><?php echo htmlspecialchars($u['email']); ?></span>
                                    <span style="font-size:.75rem;color:#888;"><?php echo htmlspecialchars($u['contact_number'] ?? '—'); ?></span>
                                </td>
                                <td><span style="font-size:.75rem;font-weight:700;color:#6B8F71;"><?php echo role_label($u['requested_role'] ?? ''); ?></span></td>
                                <td>
                                    <div style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($u['reason_for_access'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($u['reason_for_access'] ?? '—'); ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td><span class="badge-pending">Pending</span></td>
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;align-items:center;">
                                        <!-- View Details -->
                                        <a href="admin_pending.php?view=<?php echo $u['id']; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn-view">View Details</a>

                                        <!-- Approve Form -->
                                        <form method="POST" action="admin_pending.php" class="approve-form">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <select name="approved_role" class="role-select-sm" required>
                                                <option value="">Edit Assigned Role...</option>
                                                <option value="criminology_student" <?php echo ($u['requested_role'] === 'criminology_student') ? 'selected' : ''; ?>>Criminology Student</option>
                                                <option value="faculty_researcher" <?php echo ($u['requested_role'] === 'faculty_researcher') ? 'selected' : ''; ?>>Faculty Researcher</option>
                                                <option value="alumni_police_partner" <?php echo ($u['requested_role'] === 'alumni_police_partner') ? 'selected' : ''; ?>>Alumni / Police Partner</option>
                                                <option value="super_admin">Super Administrator</option>
                                            </select>
                                            <button type="submit" class="btn-approve">Approve</button>
                                        </form>

                                        <!-- Reject Form -->
                                        <form method="POST" action="admin_pending.php" style="display:inline;"
                                            onsubmit="return confirm('Are you sure you want to REJECT this registration request?');">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn-reject">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:var(--gray);padding:3rem 2rem;">
                                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#D2E2D5;display:block;margin:0 auto 1rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    No pending registrations at this time.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- VIEW DETAIL MODAL -->
<?php if ($view_user): ?>
<div class="detail-overlay open" id="detailOverlay">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3>Registration Details</h3>
            <button class="modal-close-btn" onclick="document.getElementById('detailOverlay').classList.remove('open')">&times;</button>
        </div>
        <div class="detail-modal-body">
            <p class="section-divider">Personal Information</p>
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><?php echo htmlspecialchars($view_user['full_name'] ?: trim($view_user['first_name'].' '.($view_user['middle_name'] ?? '').' '.$view_user['last_name'])); ?></span></div>
            <div class="detail-row"><span class="detail-label">ID Number</span><span class="detail-value"><?php echo htmlspecialchars($view_user['id_number'] ?? '—'); ?></span></div>
            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($view_user['email']); ?></span></div>
            <div class="detail-row"><span class="detail-label">Contact Number</span><span class="detail-value"><?php echo htmlspecialchars($view_user['contact_number'] ?? '—'); ?></span></div>
            <div class="detail-row"><span class="detail-label">Department</span><span class="detail-value"><?php echo htmlspecialchars($view_user['department'] ?? '—'); ?></span></div>
            
            <p class="section-divider">Access Request</p>
            <div class="detail-row"><span class="detail-label">Requested Role</span><span class="detail-value"><?php echo role_label($view_user['requested_role'] ?? ''); ?></span></div>
            <div class="detail-row"><span class="detail-label">Reason for Access</span><span class="detail-value"><?php echo nl2br(htmlspecialchars($view_user['reason_for_access'] ?? '—')); ?></span></div>
            <div class="detail-row"><span class="detail-label">Registered On</span><span class="detail-value"><?php echo date('F d, Y g:i A', strtotime($view_user['created_at'])); ?></span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><span class="badge-pending"><?php echo ucfirst($view_user['status']); ?></span></span></div>

            <?php if ($view_user['status'] === 'pending'): ?>
            <div style="display:flex;gap:.75rem;margin-top:1.5rem;border-top:1px solid #eee;padding-top:1.25rem;">
                <form method="POST" action="admin_pending.php" class="approve-form" style="flex:1;display:flex;gap:6px;">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="user_id" value="<?php echo $view_user['id']; ?>">
                    <select name="approved_role" class="role-select-sm" required style="flex:1;padding:8px;">
                        <option value="criminology_student" <?php echo $view_user['requested_role']==='criminology_student'?'selected':''; ?>>Criminology Student</option>
                        <option value="faculty_researcher" <?php echo $view_user['requested_role']==='faculty_researcher'?'selected':''; ?>>Faculty Researcher</option>
                        <option value="alumni_police_partner" <?php echo $view_user['requested_role']==='alumni_police_partner'?'selected':''; ?>>Alumni / Police Partner</option>
                        <option value="super_admin">Super Administrator</option>
                    </select>
                    <button type="submit" class="btn-approve" style="padding:8px 16px;">Approve Access</button>
                </form>
                <form method="POST" action="admin_pending.php" onsubmit="return confirm('Reject this registration?');">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" value="<?php echo $view_user['id']; ?>">
                    <button type="submit" class="btn-reject" style="padding:8px 14px;">Reject</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarCollapse");
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener("click", e => { e.stopPropagation(); sidebar.classList.toggle("active"); });
            document.addEventListener("click", e => {
                if (window.innerWidth <= 768 && sidebar.classList.contains("active"))
                    if (!sidebar.contains(e.target) && e.target !== toggleBtn) sidebar.classList.remove("active");
            });
        }
    });
</script>
</body>
</html>
