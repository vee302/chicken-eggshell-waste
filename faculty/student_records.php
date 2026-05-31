<?php
// faculty/student_records.php
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';

$search  = trim($_GET['search']  ?? '');
$f_pwd   = $_GET['powder']  ?? '';
$f_surf  = $_GET['surface'] ?? '';
$f_stat  = $_GET['status']  ?? '';

$where = ['1=1'];
$params = [];
if ($search)  { $where[] = 'u.full_name LIKE ?'; $params[] = '%'.$search.'%'; }
if ($f_pwd)   { $where[] = 'ft.powder_type=?';  $params[] = $f_pwd; }
if ($f_surf)  { $where[] = 'ft.surface_type=?'; $params[] = $f_surf; }
if ($f_stat)  { $where[] = 'ft.status=?';       $params[] = $f_stat; }

$rows = [];
try {
    $stmt = $pdo->prepare("
        SELECT ft.*, u.full_name AS student_name,
               fr.remarks AS faculty_remarks
        FROM fingerprint_tests ft
        JOIN users u ON u.id = ft.student_id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id=(
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id=ft.id
        )
        WHERE ".implode(' AND ',$where)."
        ORDER BY ft.submitted_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .badge-pending{background:rgba(244,162,97,.15);color:#c97d2a;}
        .badge-approved{background:rgba(82,183,136,.15);color:#2d6a4f;}
        .badge-rejected{background:rgba(224,122,95,.15);color:#c0392b;}
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="brand-text"><span>GREEN</span><span class="brand-accent">FORENSICS</span></div></div>
        <div class="sidebar-user"><div class="user-info"><div class="user-avatar">FR</div><div class="user-details"><h4><?= htmlspecialchars($faculty_name) ?></h4><span>Faculty Researcher</span></div></div></div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="faculty_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="comparison_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Comparison Dashboard</span></a></li>
            <li class="menu-item"><a href="validate_accuracy.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Validate Accuracy Scores</span></a></li>
            <li class="menu-item"><a href="surface_performance.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg><span>Surface Performance</span></a></li>
            <li class="menu-item"><a href="safety_climate_log.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Safety &amp; Climate Log</span></a></li>
            <li class="menu-item active"><a href="student_records.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>View Student Records</span></a></li>
            <li class="menu-item"><a href="generate_reports.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Generate Reports</span></a></li>
        </ul>
        <div class="sidebar-footer"><a href="../logout.php" class="menu-link" style="color:#e07a5f;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a></div>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="header-title"><h2>Green Forensics — Student Records</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>View Student Records</h1>
                    <p>All fingerprint trial submissions from registered criminology students.</p>
                </div>
                <a href="generate_reports.php" class="btn btn-primary">Generate Report</a>
            </div>

            <!-- Filter Bar -->
            <form method="GET" class="search-filter-bar">
                <div class="bar-left">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by student name..." class="form-control-inline" style="width:240px;">
                    <select name="powder" class="form-control-inline">
                        <option value="">All Powder Types</option>
                        <option value="eggshell"   <?= $f_pwd==='eggshell'  ?'selected':'' ?>>Eggshell</option>
                        <option value="commercial" <?= $f_pwd==='commercial'?'selected':'' ?>>Commercial</option>
                    </select>
                    <select name="surface" class="form-control-inline">
                        <option value="">All Surfaces</option>
                        <?php foreach(['glass','paper','wood','plastic','metal'] as $s): ?>
                        <option value="<?=$s?>" <?= $f_surf===$s?'selected':'' ?>><?=ucfirst($s)?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="form-control-inline">
                        <option value="">All Status</option>
                        <option value="pending"  <?= $f_stat==='pending' ?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $f_stat==='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $f_stat==='rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                </div>
                <div class="bar-right">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="student_records.php" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="dashboard-card">
                <div class="card-title-wrap"><h3>Submissions Found: <?= count($rows) ?></h3></div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Trial ID</th>
                                <th>Student Name</th>
                                <th>Image</th>
                                <th>Powder</th>
                                <th>Surface</th>
                                <th>Accuracy</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                                <th>Faculty Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="9" style="text-align:center;padding:2rem;color:#6c757d;">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                            <tr>
                                <td style="font-weight:600;color:#2d6a4f;">#<?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['student_name']) ?></td>
                                <td>
                                    <?php if ($r['fingerprint_image'] && file_exists('../uploads/fingerprints/'.$r['fingerprint_image'])): ?>
                                        <a href="../uploads/fingerprints/<?= htmlspecialchars($r['fingerprint_image']) ?>" target="_blank">
                                            <img src="../uploads/fingerprints/<?= htmlspecialchars($r['fingerprint_image']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="FP">
                                        </a>
                                    <?php else: ?>
                                        <div style="width:48px;height:48px;border-radius:8px;background:#f4f6f0;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#adb5bd" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-transform:capitalize;"><?= $r['powder_type'] ?></td>
                                <td style="text-transform:capitalize;"><?= $r['surface_type'] ?></td>
                                <td><strong><?= number_format($r['accuracy_score'],1) ?>%</strong></td>
                                <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                                <td><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
                                <td style="font-size:.82rem;color:#6c757d;max-width:180px;"><?= $r['faculty_remarks'] ? htmlspecialchars($r['faculty_remarks']) : '<em>No remarks yet</em>' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarCollapse');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
});
</script>
</body>
</html>
