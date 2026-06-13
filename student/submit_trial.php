<?php
// student/submit_trial.php — Submit Fingerprint Trial Data
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'submit_trial';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

$msg = $msg_type = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Submit Fingerprint Trial Data — Green Forensics">
    <title>Submit Trial Data — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
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
                <div class="header-title"><h2>Submit Trial Data</h2></div>
            </div>
            <div class="header-right">
                <div class="header-role-chip">Criminology Student</div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Submit Trial Data</h1>
                    <p>Record your fingerprint powder application trial results for faculty review.</p>
                </div>
                <a href="student_records.php" class="btn btn-secondary">View My Records</a>
            </div>

            <div id="alertContainer"></div>

            <div class="dashboard-card" style="max-width:720px;">
                <div class="card-title-wrap">
                    <h3>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="11" x2="12" y2="17"/>
                            <line x1="9" y1="14" x2="15" y2="14"/>
                        </svg>
                        New Trial Entry
                    </h3>
                </div>

                <form id="form-submit-trial">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="powder_type">Powder Type <span style="color:var(--danger)">*</span></label>
                            <select name="powder_type" id="powder_type" class="form-control" required>
                                <option value="">— Select Powder —</option>
                                <option value="eggshell">Eggshell Powder</option>
                                <option value="commercial">Commercial Powder</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="surface_type">Surface Type <span style="color:var(--danger)">*</span></label>
                            <select name="surface_type" id="surface_type" class="form-control" required>
                                <option value="">— Select Surface —</option>
                                <option value="glass">Glass</option>
                                <option value="plastic">Plastic</option>
                                <option value="metal">Metal</option>
                                <option value="paper">Paper</option>
                                <option value="wood">Wood</option>
                                <option value="ceramic">Ceramic</option>
                                <option value="fabric">Fabric</option>
                            </select>
                        </div>
                    </div>

                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fingerprint_image').click()" style="margin-bottom: 1.25rem;">
                        <div class="upload-zone-icon">
                            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="16 16 12 12 8 16"/>
                                <line x1="12" y1="12" x2="12" y2="21"/>
                                <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                            </svg>
                        </div>
                        <h4>Click to browse or drag &amp; drop fingerprint image <span style="color:var(--danger)">*</span></h4>
                        <p>Supports JPG, PNG, WebP — max 5 MB</p>
                        <p id="file-chosen" style="margin-top:.5rem;font-weight:600;color:var(--medium-green);"></p>
                    </div>
                    <input type="file" name="fingerprint_image" id="fingerprint_image"
                           accept="image/jpeg,image/png,image/webp" style="display:none;" required>

                    <div class="form-group">
                        <label for="image_label">Image Label / Description</label>
                        <input type="text" name="image_label" id="image_label" class="form-control"
                               placeholder="e.g. Eggshell on Glass — Trial 3">
                    </div>

                    <div class="form-group">
                        <label for="notes">Observations / Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4"
                                  placeholder="Describe your observations, conditions, or any relevant notes..."></textarea>
                    </div>

                    <div style="display:flex;gap:.75rem;margin-top:.5rem;">
                        <button type="submit" class="btn btn-primary" id="btn-submit-trial">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span id="btnText">Submit Trial</span>
                        </button>
                        <button type="reset" class="btn btn-secondary" id="btn-reset-trial">Clear Form</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once '_sidebar_js.php'; ?>
<script>
const inp = document.getElementById('fingerprint_image');
const chosen = document.getElementById('file-chosen');
inp.addEventListener('change', () => {
    chosen.textContent = inp.files[0] ? inp.files[0].name : '';
});
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
        inp.files = e.dataTransfer.files;
        chosen.textContent = inp.files[0].name;
    }
});

document.getElementById('btn-reset-trial').addEventListener('click', () => {
    chosen.textContent = '';
});

const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let isSubmitting = false;

function showNotification(type, message) {
    const container = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    container.innerHTML = `<div class="alert-msg ${alertClass}">${message}</div>`;
    setTimeout(() => {
        container.innerHTML = '';
    }, 6000);
}

document.getElementById('form-submit-trial').addEventListener('submit', function(e) {
    e.preventDefault();
    if (isSubmitting) return;

    if (!inp.files.length) {
        showNotification('error', 'Please select a fingerprint image file to upload.');
        return;
    }

    const btn = document.getElementById('btn-submit-trial');
    const btnText = document.getElementById('btnText');
    const originalText = btnText.textContent;
    
    btnText.textContent = 'Submitting...';
    btn.disabled = true;
    isSubmitting = true;

    const formData = new FormData(this);
    formData.append('csrf_token', csrfToken);

    fetch('ajax_submit_trial.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(res => res.json())
    .then(data => {
        isSubmitting = false;
        btnText.textContent = originalText;
        btn.disabled = false;
        
        if (data.success) {
            showNotification('success', data.message);
            document.getElementById('form-submit-trial').reset();
            chosen.textContent = '';
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(err => {
        isSubmitting = false;
        btnText.textContent = originalText;
        btn.disabled = false;
        showNotification('error', 'An error occurred during submission. Please try again.');
    });
});
</script>
</body>
</html>
