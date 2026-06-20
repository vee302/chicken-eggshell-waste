<?php
// student/safety_climate_log.php — Submit & View Safety and Climate Logs
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'safety_climate_log';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

// Fetch their submitted fingerprint trials
$my_trials = [];
try {
    $stmt = $pdo->prepare("SELECT id, trial_id, powder_type, surface_type FROM fingerprint_tests WHERE student_id = ? ORDER BY submitted_at DESC");
    $stmt->execute([$student_id]);
    $my_trials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch initial safety logs
$logs = [];
try {
    $stmt = $pdo->prepare("
        SELECT scl.*, DATE_FORMAT(scl.created_at, '%M %d, %Y %H:%i') as formatted_date, ft.trial_id as trial_code
        FROM safety_climate_log scl
        LEFT JOIN fingerprint_tests ft ON ft.id = scl.trial_id
        WHERE scl.student_id = ?
        ORDER BY scl.created_at DESC
    ");
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
        
        .badge-none { background: rgba(82, 183, 136, 0.15); color: #2d6a4f; border: 1px solid rgba(82, 183, 136, 0.25); padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; display: inline-block; }
        .badge-mild { background: rgba(244, 162, 97, 0.15); color: #c97d2a; border: 1px solid rgba(244, 162, 97, 0.25); padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; display: inline-block; }
        .badge-moderate { background: rgba(231, 111, 81, 0.15); color: #e76f51; border: 1px solid rgba(231, 111, 81, 0.25); padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; display: inline-block; }
        .badge-severe { background: rgba(230, 57, 70, 0.15); color: #e63946; border: 1px solid rgba(230, 57, 70, 0.25); padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; display: inline-block; }
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
                    <p>Record laboratory conditions, safety parameters, and physiological health feedback for testing sessions.</p>
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
                        New Safety Log Entry
                    </h3>
                </div>
                <form id="form-safety-log">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="trial_id">Related Fingerprint Trial (Optional)</label>
                            <select name="trial_id" id="trial_id" class="form-control">
                                <option value="none">No Related Trial / General testing</option>
                                <?php foreach ($my_trials as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['trial_id'] ?: 'TR-'.str_pad($t['id'], 4, '0', STR_PAD_LEFT)) ?> (<?= ucfirst($t['powder_type']) ?> on <?= ucfirst($t['surface_type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="powder_type">Powder Type Used *</label>
                            <select name="powder_type" id="powder_type" class="form-control" required>
                                <option value="">— Select Powder —</option>
                                <option value="eggshell">Eggshell-Based Powder</option>
                                <option value="commercial">Commercial Powder</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="surface_type">Surface Material Type *</label>
                            <select name="surface_type" id="surface_type" class="form-control" required>
                                <option value="">— Select Surface —</option>
                                <option value="glass">Glass</option>
                                <option value="paper">Paper</option>
                                <option value="wood">Wood</option>
                                <option value="plastic">Plastic</option>
                                <option value="metal">Metal</option>
                                <option value="ceramic">Ceramic</option>
                                <option value="fabric">Fabric</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="irritation_status">Irritation Status / Incident Category *</label>
                            <select name="irritation_status" id="irritation_status" class="form-control" required>
                                <option value="none">None (Safe condition)</option>
                                <option value="mild">Mild irritation</option>
                                <option value="moderate">Moderate irritation</option>
                                <option value="severe">Severe irritation</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="temperature">Ambient Temperature (°C)</label>
                            <input type="number" name="temperature" id="temperature" class="form-control"
                                   step="0.1" placeholder="e.g. 25.0">
                        </div>
                        <div class="form-group">
                            <label for="humidity">Relative Humidity (%)</label>
                            <input type="number" name="humidity" id="humidity" class="form-control"
                                   step="0.1" min="0" max="100" placeholder="e.g. 60.0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="health_feedback">Physiological / Health Feedback</label>
                        <input type="text" name="health_feedback" id="health_feedback" class="form-control"
                               placeholder="e.g. Coughing, watery eyes, safe, no symptoms" maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="remarks">General Remarks / Safety Observation Notes</label>
                        <textarea name="remarks" id="remarks" class="form-control" rows="3"
                                  placeholder="Provide general observations, ventilation status, or precautions taken..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btn-save-log">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Save Safety Log
                    </button>
                </form>
            </div>

            <!-- Log History -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>Log History</h3>
                    <span style="font-size:.82rem;color:var(--gray);" id="log-count"><?= count($logs) ?> entr<?= count($logs) !== 1 ? 'ies' : 'y' ?></span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table" id="safety-log-table">
                        <thead>
                            <tr>
                                <th>Date &amp; Time</th>
                                <th>Trial ID</th>
                                <th>Powder</th>
                                <th>Surface</th>
                                <th>Temp (°C)</th>
                                <th>Humidity (%)</th>
                                <th>Health Feedback</th>
                                <th>Irritation</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr class="no-data-row"><td colspan="9" style="text-align:center;color:#6c757d;padding:2rem;">No safety and climate logs submitted yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['formatted_date']) ?></td>
                                <td><strong><?= htmlspecialchars($log['trial_code'] ?: 'N/A') ?></strong></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($log['powder_type']) ?></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($log['surface_type']) ?></td>
                                <td><span class="temp-pill"><?= $log['temperature'] !== null ? htmlspecialchars($log['temperature']) . '°C' : '—' ?></span></td>
                                <td><span class="humid-pill"><?= $log['humidity'] !== null ? htmlspecialchars($log['humidity']) . '%' : '—' ?></span></td>
                                <td style="max-width:180px;font-size:0.8rem;"><?= htmlspecialchars($log['health_feedback'] ?: '—') ?></td>
                                <td><span class="badge-<?= htmlspecialchars($log['irritation_status']) ?>"><?= ucfirst(htmlspecialchars($log['irritation_status'])) ?></span></td>
                                <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:0.8rem;color:#64748b;"><?= htmlspecialchars($log['remarks'] ?: '—') ?></td>
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
const myTrialsData = <?php echo json_encode($my_trials); ?>;
let isSubmitting = false;

// Auto-fill powder and surface types when selecting a trial
document.getElementById('trial_id').addEventListener('change', function() {
    const selectedId = this.value;
    const powderSelect = document.getElementById('powder_type');
    const surfaceSelect = document.getElementById('surface_type');
    
    if (selectedId && selectedId !== 'none') {
        const found = myTrialsData.find(t => t.id == selectedId);
        if (found) {
            powderSelect.value = found.powder_type;
            surfaceSelect.value = found.surface_type;
        }
    } else {
        powderSelect.value = "";
        surfaceSelect.value = "";
    }
});

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
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
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

    fetch('ajax_submit_safety_climate_log.php', {
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
    const tbody = document.querySelector('#safety-log-table tbody');
    const noLogsRow = tbody.querySelector('.no-data-row');
    if (noLogsRow) {
        noLogsRow.remove();
    }

    const tr = document.createElement('tr');
    
    const trialCode = data.trial_code ? escapeHtml(data.trial_code) : 'N/A';
    const tempDisp = data.temperature !== null ? escapeHtml(data.temperature) + '°C' : '—';
    const humidDisp = data.humidity !== null ? escapeHtml(data.humidity) + '%' : '—';
    const capIrritation = data.irritation_status.charAt(0).toUpperCase() + data.irritation_status.slice(1);

    tr.innerHTML = `
        <td>${escapeHtml(data.formatted_date)}</td>
        <td><strong>${trialCode}</strong></td>
        <td style="text-transform:capitalize;">${escapeHtml(data.powder_type)}</td>
        <td style="text-transform:capitalize;">${escapeHtml(data.surface_type)}</td>
        <td><span class="temp-pill">${tempDisp}</span></td>
        <td><span class="humid-pill">${humidDisp}</span></td>
        <td style="max-width:180px;font-size:0.8rem;">${escapeHtml(data.health_feedback)}</td>
        <td><span class="badge-${escapeHtml(data.irritation_status)}">${capIrritation}</span></td>
        <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:0.8rem;color:#64748b;">${escapeHtml(data.remarks)}</td>
    `;
    
    tbody.insertBefore(tr, tbody.firstChild);

    // Update count span
    const countSpan = document.getElementById('log-count');
    if (countSpan) {
        const count = tbody.querySelectorAll('tr').length;
        countSpan.textContent = `${count} entr${count !== 1 ? 'ies' : 'y'}`;
    }
}
</script>
<?php include '../includes/support_chat_widget.php'; ?>
</body>
</html>
