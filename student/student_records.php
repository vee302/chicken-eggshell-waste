<?php
// student/student_records.php — View My Records & Reports
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'student_records';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

// Filters
$filter_status  = $_GET['status']  ?? '';
$filter_powder  = $_GET['powder']  ?? '';
$filter_surface = $_GET['surface'] ?? '';

// Build query
$where = ['ft.student_id = ?'];
$params = [$student_id];
if ($filter_status)  { $where[] = 'ft.status = ?';       $params[] = $filter_status; }
if ($filter_powder)  { $where[] = 'ft.powder_type = ?';  $params[] = $filter_powder; }
if ($filter_surface) { $where[] = 'ft.surface_type = ?'; $params[] = $filter_surface; }

$records = [];
try {
    $sql = "
        SELECT ft.*, fr.remarks AS faculty_remarks 
        FROM fingerprint_tests ft
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY ft.submitted_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="My Records &amp; Reports — Green Forensics">
    <title>Records &amp; Reports — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
    <style>
        .filter-bar { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1.5rem; align-items:flex-end; }
        .filter-item { display:flex; flex-direction:column; gap:.3rem; }
        .filter-item label { font-size:.72rem; font-weight:700; color:var(--dark-green); text-transform:uppercase; letter-spacing:.4px; }
        .filter-item select { padding:.5rem .9rem; border:1px solid var(--light-gray); border-radius:8px; font-size:.85rem; color:var(--dark); background:var(--white); outline:none; transition:var(--transition); }
        .filter-item select:focus { border-color:var(--medium-green); box-shadow:0 0 0 3px rgba(45,106,79,.1); }
        .badge-pending_validation { background:rgba(244,162,97,.15); color:#c97d2a; border:1px solid rgba(244,162,97,.25); }
        .badge-needs_revision { background:rgba(230,57,70,.12); color:#e63946; border:1px solid rgba(230,57,70,.2); }
        .badge-approved { background:rgba(82,183,136,.15); color:#2d6a4f; border:1px solid rgba(82,183,136,.25); }
        .badge-rejected { background:rgba(224,122,95,.15); color:#c0392b; border:1px solid rgba(224,122,95,.2); }
        @media print {
            .student-sidebar, .student-header, .filter-bar, .btn, .no-print { display:none !important; }
            .student-main { margin-left:0 !important; }
            .student-content { padding:0; }
        }
    </style>
</head>
<body>
<div class="student-wrapper">
    <div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;"
         onclick="this.style.display='none';document.getElementById('sidebar').classList.remove('active')"></div>

    <?php require_once '_sidebar.php'; ?>

    <main class="student-main">
        <header class="student-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <div class="header-title"><h2>My Records &amp; Reports</h2></div>
            </div>
            <div class="header-right"><div class="header-role-chip">Criminology Student</div></div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>View Records / Reports</h1>
                    <p>All your fingerprint trial submissions — filter and review anytime.</p>
                </div>
                <div style="display:flex;gap:.5rem;" class="no-print">
                    <button onclick="window.print()" class="btn btn-secondary">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print / Export
                    </button>
                    <a href="submit_trial.php" class="btn btn-primary">+ New Submission</a>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filter-bar no-print">
                <div class="filter-item">
                    <label>Status</label>
                    <select name="status" id="filter-status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending_validation" <?= $filter_status==='pending_validation' ? 'selected' : '' ?>>Pending Validation</option>
                        <option value="approved"           <?= $filter_status==='approved'           ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected"           <?= $filter_status==='rejected'           ? 'selected' : '' ?>>Rejected</option>
                        <option value="needs_revision"     <?= $filter_status==='needs_revision'     ? 'selected' : '' ?>>Needs Revision</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Powder Type</label>
                    <select name="powder" id="filter-powder" onchange="this.form.submit()">
                        <option value="">All Powders</option>
                        <option value="eggshell"   <?= $filter_powder==='eggshell'   ? 'selected' : '' ?>>Eggshell</option>
                        <option value="commercial" <?= $filter_powder==='commercial' ? 'selected' : '' ?>>Commercial</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Surface</label>
                    <select name="surface" id="filter-surface" onchange="this.form.submit()">
                        <option value="">All Surfaces</option>
                        <?php foreach (['glass','plastic','metal','paper','wood','ceramic','fabric'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_surface===$s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($filter_status || $filter_powder || $filter_surface): ?>
                    <div class="filter-item" style="justify-content:flex-end;">
                        <label>&nbsp;</label>
                        <a href="student_records.php" class="btn btn-secondary btn-sm">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Records Table -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        All Submissions
                    </h3>
                    <span style="font-size:.82rem;color:var(--gray);"><?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Trial ID</th>
                                <th>Fingerprint Image</th>
                                <th>Powder Type</th>
                                <th>Surface Type</th>
                                <th>Accuracy Score</th>
                                <th>Score Bar</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                                <th>Faculty Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center;color:#6c757d;padding:2.5rem;">
                                    No records found.
                                    <?php if (!$filter_status && !$filter_powder && !$filter_surface): ?>
                                        <a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload your first image →</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $i => $r): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--dark-green);"><?= htmlspecialchars($r['trial_id'] ?: 'TR-'.str_pad($r['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td>
                                    <?php if ($r['image_path']): ?>
                                        <?php if (file_exists('../uploads/fingerprints/'.$r['image_path'])): ?>
                                            <a href="../uploads/fingerprints/<?= htmlspecialchars($r['image_path']) ?>" target="_blank">
                                                <img src="../uploads/fingerprints/<?= htmlspecialchars($r['image_path']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="FP">
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 0.72rem; color: var(--danger); font-weight:600;">Image not found</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: var(--gray); font-style:italic;">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($r['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($r['surface_type']) ?></td>
                                <td>
                                    <strong><?= $r['accuracy_score'] !== null ? number_format($r['accuracy_score'], 1) . '%' : 'Awaiting Validation' ?></strong>
                                </td>
                                <td style="min-width:120px;">
                                    <?php if ($r['accuracy_score'] !== null): ?>
                                        <div class="score-bar">
                                            <div class="score-bar-track">
                                                <div class="score-bar-fill" style="width:<?= min(100,$r['accuracy_score']) ?>%"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 0.75rem; color: var(--gray); font-style:italic;">Awaiting review</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $r['status'] ?>">
                                        <?= $r['status'] === 'pending_validation' ? 'Pending Validation' : ($r['status'] === 'needs_revision' ? 'Needs Revision' : ucfirst($r['status'])) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($r['submitted_at'])) ?></td>
                                <td style="font-size:.82rem; color:#5f5f5f; max-width:180px;"><?= $r['faculty_remarks'] ? htmlspecialchars($r['faculty_remarks']) : '<em>No remarks yet</em>' ?></td>
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
<?php require_once '_sidebar_js.php'; ?>
</body>
</html>
