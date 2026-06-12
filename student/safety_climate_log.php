<?php
// student/safety_climate_log.php — Submit & View Safety and Climate Logs
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'safety_climate_log';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

$msg = $msg_type = '';

// Fetch logs
$logs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM safety_logs WHERE student_id = ? ORDER BY logged_at DESC LIMIT 30");
    $stmt->execute([$student_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Safety and Climate Log — Green Forensics">
    <title>Safety &amp; Climate Log — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <style>
        .temp-pill  { background:rgba(116,198,157,.15); color:var(--dark-green); padding:2px 10px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .humid-pill { background:rgba(45,106,79,.1); color:var(--medium-green); padding:2px 10px; border-radius:20px; font-size:.8rem; font-weight:600; }
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
                <div class="header-title"><h2>Safety &amp; Climate Log</h2></div>
            </div>
            <div class="header-right"><div class="header-role-chip">Criminology Student</div></div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Safety &amp; Climate Log</h1>
                    <p>Record laboratory conditions and safety compliance for each session.</p>
                </div>
            </div>

            <div id="alertContainer"></div>

            <!-- Log Form -->
            <div class="dashboard-card" style="max-width:720px;">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        New Log Entry
                    </h3>
                </div>
                <form id="form-safety-log">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="temperature">Temperature (°C)</label>
                            <input type="number" name="temperature" id="temperature" class="form-control"
                                   step="0.1" placeholder="e.g. 24.5">
                        </div>
                        <div class="form-group">
                            <label for="humidity">Humidity (%)</label>
                            <input type="number" name="humidity" id="humidity" class="form-control"
                                   step="0.1" min="0" max="100" placeholder="e.g. 65.0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ppe_worn">PPE Equipment Worn</label>
                        <input type="text" name="ppe_worn" id="ppe_worn" class="form-control"
                               placeholder="e.g. Gloves, Mask, Lab Coat, Goggles">
                    </div>

                    <div class="form-group">
                        <label for="conditions">General Lab Conditions</label>
                        <select name="conditions" id="conditions" class="form-control">
                            <option value="">— Select Condition —</option>
                            <option value="Optimal">Optimal</option>
                            <option value="Acceptable">Acceptable</option>
                            <option value="Suboptimal">Suboptimal</option>
                            <option value="Hazardous">Hazardous</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"
                                  placeholder="Any additional observations or safety concerns..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btn-save-log">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Save Log
                    </button>
                </form>
            </div>

            <!-- Log History -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>Log History</h3>
                    <span style="font-size:.82rem;color:var(--gray);"><?= count($logs) ?> entr<?= count($logs) !== 1 ? 'ies' : 'y' ?></span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Date &amp; Time</th>
                                <th>Temperature</th>
                                <th>Humidity</th>
                                <th>PPE Worn</th>
                                <th>Lab Conditions</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" style="text-align:center;color:#6c757d;padding:2rem;">No logs recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($log['logged_at'])) ?></td>
                                <td><span class="temp-pill"><?= $log['temperature'] ?>°C</span></td>
                                <td><span class="humid-pill"><?= $log['humidity'] ?>%</span></td>
                                <td><?= htmlspecialchars($log['ppe_worn'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($log['lab_conditions'] ?: '—') ?></td>
                                <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($log['notes'] ?: '—') ?></td>
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
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let isSubmitting = false;

function showNotification(type, message) {
    const container = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    container.innerHTML = `<div class="alert-msg ${alertClass}">${message}</div>`;
    setTimeout(() => {
        container.innerHTML = '';
    }, 5000);
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
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

document.getElementById('form-safety-log').addEventListener('submit', function(e) {
    e.preventDefault();
    if (isSubmitting) return;

    const btn = document.getElementById('btn-save-log');
    const originalText = btn.innerHTML;
    
    btn.textContent = 'Saving...';
    btn.disabled = true;
    isSubmitting = true;

    const formData = new FormData(this);
    formData.append('csrf_token', csrfToken);

    fetch('ajax_submit_climate_log.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(res => res.json())
    .then(data => {
        isSubmitting = false;
        btn.innerHTML = originalText;
        btn.disabled = false;

        if (data.success) {
            showNotification('success', data.message);
            appendLogHistoryRow(data.data);
            document.getElementById('form-safety-log').reset();
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(err => {
        isSubmitting = false;
        btn.innerHTML = originalText;
        btn.disabled = false;
        showNotification('error', 'An error occurred. Please try again.');
    });
});

function appendLogHistoryRow(data) {
    const tbody = document.querySelector('.custom-table tbody');
    const noLogsRow = tbody.querySelector('tr td[colspan="6"]');
    if (noLogsRow) {
        noLogsRow.parentElement.remove();
    }

    const tr = document.createElement('tr');
    
    const loggedAtStr = new Date(data.logged_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + 
                       new Date(data.logged_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });

    tr.innerHTML = `
        <td>${loggedAtStr}</td>
        <td><span class="temp-pill">${data.temperature}°C</span></td>
        <td><span class="humid-pill">${data.humidity}%</span></td>
        <td>${escapeHtml(data.ppe_worn)}</td>
        <td>${escapeHtml(data.lab_conditions)}</td>
        <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(data.notes)}</td>
    `;
    
    tbody.insertBefore(tr, tbody.firstChild);

    // Update count span
    const countSpan = document.querySelector('.card-title-wrap span');
    if (countSpan) {
        const count = tbody.querySelectorAll('tr').length;
        countSpan.textContent = `${count} entr${count !== 1 ? 'ies' : 'y'}`;
    }
}
</script>
</body>
</html>
