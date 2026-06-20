<?php
// admin/unlock_requests.php - Account Unlock Requests Management
require_once "../config.php";
require_once "auth.php";
check_admin_auth();

$error = "";
$success = "";

// Handle Approve / Reject actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];
    $req_id = intval($_POST["request_id"] ?? 0);
    $admin_id = $_SESSION["user_id"] ?? null;

    if ($action === "approve") {
        try {
            $pdo->beginTransaction();
            // Get request details
            $req_stmt = $pdo->prepare("SELECT email, user_id FROM account_unlock_requests WHERE id = :id LIMIT 1");
            $req_stmt->execute([':id' => $req_id]);
            $req = $req_stmt->fetch(PDO::FETCH_ASSOC);

            if ($req) {
                $email = $req['email'];
                $user_id = $req['user_id'];

                // Update request status
                $upd_req = $pdo->prepare("UPDATE account_unlock_requests SET status = 'approved', reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id");
                $upd_req->execute([':reviewed_by' => $admin_id, ':id' => $req_id]);

                // Reset user lockout details without changing status or role
                if ($user_id) {
                    $upd_user = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_failed_login = NULL WHERE id = :user_id");
                    $upd_user->execute([':user_id' => $user_id]);
                } else {
                    $upd_user = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_failed_login = NULL WHERE email = :email");
                    $upd_user->execute([':email' => $email]);
                }

                log_activity("Unlock Request Approved", "Approved account unlock request for $email");
                $success = "Account unlocked successfully.";
            } else {
                $error = "Unlock request not found.";
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($action === "reject") {
        try {
            $pdo->beginTransaction();
            // Get request details
            $req_stmt = $pdo->prepare("SELECT email FROM account_unlock_requests WHERE id = :id LIMIT 1");
            $req_stmt->execute([':id' => $req_id]);
            $email = $req_stmt->fetchColumn() ?: "ID $req_id";

            // Update request status
            $upd_req = $pdo->prepare("UPDATE account_unlock_requests SET status = 'rejected', reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id");
            $upd_req->execute([':reviewed_by' => $admin_id, ':id' => $req_id]);

            log_activity("Unlock Request Rejected", "Rejected account unlock request for $email");
            $success = "Unlock request rejected.";
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch unlock requests
$search = trim($_GET['search'] ?? '');
$qStr = "SELECT r.*, u.full_name, u.status as user_status FROM account_unlock_requests r LEFT JOIN users u ON r.user_id = u.id";
$params = [];
if (!empty($search)) {
    $qStr .= " WHERE r.email LIKE :s OR u.full_name LIKE :s";
    $params[':s'] = "%$search%";
}
$qStr .= " ORDER BY r.status ASC, r.requested_at DESC";
$stmt = $pdo->prepare($qStr);
$stmt->execute($params);
$unlock_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unlock Requests - Green Forensics Admin</title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .alert { padding:.85rem 1.25rem; margin-bottom:1.5rem; border-radius:8px; font-size:.85rem; font-weight:500; }
        .alert-danger  { background:rgba(224,122,95,.15); color:var(--danger); border:1px solid rgba(224,122,95,.2); }
        .alert-success { background:rgba(82,183,136,.15); color:var(--medium-green); border:1px solid rgba(82,183,136,.2); }
        .badge-pending  { background:rgba(243,156,18,.15); color:#b7770d; border:1px solid rgba(243,156,18,.25); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-approved { background:rgba(82,183,136,.15); color:#1e7e34; border:1px solid rgba(82,183,136,.25); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-rejected { background:rgba(220,53,69,.12); color:#c0392b; border:1px solid rgba(220,53,69,.2); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-user-status { font-size:.65rem; padding:1px 6px; border-radius:4px; font-weight:600; text-transform:uppercase; margin-left: 5px; }
        .badge-user-active { background:#e2f0d9; color:#385723; }
        .badge-user-suspended { background:#fce4d6; color:#c65911; }
        .badge-user-pending { background:#fff2cc; color:#7f6000; }
        .badge-user-rejected { background:#f8d7da; color:#721c24; }
        .btn-approve { background:var(--medium-green); color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:.75rem; font-weight:700; cursor:pointer; transition:background .2s; }
        .btn-approve:hover { background:var(--dark-green); }
        .btn-reject  { background:transparent; color:var(--danger); border:1.5px solid var(--danger); padding:6px 10px; border-radius:6px; font-size:.75rem; font-weight:700; cursor:pointer; transition:all .2s; }
        .btn-reject:hover { background:var(--danger); color:#fff; }
        .count-badge { background:rgba(47,79,58,.1); color:#2F4F3A; border-radius:20px; font-size:.75rem; font-weight:700; padding:2px 10px; margin-left:8px; }
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
                    <h1>Account Unlock Requests <span class="count-badge"><?php echo count($unlock_requests); ?> total</span></h1>
                    <p>Review and process unlock requests submitted by users who have been locked out due to failed login attempts.</p>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Search -->
            <div class="dashboard-card" style="margin-bottom:1.5rem;padding:1.25rem;">
                <form method="GET" action="unlock_requests.php" class="search-filter-bar">
                    <div class="bar-left">
                        <input type="text" name="search" class="form-control-inline"
                            placeholder="Search requests by email or name..."
                            value="<?php echo htmlspecialchars($search); ?>" style="min-width:320px;">
                        <button type="submit" class="btn btn-secondary">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="unlock_requests.php" class="btn btn-secondary btn-sm" style="border:none;">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Unlock Requests Table -->
            <div class="dashboard-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Name / Email</th>
                                <th>User Account Status</th>
                                <th>Reason / Message</th>
                                <th>Requested At</th>
                                <th>Request Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($unlock_requests) > 0): ?>
                            <?php foreach ($unlock_requests as $r): ?>
                            <tr>
                                <td>
                                    <?php if ($r['full_name']): ?>
                                        <strong style="color:var(--dark-green);display:block;"><?php echo htmlspecialchars($r['full_name']); ?></strong>
                                    <?php else: ?>
                                        <span style="font-size:.85rem;color:#888;font-style:italic;display:block;">Unregistered Account</span>
                                    <?php endif; ?>
                                    <span style="font-size:.85rem;color:#5f5f5f;"><?php echo htmlspecialchars($r['email']); ?></span>
                                </td>
                                <td>
                                    <?php if ($r['user_status']): ?>
                                        <?php 
                                            $stClass = '';
                                            if ($r['user_status'] === 'active') $stClass = 'badge-user-active';
                                            elseif ($r['user_status'] === 'suspended') $stClass = 'badge-user-suspended';
                                            elseif ($r['user_status'] === 'pending') $stClass = 'badge-user-pending';
                                            elseif ($r['user_status'] === 'rejected') $stClass = 'badge-user-rejected';
                                        ?>
                                        <span class="badge-user-status <?php echo $stClass; ?>"><?php echo htmlspecialchars($r['user_status']); ?></span>
                                    <?php else: ?>
                                        <span style="font-size:.85rem;color:#888;font-style:italic;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width:240px; word-wrap:break-word; font-size:.85rem;">
                                        <?php echo nl2br(htmlspecialchars($r['reason'] ?? '—')); ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y h:i A', strtotime($r['requested_at'])); ?></td>
                                <td>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <span class="badge-pending">Pending</span>
                                    <?php elseif ($r['status'] === 'approved'): ?>
                                        <span class="badge-approved">Approved</span>
                                    <?php else: ?>
                                        <span class="badge-rejected">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                                            <form method="POST" action="unlock_requests.php" style="display:inline;" onsubmit="return confirm('Unlock this user\'s login attempts?');">
                                                <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-approve">Approve</button>
                                            </form>
                                            <form method="POST" action="unlock_requests.php" style="display:inline;" onsubmit="return confirm('Reject this unlock request?');">
                                                <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn-reject">Reject</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size:.8rem;color:#999;font-style:italic;">Reviewed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:var(--gray);padding:3rem 2rem;">
                                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#D2E2D5;display:block;margin:0 auto 1rem;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>
                                    No unlock requests found.
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
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarCollapse");
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener("click", e => { e.stopPropagation(); sidebar.classList.toggle("active"); });
            document.addEventListener("click", e => {
                if (window.innerWidth <= 768 && sidebar.classList.contains("active")) {
                    if (!sidebar.contains(e.target) && e.target !== toggleBtn) sidebar.classList.remove("active");
                }
            });
        }
    });
</script>
<?php include '../includes/support_chat_widget.php'; ?>
</body>
</html>
