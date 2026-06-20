<?php
// admin/safety_climate_logs.php - Super Administrator Safety & Climate Log Monitoring
require_once "../config.php";
require_once "auth.php";

// Enforce admin authentication
check_admin_auth();

$admin_name = $_SESSION['user_name'] ?? 'Super Admin';

$logs = [];
try {
    $stmt = $pdo->query("
        SELECT scl.*, u.full_name AS student_name, ft.trial_id AS trial_code,
               fac.full_name AS validator_name,
               DATE_FORMAT(scl.created_at, '%M %d, %Y %H:%i') AS formatted_date
        FROM safety_climate_log scl
        JOIN users u ON u.id = scl.student_id
        LEFT JOIN fingerprint_tests ft ON ft.id = scl.trial_id
        LEFT JOIN users fac ON ft.validated_by = fac.id
        ORDER BY scl.created_at DESC
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safety &amp; Climate Logs - Green Forensics</title>
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
    <?php include "sidebar.php"; ?>

    <!-- MAIN -->
    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="sidebarCollapse"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="header-title"><h2>Green Forensics — Safety &amp; Climate Logs</h2></div>
            </div>
        </header>

        <div class="admin-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Safety &amp; Climate Logs</h1>
                    <p>Global system monitoring of laboratory environmental conditions and safety incidents.</p>
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
                <div class="card-title-wrap"><h3>Global Safety Records (<span id="scl-count"><?= count($logs) ?></span> entries)</h3></div>
                <div class="table-responsive">
                    <table class="custom-table" id="scl-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Faculty Reviewer</th>
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
                            <tr class="no-data-row"><td colspan="11" style="text-align:center;padding:2rem;color:#6c757d;">No safety logs recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($log['student_name']) ?></strong></td>
                                <td><?= htmlspecialchars($log['validator_name'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($log['trial_code'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($log['formatted_date']) ?></td>
                                <td><span class="temp-pill"><?= $log['temperature'] !== null ? htmlspecialchars($log['temperature']) . '°C' : '—' ?></span></td>
                                <td><span class="humid-pill"><?= $log['humidity'] !== null ? htmlspecialchars($log['humidity']) . '%' : '—' ?></span></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($log['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($log['surface_type']) ?></td>
                                <td style="max-width:160px;font-size:.82rem;"><?= htmlspecialchars($log['health_feedback'] ?: '—') ?></td>
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
        tbody.innerHTML = '<tr class="no-data-row"><td colspan="11" style="text-align:center;padding:2rem;color:#6c757d;">No safety logs recorded yet.</td></tr>';
        return;
    }

    tbody.innerHTML = logs.map(log => {
        const trialCode = log.trial_code ? escapeHtml(log.trial_code) : 'N/A';
        const validatorName = log.validator_name ? escapeHtml(log.validator_name) : '—';
        const tempDisp = log.temperature !== null ? escapeHtml(log.temperature) + '°C' : '—';
        const humidDisp = log.humidity !== null ? escapeHtml(log.humidity) + '%' : '—';
        const capIrritation = log.irritation_status.charAt(0).toUpperCase() + log.irritation_status.slice(1);
        
        return `
            <tr>
                <td><strong>${escapeHtml(log.student_name)}</strong></td>
                <td>${validatorName}</td>
                <td>${trialCode}</td>
                <td>${escapeHtml(log.formatted_date)}</td>
                <td><span class="temp-pill">${tempDisp}</span></td>
                <td><span class="humid-pill">${humidDisp}</span></td>
                <td style="text-transform:capitalize;">${escapeHtml(log.powder_type)}</td>
                <td style="text-transform:capitalize;">${escapeHtml(log.surface_type)}</td>
                <td style="max-width:160px;font-size:.82rem;">${escapeHtml(log.health_feedback)}</td>
                <td><span class="badge-${escapeHtml(log.irritation_status)}">${capIrritation}</span></td>
                <td style="max-width:200px;font-size:.82rem;color:#6c757d;">${escapeHtml(log.remarks)}</td>
            </tr>
        `;
    }).join('');
}
</script>
<?php include '../includes/support_chat_widget.php'; ?>
</body>
</html>
