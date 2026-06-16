<?php
// student/upload_fingerprint.php — Upload Fingerprint Images
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$active_page  = 'upload_fingerprint';
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_id   = $_SESSION['user_id']  ?? 0;

$msg = $msg_type = '';

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
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
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

            <div id="alertContainer"></div>

            <!-- Upload Form -->
            <div class="dashboard-card" style="max-width:680px;">
                <div class="card-title-wrap">
                    <h3>Upload New Image</h3>
                </div>
                <form id="form-upload-fingerprint">
                    <!-- Webcam / Upload Container -->
                    <div class="upload-options-container" style="position: relative; margin-bottom: 1.25rem;">
                        <!-- Drag & Drop Zone -->
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

                        <!-- Webcam Interface Zone (Hidden initially) -->
                        <div id="webcamZone" style="display: none; border: 2px dashed var(--medium-green); border-radius: 12px; padding: 1.5rem; background: var(--cream); text-align: center; position: relative;">
                            <h4 style="margin-bottom: 1rem; color: var(--dark-green); display: flex; align-items: center; justify-content: center; gap: 8px; font-weight:700;">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--medium-green);"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                Live Webcam Feed
                            </h4>
                            
                            <!-- Video feed wrapper -->
                            <div style="position: relative; max-width: 400px; margin: 0 auto 1rem; border-radius: 8px; overflow: hidden; background: #000; border: 1px solid var(--light-gray); display: flex; align-items: center; justify-content: center; min-height: 250px;">
                                <video id="webcamVideo" autoplay playsinline style="width: 100%; max-height: 300px; display: block; object-fit: cover;"></video>
                                <canvas id="webcamCanvas" style="display: none;"></canvas>
                                <img id="webcamCapturePreview" style="display: none; width: 100%; max-height: 300px; object-fit: cover;" alt="Captured Photo">
                            </div>

                            <!-- Buttons -->
                            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                <button type="button" class="btn btn-primary" id="btnCapturePhoto" style="padding: 0.5rem 1rem; font-size: 0.85rem; font-weight:600;">Capture Photo</button>
                                <button type="button" class="btn btn-secondary" id="btnRetakePhoto" style="display: none; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight:600; background: #f59e0b; border-color:#f59e0b; color:#fff;">Retake</button>
                                <button type="button" class="btn btn-secondary" id="btnCancelWebcam" style="padding: 0.5rem 1rem; font-size: 0.85rem; font-weight:600; background: #dc2626; border-color: #dc2626; color: #fff;">Close Camera</button>
                            </div>
                        </div>
                    </div>

                    <!-- Toggle Button Row -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;" class="no-print">
                        <button type="button" class="btn btn-secondary" id="btnStartWebcam" style="font-size: 0.82rem; font-weight: 600; display: flex; align-items: center; gap: 6px; padding: 0.4rem 0.8rem; background: var(--cream); border: 1px solid var(--light-gray); color: var(--dark-green);">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                <circle cx="12" cy="13" r="4"/>
                            </svg>
                            Use Webcam
                        </button>
                        <span id="selected-source-label" style="font-size: 0.78rem; font-weight: 600; color: var(--gray);">Source: File Upload</span>
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
                        <span id="btnText">Upload Image</span>
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
                    <p id="noImagesText" style="color:var(--gray);font-size:.88rem;text-align:center;padding:1.5rem 0;">No images uploaded yet.</p>
                <?php else: ?>
                    <div class="image-gallery">
                        <?php foreach ($images as $img): ?>
                        <div class="image-thumb">
                            <?php if ($img['image_path'] && file_exists(dirname(__DIR__) . '/uploads/fingerprints/' . $img['image_path'])): ?>
                                <img src="../view_fingerprint.php?test_id=<?= $img['id'] ?>" alt="Fingerprint image">
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
const chosen = document.getElementById('file-chosen');

let stream = null;

const uploadZone = document.getElementById('uploadZone');
const webcamZone = document.getElementById('webcamZone');
const btnStartWebcam = document.getElementById('btnStartWebcam');
const btnCancelWebcam = document.getElementById('btnCancelWebcam');
const btnCapturePhoto = document.getElementById('btnCapturePhoto');
const btnRetakePhoto = document.getElementById('btnRetakePhoto');
const webcamVideo = document.getElementById('webcamVideo');
const webcamCanvas = document.getElementById('webcamCanvas');
const webcamCapturePreview = document.getElementById('webcamCapturePreview');
const sourceLabel = document.getElementById('selected-source-label');

inp.addEventListener('change', () => {
    chosen.textContent = inp.files[0] ? inp.files[0].name : '';
    // If a manual file is uploaded, stop the webcam and show the file zone
    if (inp.files[0] && inp.files[0].name !== 'webcam_capture.png') {
        stopWebcam();
        webcamZone.style.display = 'none';
        uploadZone.style.display = 'block';
        btnStartWebcam.style.display = 'inline-block';
        sourceLabel.textContent = "Source: File Upload";
    }
});

const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
        inp.files = e.dataTransfer.files;
        chosen.textContent = inp.files[0].name;
        
        // If a file is dropped, stop the webcam and show the file zone
        stopWebcam();
        webcamZone.style.display = 'none';
        uploadZone.style.display = 'block';
        btnStartWebcam.style.display = 'inline-block';
        sourceLabel.textContent = "Source: File Upload";
    }
});

btnStartWebcam.addEventListener('click', async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
        webcamVideo.srcObject = stream;
        webcamVideo.style.display = 'block';
        webcamCapturePreview.style.display = 'none';
        
        uploadZone.style.display = 'none';
        webcamZone.style.display = 'block';
        btnStartWebcam.style.display = 'none';
        
        btnCapturePhoto.style.display = 'inline-block';
        btnRetakePhoto.style.display = 'none';
        
        sourceLabel.textContent = "Source: Live Webcam Feed";
    } catch (err) {
        alert("Unable to access the webcam. Please check your camera permissions.");
        console.error(err);
    }
});

function stopWebcam() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
}

btnCancelWebcam.addEventListener('click', () => {
    stopWebcam();
    webcamZone.style.display = 'none';
    uploadZone.style.display = 'block';
    btnStartWebcam.style.display = 'inline-block';
    sourceLabel.textContent = "Source: File Upload";
    webcamVideo.srcObject = null;
});

btnCapturePhoto.addEventListener('click', () => {
    // Capture to canvas
    webcamCanvas.width = webcamVideo.videoWidth || 640;
    webcamCanvas.height = webcamVideo.videoHeight || 480;
    const ctx = webcamCanvas.getContext('2d');
    ctx.drawImage(webcamVideo, 0, 0, webcamCanvas.width, webcamCanvas.height);
    
    const dataUrl = webcamCanvas.toDataURL('image/png');
    
    // Show preview
    webcamVideo.style.display = 'none';
    webcamCapturePreview.src = dataUrl;
    webcamCapturePreview.style.display = 'block';
    
    btnCapturePhoto.style.display = 'none';
    btnRetakePhoto.style.display = 'inline-block';
    
    // Set to file input
    try {
        const file = dataURLtoFile(dataUrl, 'webcam_capture.png');
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        inp.files = dataTransfer.files;
        
        chosen.textContent = "webcam_capture.png (Captured)";
    } catch (e) {
        console.error("Error setting file input: ", e);
    }
});

btnRetakePhoto.addEventListener('click', () => {
    webcamVideo.style.display = 'block';
    webcamCapturePreview.style.display = 'none';
    
    btnCapturePhoto.style.display = 'inline-block';
    btnRetakePhoto.style.display = 'none';
    
    // Clear input
    inp.value = '';
    chosen.textContent = '';
});

function dataURLtoFile(dataurl, filename) {
    var arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1],
        bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
    while(n--){
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new File([u8arr], filename, {type:mime});
}

// AJAX file upload logic
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let isUploading = false;

function showNotification(type, message) {
    const container = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    container.innerHTML = `<div class="alert-msg ${alertClass}">${message}</div>`;
    setTimeout(() => {
        container.innerHTML = '';
    }, 6000);
}

document.getElementById('form-upload-fingerprint').addEventListener('submit', function(e) {
    e.preventDefault();
    if (isUploading) return;

    if (!inp.files.length) {
        showNotification('error', 'Please select a fingerprint image file to upload.');
        return;
    }

    const btn = document.getElementById('btn-upload-image');
    const btnText = document.getElementById('btnText');
    const originalText = btnText.textContent;
    
    btnText.textContent = 'Uploading...';
    btn.disabled = true;
    isUploading = true;

    const formData = new FormData(this);
    formData.append('csrf_token', csrfToken);

    fetch('ajax_upload_fingerprint.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(res => res.json())
    .then(data => {
        isUploading = false;
        btnText.textContent = originalText;
        btn.disabled = false;
        
        if (data.success) {
            showNotification('success', data.message);
            appendGalleryCard(data.data);
            
            // Reset form
            document.getElementById('form-upload-fingerprint').reset();
            chosen.textContent = '';
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(err => {
        isUploading = false;
        btnText.textContent = originalText;
        btn.disabled = false;
        showNotification('error', 'An error occurred during upload. Please try again.');
    });
});

function appendGalleryCard(data) {
    const noImagesText = document.getElementById('noImagesText');
    if (noImagesText) {
        noImagesText.remove();
    }

    let gallery = document.querySelector('.image-gallery');
    if (!gallery) {
        gallery = document.createElement('div');
        gallery.className = 'image-gallery';
        const cardBody = document.querySelector('.dashboard-card:nth-of-type(2)');
        cardBody.appendChild(gallery);
    }

    const card = document.createElement('div');
    card.className = 'image-thumb';
    
    card.innerHTML = `
        <img src="../view_fingerprint.php?test_id=${data.id}" alt="Fingerprint image">
        <div class="image-thumb-info">
            <div class="image-thumb-label" style="font-size: 0.8rem; font-weight:700; color:var(--dark-green);">${data.trial_id}</div>
            <div class="image-thumb-label" title="${data.image_label || 'Untitled'}">${data.image_label || 'Untitled'}</div>
            <div style="font-size:0.7rem; color:var(--gray); text-transform:capitalize; margin-bottom: 2px;">
                ${data.powder_type} | ${data.surface_type}
            </div>
            <div class="image-thumb-date" style="font-size:0.68rem;">Just now</div>
            <div style="margin-top: 4px;">
                <span class="badge badge-pending" style="font-size: 0.65rem; padding: 2px 6px;">
                    Pending Validation
                </span>
            </div>
        </div>
    `;
    
    gallery.insertBefore(card, gallery.firstChild);

    // Update count span
    const countSpan = document.querySelector('.card-title-wrap span');
    if (countSpan) {
        const count = gallery.querySelectorAll('.image-thumb').length;
        countSpan.textContent = `${count} image${count !== 1 ? 's' : ''}`;
    }
}
</script>
</body>
</html>
