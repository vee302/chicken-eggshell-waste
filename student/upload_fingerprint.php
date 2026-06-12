<?php
// student/upload_fingerprint.php — Upload Fingerprint Images
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'upload_fingerprint';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fingerprint_image'])) {
    $file         = $_FILES['fingerprint_image'];
    $powder_type  = trim($_POST['powder_type'] ?? '');
    $surface_type = trim($_POST['surface_type'] ?? '');
    $label        = trim($_POST['image_label'] ?? '');
    
    $allowed_exts  = ['jpg', 'jpeg', 'png', 'webp'];
    $max_bytes     = 5 * 1024 * 1024; // 5 MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!$powder_type || !$surface_type) {
        $msg = 'Powder Type and Surface Type are required.'; $msg_type = 'error';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload error. Please try again.'; $msg_type = 'error';
    } elseif (!in_array($ext, $allowed_exts)) {
        $msg = 'Only JPG, JPEG, PNG and WebP images are allowed.'; $msg_type = 'error';
    } elseif ($file['size'] > $max_bytes) {
        $msg = 'File size must not exceed 5 MB.'; $msg_type = 'error';
    } else {
        $filename = 'fp_' . $student_id . '_' . time() . '.' . $ext;
        $dest_dir = '../uploads/fingerprints/';
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0777, true);
        }
        $dest = $dest_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            try {
                // Generate a unique trial_id
                $stmt = $pdo->query("SELECT MAX(id) FROM fingerprint_tests");
                $max_id = $stmt->fetchColumn() ?: 0;
                $next_id = $max_id + 1;
                $trial_id = 'TR-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

                // Insert trial record
                $stmt = $pdo->prepare("
                    INSERT INTO fingerprint_tests 
                        (trial_id, student_id, image_path, image_label, powder_type, surface_type, 
                         ridge_clarity_score, visibility_score, adhesion_score, accuracy_score, status, submitted_at)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, 'pending_validation', NOW())
                ");
                $stmt->execute([$trial_id, $student_id, $filename, $label, $powder_type, $surface_type]);

                $msg = 'Fingerprint image uploaded successfully and submitted for faculty validation.'; $msg_type = 'success';
            } catch (PDOException $e) { 
                $msg = 'Database error. Failed to save record.'; $msg_type = 'error';
            }
        } else {
            $msg = 'Failed to save image. Check uploads directory permissions.'; $msg_type = 'error';
        }
    }
}

// Fetch my uploaded images
$images = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, trial_id, image_path, image_label, status, submitted_at, powder_type, surface_type 
        FROM fingerprint_tests 
        WHERE student_id = ? AND image_path IS NOT NULL AND image_path != '' 
        ORDER BY submitted_at DESC LIMIT 20
    ");
    $stmt->execute([$student_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Upload Fingerprint Images — Green Forensics">
    <title>Upload Fingerprint Images — Green Forensics</title>
    <link rel="stylesheet" href="../css/student_style.css?v=1.0">
    <style>
        .image-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .image-thumb { background: var(--cream); border-radius: 10px; overflow: hidden; border: 1px solid var(--light-gray); }
        .image-thumb img { width: 100%; height: 130px; object-fit: cover; display: block; }
        .image-thumb-info { padding: .6rem .75rem; font-size: .75rem; }
        .image-thumb-label { font-weight: 600; color: var(--dark-green); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .image-thumb-date  { color: var(--gray); }
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
                <div class="header-title"><h2>Upload Fingerprint Images</h2></div>
            </div>
            <div class="header-right">
                <div class="header-role-chip">Criminology Student</div>
            </div>
        </header>

        <div class="student-content">
            <div class="page-header-wrap">
                <div class="page-title">
                    <h1>Upload Fingerprint Images</h1>
                    <p>Upload high-resolution fingerprint images from your laboratory trials.</p>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert-msg alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Upload Form -->
            <div class="dashboard-card" style="max-width:680px;">
                <div class="card-title-wrap">
                    <h3>Upload New Image</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" id="form-upload-fingerprint">
                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fingerprint_image').click()">
                        <div class="upload-zone-icon">
                            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="16 16 12 12 8 16"/>
                                <line x1="12" y1="12" x2="12" y2="21"/>
                                <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                            </svg>
                        </div>
                        <h4>Click to browse or drag &amp; drop</h4>
                        <p>Supports JPG, PNG, WebP — max 5 MB</p>
                        <p id="file-chosen" style="margin-top:.5rem;font-weight:600;color:var(--medium-green);"></p>
                    </div>
                    <input type="file" name="fingerprint_image" id="fingerprint_image"
                           accept="image/jpeg,image/png,image/webp" style="display:none;" required>

                    <div class="form-grid-2" style="margin-top: 1.25rem;">
                        <div class="form-group">
                            <label for="powder_type">Powder Type <span style="color:var(--danger)">*</span></label>
                            <select name="powder_type" id="powder_type" class="form-control" required>
                                <option value="">— Select Powder —</option>
                                <option value="eggshell">Eggshell-Based Powder</option>
                                <option value="commercial">Commercial Powder</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="surface_type">Surface Type <span style="color:var(--danger)">*</span></label>
                            <select name="surface_type" id="surface_type" class="form-control" required>
                                <option value="">— Select Surface —</option>
                                <option value="glass">Glass</option>
                                <option value="paper">Paper</option>
                                <option value="wood">Wood</option>
                                <option value="plastic">Plastic</option>
                                <option value="metal">Metal</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:1.25rem;">
                        <label for="image_label">Image Label / Description</label>
                        <input type="text" name="image_label" id="image_label" class="form-control"
                               placeholder="e.g. Eggshell on Glass — Trial 3">
                    </div>

                    <button type="submit" class="btn btn-primary" id="btn-upload-image">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="16 16 12 12 8 16"/>
                            <line x1="12" y1="12" x2="12" y2="21"/>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                        </svg>
                        Upload Image
                    </button>
                </form>
            </div>

            <!-- Image Gallery -->
            <div class="dashboard-card">
                <div class="card-title-wrap">
                    <h3>My Uploaded Images</h3>
                    <span style="font-size:.82rem;color:var(--gray);"><?= count($images) ?> image<?= count($images) !== 1 ? 's' : '' ?></span>
                </div>
                <?php if (empty($images)): ?>
                    <p style="color:var(--gray);font-size:.88rem;text-align:center;padding:1.5rem 0;">No images uploaded yet.</p>
                <?php else: ?>
                    <div class="image-gallery">
                        <?php foreach ($images as $img): ?>
                        <div class="image-thumb">
                            <?php if ($img['image_path'] && file_exists('../uploads/fingerprints/' . $img['image_path'])): ?>
                                <img src="../uploads/fingerprints/<?= htmlspecialchars($img['image_path']) ?>" alt="Fingerprint image">
                            <?php else: ?>
                                <div style="height:130px; background:#f4f6f0; display:flex; align-items:center; justify-content:center; color:var(--danger); font-size:0.75rem; font-weight:600;">Image not found</div>
                            <?php endif; ?>
                            <div class="image-thumb-info">
                                <div class="image-thumb-label" style="font-size: 0.8rem; font-weight:700; color:var(--dark-green);"><?= htmlspecialchars($img['trial_id']) ?></div>
                                <div class="image-thumb-label" title="<?= htmlspecialchars($img['image_label'] ?: 'No Label') ?>"><?= htmlspecialchars($img['image_label'] ?: 'Untitled') ?></div>
                                <div style="font-size:0.7rem; color:var(--gray); text-transform:capitalize; margin-bottom: 2px;">
                                    <?= htmlspecialchars($img['powder_type']) ?> | <?= htmlspecialchars($img['surface_type']) ?>
                                </div>
                                <div class="image-thumb-date" style="font-size:0.68rem;"><?= date('M d, Y', strtotime($img['submitted_at'])) ?></div>
                                <div style="margin-top: 4px;">
                                    <span class="badge badge-<?= $img['status'] ?>" style="font-size: 0.65rem; padding: 2px 6px;">
                                        <?= $img['status'] === 'pending_validation' ? 'Pending Validation' : ($img['status'] === 'needs_revision' ? 'Needs Revision' : ucfirst($img['status'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>
<?php require_once '_sidebar_js.php'; ?>
<script>
const inp = document.getElementById('fingerprint_image');
inp.addEventListener('change', () => {
    const chosen = document.getElementById('file-chosen');
    chosen.textContent = inp.files[0] ? inp.files[0].name : '';
});
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
        inp.files = e.dataTransfer.files;
        document.getElementById('file-chosen').textContent = inp.files[0].name;
    }
});
</script>
</body>
</html>
