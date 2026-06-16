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
        SELECT ft.*, fr.remarks AS faculty_remarks, faculty.full_name AS faculty_validator
        FROM fingerprint_tests ft
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY ft.submitted_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($records as &$row) {
        $row['image_exists'] = false;
        if (!empty($row['image_path'])) {
            $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $row['image_path'];
            if (file_exists($filePath)) {
                $row['image_exists'] = true;
            }
        }
    }
    unset($row);
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
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
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
        
        .custom-table tbody tr { cursor: pointer; transition: background 0.2s; }
        .custom-table tbody tr:hover { background: #f9fbf7; }

        /* Detail Modal styling matching super admin */
        .detail-overlay { display:none; position:fixed; inset:0; background:rgba(27, 67, 50, 0.45); backdrop-filter: blur(4px); z-index:9999; align-items:center; justify-content:center; }
        .detail-overlay.open { display:flex; }
        .detail-modal { background:#fff; border-radius:16px; max-width:600px; width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); border: 1px solid rgba(27,67,50,0.1); }
        .detail-modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:var(--dark-green); color:#fff; }
        .detail-modal-header h3 { color:#fff; font-size:1.05rem; font-weight:700; margin:0; }
        .detail-modal-body { padding:1.5rem; }
        .detail-row { display:flex; gap:.5rem; margin-bottom:.75rem; font-size:.85rem; }
        .detail-label { min-width:160px; font-weight:600; color:var(--dark-green); }
        .detail-value { color:#5f5f5f; flex:1; }
        .modal-close-btn { background:none; border:none; font-size:1.4rem; cursor:pointer; color:#fff; opacity:0.8; line-height:1; }
        .modal-close-btn:hover { opacity:1; }
        .section-divider { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#6B8F71; border-bottom:1px solid #D2E2D5; padding-bottom:.35rem; margin:1.25rem 0 .6rem; }
        .section-divider:first-child { margin-top: 0; }
        
        .score-box { background: var(--cream); border-radius:8px; padding:10px 15px; margin-bottom:1rem; border:1px solid rgba(45,106,79,0.08); }
        .score-title { font-size:0.75rem; font-weight:700; color:var(--medium-green); margin-bottom:6px; text-transform:uppercase; }
        .score-values { display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; text-align:center; }
        .score-val { font-size:1.15rem; font-weight:800; color:var(--dark-green); }
        .score-lbl { font-size:0.65rem; color:var(--gray); font-weight:600; text-transform:uppercase; }

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
            <form id="filterForm" class="filter-bar no-print">
                <div class="filter-item">
                    <label>Status</label>
                    <select name="status" id="filter-status">
                        <option value="">All Statuses</option>
                        <option value="pending_validation" <?= $filter_status==='pending_validation' ? 'selected' : '' ?>>Pending Validation</option>
                        <option value="approved"           <?= $filter_status==='approved'           ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected"           <?= $filter_status==='rejected'           ? 'selected' : '' ?>>Rejected</option>
                        <option value="needs_revision"     <?= $filter_status==='needs_revision'     ? 'selected' : '' ?>>Needs Revision</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Powder Type</label>
                    <select name="powder" id="filter-powder">
                        <option value="">All Powders</option>
                        <option value="eggshell"   <?= $filter_powder==='eggshell'   ? 'selected' : '' ?>>Eggshell</option>
                        <option value="commercial" <?= $filter_powder==='commercial' ? 'selected' : '' ?>>Commercial</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Surface</label>
                    <select name="surface" id="filter-surface">
                        <option value="">All Surfaces</option>
                        <?php foreach (['glass','plastic','metal','paper','wood','ceramic','fabric'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_surface===$s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item" style="justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnClearFilters" style="display: <?= ($filter_status || $filter_powder || $filter_surface) ? 'inline-block' : 'none' ?>;">Clear Filters</button>
                </div>
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
                    <span id="recordCount" style="font-size:.82rem;color:var(--gray);"><?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Trial ID</th>
                                <th>Fingerprint Image</th>
                                <th>Image Label</th>
                                <th>Powder Type</th>
                                <th>Surface Type</th>
                                <th>Accuracy Score</th>
                                <th>Score Bar</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                                <th>Faculty Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="recordsTableBody">
                        <?php if (empty($records)): ?>
                            <tr class="no-data-row">
                                <td colspan="10" style="text-align:center;color:#6c757d;padding:2.5rem;">
                                    No records found.
                                    <?php if (!$filter_status && !$filter_powder && !$filter_surface): ?>
                                        <a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload your first image →</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $i => $r): ?>
                            <tr data-trial-db-id="<?= $r['id'] ?>" onclick='openDetailModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>
                                <td style="font-weight: 700; color: var(--dark-green);"><?= htmlspecialchars($r['trial_id'] ?: 'TR-'.str_pad($r['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td>
                                    <?php if ($r['image_path']): ?>
                                        <?php if ($r['image_exists']): ?>
                                            <a href="../view_fingerprint.php?test_id=<?= $r['id'] ?>" target="_blank" onclick="event.stopPropagation();">
                                                <img src="../view_fingerprint.php?test_id=<?= $r['id'] ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="FP">
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 0.72rem; color: var(--danger); font-weight:600;">Image not found</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: var(--gray); font-style:italic;">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['image_label'] ?: 'Untitled') ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($r['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($r['surface_type']) ?></td>
                                <td>
                                    <strong>
                                        <?php if ($r['status'] === 'approved' && $r['accuracy_score'] !== null): ?>
                                            <?= number_format($r['accuracy_score'], 1) ?>%
                                        <?php elseif ($r['status'] === 'pending_validation'): ?>
                                            Awaiting Validation
                                        <?php elseif ($r['status'] === 'needs_revision'): ?>
                                            Needs Revision
                                        <?php elseif ($r['status'] === 'rejected'): ?>
                                            Rejected
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td style="min-width:120px;">
                                    <?php if ($r['status'] === 'approved' && $r['accuracy_score'] !== null): ?>
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

<!-- VIEW DETAILS MODAL -->
<div class="detail-overlay" id="detailOverlay">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3 id="modalDetailTitle">Trial Submission Details</h3>
            <button class="modal-close-btn" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="detail-modal-body">
            <p class="section-divider">Forensic Submission Details</p>
            <div class="detail-row"><span class="detail-label">Trial ID</span><span class="detail-value" id="det-trial-id"></span></div>
            <div class="detail-row"><span class="detail-label">Powder Type Used</span><span class="detail-value" id="det-powder" style="text-transform: capitalize; font-weight: 600;"></span></div>
            <div class="detail-row"><span class="detail-label">Surface Material Type</span><span class="detail-value" id="det-surface" style="text-transform: capitalize; font-weight: 600;"></span></div>
            <div class="detail-row"><span class="detail-label">Image Label</span><span class="detail-value" id="det-label"></span></div>
            <div class="detail-row"><span class="detail-label">Notes from Submission</span><span class="detail-value" id="det-notes"></span></div>
            <div class="detail-row"><span class="detail-label">Date Submitted</span><span class="detail-value" id="det-submitted-at"></span></div>

            <p class="section-divider">Fingerprint Image Asset</p>
            <div style="text-align:center; margin-bottom:1rem; border:1px solid #e9ecef; padding:10px; border-radius:8px; background:#fafafa;" id="det-img-wrapper">
                <img src="" style="max-height:220px; max-width:100%; object-fit:contain; border-radius:6px; border:1px solid #ddd;" alt="Fingerprint" id="det-img">
            </div>

            <p class="section-divider">Evaluation & Scores</p>
            <div class="score-box" id="det-score-box">
                <div class="score-title">Forensic Performance Metrics</div>
                <div class="score-values">
                    <div>
                        <div class="score-val" id="det-clarity">—</div>
                        <div class="score-lbl">Clarity</div>
                    </div>
                    <div>
                        <div class="score-val" id="det-visibility">—</div>
                        <div class="score-lbl">Visibility</div>
                    </div>
                    <div>
                        <div class="score-val" id="det-adhesion">—</div>
                        <div class="score-lbl">Adhesion</div>
                    </div>
                    <div>
                        <div class="score-val" id="det-contrast">—</div>
                        <div class="score-lbl">Contrast</div>
                    </div>
                </div>
            </div>
            
            <div class="detail-row" id="det-ai-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green); margin-bottom: 0.5rem;">
                <span class="detail-label" style="font-weight: 700;">AI Preliminary Score</span>
                <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;" id="det-ai-score">—</span>
            </div>
            
            <div class="detail-row" id="det-faculty-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green);">
                <span class="detail-label" style="font-weight: 700;">Faculty Final Score</span>
                <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;" id="det-faculty-score">—</span>
            </div>

            <p class="section-divider" id="det-validation-divider">Validation Details</p>
            <div class="detail-row" id="det-status-row"><span class="detail-label">Validation Status</span><span class="detail-value" id="det-status"></span></div>
            <div class="detail-row" id="det-reviewer-row"><span class="detail-label">Faculty Reviewer</span><span class="detail-value" id="det-reviewer" style="font-weight: 600;"></span></div>
            <div class="detail-row" id="det-validated-date-row"><span class="detail-label">Review Date</span><span class="detail-value" id="det-validated-at"></span></div>
            <div class="detail-row" id="det-remarks-row"><span class="detail-label">Remarks from Reviewer</span><span class="detail-value" id="det-remarks" style="font-style: italic;"></span></div>
        </div>
    </div>
</div>

<?php require_once '_sidebar_js.php'; ?>
<script>
let isFetching = false;

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function getBadgeClass(status) {
    if (status === 'pending_validation') return 'badge-pending_validation';
    if (status === 'needs_revision') return 'badge-needs_revision';
    if (status === 'approved') return 'badge-approved';
    if (status === 'rejected') return 'badge-rejected';
    return 'badge-' + status;
}

function getStatusLabel(status) {
    if (status === 'pending_validation') return 'Pending Validation';
    if (status === 'needs_revision') return 'Needs Revision';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function fetchFilteredRecords() {
    if (isFetching) return;
    
    const status = document.getElementById('filter-status').value;
    const powder = document.getElementById('filter-powder').value;
    const surface = document.getElementById('filter-surface').value;
    
    // Toggle Clear Filters button visibility
    const clearBtn = document.getElementById('btnClearFilters');
    if (status || powder || surface) {
        clearBtn.style.display = 'inline-block';
    } else {
        clearBtn.style.display = 'none';
    }

    isFetching = true;
    
    // Update Address Bar Query Params
    const url = new URL(window.location);
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    if (powder) url.searchParams.set('powder', powder); else url.searchParams.delete('powder');
    if (surface) url.searchParams.set('surface', surface); else url.searchParams.delete('surface');
    window.history.pushState({}, '', url);

    fetch(`ajax_get_student_records.php?status=${encodeURIComponent(status)}&powder=${encodeURIComponent(powder)}&surface=${encodeURIComponent(surface)}`)
        .then(res => res.json())
        .then(data => {
            isFetching = false;
            if (data.success) {
                renderRecordsTable(data.data.records);
            }
        })
        .catch(err => {
            isFetching = false;
        });
}

function renderRecordsTable(records) {
    const tbody = document.getElementById('recordsTableBody');
    const countSpan = document.getElementById('recordCount');
    
    countSpan.textContent = `${records.length} record${records.length !== 1 ? 's' : ''}`;
    
    if (records.length === 0) {
        const status = document.getElementById('filter-status').value;
        const powder = document.getElementById('filter-powder').value;
        const surface = document.getElementById('filter-surface').value;
        
        let linkHtml = '';
        if (!status && !powder && !surface) {
            linkHtml = '<br><a href="upload_fingerprint.php" style="color:var(--medium-green);font-weight:600;">Upload your first image →</a>';
        }
        
        tbody.innerHTML = `
            <tr class="no-data-row">
                <td colspan="10" style="text-align:center;color:#6c757d;padding:2.5rem;">
                    No records found.${linkHtml}
                </td>
            </tr>`;
        return;
    }

    const existingRows = Array.from(tbody.querySelectorAll('tr[data-trial-db-id]'));
    const existingIds = existingRows.map(row => parseInt(row.getAttribute('data-trial-db-id')));
    const newIds = records.map(r => parseInt(r.id));

    // Remove rows no longer matching
    existingRows.forEach(row => {
        const id = parseInt(row.getAttribute('data-trial-db-id'));
        if (!newIds.includes(id)) {
            row.remove();
        }
    });

    // Add or update rows
    records.forEach(r => {
        let row = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
        
        let imageHtml = '<span style="font-size: 0.72rem; color: var(--gray); font-style:italic;">No image</span>';
        if (r.image_path) {
            if (r.image_exists) {
                imageHtml = `
                    <a href="../view_fingerprint.php?test_id=${r.id}" target="_blank" onclick="event.stopPropagation();">
                        <img src="../view_fingerprint.php?test_id=${r.id}" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;" alt="FP">
                    </a>`;
            } else {
                imageHtml = '<span style="font-size: 0.72rem; color: var(--danger); font-weight:600;">Image not found</span>';
            }
        }

        const isApproved = r.status === 'approved';
        const scoreText = isApproved ? (r.accuracy_score !== null ? parseFloat(r.accuracy_score).toFixed(1) + '%' : '—') : (r.status === 'pending_validation' ? 'Awaiting Validation' : (r.status === 'needs_revision' ? 'Needs Revision' : (r.status === 'rejected' ? 'Rejected' : 'N/A')));
        const scoreBarHtml = (isApproved && r.accuracy_score !== null) ? `
            <div class="score-bar">
                <div class="score-bar-track">
                    <div class="score-bar-fill" style="width:${Math.min(100, r.accuracy_score)}%"></div>
                </div>
            </div>` : '<div style="font-size: 0.75rem; color: var(--gray); font-style:italic;">Awaiting review</div>';

        const remarksHtml = r.faculty_remarks ? escapeHtml(r.faculty_remarks) : '<em>No remarks yet</em>';

        if (row) {
            // Update row fields
            row.children[1].innerHTML = imageHtml;
            row.children[2].textContent = r.image_label || 'Untitled';
            row.children[3].textContent = r.powder_type || '';
            row.children[4].textContent = r.surface_type || '';
            row.children[5].innerHTML = `<strong>${scoreText}</strong>`;
            row.children[6].innerHTML = scoreBarHtml;
            row.children[7].innerHTML = `<span class="badge ${getBadgeClass(r.status)}">${getStatusLabel(r.status)}</span>`;
            row.children[9].innerHTML = remarksHtml;
        } else {
            // Prepend new row
            const tr = document.createElement('tr');
            tr.setAttribute('data-trial-db-id', r.id);
            
            tr.innerHTML = `
                <td style="font-weight: 700; color: var(--dark-green);">${r.trial_id || 'TR-' + String(r.id).padStart(4, '0')}</td>
                <td>${imageHtml}</td>
                <td>${escapeHtml(r.image_label || 'Untitled')}</td>
                <td style="text-transform:capitalize;">${r.powder_type || ''}</td>
                <td style="text-transform:capitalize;">${r.surface_type || ''}</td>
                <td><strong>${scoreText}</strong></td>
                <td style="min-width:120px;">${scoreBarHtml}</td>
                <td><span class="badge ${getBadgeClass(r.status)}">${getStatusLabel(r.status)}</span></td>
                <td>${new Date(r.submitted_at.replace(/-/g, "/")).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} ${new Date(r.submitted_at.replace(/-/g, "/")).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</td>
                <td style="font-size:.82rem; color:#5f5f5f; max-width:180px;">${remarksHtml}</td>
            `;
            const noData = tbody.querySelector('.no-data-row');
            if (noData) noData.remove();
            tbody.insertBefore(tr, tbody.firstChild);
        }
        
        // Update/bind row click listener
        const trNode = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
        if (trNode) {
            trNode.onclick = () => openDetailModal(r);
        }
    });
}

function openDetailModal(row) {
    document.getElementById('det-trial-id').textContent = row.trial_id || 'TR-' + String(row.id).padStart(4, '0');
    document.getElementById('det-powder').textContent = row.powder_type || '';
    document.getElementById('det-surface').textContent = row.surface_type || '';
    document.getElementById('det-label').textContent = row.image_label || 'Untitled';
    document.getElementById('det-notes').innerHTML = row.notes ? escapeHtml(row.notes).replace(/\n/g, '<br>') : 'No notes provided.';
    document.getElementById('det-submitted-at').textContent = new Date(row.submitted_at.replace(/-/g, "/")).toLocaleString();

    const imgWrapper = document.getElementById('det-img-wrapper');
    const img = document.getElementById('det-img');
    if (row.image_path && row.image_exists) {
        img.src = '../view_fingerprint.php?test_id=' + row.id;
        imgWrapper.style.display = 'block';
    } else {
        imgWrapper.style.display = 'none';
    }

    // Performance Metrics
    document.getElementById('det-clarity').textContent = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score).toFixed(1) + '%' : '—';
    document.getElementById('det-visibility').textContent = row.visibility_score !== null ? parseFloat(row.visibility_score).toFixed(1) + '%' : '—';
    document.getElementById('det-adhesion').textContent = row.adhesion_score !== null ? parseFloat(row.adhesion_score).toFixed(1) + '%' : '—';
    document.getElementById('det-contrast').textContent = row.contrast_score !== null ? parseFloat(row.contrast_score).toFixed(1) + '%' : '—';

    // AI score
    document.getElementById('det-ai-score').textContent = row.ai_accuracy_score !== null ? parseFloat(row.ai_accuracy_score).toFixed(1) + '%' : 'Awaiting AI Evaluation';
    
    // Faculty Final Score
    if (row.status === 'approved' && row.faculty_final_score !== null) {
        document.getElementById('det-faculty-score').textContent = parseFloat(row.faculty_final_score).toFixed(1) + '%';
        document.getElementById('det-faculty-row').style.display = 'flex';
    } else if (row.status === 'pending_validation') {
        document.getElementById('det-faculty-score').textContent = 'Awaiting Validation';
        document.getElementById('det-faculty-row').style.display = 'flex';
    } else {
        document.getElementById('det-faculty-row').style.display = 'none';
    }

    // Validation Details
    document.getElementById('det-status').innerHTML = `<span class="badge ${getBadgeClass(row.status)}">${getStatusLabel(row.status)}</span>`;
    
    const reviewerRow = document.getElementById('det-reviewer-row');
    const validatedDateRow = document.getElementById('det-validated-date-row');
    const remarksRow = document.getElementById('det-remarks-row');

    if (row.status === 'pending_validation') {
        reviewerRow.style.display = 'none';
        validatedDateRow.style.display = 'none';
        remarksRow.style.display = 'none';
    } else {
        reviewerRow.style.display = 'flex';
        validatedDateRow.style.display = 'flex';
        remarksRow.style.display = 'flex';

        document.getElementById('det-reviewer').textContent = row.faculty_validator || 'Faculty Reviewer';
        document.getElementById('det-validated-at').textContent = row.validated_at ? new Date(row.validated_at.replace(/-/g, "/")).toLocaleString() : '—';
        document.getElementById('det-remarks').innerHTML = row.faculty_remarks ? escapeHtml(row.faculty_remarks).replace(/\n/g, '<br>') : 'No evaluation remarks submitted.';
    }

    document.getElementById('detailOverlay').classList.add('open');
}

function closeDetailModal() {
    document.getElementById('detailOverlay').classList.remove('open');
}

// Close modal when clicking outside content
document.getElementById('detailOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('detailOverlay')) closeDetailModal();
});

function isAutoRefreshPaused() {
    const isModalOpen = document.getElementById('detailOverlay').classList.contains('open');
    const isUserTyping = document.activeElement && (
        document.activeElement.tagName === 'INPUT' || 
        document.activeElement.tagName === 'TEXTAREA' || 
        document.activeElement.tagName === 'SELECT'
    );
    return isModalOpen || isUserTyping || isFetching;
}

function autoRefreshStudentRecords() {
    if (isAutoRefreshPaused()) return;
    
    const status = document.getElementById('filter-status').value;
    const powder = document.getElementById('filter-powder').value;
    const surface = document.getElementById('filter-surface').value;

    fetch(`ajax_get_student_records.php?status=${encodeURIComponent(status)}&powder=${encodeURIComponent(powder)}&surface=${encodeURIComponent(surface)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderRecordsTable(data.data.records);
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    // Hook Filter elements changes
    document.getElementById('filter-status').addEventListener('change', fetchFilteredRecords);
    document.getElementById('filter-powder').addEventListener('change', fetchFilteredRecords);
    document.getElementById('filter-surface').addEventListener('change', fetchFilteredRecords);
    
    // Clear Filters button
    document.getElementById('btnClearFilters').addEventListener('click', () => {
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-powder').value = '';
        document.getElementById('filter-surface').value = '';
        fetchFilteredRecords();
    });

    // Initialize click handlers on page load
    const rows = document.querySelectorAll('#recordsTableBody tr[data-trial-db-id]');
    rows.forEach(r => {
        const rowData = <?php echo json_encode($records); ?>;
        const id = parseInt(r.getAttribute('data-trial-db-id'));
        const matchingRec = rowData.find(item => parseInt(item.id) === id);
        if (matchingRec) {
            r.onclick = () => openDetailModal(matchingRec);
        }
    });

    // 10s auto-refresh
    setInterval(autoRefreshStudentRecords, 10000);
});
</script>
</body>
</html>
