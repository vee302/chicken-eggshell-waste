<?php
// admin/admin_records.php - Super Administrator Records Management
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

// Filtering inputs
$search_student = isset($_GET["student"]) ? trim($_GET["student"]) : "";
$filter_powder = isset($_GET["powder"]) ? trim($_GET["powder"]) : "";
$filter_surface = isset($_GET["surface"]) ? trim($_GET["surface"]) : "";
$filter_status = isset($_GET["status"]) ? trim($_GET["status"]) : "";

// Build SQL Query
$query_str = "
    SELECT 
        ft.*,
        student.full_name AS student_name,
        faculty.full_name AS faculty_validator,
        faculty.full_name AS validator_name,
        frm.remarks AS validation_remarks,
        frm.created_at AS validation_date
    FROM fingerprint_tests ft
    LEFT JOIN users student ON ft.student_id = student.id
    LEFT JOIN users faculty ON ft.validated_by = faculty.id
    LEFT JOIN faculty_remarks frm ON ft.id = frm.test_id AND frm.id = (
        SELECT MAX(frm2.id) FROM faculty_remarks frm2 WHERE frm2.test_id = ft.id
    )
    WHERE 1=1
";

$params = [];

if (!empty($search_student)) {
    $query_str .= " AND student.full_name LIKE :student";
    $params[':student'] = '%' . $search_student . '%';
}

if (!empty($filter_powder)) {
    $query_str .= " AND ft.powder_type = :powder";
    $params[':powder'] = $filter_powder;
}

if (!empty($filter_surface)) {
    $query_str .= " AND ft.surface_type = :surface";
    $params[':surface'] = $filter_surface;
}

if (!empty($filter_status)) {
    $query_str .= " AND ft.status = :status";
    $params[':status'] = $filter_status;
}

$query_str .= " ORDER BY ft.id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$trial_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($trial_records as &$rec) {
    $rec['image_exists'] = false;
    if (!empty($rec['image_path'])) {
        $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $rec['image_path'];
        if (file_exists($filePath)) {
            $rec['image_exists'] = true;
        }
    }
}
unset($rec);

// View detail of a single record (Initial URL check)
$view_record = null;
if (isset($_GET['view'])) {
    $v_id = intval($_GET['view']);
    foreach ($trial_records as $rec) {
        if ((int)$rec['id'] === $v_id) {
            $view_record = $rec;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trial Records Monitoring - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .badge-pending_validation { background:rgba(244,162,97,.15); color:#c97d2a; border:1px solid rgba(244,162,97,.25); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; display:inline-block; }
        .badge-needs_revision { background:rgba(230,57,70,.12); color:#e63946; border:1px solid rgba(230,57,70,.2); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; display:inline-block; }
        .badge-approved { background:rgba(82,183,136,.15); color:#2d6a4f; border:1px solid rgba(82,183,136,.25); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; display:inline-block; }
        .badge-rejected { background:rgba(224,122,95,.15); color:#c0392b; border:1px solid rgba(224,122,95,.2); padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; display:inline-block; }

        /* Detail Modal */
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

        .warning-banner { background:rgba(244,162,97,0.12); color:#c87b1c; padding:10px 15px; border-radius:8px; border:1.5px solid rgba(244,162,97,0.2); font-size:0.8rem; font-weight:600; margin-bottom:1.25rem; display:flex; gap:8px; align-items:center; }
        
        #adminRecordsTableBody tr { cursor: pointer; }
        #adminRecordsTableBody tr:hover { background-color: #f8faf6; }
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
                        <h1>Trial Records Monitoring</h1>
                        <p>Detailed evaluation database of latent fingerprint powder clarity, accuracy scores, and validation histories.</p>
                    </div>
                </div>

                <?php
                // Check if running on Railway and persistent volume is mounted
                $is_railway = (getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_STATIC_URL') !== false);
                $is_volume_mounted = false;
                if ($is_railway) {
                    if (file_exists('/proc/mounts')) {
                        $mounts = file_get_contents('/proc/mounts');
                        if (strpos($mounts, '/var/www/html/uploads') !== false) {
                            $is_volume_mounted = true;
                        }
                    }
                }
                if ($is_railway && !$is_volume_mounted):
                ?>
                <div class="warning-banner" style="background:rgba(224,122,95,0.12); color:#c0392b; border:1.5px solid rgba(224,122,95,0.2); margin-bottom:1.5rem; display:flex; gap:10px; align-items:center; padding:12px 18px; border-radius:10px; font-size:0.85rem; font-weight:600;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span><strong>Railway Container Warning:</strong> No persistent volume is detected mounted at `/var/www/html/uploads`. Uploaded fingerprint images will be lost when the container restarts. Please configure a Railway Volume mounted to `/var/www/html/uploads` to persist these assets.</span>
                </div>
                <?php endif; ?>

                <!-- SEARCH AND FILTERS -->
                <div class="dashboard-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
                    <form id="filterForm" class="search-filter-bar">
                        <div class="bar-left">
                            <input type="text" name="student" id="filter-student" class="form-control-inline"
                                placeholder="Filter by Student Name..."
                                value="<?php echo htmlspecialchars($search_student); ?>" style="min-width: 200px;">
                            
                            <select name="powder" id="filter-powder" class="form-control-inline">
                                <option value="">All Powder Types</option>
                                <option value="eggshell" <?php echo $filter_powder === 'eggshell' ? 'selected' : ''; ?>>Eggshell Powder</option>
                                <option value="commercial" <?php echo $filter_powder === 'commercial' ? 'selected' : ''; ?>>Commercial Carbon</option>
                            </select>

                            <select name="surface" id="filter-surface" class="form-control-inline">
                                <option value="">All Surface Types</option>
                                <option value="glass" <?php echo $filter_surface === 'glass' ? 'selected' : ''; ?>>Glass</option>
                                <option value="paper" <?php echo $filter_surface === 'paper' ? 'selected' : ''; ?>>Paper</option>
                                <option value="wood" <?php echo $filter_surface === 'wood' ? 'selected' : ''; ?>>Wood</option>
                                <option value="plastic" <?php echo $filter_surface === 'plastic' ? 'selected' : ''; ?>>Plastic</option>
                                <option value="metal" <?php echo $filter_surface === 'metal' ? 'selected' : ''; ?>>Metal</option>
                                <option value="ceramic" <?php echo $filter_surface === 'ceramic' ? 'selected' : ''; ?>>Ceramic</option>
                                <option value="fabric" <?php echo $filter_surface === 'fabric' ? 'selected' : ''; ?>>Fabric</option>
                            </select>

                            <select name="status" id="filter-status" class="form-control-inline">
                                <option value="">All Statuses</option>
                                <option value="pending_validation" <?php echo $filter_status === 'pending_validation' ? 'selected' : ''; ?>>Pending Validation</option>
                                <option value="approved"           <?php echo $filter_status === 'approved'           ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected"           <?php echo $filter_status === 'rejected'           ? 'selected' : ''; ?>>Rejected</option>
                                <option value="needs_revision"     <?php echo $filter_status === 'needs_revision'     ? 'selected' : ''; ?>>Needs Revision</option>
                            </select>

                            <button type="submit" class="btn btn-secondary">Filter Records</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="btnClearFilters" style="border: none; display: <?php echo (!empty($search_student) || !empty($filter_powder) || !empty($filter_surface) || !empty($filter_status)) ? 'inline-block' : 'none'; ?>;">Clear Filters</button>
                        </div>
                    </form>
                </div>

                <!-- RECORDS TABLE -->
                <div class="dashboard-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Trial ID</th>
                                    <th>Student Name</th>
                                    <th>Powder Type</th>
                                    <th>Surface Type</th>
                                    <th>Fingerprint Image</th>
                                    <th>Accuracy Score</th>
                                    <th>Status</th>
                                    <th>Date Submitted</th>
                                    <th>Faculty Validator</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="adminRecordsTableBody">
                                <?php if (count($trial_records) > 0): ?>
                                    <?php foreach ($trial_records as $rec): ?>
                                        <tr data-trial-db-id="<?php echo $rec['id']; ?>">
                                            <td style="font-weight: 700; color: var(--dark-green);"><?php echo htmlspecialchars($rec['trial_id'] ?: 'TR-'.str_pad($rec['id'], 4, '0', STR_PAD_LEFT)); ?></td>
                                            <td style="font-weight: 600;"><?php echo htmlspecialchars($rec['student_name']); ?></td>
                                            <td>
                                                <span style="color: <?php echo ($rec['powder_type'] === 'eggshell') ? 'var(--medium-green)' : 'var(--gray)'; ?>; font-weight: 600; text-transform: capitalize;">
                                                    <?php echo $rec['powder_type']; ?>
                                                </span>
                                            </td>
                                            <td style="text-transform: capitalize; font-weight: 500;"><?php echo htmlspecialchars($rec['surface_type']); ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <div style="width: 32px; height: 32px; border-radius: 4px; background: #e9ecef; border: 1px solid var(--light-gray); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                                        <?php if (!empty($rec['image_path']) && $rec['image_exists']): ?>
                                                            <a href="../view_fingerprint.php?test_id=<?php echo $rec['id']; ?>" target="_blank" onclick="event.stopPropagation();">
                                                                <img src="../view_fingerprint.php?test_id=<?php echo $rec['id']; ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Fingerprint">
                                                            </a>
                                                        <?php else: ?>
                                                            <div style="font-size:0.55rem;color:var(--danger);font-weight:700;text-align:center;padding:1px;line-height:1.1;">Not found</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span style="font-family: monospace; font-size: 0.75rem; color: var(--gray);">
                                                        <?php echo !empty($rec['image_path']) ? htmlspecialchars($rec['image_path']) : 'placeholder.jpg'; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                                    <?php if ($rec['status'] === 'approved' && $rec['accuracy_score'] !== null): ?>
                                                        <div style="width: 50px; background-color: var(--light-gray); height: 6px; border-radius: 3px; overflow:hidden;">
                                                            <div style="width: <?php echo $rec['accuracy_score']; ?>%; height: 100%; background-color: <?php echo ($rec['accuracy_score'] >= 90) ? 'var(--medium-green)' : (($rec['accuracy_score'] >= 80) ? 'var(--accent-green)' : 'var(--warning)'); ?>;"></div>
                                                        </div>
                                                        <span style="font-weight: 700; color: var(--dark-green);"><?php echo number_format($rec['accuracy_score'], 1); ?>%</span>
                                                    <?php elseif ($rec['status'] === 'pending_validation'): ?>
                                                        <span style="font-size:0.75rem; color:var(--gray); font-style:italic;">Awaiting Validation</span>
                                                    <?php elseif ($rec['status'] === 'needs_revision'): ?>
                                                        <span style="font-size:0.75rem; color:var(--gray); font-style:italic;">Needs Revision</span>
                                                    <?php elseif ($rec['status'] === 'rejected'): ?>
                                                        <span style="font-size:0.75rem; color:var(--gray); font-style:italic;">Rejected</span>
                                                    <?php else: ?>
                                                        <span style="font-size:0.75rem; color:var(--gray); font-style:italic;">N/A</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-<?php echo $rec['status']; ?>">
                                                    <?php 
                                                        if ($rec['status'] === 'pending_validation') {
                                                            echo 'Pending Validation';
                                                        } elseif ($rec['status'] === 'needs_revision') {
                                                            echo 'Needs Revision';
                                                        } else {
                                                            echo ucfirst($rec['status']);
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($rec['submitted_at'])); ?></td>
                                            <td>
                                                <span style="font-weight: 600; color: #5f5f5f;">
                                                    <?php echo htmlspecialchars($rec['validator_name'] ?: 'Not yet validated'); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <button type="button" class="btn btn-secondary btn-sm btn-view-details">
                                                    <span>View Details</span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="no-data-row">
                                        <td colspan="10" style="text-align: center; color: var(--gray); padding: 2rem;">No trial records match filter options.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- VIEW RECORD MODAL -->
    <div class="detail-overlay <?php echo $view_record ? 'open' : ''; ?>" id="recordOverlay">
        <div class="detail-modal">
            <div class="detail-modal-header">
                <h3 id="det-modal-title">Trial Record Details</h3>
                <button class="modal-close-btn" onclick="closeDetailModal()">&times;</button>
            </div>
            <div class="detail-modal-body">
                <div class="warning-banner">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>Read-Only Mode: Scientific accuracy scores and validation remarks are restricted to Faculty Researchers.</span>
                </div>

                <p class="section-divider">Forensic Submission Details</p>
                <div class="detail-row"><span class="detail-label">Student Submitter</span><span class="detail-value" id="det-student">—</span></div>
                <div class="detail-row"><span class="detail-label">Powder Type Used</span><span class="detail-value" id="det-powder" style="text-transform: capitalize; font-weight: 600;">—</span></div>
                <div class="detail-row"><span class="detail-label">Surface Material Type</span><span class="detail-value" id="det-surface" style="text-transform: capitalize; font-weight: 600;">—</span></div>
                <div class="detail-row"><span class="detail-label">Image Label</span><span class="detail-value" id="det-label">—</span></div>
                <div class="detail-row"><span class="detail-label">Notes from Submission</span><span class="detail-value" id="det-notes">—</span></div>
                <div class="detail-row"><span class="detail-label">Date Submitted</span><span class="detail-value" id="det-submitted-at">—</span></div>

                <p class="section-divider">Fingerprint Image Asset</p>
                <div style="text-align:center; margin-bottom:1rem; border:1px solid #e9ecef; padding:10px; border-radius:8px; background:#fafafa;" id="det-img-wrapper">
                    <img src="" style="max-height:220px; max-width:100%; object-fit:contain; border-radius:6px; border:1px solid #ddd;" alt="Fingerprint Image Asset" id="det-img">
                </div>

                <p class="section-divider">Automated Image Evaluation Scores</p>
                <div class="score-box">
                    <div class="score-title">Individual Forensic Performance Metrics</div>
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
                <div class="detail-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green); margin-bottom: 0.5rem;">
                    <span class="detail-label" style="font-weight: 700;">AI Preliminary Score</span>
                    <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;" id="det-ai-score">—</span>
                </div>
                <div class="detail-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green);">
                    <span class="detail-label" style="font-weight: 700;">Faculty Final Score</span>
                    <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;" id="det-faculty-score">—</span>
                </div>

                <p class="section-divider">Validation Details</p>
                <div class="detail-row"><span class="detail-label">Validation Status</span><span class="detail-value" id="det-status">—</span></div>
                <div class="detail-row"><span class="detail-label">Faculty Reviewer</span><span class="detail-value" id="det-reviewer" style="font-weight: 600;">—</span></div>
                <div class="detail-row"><span class="detail-label">Review Date</span><span class="detail-value" id="det-validated-at">—</span></div>
                <div class="detail-row"><span class="detail-label">Remarks from Reviewer</span><span class="detail-value" id="det-remarks" style="font-style: italic;">—</span></div>
            </div>
        </div>
    </div>

    <!-- JS Toggles -->
    <script>
        let currentRecords = <?php echo json_encode($trial_records); ?>;
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

        function getStatusLabel(status) {
            if (status === 'pending_validation') return 'Pending Validation';
            if (status === 'needs_revision') return 'Needs Revision';
            return status.charAt(0).toUpperCase() + status.slice(1);
        }

        function fetchFilteredRecords() {
            if (isFetching) return;
            
            const student = document.getElementById('filter-student').value;
            const powder = document.getElementById('filter-powder').value;
            const surface = document.getElementById('filter-surface').value;
            const status = document.getElementById('filter-status').value;
            
            // Toggle Clear Filters button visibility
            const clearBtn = document.getElementById('btnClearFilters');
            if (student || powder || surface || status) {
                clearBtn.style.display = 'inline-block';
            } else {
                clearBtn.style.display = 'none';
            }

            isFetching = true;
            
            // Update Address Bar Query Params
            const url = new URL(window.location);
            if (student) url.searchParams.set('student', student); else url.searchParams.delete('student');
            if (powder) url.searchParams.set('powder', powder); else url.searchParams.delete('powder');
            if (surface) url.searchParams.set('surface', surface); else url.searchParams.delete('surface');
            if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
            window.history.pushState({}, '', url);

            fetch(`ajax_get_trial_records.php?student=${encodeURIComponent(student)}&powder=${encodeURIComponent(powder)}&surface=${encodeURIComponent(surface)}&status=${encodeURIComponent(status)}`)
                .then(res => res.json())
                .then(data => {
                    isFetching = false;
                    if (data.success) {
                        currentRecords = data.data.records;
                        renderRecordsTable(currentRecords);
                    }
                })
                .catch(err => {
                    isFetching = false;
                });
        }

        function renderRecordsTable(records) {
            const tbody = document.getElementById('adminRecordsTableBody');
            
            if (records.length === 0) {
                tbody.innerHTML = `
                    <tr class="no-data-row">
                        <td colspan="10" style="text-align: center; color: var(--gray); padding: 2rem;">No trial records match filter options.</td>
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
                
                let imageHtml = '<div style="font-size:0.55rem;color:var(--danger);font-weight:700;text-align:center;padding:1px;line-height:1.1;">Not found</div>';
                if (r.image_path) {
                    if (r.image_exists) {
                        imageHtml = `
                            <a href="../view_fingerprint.php?test_id=${r.id}" target="_blank" onclick="event.stopPropagation();">
                                <img src="../view_fingerprint.php?test_id=${r.id}" style="width: 100%; height: 100%; object-fit: cover;" alt="Fingerprint">
                            </a>`;
                    }
                }

                let accuracyHtml = '<span style="font-size:0.75rem; color:var(--gray); font-style:italic;">N/A</span>';
                if (r.status === 'approved' && r.accuracy_score !== null) {
                    const pct = parseFloat(r.accuracy_score);
                    const color = (pct >= 90) ? 'var(--medium-green)' : ((pct >= 80) ? 'var(--accent-green)' : 'var(--warning)');
                    accuracyHtml = `
                        <div style="width: 50px; background-color: var(--light-gray); height: 6px; border-radius: 3px; overflow:hidden;">
                            <div style="width: ${pct}%; height: 100%; background-color: ${color};"></div>
                        </div>
                        <span style="font-weight: 700; color: var(--dark-green);">${pct.toFixed(1)}%</span>`;
                } else if (r.status === 'pending_validation') {
                    accuracyHtml = '<span style="font-size:0.75rem; color:var(--gray); font-style:italic;">Awaiting Validation</span>';
                } else if (r.status === 'needs_revision') {
                    accuracyHtml = '<span style="font-size:0.75rem; color:var(--gray); font-style:italic;">Needs Revision</span>';
                } else if (r.status === 'rejected') {
                    accuracyHtml = '<span style="font-size:0.75rem; color:var(--gray); font-style:italic;">Rejected</span>';
                }

                const powderColor = (r.powder_type === 'eggshell') ? 'var(--medium-green)' : 'var(--gray)';

                const trHtml = `
                    <td style="font-weight: 700; color: var(--dark-green);">${r.trial_id || 'TR-' + String(r.id).padStart(4, '0')}</td>
                    <td style="font-weight: 600;">${escapeHtml(r.student_name)}</td>
                    <td>
                        <span style="color: ${powderColor}; font-weight: 600; text-transform: capitalize;">
                            ${escapeHtml(r.powder_type)}
                        </span>
                    </td>
                    <td style="text-transform: capitalize; font-weight: 500;">${escapeHtml(r.surface_type)}</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 32px; height: 32px; border-radius: 4px; background: #e9ecef; border: 1px solid var(--light-gray); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                ${imageHtml}
                            </div>
                            <span style="font-family: monospace; font-size: 0.75rem; color: var(--gray);">
                                ${escapeHtml(r.image_path || 'placeholder.jpg')}
                            </span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            ${accuracyHtml}
                        </div>
                    </td>
                    <td>
                        <span class="badge-${r.status}">
                            ${getStatusLabel(r.status)}
                        </span>
                    </td>
                    <td>${new Date(r.submitted_at.replace(/-/g, "/")).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} ${new Date(r.submitted_at.replace(/-/g, "/")).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</td>
                    <td>
                        <span style="font-weight: 600; color: #5f5f5f;">
                            ${escapeHtml(r.validator_name || 'Not yet validated')}
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <button type="button" class="btn btn-secondary btn-sm btn-view-details">
                            <span>View Details</span>
                        </button>
                    </td>
                `;

                if (row) {
                    row.innerHTML = trHtml;
                } else {
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-trial-db-id', r.id);
                    tr.innerHTML = trHtml;
                    
                    const noData = tbody.querySelector('.no-data-row');
                    if (noData) noData.remove();
                    tbody.insertBefore(tr, tbody.firstChild);
                }
                
                // Bind click actions
                const trNode = tbody.querySelector(`tr[data-trial-db-id="${r.id}"]`);
                if (trNode) {
                    trNode.onclick = () => openDetailModal(r);
                    trNode.querySelector('.btn-view-details').onclick = (e) => {
                        e.stopPropagation();
                        openDetailModal(r);
                    };
                }
            });
        }

        function openDetailModal(row) {
            document.getElementById('det-modal-title').textContent = 'Trial Record Details: ID #' + (row.trial_id || 'TR-' + String(row.id).padStart(4, '0'));
            document.getElementById('det-student').textContent = row.student_name || '—';
            document.getElementById('det-powder').textContent = row.powder_type || '—';
            document.getElementById('det-surface').textContent = row.surface_type || '—';
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

            // Metrics
            document.getElementById('det-clarity').textContent = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score).toFixed(1) + '%' : '—';
            document.getElementById('det-visibility').textContent = row.visibility_score !== null ? parseFloat(row.visibility_score).toFixed(1) + '%' : '—';
            document.getElementById('det-adhesion').textContent = row.adhesion_score !== null ? parseFloat(row.adhesion_score).toFixed(1) + '%' : '—';
            document.getElementById('det-contrast').textContent = row.contrast_score !== null ? parseFloat(row.contrast_score).toFixed(1) + '%' : '—';

            document.getElementById('det-ai-score').textContent = row.ai_accuracy_score !== null ? parseFloat(row.ai_accuracy_score).toFixed(1) + '%' : 'Awaiting AI Evaluation';
            
            if (row.status === 'approved' && row.faculty_final_score !== null) {
                document.getElementById('det-faculty-score').textContent = parseFloat(row.faculty_final_score).toFixed(1) + '%';
            } else if (row.status === 'pending_validation') {
                document.getElementById('det-faculty-score').textContent = 'Awaiting Validation';
            } else {
                document.getElementById('det-faculty-score').textContent = '—';
            }

            // Validation Details
            document.getElementById('det-status').innerHTML = `<span class="badge-${row.status}">${getStatusLabel(row.status)}</span>`;
            document.getElementById('det-reviewer').textContent = row.validator_name || 'Awaiting Review';
            document.getElementById('det-validated-at').textContent = row.validation_date ? new Date(row.validation_date.replace(/-/g, "/")).toLocaleString() : '—';
            document.getElementById('det-remarks').innerHTML = row.validation_remarks ? escapeHtml(row.validation_remarks).replace(/\n/g, '<br>') : 'No evaluation remarks submitted yet.';

            document.getElementById('recordOverlay').classList.add('open');
        }

        function closeDetailModal() {
            document.getElementById('recordOverlay').classList.remove('open');
        }

        // Close modal when clicking outside content
        document.getElementById('recordOverlay').addEventListener('click', e => {
            if (e.target === document.getElementById('recordOverlay')) closeDetailModal();
        });

        function isAutoRefreshPaused() {
            const isModalOpen = document.getElementById('recordOverlay').classList.contains('open');
            const isUserTyping = document.activeElement && (
                document.activeElement.tagName === 'INPUT' || 
                document.activeElement.tagName === 'TEXTAREA' || 
                document.activeElement.tagName === 'SELECT'
            );
            return isModalOpen || isUserTyping || isFetching;
        }

        function autoRefreshAdminRecords() {
            if (isAutoRefreshPaused()) return;
            
            const student = document.getElementById('filter-student').value;
            const powder = document.getElementById('filter-powder').value;
            const surface = document.getElementById('filter-surface').value;
            const status = document.getElementById('filter-status').value;

            fetch(`ajax_get_trial_records.php?student=${encodeURIComponent(student)}&powder=${encodeURIComponent(powder)}&surface=${encodeURIComponent(surface)}&status=${encodeURIComponent(status)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        currentRecords = data.data.records;
                        renderRecordsTable(currentRecords);
                    }
                });
        }

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

            // Hook filters form submission
            document.getElementById('filterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                fetchFilteredRecords();
            });

            // Handle clear filters
            document.getElementById('btnClearFilters').addEventListener('click', () => {
                document.getElementById('filter-student').value = '';
                document.getElementById('filter-powder').value = '';
                document.getElementById('filter-surface').value = '';
                document.getElementById('filter-status').value = '';
                fetchFilteredRecords();
            });

            // Bind click handlers to initial rows on page load
            const rows = document.querySelectorAll('#adminRecordsTableBody tr[data-trial-db-id]');
            rows.forEach(r => {
                const id = parseInt(r.getAttribute('data-trial-db-id'));
                const rec = currentRecords.find(item => parseInt(item.id) === id);
                if (rec) {
                    r.onclick = () => openDetailModal(rec);
                    r.querySelector('.btn-view-details').onclick = (e) => {
                        e.stopPropagation();
                        openDetailModal(rec);
                    };
                }
            });

            // If a detail check query param view is present, open modal
            <?php if ($view_record): ?>
                const initialViewRec = currentRecords.find(item => parseInt(item.id) === <?php echo $view_record['id']; ?>);
                if (initialViewRec) {
                    openDetailModal(initialViewRec);
                }
            <?php endif; ?>

            // Start 10s auto-refresh
            setInterval(autoRefreshAdminRecords, 10000);
        });
    </script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>

</html>