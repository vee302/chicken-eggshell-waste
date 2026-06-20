<?php
// faculty/safety_climate_log.php — Faculty Safety & Climate Log Monitoring
require_once '../config.php';
require_once 'auth.php';
check_faculty_auth();

$faculty_name = $_SESSION['user_name'] ?? 'Faculty Researcher';
$faculty_id   = $_SESSION['user_id']  ?? 0;

$logs = [];
try {
    $where_clauses = ["1=1"];
    $params = [];

    // Check if assigned_faculty_id exists in fingerprint_tests
    $check_cols = $pdo->query("SHOW COLUMNS FROM `fingerprint_tests` LIKE 'assigned_faculty_id'")->fetch();
    if ($check_cols) {
        $where_clauses[] = "(scl.trial_id IS NOT NULL AND ft.assigned_faculty_id = :faculty_id)";
        $params[':faculty_id'] = $faculty_id;
    }

    $sql = "
        SELECT scl.*, u.full_name AS student_name, ft.trial_id AS trial_code,
               DATE_FORMAT(scl.created_at, '%M %d, %Y %H:%i') AS formatted_date
        FROM safety_climate_log scl
        JOIN users u ON u.id = scl.student_id
        LEFT JOIN fingerprint_tests ft ON ft.id = scl.trial_id
        WHERE " . implode(" AND ", $where_clauses) . "
        ORDER BY scl.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safety &amp; Climate Log - Green Forensics</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .temp-pill{background:rgba(116,198,157,.15);color:#1b4332;padding:2px 10px;border-radius:20px;font-size:.8rem;font-weight:600;}
        .humid-pill{background:rgba(45,106,79,.1);color:#2d6a4f;padding:2px 10px;border-radius:20px;font-size:.8rem;font-weight:600;}
        
        .badge-none { background: rgba(82, 183, 136, 0.15); color: #2d6a4f; border: 1px solid rgba(82, 183, 136, 0.25); padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; display: inline-block; }
        .badge-mild { background: rgba(244, 162, 97, 0.15); color: #c97d2a; border: 1px solid rgba(244, 162, 97, 0.25); padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; display: inline-block; }
        .badge-moderate { background: rgba(231, 111, 81, 0.15); color: #e76f51; border: 1px solid rgba(231, 111, 81, 0.25); padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; display: inline-block; }
        .badge-severe { background: rgba(230, 57, 70, 0.15); color: #e63946; border: 1px solid rgba(230, 57, 70, 0.25); padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; display: inline-block; }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="brand-text"><span>GREEN</span><span class="brand-accent">FORENSICS</span></div></div>
        <div class="sidebar-user">
            <div class="user-info">
                <div class="user-avatar">FR</div>
                <div class="user-details"><h4><?= htmlspecialchars($faculty_name) ?></h4><span>Faculty Researcher</span></div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="faculty_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="comparison_dashboard.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Comparison Dashboard</span></a></li>
            <li class="menu-item"><a href="validate_accuracy.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Validate Accuracy Scores</span></a></li>
            <li class="menu-item"><a href="surface_performance.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg><span>Surface Performance</span></a></li>
            <li class="menu-item active"><a href="safety_climate_log.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Safety &amp; Climate Log</span></a></li>
            <li class="menu-item"><a href="student_records.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>View Student Records</span></a></li>
            <li class="menu-item"><a href="generate_reports.php" class="menu-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Generate Reports</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../logout.php" class="menu-link" style="color:#e07a5f;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="header-title"><h2>Green Forensics — Safety &amp; Climate Log</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Safety &amp; Climate Log</h1>
                    <p>Monitor health compliance, safety checklists, and ambient environmental logs recorded during fingerprint testing.</p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="dashboard-card" style="margin-bottom:1.5rem; padding:1.25rem;">
                <div class="search-filter-bar">
                    <div class="bar-left" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                        <input type="text" id="filter-search" class="form-control-inline" placeholder="Search student name..." oninput="triggerFilter()" style="min-width:180px;">
                        
                        <select id="filter-powder" class="form-control-inline" onchange="triggerFilter()">
                            <option value="">All Powder Types</option>
                            <option value="eggshell">Eggshell-Based Powder</option>
                            <option value="commercial">Commercial Powder</option>
                        </select>

                        <select id="filter-surface" class="form-control-inline" onchange="triggerFilter()">
                            <option value="">All Surfaces</option>
                            <option value="glass">Glass</option>
                            <option value="paper">Paper</option>
                            <option value="wood">Wood</option>
                            <option value="plastic">Plastic</option>
                            <option value="metal">Metal</option>
                            <option value="ceramic">Ceramic</option>
                            <option value="fabric">Fabric</option>
                        </select>

                        <select id="filter-irritation" class="form-control-inline" onchange="triggerFilter()">
                            <option value="">All Irritation Statuses</option>
                            <option value="none">None</option>
                            <option value="mild">Mild</option>
                            <option value="moderate">Moderate</option>
                            <option value="severe">Severe</option>
                        </select>

                        <input type="date" id="filter-date" class="form-control-inline" onchange="triggerFilter()">
                        
                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetFilters()">Reset</button>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="dashboard-card">
                <div class="card-title-wrap"><h3>Safety Records (<span id="scl-count"><?= count($logs) ?></span> entries)</h3></div>
                <div class="table-responsive">
                    <table class="custom-table" id="scl-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Trial ID</th>
                                <th>Date &amp; Time</th>
                                <th>Temp (°C)</th>
                                <th>Humidity (%)</th>
                                <th>Powder Type</th>
                                <th>Surface Type</th>
                                <th>Health Feedback</th>
                                <th>Irritation Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr class="no-data-row"><td colspan="10" style="text-align:center;padding:2rem;color:#6c757d;">No safety logs recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($log['student_name']) ?></strong></td>
                                <td><?= htmlspecialchars($log['trial_code'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($log['formatted_date']) ?></td>
                                <td><span class="temp-pill"><?= $log['temperature'] !== null ? htmlspecialchars($log['temperature']) . '°C' : '—' ?></span></td>
                                <td><span class="humid-pill"><?= $log['humidity'] !== null ? htmlspecialchars($log['humidity']) . '%' : '—' ?></span></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($log['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($log['surface_type']) ?></td>
                                <td style="max-width:180px;font-size:.82rem;"><?= htmlspecialchars($log['health_feedback'] ?: '—') ?></td>
                                <td><span class="badge-<?= htmlspecialchars($log['irritation_status']) ?>"><?= ucfirst(htmlspecialchars($log['irritation_status'])) ?></span></td>
                                <td style="max-width:200px;font-size:.82rem;color:#6c757d;"><?= htmlspecialchars($log['remarks'] ?: '—') ?></td>
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

let filterTimeout = null;
function triggerFilter() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        const search = document.getElementById('filter-search').value.trim();
        const powder = document.getElementById('filter-powder').value;
        const surface = document.getElementById('filter-surface').value;
        const irritation = document.getElementById('filter-irritation').value;
        const logDate = document.getElementById('filter-date').value;

        const params = new URLSearchParams({
            search: search,
            powder: powder,
            surface: surface,
            irritation: irritation,
            date: logDate
        });

        fetch('ajax_get_safety_climate_logs.php?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderSafetyLogs(data.logs);
                }
            })
            .catch(err => console.error("Error filtering safety logs:", err));
    }, 300);
}

function resetFilters() {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-powder').value = '';
    document.getElementById('filter-surface').value = '';
    document.getElementById('filter-irritation').value = '';
    document.getElementById('filter-date').value = '';
    triggerFilter();
}

function escapeHtml(text) {
    if (!text) return '—';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function renderSafetyLogs(logs) {
    const tbody = document.querySelector('#scl-table tbody');
    const countSpan = document.getElementById('scl-count');
    
    countSpan.textContent = logs.length;

    if (logs.length === 0) {
        tbody.innerHTML = '<tr class="no-data-row"><td colspan="10" style="text-align:center;padding:2rem;color:#6c757d;">No safety logs recorded yet.</td></tr>';
        return;
    }

    tbody.innerHTML = logs.map(log => {
        const trialCode = log.trial_code ? escapeHtml(log.trial_code) : 'N/A';
        const tempDisp = log.temperature !== null ? escapeHtml(log.temperature) + '°C' : '—';
        const humidDisp = log.humidity !== null ? escapeHtml(log.humidity) + '%' : '—';
        const capIrritation = log.irritation_status.charAt(0).toUpperCase() + log.irritation_status.slice(1);
        
        return `
            <tr>
                <td><strong>${escapeHtml(log.student_name)}</strong></td>
                <td>${trialCode}</td>
                <td>${escapeHtml(log.formatted_date)}</td>
                <td><span class="temp-pill">${tempDisp}</span></td>
                <td><span class="humid-pill">${humidDisp}</span></td>
                <td style="text-transform:capitalize;">${escapeHtml(log.powder_type)}</td>
                <td style="text-transform:capitalize;">${escapeHtml(log.surface_type)}</td>
                <td style="max-width:180px;font-size:.82rem;">${escapeHtml(log.health_feedback)}</td>
                <td><span class="badge-${escapeHtml(log.irritation_status)}">${capIrritation}</span></td>
                <td style="max-width:200px;font-size:.82rem;color:#6c757d;">${escapeHtml(log.remarks)}</td>
            </tr>
        `;
    }).join('');
}
</script>
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
