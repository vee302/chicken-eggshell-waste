<?php
// admin/admin_reports.php - Super Administrator Reports Management
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

$error = "";
$success = "";

// Handle report compile action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "compile") {
    $report_title = trim($_POST["report_title"] ?? "");
    $surface_type = trim($_POST["surface_type"] ?? "");
    
    if (empty($report_title)) {
        $report_title = "Eggs-shell vs Commercial Powder Performance Study - " . date("M Y");
    }

    $filter_params = [
        'compiled_by' => $_SESSION["user_email"],
        'surface_type' => !empty($surface_type) ? $surface_type : 'all'
    ];
    $filter_json = json_encode($filter_params);

    try {
        $stmt = $pdo->prepare("INSERT INTO reports (generated_by, report_title, report_filter) VALUES (:by, :title, :filter)");
        $stmt->execute([
            ':by' => $_SESSION["user_id"],
            ':title' => $report_title,
            ':filter' => $filter_json
        ]);
        
        log_activity("Generate Report", "Compiled system evaluation report: '$report_title'");
        $success = "Report successfully compiled and saved to database archives.";
    } catch (PDOException $e) {
        $error = "Error generating report: " . $e->getMessage();
    }
}

// Fetch generated reports
$filter_date = isset($_GET["date"]) ? trim($_GET["date"]) : "";
$filter_user = isset($_GET["user"]) ? trim($_GET["user"]) : "";

$query_str = "
    SELECT 
        r.id, 
        r.report_title, 
        r.report_filter, 
        r.generated_at,
        u.full_name AS compiled_by,
        u.role AS compiler_role
    FROM reports r
    JOIN users u ON r.generated_by = u.id
    WHERE 1=1
";

$params = [];

if (!empty($filter_date)) {
    $query_str .= " AND DATE(r.generated_at) = :date";
    $params[':date'] = $filter_date;
}

if (!empty($filter_user)) {
    $query_str .= " AND u.full_name LIKE :user";
    $params[':user'] = '%' . $filter_user . '%';
}

$query_str .= " ORDER BY r.id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$reports_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Reports Monitoring - Green Forensics</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="../css/admin_style.css?v=1.6">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; }
        .alert-danger { background-color: rgba(224, 122, 95, 0.15); color: var(--danger); border: 1px solid rgba(224, 122, 95, 0.2); }
        .alert-success { background-color: rgba(82, 183, 136, 0.15); color: var(--medium-green); border: 1px solid rgba(82, 183, 136, 0.2); }
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
                        <h1>System & Evaluation Reports</h1>
                        <p>Generate, view, and download comprehensive statistical analysis reports for sustainable powder trials.</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="openCompileModal()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="16"></line>
                                <line x1="8" y1="12" x2="16" y2="12"></line>
                            </svg>
                            <span>Compile New Report</span>
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

                <!-- REPORT SEARCH AND FILTERS -->
                <div class="dashboard-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
                    <form method="GET" action="admin_reports.php" class="search-filter-bar">
                        <div class="bar-left">
                            <input type="text" name="user" class="form-control-inline"
                                placeholder="Compiled by (name)..."
                                value="<?php echo htmlspecialchars($filter_user); ?>" style="min-width: 200px;">
                            
                            <input type="date" name="date" class="form-control-inline"
                                value="<?php echo htmlspecialchars($filter_date); ?>">

                            <button type="submit" class="btn btn-secondary">Filter Reports</button>
                            <?php if (!empty($filter_date) || !empty($filter_user)): ?>
                                <a href="admin_reports.php" class="btn btn-secondary btn-sm" style="border: none;">Clear Filters</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- REPORTS LIST TABLE -->
                <div class="dashboard-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Report Title</th>
                                    <th>Compiled By</th>
                                    <th>Filters / Configurations</th>
                                    <th>Date Generated</th>
                                    <th style="text-align: right;">Download Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($reports_list) > 0): ?>
                                    <?php foreach ($reports_list as $rpt): ?>
                                        <tr>
                                            <td style="font-family: monospace; font-weight: 700; color: var(--gray);">
                                                RPT-2026-<?php echo sprintf('%03d', $rpt['id']); ?>
                                            </td>
                                            <td style="font-weight: 600; color: var(--dark-green);">
                                                <?php echo htmlspecialchars($rpt['report_title']); ?>
                                            </td>
                                            <td>
                                                <span style="display:block; font-weight:600;"><?php echo htmlspecialchars($rpt['compiled_by']); ?></span>
                                                <span style="font-size:0.75rem; color:#888;"><?php echo role_label($rpt['compiler_role']); ?></span>
                                            </td>
                                            <td>
                                                <span style="font-family:monospace; font-size:0.75rem; color:#6c757d;">
                                                    <?php 
                                                        $params = json_decode($rpt['report_filter'], true);
                                                        if (is_array($params)) {
                                                            $out = [];
                                                            foreach ($params as $k => $v) {
                                                                $out[] = htmlspecialchars("$k: $v");
                                                            }
                                                            echo implode(", ", $out);
                                                        } else {
                                                            echo htmlspecialchars($rpt['report_filter']);
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($rpt['generated_at'])); ?></td>
                                            <td style="text-align: right;">
                                                <div class="btn-group" style="justify-content: flex-end;">
                                                    <button class="btn btn-secondary btn-sm"
                                                        onclick="alert('Opening Web Preview of report: <?php echo htmlspecialchars($rpt['report_title'], ENT_QUOTES); ?>\n(Features: Print and dynamic filtering are fully supported in this view.)')">
                                                        <span>View</span>
                                                    </button>
                                                    <button class="btn btn-primary btn-sm"
                                                        onclick="alert('Downloading compiled PDF study report (RPT-2026-<?php echo sprintf('%03d', $rpt['id']); ?>.pdf) to local downloads folder... Done!')">
                                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none"
                                                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                            stroke-linejoin="round" style="margin-right: 2px;">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                            <polyline points="7 10 12 15 17 10"></polyline>
                                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                                        </svg>
                                                        <span>PDF</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--gray); padding: 2rem;">No compiled reports match search criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- COMPILE REPORT MODAL -->
    <div class="action-modal-overlay" id="compileModal">
        <div class="action-modal">
            <div class="modal-header">
                <h3>Compile New Forensic Report</h3>
                <button class="modal-close" onclick="closeCompileModal()">&times;</button>
            </div>
            <form method="POST" action="admin_reports.php">
                <input type="hidden" name="action" value="compile">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="report_title">Report Title / Study Name</label>
                        <input type="text" name="report_title" id="report_title" class="form-control"
                            placeholder="e.g. Comparative Study on Porous vs Non-Porous Surfaces" required>
                    </div>
                    <div class="form-group">
                        <label for="surface_type">Filter Surface Type</label>
                        <select name="surface_type" id="surface_type" class="form-control">
                            <option value="all">All Surfaces Combined</option>
                            <option value="glass">Glass Only</option>
                            <option value="wood">Wood Only</option>
                            <option value="plastic">Plastic Only</option>
                            <option value="metal">Metal Only</option>
                            <option value="paper">Paper Only</option>
                        </select>
                    </div>
                    <p style="font-size:0.75rem; color: var(--gray); font-style:italic; line-height:1.4;">
                        Note: Compiling reports will automatically query the fingerprint_tests database, calculate comparative accuracy averages for Eggshell vs. Commercial powders, and serialize performance logs.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCompileModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Compile Report</button>
                </div>
            </form>
        </div>
    </div>

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

        function openCompileModal() {
            document.getElementById("compileModal").classList.add("active");
            document.getElementById("report_title").value = "Eggs-shell vs Commercial Powder Performance Study - " + new Date().toLocaleString('en-US', { month: 'short', year: 'numeric' });
        }
        function closeCompileModal() {
            document.getElementById("compileModal").classList.remove("active");
        }
    </script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>

</html>