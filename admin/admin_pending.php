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
$qStr = "SELECT id, first_name, middle_name, last_name, full_name, email, contact_number, id_number, department, affiliation, requested_role, reason_for_access, proof_of_affiliation, status, created_at FROM users WHERE status='pending'";
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
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
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

            <div id="alertContainer"></div>

            <!-- Search -->
            <div class="dashboard-card" style="margin-bottom:1.5rem;padding:1.25rem;">
                <form method="GET" action="admin_pending.php" class="search-filter-bar" id="searchForm">
                    <div class="bar-left">
                        <input type="text" name="search" id="searchInput" class="form-control-inline"
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
                    <table class="custom-table" id="pendingTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>ID Number</th>
                                <th>Department / Affiliation</th>
                                <th>Requested Role</th>
                                <th>Reason for Access</th>
                                <th>Proof of Affiliation</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pendingTableBody">
                        <?php if (count($pending_users) > 0): ?>
                            <?php foreach ($pending_users as $u): ?>
                            <tr data-user-id="<?php echo $u['id']; ?>">
                                <td>
                                    <strong class="user-full-name" style="color:var(--dark-green);display:block;">
                                        <?php echo htmlspecialchars($u['full_name'] ?: trim($u['first_name'].' '.$u['last_name'])); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="user-email"><?php echo htmlspecialchars($u['email']); ?></span>
                                </td>
                                <td>
                                    <span class="user-id-number"><?php echo htmlspecialchars($u['id_number'] ?? '—'); ?></span>
                                </td>
                                <td>
                                    <span class="user-department"><?php echo htmlspecialchars($u['department'] ?: '—'); ?></span>
                                </td>
                                <td><span class="user-requested-role" style="font-size:.75rem;font-weight:700;color:#6B8F71;" data-role="<?php echo htmlspecialchars($u['requested_role'] ?? ''); ?>"><?php echo role_label($u['requested_role'] ?? ''); ?></span></td>
                                <td>
                                    <div class="user-reason" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($u['reason_for_access'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($u['reason_for_access'] ?? '—'); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($u['proof_of_affiliation'])): ?>
                                        <a href="view_proof.php?user_id=<?php echo $u['id']; ?>" target="_blank" class="btn-view" style="padding: 4px 8px; font-size: 0.7rem;">View Proof</a>
                                    <?php else: ?>
                                        <span style="font-size:.75rem;color:#888;font-style:italic;">No Proof Uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td><span class="badge-pending">Pending</span></td>
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;align-items:center;">
                                        <button type="button" class="btn-view" onclick='showUserDetails(<?php echo json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>View Details</button>
                                        <?php if (!empty($u['proof_of_affiliation'])): ?>
                                            <a href="view_proof.php?user_id=<?php echo $u['id']; ?>" target="_blank" class="btn-view">View Proof</a>
                                        <?php endif; ?>
                                        <form class="approve-form" onsubmit="handleApprove(event, <?php echo $u['id']; ?>)">
                                            <select name="approved_role" class="role-select-sm" required>
                                                <option value="">Edit Assigned Role...</option>
                                                <option value="criminology_student" <?php echo ($u['requested_role'] === 'criminology_student') ? 'selected' : ''; ?>>Criminology Student</option>
                                                <option value="faculty_researcher" <?php echo ($u['requested_role'] === 'faculty_researcher') ? 'selected' : ''; ?>>Faculty Researcher</option>
                                                <option value="alumni_police_partner" <?php echo ($u['requested_role'] === 'alumni_police_partner') ? 'selected' : ''; ?>>Alumni / Police Partner</option>
                                                <option value="super_admin">Super Administrator</option>
                                            </select>
                                            <button type="submit" class="btn-approve">Approve</button>
                                        </form>
                                        <button type="button" class="btn-reject" onclick="handleReject(<?php echo $u['id']; ?>)">Reject</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-data-row">
                                <td colspan="10" style="text-align:center;color:var(--gray);padding:3rem 2rem;">
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
<div class="detail-overlay" id="detailOverlay">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3>Registration Details</h3>
            <button class="modal-close-btn" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="detail-modal-body" id="detailModalBody">
            <!-- Dynamically populated in JavaScript -->
        </div>
    </div>
</div>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let isSubmitting = false;

    // Helper: Role Label mapping
    function roleLabel(r) {
        const map = {
            'criminology_student': 'Criminology Student',
            'faculty_researcher': 'Faculty Researcher',
            'alumni_police_partner': 'Alumni / Police Partner',
            'super_admin': 'Super Administrator'
        };
        return map[r] || r.replace(/_/g, ' ');
    }

    // Toggle Sidebar
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
        
        // Auto-refresh interval (10s)
        setInterval(autoRefreshPendingUsers, 10000);
    });

    // Check if auto-refresh should be paused
    function isAutoRefreshPaused() {
        const isModalOpen = document.getElementById('detailOverlay').classList.contains('open');
        const isUserTyping = document.activeElement && (
            document.activeElement.tagName === 'INPUT' || 
            document.activeElement.tagName === 'TEXTAREA' || 
            document.activeElement.tagName === 'SELECT'
        );
        return isModalOpen || isUserTyping || isSubmitting;
    }

    // Display Toast/Alert Notification
    function showNotification(type, message) {
        const container = document.getElementById('alertContainer');
        container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    }

    // Show Details Modal
    function showUserDetails(user) {
        const modal = document.getElementById('detailOverlay');
        const body = document.getElementById('detailModalBody');
        
        let actionsHtml = '';
        if (user.status === 'pending') {
            actionsHtml = `
            <div style="display:flex;gap:.75rem;margin-top:1.5rem;border-top:1px solid #eee;padding-top:1.25rem;">
                <form class="approve-form" onsubmit="handleApprove(event, ${user.id}, true)" style="flex:1;display:flex;gap:6px;">
                    <select name="approved_role" class="role-select-sm" required style="flex:1;padding:8px;">
                        <option value="criminology_student" ${user.requested_role==='criminology_student'?'selected':''}>Criminology Student</option>
                        <option value="faculty_researcher" ${user.requested_role==='faculty_researcher'?'selected':''}>Faculty Researcher</option>
                        <option value="alumni_police_partner" ${user.requested_role==='alumni_police_partner'?'selected':''}>Alumni / Police Partner</option>
                        <option value="super_admin">Super Administrator</option>
                    </select>
                    <button type="submit" class="btn-approve" style="padding:8px 16px;">Approve Access</button>
                </form>
                <button type="button" class="btn-reject" onclick="handleReject(${user.id}, true)" style="padding:8px 14px;">Reject</button>
            </div>`;
        }

        const hasProof = user.proof_of_affiliation && user.proof_of_affiliation.trim() !== '';
        const proofHtml = hasProof 
            ? `<a href="view_proof.php?user_id=${user.id}" target="_blank" class="btn-view" style="padding: 4px 8px; font-size: 0.75rem;">View Proof</a>`
            : `<span style="font-size:.85rem;color:#888;font-style:italic;">No Proof Uploaded</span>`;

        body.innerHTML = `
            <p class="section-divider">Personal Information</p>
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value">${user.full_name || (user.first_name + ' ' + (user.middle_name || '') + ' ' + user.last_name)}</span></div>
            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">${user.email}</span></div>
            <div class="detail-row"><span class="detail-label">Contact Number</span><span class="detail-value">${user.contact_number || '—'}</span></div>
            <div class="detail-row"><span class="detail-label">ID Number</span><span class="detail-value">${user.id_number || '—'}</span></div>
            <div class="detail-row"><span class="detail-label">Department / Affiliation</span><span class="detail-value">${user.department || '—'}</span></div>
            
            <p class="section-divider">Access Request</p>
            <div class="detail-row"><span class="detail-label">Requested Role</span><span class="detail-value">${roleLabel(user.requested_role)}</span></div>
            <div class="detail-row"><span class="detail-label">Reason for Access</span><span class="detail-value">${(user.reason_for_access || '—').replace(/\n/g, '<br>')}</span></div>
            <div class="detail-row"><span class="detail-label">Proof of Affiliation</span><span class="detail-value">${proofHtml}</span></div>
            <div class="detail-row"><span class="detail-label">Registration Date</span><span class="detail-value">${user.created_at}</span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><span class="badge-pending">${user.status}</span></span></div>
            ${actionsHtml}
        `;
        
        modal.classList.add('open');
    }

    function closeDetailModal() {
        document.getElementById('detailOverlay').classList.remove('open');
    }

    // Approve user via AJAX
    function handleApprove(event, userId, fromModal = false) {
        event.preventDefault();
        if (isSubmitting) return;
        
        const form = event.target;
        const select = form.querySelector('select[name="approved_role"]');
        const role = select.value;
        if (!role) {
            showNotification('danger', 'Please select a role.');
            return;
        }

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        
        btn.textContent = 'Saving...';
        btn.disabled = true;
        isSubmitting = true;

        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('approved_role', role);
        formData.append('csrf_token', csrfToken);

        fetch('ajax_approve_user.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': csrfToken
            }
        })
        .then(res => res.json())
        .then(data => {
            isSubmitting = false;
            btn.textContent = originalText;
            btn.disabled = false;
            if (data.success) {
                showNotification('success', data.message);
                removeUserRow(userId);
                if (fromModal) closeDetailModal();
                updateDashboardCounts();
            } else {
                showNotification('danger', data.message);
            }
        })
        .catch(err => {
            isSubmitting = false;
            btn.textContent = originalText;
            btn.disabled = false;
            showNotification('danger', 'An error occurred. Please try again.');
        });
    }

    // Reject user via AJAX
    function handleReject(userId, fromModal = false) {
        if (isSubmitting) return;
        if (!confirm('Are you sure you want to REJECT this registration request?')) return;

        isSubmitting = true;
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('csrf_token', csrfToken);

        fetch('ajax_reject_user.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': csrfToken
            }
        })
        .then(res => res.json())
        .then(data => {
            isSubmitting = false;
            if (data.success) {
                showNotification('success', data.message);
                removeUserRow(userId);
                if (fromModal) closeDetailModal();
                updateDashboardCounts();
            } else {
                showNotification('danger', data.message);
            }
        })
        .catch(err => {
            isSubmitting = false;
            showNotification('danger', 'An error occurred. Please try again.');
        });
    }

    function removeUserRow(userId) {
        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
        if (row) {
            row.remove();
        }
        
        // Update counts
        const tbody = document.getElementById('pendingTableBody');
        const rows = tbody.querySelectorAll('tr[data-user-id]');
        const countBadge = document.querySelector('.count-badge');
        if (countBadge) {
            countBadge.textContent = `${rows.length} pending`;
        }
        
        if (rows.length === 0) {
            tbody.innerHTML = `
                <tr class="no-data-row">
                    <td colspan="10" style="text-align:center;color:var(--gray);padding:3rem 2rem;">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#D2E2D5;display:block;margin:0 auto 1rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        No pending registrations at this time.
                    </td>
                </tr>`;
        }
    }

    function updateDashboardCounts() {
        fetch('ajax_get_dashboard_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update user counts on dashboard if present
                    const activeCountEl = document.getElementById('activeUsersCount');
                    const pendingCountEl = document.getElementById('pendingApprovalsCount');
                    if (activeCountEl) activeCountEl.textContent = data.data.active_users;
                    if (pendingCountEl) pendingCountEl.textContent = data.data.pending_count;
                }
            });
    }

    // Auto refresh pending users
    function autoRefreshPendingUsers() {
        if (isAutoRefreshPaused()) return;
        
        const searchInput = document.getElementById('searchInput');
        const searchVal = searchInput ? searchInput.value : '';

        fetch(`ajax_get_pending_users.php?search=${encodeURIComponent(searchVal)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('pendingTableBody');
                    const users = data.data.pending_users;
                    
                    const existingRows = Array.from(tbody.querySelectorAll('tr[data-user-id]'));
                    const existingIds = existingRows.map(row => parseInt(row.getAttribute('data-user-id')));
                    const newIds = users.map(u => parseInt(u.id));

                    // Remove users no longer pending
                    existingRows.forEach(row => {
                        const id = parseInt(row.getAttribute('data-user-id'));
                        if (!newIds.includes(id)) {
                            row.remove();
                        }
                    });

                    // Update or Add users
                    users.forEach(u => {
                        let row = tbody.querySelector(`tr[data-user-id="${u.id}"]`);
                        if (row) {
                            // Update details if any change
                            const nameEl = row.querySelector('.user-full-name');
                            nameEl.textContent = u.full_name || (u.first_name + ' ' + u.last_name);
                            const roleEl = row.querySelector('.user-requested-role');
                            if (roleEl.getAttribute('data-role') !== u.requested_role) {
                                roleEl.setAttribute('data-role', u.requested_role);
                                roleEl.textContent = roleLabel(u.requested_role);
                            }
                        } else {
                            // Insert row
                            const tr = document.createElement('tr');
                            tr.setAttribute('data-user-id', u.id);
                            
                            const escU = JSON.stringify(u).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                            const hasProof = u.proof_of_affiliation && u.proof_of_affiliation.trim() !== '';
                            const proofHtml = hasProof 
                                ? `<a href="view_proof.php?user_id=${u.id}" target="_blank" class="btn-view" style="padding: 4px 8px; font-size: 0.7rem;">View Proof</a>`
                                : `<span style="font-size:.75rem;color:#888;font-style:italic;">No Proof Uploaded</span>`;

                            const viewProofAction = hasProof
                                ? `<a href="view_proof.php?user_id=${u.id}" target="_blank" class="btn-view">View Proof</a>`
                                : '';
                            
                            tr.innerHTML = `
                                <td>
                                    <strong class="user-full-name" style="color:var(--dark-green);display:block;">
                                        \${u.full_name || (u.first_name + ' ' + u.last_name)}
                                    </strong>
                                </td>
                                <td>
                                    <span class="user-email">\${u.email}</span>
                                </td>
                                <td>
                                    <span class="user-id-number">\${u.id_number || '—'}</span>
                                </td>
                                <td>
                                    <span class="user-department">\${u.department || '—'}</span>
                                </td>
                                <td><span class="user-requested-role" style="font-size:.75rem;font-weight:700;color:#6B8F71;" data-role="\${u.requested_role}">\${roleLabel(u.requested_role)}</span></td>
                                <td>
                                    <div class="user-reason" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="\${u.reason_for_access || ''}">
                                        \${u.reason_for_access || '—'}
                                    </div>
                                </td>
                                <td>\${proofHtml}</td>
                                <td>\${new Date(u.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                <td><span class="badge-pending">Pending</span></td>
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;align-items:center;">
                                        <button type="button" class="btn-view" onclick='showUserDetails(\${escU})'>View Details</button>
                                        \${viewProofAction}
                                        <form class="approve-form" onsubmit="handleApprove(event, \${u.id})">
                                            <select name="approved_role" class="role-select-sm" required>
                                                <option value="">Edit Assigned Role...</option>
                                                <option value="criminology_student" \${u.requested_role === 'criminology_student' ? 'selected' : ''}>Criminology Student</option>
                                                <option value="faculty_researcher" \${u.requested_role === 'faculty_researcher' ? 'selected' : ''}>Faculty Researcher</option>
                                                <option value="alumni_police_partner" \${u.requested_role === 'alumni_police_partner' ? 'selected' : ''}>Alumni / Police Partner</option>
                                                <option value="super_admin">Super Administrator</option>
                                            </select>
                                            <button type="submit" class="btn-approve">Approve</button>
                                        </form>
                                        <button type="button" class="btn-reject" onclick="handleReject(\${u.id})">Reject</button>
                                    </div>
                                </td>
                            `;
                            // Prepend row to table body
                            const noData = tbody.querySelector('.no-data-row');
                            if (noData) noData.remove();
                            tbody.insertBefore(tr, tbody.firstChild);
                        }
                    });

                    // Update count badge
                    const countBadge = document.querySelector('.count-badge');
                    if (countBadge) {
                        countBadge.textContent = `\${users.length} pending`;
                    }
                    if (users.length === 0 && !tbody.querySelector('.no-data-row')) {
                        tbody.innerHTML = `
                            <tr class="no-data-row">
                                <td colspan="10" style="text-align:center;color:var(--gray);padding:3rem 2rem;">
                                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#D2E2D5;display:block;margin:0 auto 1rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    No pending registrations at this time.
                                </td>
                            </tr>`;
                    }
                }
            });
    }
</script>
</body>
</html>

