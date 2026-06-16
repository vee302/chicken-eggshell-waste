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
                <form id="form-upload-fingerprint">
                    <!-- Camera-Based Evaluation Preview Panel -->
                    <div class="camera-preview-panel" style="background: #fff; text-align: center; margin-bottom: 1.5rem;">

                        
                        <!-- Title & Subtitle -->
                        <h2 style="font-size: 1.5rem; font-weight: 800; color: #1e392a; margin: 0 0 8px 0; font-family: 'Inter', system-ui, sans-serif;">Camera-Based Evaluation Preview</h2>
                        <p style="font-size: 0.88rem; color: #5f6368; margin: 0 0 1.5rem 0;">Visual preview of the secured student dashboard feature.</p>

                        <!-- Dashed Box Container (Visual Area) -->
                        <div id="previewContainer" style="border: 2px dashed #c3d9c3; border-radius: 12px; padding: 2.5rem 2rem; background: #fafdfa; min-height: 220px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; margin-bottom: 1.5rem; transition: all 0.3s ease;">
                            
                            <!-- Video feed (Hidden initially) -->
                            <video id="webcamVideo" autoplay playsinline style="display: none; width: 100%; max-height: 250px; object-fit: contain; border-radius: 8px;"></video>
                            <canvas id="webcamCanvas" style="display: none;"></canvas>
                            
                            <!-- Captured or Uploaded Image Preview (Hidden initially) -->
                            <img id="webcamCapturePreview" style="display: none; width: 100%; max-height: 250px; object-fit: contain; border-radius: 8px;" alt="Preview Image">

                            <!-- Idle Placeholder (Shown initially) -->
                            <div id="previewPlaceholder" style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #6e8c78;">
                                <div style="margin-bottom: 0.85rem; color: #b4d2b4;">
                                    <!-- Camera SVG Icon -->
                                    <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                </div>
                                <h4 style="font-size: 1.1rem; font-weight: 700; color: #2d4c38; margin: 0 0 6px 0;">Camera Preview / Uploaded Image</h4>
                                <p style="font-size: 0.82rem; color: #88a68f; margin: 0; font-weight: 600;">Preview Mode Only</p>
                            </div>
                        </div>

                        <!-- Buttons Row -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;" class="no-print">
                            <!-- Start/Capture Camera Button -->
                            <button type="button" class="btn" id="btnStartWebcam" style="background: #e2ebe4; border: 1px solid #ccdcd0; color: #1b4332; font-weight: 700; font-size: 0.82rem; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0.75rem 1rem; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 0.03em;">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #1b4332;">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                    <circle cx="12" cy="13" r="4"/>
                                </svg>
                                <span id="btnCameraText">START CAMERA</span>
                            </button>

                            <!-- Upload File Button -->
                            <button type="button" class="btn" id="btnUploadTrigger" style="background: #fff; border: 1px solid #ccdcd0; color: #2d4c38; font-weight: 700; font-size: 0.82rem; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0.75rem 1rem; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 0.03em;">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #2d4c38;">
                                    <polyline points="16 16 12 12 8 16"/>
                                    <line x1="12" y1="12" x2="12" y2="21"/>
                                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                                </svg>
                                <span id="btnUploadText">UPLOAD FILE</span>
                            </button>
                        </div>
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

                    <button type="submit" class="btn btn-primary" id="btn-upload-image" style="width: 100%; padding: 0.85rem; font-size: 0.95rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; justify-content: center; gap: 8px; background: #224229 !important; border-color: #224229 !important; border-radius: 8px; color: #fff;">
                        <span id="btnText">Evaluate Print Clarity</span>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #fff;">
                            <line x1="7" y1="17" x2="17" y2="7"/>
                            <polyline points="7 7 17 7 17 17"/>
                        </svg>
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
let isCameraActive = false;

const previewContainer = document.getElementById('previewContainer');
const previewPlaceholder = document.getElementById('previewPlaceholder');
const webcamVideo = document.getElementById('webcamVideo');
const webcamCanvas = document.getElementById('webcamCanvas');
const webcamCapturePreview = document.getElementById('webcamCapturePreview');

const btnStartWebcam = document.getElementById('btnStartWebcam');
const btnCameraText = document.getElementById('btnCameraText');
const btnUploadTrigger = document.getElementById('btnUploadTrigger');
const btnUploadText = document.getElementById('btnUploadText');

// Trigger local file selection when clicking "UPLOAD FILE"
btnUploadTrigger.addEventListener('click', () => {
    if (isCameraActive) {
        // CLOSE CAMERA action
        stopWebcam();
        setIdleState();
    } else {
        // UPLOAD FILE action
        inp.click();
    }
});

// Start Camera or Capture Photo
btnStartWebcam.addEventListener('click', async () => {
    if (isCameraActive) {
        // CAPTURE PHOTO action
        capturePhoto();
    } else {
        // START CAMERA action
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
            webcamVideo.srcObject = stream;
            
            // Toggle view visibility
            previewPlaceholder.style.display = 'none';
            webcamCapturePreview.style.display = 'none';
            webcamVideo.style.display = 'block';
            
            // Set state active
            isCameraActive = true;
            
            // Toggle button texts
            btnCameraText.textContent = "CAPTURE PHOTO";
            btnUploadText.textContent = "CLOSE CAMERA";
            
            // Update button icons
            btnStartWebcam.querySelector('svg').innerHTML = `
                <circle cx="12" cy="12" r="10"/>
                <circle cx="12" cy="12" r="3"/>
            `;
            btnUploadTrigger.querySelector('svg').innerHTML = `
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            `;
        } catch (err) {
            alert("Unable to access the webcam. Please check your camera permissions.");
            console.error(err);
        }
    }
});

function stopWebcam() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    webcamVideo.srcObject = null;
    isCameraActive = false;
}

function setIdleState() {
    webcamVideo.style.display = 'none';
    webcamCapturePreview.style.display = 'none';
    previewPlaceholder.style.display = 'flex';
    
    btnCameraText.textContent = "START CAMERA";
    btnUploadText.textContent = "UPLOAD FILE";
    
    // Reset icons
    btnStartWebcam.querySelector('svg').innerHTML = `
        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
        <circle cx="12" cy="13" r="4"/>
    `;
    btnUploadTrigger.querySelector('svg').innerHTML = `
        <polyline points="16 16 12 12 8 16"/>
        <line x1="12" y1="12" x2="12" y2="21"/>
        <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
    `;
}

function capturePhoto() {
    // Capture stream frame to canvas
    webcamCanvas.width = webcamVideo.videoWidth || 640;
    webcamCanvas.height = webcamVideo.videoHeight || 480;
    const ctx = webcamCanvas.getContext('2d');
    ctx.drawImage(webcamVideo, 0, 0, webcamCanvas.width, webcamCanvas.height);
    
    const dataUrl = webcamCanvas.toDataURL('image/png');
    
    // Stop camera stream
    stopWebcam();
    
    // Show image preview
    webcamVideo.style.display = 'none';
    webcamCapturePreview.src = dataUrl;
    webcamCapturePreview.style.display = 'block';
    
    // Reset buttons back to start state
    setIdleState();
    
    // Bind to the file input
    try {
        const file = dataURLtoFile(dataUrl, 'webcam_capture.png');
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        inp.files = dataTransfer.files;
        
        chosen.textContent = "webcam_capture.png (Captured from Webcam)";
    } catch (e) {
        console.error("Error creating File from capture: ", e);
    }
}

// Drag and drop events on previewContainer
previewContainer.addEventListener('dragover', e => {
    e.preventDefault();
    previewContainer.style.borderColor = '#1b4332';
    previewContainer.style.background = '#e6f4ea';
});
previewContainer.addEventListener('dragleave', () => {
    previewContainer.style.borderColor = '#c3d9c3';
    previewContainer.style.background = '#fafdfa';
});
previewContainer.addEventListener('drop', e => {
    e.preventDefault();
    previewContainer.style.borderColor = '#c3d9c3';
    previewContainer.style.background = '#fafdfa';
    
    if (e.dataTransfer.files.length) {
        inp.files = e.dataTransfer.files;
        // Trigger change event to load preview
        const event = new Event('change');
        inp.dispatchEvent(event);
    }
});

// Handle local file uploads
inp.addEventListener('change', () => {
    if (inp.files && inp.files[0]) {
        const file = inp.files[0];
        chosen.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        
        // Show file image preview
        const reader = new FileReader();
        reader.onload = e => {
            stopWebcam();
            webcamVideo.style.display = 'none';
            webcamCapturePreview.src = e.target.result;
            webcamCapturePreview.style.display = 'block';
            previewPlaceholder.style.display = 'none';
            setIdleState();
        };
        reader.readAsDataURL(file);
    } else {
        chosen.textContent = '';
        setIdleState();
    }
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
