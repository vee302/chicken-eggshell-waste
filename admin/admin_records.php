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

// View detail of a single record
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

                <!-- SEARCH AND FILTERS -->
                <div class="dashboard-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
                    <form id="filterForm" class="search-filter-bar" onsubmit="event.preventDefault(); fetchFilteredRecords();">
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
                            <tbody>
                                <?php if (count($trial_records) > 0): ?>
                                    <?php foreach ($trial_records as $rec): ?>
                                        <tr>
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
                                                        <?php if (!empty($rec['image_path']) && file_exists('../uploads/fingerprints/' . $rec['image_path'])): ?>
                                                            <img src="../uploads/fingerprints/<?php echo htmlspecialchars($rec['image_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Fingerprint">
                                                        <?php else: ?>
                                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--medium-green);">
                                                                <path d="M12 2a10 10 0 0 0-7.3 16.8"></path>
                                                                <path d="M12 2a10 10 0 0 1 7.3 16.8"></path>
                                                                <path d="M12 6a6 6 0 0 0-4.4 10.1"></path>
                                                                <path d="M12 6a6 6 0 0 1 4.4 10.1"></path>
                                                                <path d="M12 10a2 2 0 0 0-1.5 3.4"></path>
                                                                <path d="M12 10a2 2 0 0 1 1.5 3.4"></path>
                                                                <path d="M12 14v4"></path>
                                                            </svg>
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
                                                <a href="admin_records.php?view=<?php echo $rec['id']; ?><?php 
                                                    echo !empty($search_student) ? '&student='.urlencode($search_student) : '';
                                                    echo !empty($filter_powder) ? '&powder='.urlencode($filter_powder) : '';
                                                    echo !empty($filter_surface) ? '&surface='.urlencode($filter_surface) : '';
                                                    echo !empty($filter_status) ? '&status='.urlencode($filter_status) : '';
                                                ?>" class="btn btn-secondary btn-sm">
                                                    <span>View Details</span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
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
    <?php if ($view_record): ?>
    <div class="detail-overlay open" id="recordOverlay">
        <div class="detail-modal">
            <div class="detail-modal-header">
                <h3>Trial Record Details: ID #<?php echo htmlspecialchars($view_record['trial_id'] ?: 'TR-'.str_pad($view_record['id'], 4, '0', STR_PAD_LEFT)); ?></h3>
                <button class="modal-close-btn" onclick="document.getElementById('recordOverlay').classList.remove('open')">&times;</button>
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
                <div class="detail-row"><span class="detail-label">Student Submitter</span><span class="detail-value"><?php echo htmlspecialchars($view_record['student_name']); ?></span></div>
                <div class="detail-row"><span class="detail-label">Powder Type Used</span><span class="detail-value" style="text-transform: capitalize; font-weight: 600;"><?php echo htmlspecialchars($view_record['powder_type']); ?></span></div>
                <div class="detail-row"><span class="detail-label">Surface Material Type</span><span class="detail-value" style="text-transform: capitalize; font-weight: 600;"><?php echo htmlspecialchars($view_record['surface_type']); ?></span></div>
                <div class="detail-row"><span class="detail-label">Image Label</span><span class="detail-value"><?php echo htmlspecialchars($view_record['image_label'] ?: 'Untitled'); ?></span></div>
                <div class="detail-row"><span class="detail-label">Notes from Submission</span><span class="detail-value"><?php echo nl2br(htmlspecialchars($view_record['notes'] ?: 'No notes provided.')); ?></span></div>
                <div class="detail-row"><span class="detail-label">Date Submitted</span><span class="detail-value"><?php echo date('F d, Y g:i A', strtotime($view_record['submitted_at'])); ?></span></div>

                <p class="section-divider">Automated Image Evaluation Scores</p>
                <div class="score-box">
                    <div class="score-title">Individual Forensic Performance Metrics</div>
                    <div class="score-values">
                        <div>
                            <div class="score-val"><?php echo $view_record['ridge_clarity_score'] !== null ? number_format($view_record['ridge_clarity_score'], 1) . '%' : '—'; ?></div>
                            <div class="score-lbl">Clarity</div>
                        </div>
                        <div>
                            <div class="score-val"><?php echo $view_record['visibility_score'] !== null ? number_format($view_record['visibility_score'], 1) . '%' : '—'; ?></div>
                            <div class="score-lbl">Visibility</div>
                        </div>
                        <div>
                            <div class="score-val"><?php echo $view_record['adhesion_score'] !== null ? number_format($view_record['adhesion_score'], 1) . '%' : '—'; ?></div>
                            <div class="score-lbl">Adhesion</div>
                        </div>
                        <div>
                            <div class="score-val"><?php echo $view_record['contrast_score'] !== null ? number_format($view_record['contrast_score'], 1) . '%' : '—'; ?></div>
                            <div class="score-lbl">Contrast</div>
                        </div>
                    </div>
                </div>
                <div class="detail-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green); margin-bottom: 0.5rem;">
                    <span class="detail-label" style="font-weight: 700;">AI Preliminary Score</span>
                    <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;"><?php echo $view_record['ai_accuracy_score'] !== null ? number_format($view_record['ai_accuracy_score'], 1) . '%' : 'Awaiting AI Evaluation'; ?></span>
                </div>
                <div class="detail-row" style="background: var(--cream); padding: 8px 12px; border-radius: 6px; border-left: 4px solid var(--medium-green);">
                    <span class="detail-label" style="font-weight: 700;">Faculty Final Score</span>
                    <span class="detail-value" style="font-weight: 800; color: var(--dark-green); font-size:1.1rem;"><?php echo $view_record['faculty_final_score'] !== null ? number_format($view_record['faculty_final_score'], 1) . '%' : ($view_record['status'] === 'pending_validation' ? 'Awaiting Validation' : '—'); ?></span>
                </div>

                <p class="section-divider">Validation Details</p>
                <div class="detail-row"><span class="detail-label">Validation Status</span><span class="detail-value">
                    <span class="badge-<?php echo $view_record['status']; ?>">
                        <?php 
                            if ($view_record['status'] === 'pending_validation') {
                                echo 'Pending Validation';
                            } elseif ($view_record['status'] === 'needs_revision') {
                                echo 'Needs Revision';
                            } else {
                                echo ucfirst($view_record['status']);
                            }
                        ?>
                    </span>
                </span></div>
                <div class="detail-row"><span class="detail-label">Faculty Reviewer</span><span class="detail-value" style="font-weight: 600;"><?php echo htmlspecialchars($view_record['validator_name'] ?: 'Awaiting Review'); ?></span></div>
                <div class="detail-row"><span class="detail-label">Review Date</span><span class="detail-value"><?php echo $view_record['validation_date'] ? date('F d, Y g:i A', strtotime($view_record['validation_date'])) : '—'; ?></span></div>
                <div class="detail-row"><span class="detail-label">Remarks from Reviewer</span><span class="detail-value" style="font-style: italic;"><?php echo nl2br(htmlspecialchars($view_record['validation_remarks'] ?: 'No evaluation remarks submitted yet.')); ?></span></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
</body>

</html>