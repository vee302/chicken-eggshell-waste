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
                        <!-- Filename display underneath -->
                        <div id="file-chosen" style="font-size: 0.82rem; font-weight: 700; color: #2d6a4f; margin-top: 1rem; text-align: center; min-height: 1.2rem;"></div>
                    </div>
                    <input type="file" name="fingerprint_image" id="fingerprint_image"
                           accept="image/jpeg,image/png,image/webp" style="display:none;" required>
                    <input type="file" id="fingerprint_camera" accept="image/*" capture="environment" style="display:none;">

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

                    <!-- Non-biometric identification notice / disclaimer -->
                    <div style="margin: 1.5rem 0; padding: 0.95rem 1.1rem; background: #f4f6f0; border-left: 4px solid var(--medium-green); border-radius: 4px; font-size: 0.78rem; color: #5f6368; line-height: 1.45;">
                        <strong>Disclaimer / Notice:</strong> This feature is for educational and research evaluation only. It is not used for biometric identification. It is only designed for Camera-Based Latent Fingerprint Capture and AI-Assisted Image Quality Evaluation using Chicken Eggshell Waste powder.
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

<!-- Camera Capture Modal -->
<div id="cameraModal" class="camera-modal" style="display: none;">
    <!-- Live Video Stream -->
    <video id="cameraVideo" class="camera-video" autoplay playsinline></video>

    <!-- Blur/Dim Backdrop Mask -->
    <div class="camera-blur-overlay"></div>

    <!-- Center Oval Focus Guide -->
    <div class="focus-guide-oval"></div>

    <!-- Top Text Instructions -->
    <div class="camera-header">
        <h3 class="camera-title">Fingerprint Scan</h3>
        <p class="camera-subtitle" id="cameraSubtitle">Align the latent fingerprint inside the guide.</p>
    </div>

    <!-- Auto-Capture Control Widget -->
    <div class="autocapture-container" style="display: none;">
        <div class="autocapture-row">
            <div class="autocapture-label-group">
                <span class="autocapture-title-text">Auto-Capture</span>
                <div>
                    <span id="autoCaptureStatus" class="autocapture-status-badge">Off</span>
                </div>
            </div>
            <label class="switch-toggle" title="Toggle Auto-Capture">
                <input type="checkbox" id="chkAutoCapture" checked>
                <span class="switch-slider"></span>
            </label>
        </div>
        
        <!-- Clarity Meter -->
        <div class="clarity-meter-wrap">
            <div class="clarity-meter-header">
                <span>Print Clarity</span>
                <span id="clarityValue">0%</span>
            </div>
            <div class="clarity-meter-bg">
                <div id="clarityMeterFill" class="clarity-meter-fill"></div>
            </div>
        </div>

        <!-- Sensitivity Controller -->
        <div class="sensitivity-control">
            <div class="sensitivity-header">
                <span>Capture Sensitivity</span>
                <span id="sensitivityValue">50%</span>
            </div>
            <input type="range" id="rangeSensitivity" class="sensitivity-slider" min="20" max="80" value="50" step="5">
        </div>
    </div>

    <!-- Pattern Detection Warning Banner -->
    <div id="patternWarning" class="autocapture-warning" style="display: none;">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>No fingerprint pattern detected. Align in guide.</span>
    </div>

    <!-- Controls Row -->
    <div class="camera-controls">
        <!-- Close/Cancel Button -->
        <button type="button" class="ctrl-btn btn-cancel" id="btnCancelCamera" title="Cancel Capture">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <!-- Capture Photo Button -->
        <button type="button" class="ctrl-btn btn-capture" id="btnCapturePhoto" title="Capture Fingerprint">
            <span class="capture-inner"></span>
        </button>

        <!-- Switch Front/Back Camera Button -->
        <button type="button" class="ctrl-btn btn-switch" id="btnSwitchCamera" title="Switch Camera">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
            </svg>
        </button>
    </div>
</div>

<?php require_once '_sidebar_js.php'; ?>
<script>
const inp = document.getElementById('fingerprint_image');
const chosen = document.getElementById('file-chosen');

const previewContainer = document.getElementById('previewContainer');
const previewPlaceholder = document.getElementById('previewPlaceholder');
const webcamCapturePreview = document.getElementById('webcamCapturePreview');

const btnStartWebcam = document.getElementById('btnStartWebcam');
const btnUploadTrigger = document.getElementById('btnUploadTrigger');

let cameraStream = null;
let currentFacingMode = 'environment';
let isCameraProcessing = false;

// Auto-Capture state variables
let autoCaptureLoopId = null;
let lastProcessingTime = 0;
let stableFocusFrames = 0;
let isCaptureTriggered = false;
let cameraStartTime = 0;

// DOM elements for Auto-Capture
const chkAutoCapture = document.getElementById('chkAutoCapture');
const autoCaptureStatus = document.getElementById('autoCaptureStatus');
const clarityMeterFill = document.getElementById('clarityMeterFill');
const clarityValue = document.getElementById('clarityValue');
const rangeSensitivity = document.getElementById('rangeSensitivity');
const sensitivityValue = document.getElementById('sensitivityValue');

// Update Auto-Capture toggle visual style and text state
function updateAutoCaptureUI() {
    if (chkAutoCapture.checked) {
        autoCaptureStatus.textContent = "Active";
        autoCaptureStatus.className = "autocapture-status-badge active";
        document.querySelector('.clarity-meter-wrap').style.opacity = "1";
        document.querySelector('.sensitivity-control').style.opacity = "1";
    } else {
        autoCaptureStatus.textContent = "Off";
        autoCaptureStatus.className = "autocapture-status-badge";
        document.querySelector('.clarity-meter-wrap').style.opacity = "0.5";
        document.querySelector('.sensitivity-control').style.opacity = "0.5";
        
        // Reset progress bar values
        clarityMeterFill.style.width = "0%";
        clarityMeterFill.classList.remove('focused');
        clarityValue.textContent = "0%";
        
        // Remove clear detected glow state from the oval focus guide
        const oval = document.querySelector('.focus-guide-oval');
        if (oval) {
            oval.classList.remove('clear-detected');
        }
    }
}

// Bind event listeners for auto-capture settings
chkAutoCapture.addEventListener('change', updateAutoCaptureUI);
rangeSensitivity.addEventListener('input', () => {
    sensitivityValue.textContent = `${rangeSensitivity.value}%`;
});

// Stop and release camera tracks
function stopWebcam() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    const video = document.getElementById('cameraVideo');
    if (video) {
        video.srcObject = null;
    }
    
    // Stop the auto-capture frame analysis loop
    if (autoCaptureLoopId) {
        cancelAnimationFrame(autoCaptureLoopId);
        autoCaptureLoopId = null;
    }
    
    // Remove clarity overlay focus indicators
    const oval = document.querySelector('.focus-guide-oval');
    if (oval) {
        oval.classList.remove('clear-detected');
    }

    // Hide the pattern warning banner
    const warnBanner = document.getElementById('patternWarning');
    if (warnBanner) {
        warnBanner.style.display = 'none';
    }
}

// Stop tracks on page unload or visibility hidden to prevent background active camera
window.addEventListener('beforeunload', stopWebcam);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        stopWebcam();
        document.getElementById('cameraModal').style.display = 'none';
    }
});

// Launch custom fullscreen live camera overlay
async function startWebcam() {
    stopWebcam();
    const modal = document.getElementById('cameraModal');
    const video = document.getElementById('cameraVideo');
    
    const constraints = {
        video: {
            facingMode: currentFacingMode,
            width: { ideal: 1280 },
            height: { ideal: 720 }
        },
        audio: false
    };

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = cameraStream;
        modal.style.display = 'flex';
        
        // Start processing frames when video metadata loads
        video.onloadedmetadata = () => {
            startAutoCaptureLoop();
        };
        // Fallback in case metadata is already loaded
        if (video.videoWidth > 0) {
            startAutoCaptureLoop();
        }
    } catch (err) {
        console.error("Camera access error: ", err);
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            alert("Camera access was denied. Please allow camera permission or upload a file instead.");
        } else {
            // Try general webcam fallback
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                video.srcObject = cameraStream;
                modal.style.display = 'flex';
                
                video.onloadedmetadata = () => {
                    startAutoCaptureLoop();
                };
                if (video.videoWidth > 0) {
                    startAutoCaptureLoop();
                }
            } catch (fallbackErr) {
                alert("Camera is not available on this device. Please upload a file instead.");
            }
        }
    }
}

// Update Camera overlay subtitle message and auto-capture badge states
function updateCameraStatus(state, message) {
    const subtitle = document.getElementById('cameraSubtitle');
    if (subtitle) {
        subtitle.textContent = message;
    }
    const badge = document.getElementById('autoCaptureStatus');
    if (badge) {
        if (!chkAutoCapture.checked) {
            badge.textContent = "Off";
            badge.className = "autocapture-status-badge";
        } else {
            if (state === 'searching') {
                badge.textContent = "Searching";
                badge.className = "autocapture-status-badge";
            } else if (state === 'pattern_detected') {
                badge.textContent = "Detected";
                badge.className = "autocapture-status-badge active";
            } else if (state === 'blurry') {
                badge.textContent = "Blurry";
                badge.className = "autocapture-status-badge active";
            } else if (state === 'ready') {
                badge.textContent = "Ready";
                badge.className = "autocapture-status-badge active";
            } else if (state === 'captured') {
                badge.textContent = "Captured";
                badge.className = "autocapture-status-badge active";
            } else if (state === 'invalid') {
                badge.textContent = "Invalid";
                badge.className = "autocapture-status-badge";
            }
        }
    }
}

// Start auto capture processing frame loop
function startAutoCaptureLoop() {
    stableFocusFrames = 0;
    isCaptureTriggered = false;
    lastProcessingTime = 0;
    cameraStartTime = Date.now(); // Record start time for sensor stabilization
    
    updateCameraStatus('searching', "Align the latent fingerprint inside the guide.");

    const video = document.getElementById('cameraVideo');
    const oval = document.querySelector('.focus-guide-oval');

    // Create offscreen analysis canvas to compute sharpness metrics
    const analysisCanvas = document.createElement('canvas');
    analysisCanvas.width = 150;
    analysisCanvas.height = 225;
    const analysisCtx = analysisCanvas.getContext('2d');

    function processFrame(timestamp) {
        if (!cameraStream || isCameraProcessing || isCaptureTriggered) {
            autoCaptureLoopId = requestAnimationFrame(processFrame);
            return;
        }

        // Wait 800ms after camera opens to let autofocus/white balance stabilize
        if (Date.now() - cameraStartTime < 800) {
            autoCaptureLoopId = requestAnimationFrame(processFrame);
            return;
        }

        // Run approximately every 150ms to keep it extremely lightweight on mobile
        if (timestamp - lastProcessingTime < 150) {
            autoCaptureLoopId = requestAnimationFrame(processFrame);
            return;
        }
        lastProcessingTime = timestamp;

        if (!chkAutoCapture.checked) {
            updateCameraStatus('off', "Align the latent fingerprint inside the guide.");
            autoCaptureLoopId = requestAnimationFrame(processFrame);
            return;
        }

        const videoW = video.videoWidth;
        const videoH = video.videoHeight;
        const viewW = window.innerWidth;
        const viewH = window.innerHeight;

        if (!videoW || !videoH) {
            autoCaptureLoopId = requestAnimationFrame(processFrame);
            return;
        }

        // Bounding box of the center guide (matches crop dimensions)
        const width = 0.40 * viewH;
        const height = 0.60 * viewH;
        const left = (viewW - width) / 2;
        const top = (viewH - height) / 2;

        const scale = Math.max(viewW / videoW, viewH / videoH);
        const offsetX = (videoW * scale - viewW) / 2;
        const offsetY = (videoH * scale - viewH) / 2;

        const cropX = (left + offsetX) / scale;
        const cropY = (top + offsetY) / scale;
        const cropW = width / scale;
        const cropH = height / scale;

        // Draw cropped guide area onto analysis canvas
        analysisCtx.drawImage(video, cropX, cropY, cropW, cropH, 0, 0, 150, 225);

        // Run Fingerprint Pattern Validation (gradient coherence, bounding box checks)
        const pattern = validateFingerprintPattern(analysisCtx, 150, 225);
        const warnBanner = document.getElementById('patternWarning');

        if (!pattern.isValid) {
            // Show warning banner prompting user to align fingerprint
            warnBanner.style.display = 'flex';
            updateCameraStatus('searching', "Align the latent fingerprint inside the guide.");
            
            // Reset clarity meter progress
            clarityMeterFill.style.width = '0%';
            clarityValue.textContent = '0%';
            clarityMeterFill.classList.remove('focused');
            stableFocusFrames = 0;
            if (oval) {
                oval.classList.remove('clear-detected');
            }
            
            autoCaptureLoopId = requestAnimationFrame(processFrame);
            return;
        } else {
            // Hide warning banner since fingerprint pattern is detected and positioned correctly
            warnBanner.style.display = 'none';
        }

        // Get image analysis metrics
        const analysis = getClarityMetrics(analysisCtx, 150, 225);

        // Map slider sensitivity (20 to 80) to target sharpness threshold
        // Sensitivity 20: Threshold = 140 (Extreme sharpness)
        // Sensitivity 50: Threshold = 80 (Normal balanced focus)
        // Sensitivity 80: Threshold = 20 (Triggers easily on low-end cameras)
        const sensitivity = parseInt(rangeSensitivity.value, 10);
        const threshold = 180 - (sensitivity * 2); 

        let relativeClarity = 0;
        
        // We require standard deviation of pixel values > 30 (variance > 900)
        // This ensures an actual high-contrast pattern is inside the guide and not empty/flat background.
        if (analysis.contrastStdDev > 30) {
            relativeClarity = Math.min(100, Math.round((analysis.sharpness / threshold) * 100));
        }

        // Update Clarity Meter visual states
        clarityMeterFill.style.width = `${relativeClarity}%`;
        clarityValue.textContent = `${relativeClarity}%`;

        if (relativeClarity < 100) {
            // State 3: Blurry. Message: “Hold steady. Image is not clear enough.”
            updateCameraStatus('blurry', "Hold steady. Image is not clear enough.");
            clarityMeterFill.classList.remove('focused');
            stableFocusFrames = 0;
            if (oval) {
                oval.classList.remove('clear-detected');
            }
        } else {
            // High clarity frame!
            clarityMeterFill.classList.add('focused');
            stableFocusFrames++;

            if (stableFocusFrames < 10) {
                // State 2: Pattern detected. Message: “Fingerprint pattern detected. Hold steady.”
                updateCameraStatus('pattern_detected', "Fingerprint pattern detected. Hold steady.");
            } else {
                // State 4: Ready. Message: “Fingerprint aligned. Capturing...”
                isCaptureTriggered = true;
                updateCameraStatus('ready', "Fingerprint aligned. Capturing...");
                
                if (oval) {
                    oval.classList.add('clear-detected');
                }
                
                // Play focus confirmation beep
                playBeep();

                // Wait 250ms for visual focus animation frame before triggering capture click
                setTimeout(() => {
                    updateCameraStatus('captured', "Fingerprint image captured successfully.");
                    document.getElementById('btnCapturePhoto').click();
                }, 250);
            }
        }

        autoCaptureLoopId = requestAnimationFrame(processFrame);
    }

    autoCaptureLoopId = requestAnimationFrame(processFrame);
}

// Compute contrast standard deviation and spatial gradient variance (variance of Laplacian)
function getClarityMetrics(ctx, w, h) {
    const imgData = ctx.getImageData(0, 0, w, h);
    const data = imgData.data;
    const len = data.length;
    
    // Grayscale mapping
    const gray = new Float32Array(w * h);
    let sum = 0;
    for (let i = 0; i < len; i += 4) {
        const val = 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
        gray[i / 4] = val;
        sum += val;
    }
    const mean = sum / gray.length;

    // Contrast standard deviation
    let varianceSum = 0;
    for (let i = 0; i < gray.length; i++) {
        const diff = gray[i] - mean;
        varianceSum += diff * diff;
    }
    const contrastStdDev = Math.sqrt(varianceSum / gray.length);

    // Variance of Laplacian filter (measure of edge focus sharpness)
    // Kernel:
    // [ 0,  1,  0]
    // [ 1, -4,  1]
    // [ 0,  1,  0]
    let lapSum = 0;
    let lapSqSum = 0;
    let count = 0;

    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const idx = y * w + x;
            const val = gray[idx];
            const left = gray[idx - 1];
            const right = gray[idx + 1];
            const top = gray[idx - w];
            const bottom = gray[idx + w];
            
            const lap = left + right + top + bottom - 4 * val;
            lapSum += lap;
            lapSqSum += lap * lap;
            count++;
        }
    }

    const lapMean = lapSum / count;
    const lapVar = (lapSqSum / count) - (lapMean * lapMean);

    return {
        contrastStdDev: contrastStdDev,
        sharpness: lapVar
    };
}

// Compute gradient coherence and bounding box coordinates to validate fingerprint shape
function validateFingerprintPattern(ctx, w, h) {
    const imgData = ctx.getImageData(0, 0, w, h);
    const data = imgData.data;
    
    // 1. Grayscale mapping
    const gray = new Float32Array(w * h);
    let sum = 0;
    for (let i = 0; i < data.length; i += 4) {
        const val = 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
        gray[i / 4] = val;
        sum += val;
    }
    const mean = sum / gray.length;

    // 2. Check global contrast standard deviation
    let varianceSum = 0;
    for (let i = 0; i < gray.length; i++) {
        const diff = gray[i] - mean;
        varianceSum += diff * diff;
    }
    const globalStdDev = Math.sqrt(varianceSum / gray.length);
    
    if (globalStdDev < 30) {
        return { isValid: false, reason: "low_contrast", ridgeRatio: 0 };
    }
    
    // 3. Corner-sampling background analysis to detect paper vs dark surfaces
    const cornerPixels = [
        gray[2 + 2 * w],
        gray[(w - 3) + 2 * w],
        gray[2 + (h - 3) * w],
        gray[(w - 3) + (h - 3) * w]
    ];
    const bgGray = cornerPixels.reduce((a, b) => a + b, 0) / 4;
    const isLightBg = bgGray > 128;
    const diffThreshold = 40; // minimum difference from background to count as print ridge

    // Compute pixel gradients gx and gy
    const gx = new Float32Array(w * h);
    const gy = new Float32Array(w * h);
    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const idx = y * w + x;
            gx[idx] = gray[idx + 1] - gray[idx - 1];
            gy[idx] = gray[idx + w] - gray[idx - w];
        }
    }
    
    // Segment guide region into 15x15 blocks (150 blocks total)
    const blockSize = 15;
    const cols = Math.floor(w / blockSize);
    const rows = Math.floor(h / blockSize);
    let validRidgeBlocks = 0;
    let totalBlocks = cols * rows;
    let sumCoherence = 0;
    let evaluatedBlocks = 0;

    // Bounding Box Engine variables
    let xMin = w, xMax = 0, yMin = h, yMax = 0;
    let foundRidgePixels = false;

    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            let vxx = 0, vyy = 0, vxy = 0;
            let minVal = 255, maxVal = 0;
            
            for (let y = r * blockSize; y < (r + 1) * blockSize; y++) {
                for (let x = c * blockSize; x < (c + 1) * blockSize; x++) {
                    const idx = y * w + x;
                    const val = gray[idx];
                    if (val < minVal) minVal = val;
                    if (val > maxVal) maxVal = val;
                    
                    vxx += gx[idx] * gx[idx];
                    vyy += gy[idx] * gy[idx];
                    vxy += gx[idx] * gy[idx];

                    // Track individual ridge pixels for bounding box detection
                    const isRidgePixel = isLightBg ? (val < bgGray - diffThreshold) : (val > bgGray + diffThreshold);
                    if (isRidgePixel) {
                        if (x < xMin) xMin = x;
                        if (x > xMax) xMax = x;
                        if (y < yMin) yMin = y;
                        if (y > yMax) yMax = y;
                        foundRidgePixels = true;
                    }
                }
            }
            
            const blockContrast = maxVal - minVal;
            const gradientSum = vxx + vyy;
            
            // Validate block properties
            if (blockContrast > 40 && gradientSum > 8000) {
                // Coherence: measures gradient alignment. High coherence (0 to 1) means parallel lines (ridges)
                const coherence = Math.sqrt(Math.pow(vxx - vyy, 2) + 4 * vxy * vxy) / (vxx + vyy + 0.001);
                sumCoherence += coherence;
                evaluatedBlocks++;

                if (coherence > 0.45) {
                    validRidgeBlocks++;
                }
            }
        }
    }
    
    const avgCoherence = evaluatedBlocks > 0 ? (sumCoherence / evaluatedBlocks) : 0;
    const ridgeRatio = validRidgeBlocks / totalBlocks;

    const bboxW = xMax - xMin;
    const bboxH = yMax - yMin;

    // Bounding Box Validation Rules:
    // 1. Bounding Box must not touch the very margins of the 150x225 analysis canvas (ensure it is inside center oval guide)
    const isBboxInside = foundRidgePixels && (xMin > 3 && xMax < w - 3 && yMin > 3 && yMax < h - 3);
    // 2. Bounding Box must have a reasonable fingerprint-like size (Reject if too small or too large)
    const isBboxSizeReasonable = foundRidgePixels && (bboxW >= 45 && bboxW <= 140 && bboxH >= 65 && bboxH <= 210);

    let isValid = ridgeRatio > 0.15 && avgCoherence > 0.45;
    let reason = "ok";

    if (!isValid) {
        reason = "no_ridge_pattern";
    } else if (!isBboxInside) {
        isValid = false;
        reason = "outside_guide";
    } else if (!isBboxSizeReasonable) {
        isValid = false;
        reason = "bad_size";
    }

    return {
        isValid: isValid,
        reason: reason,
        ridgeRatio: ridgeRatio,
        avgCoherence: avgCoherence,
        bbox: {
            xMin: xMin,
            xMax: xMax,
            yMin: yMin,
            yMax: yMax,
            width: bboxW,
            height: bboxH
        }
    };
}

// Scan cropped canvas and return a new canvas cropped tightly around the detected print area
function getTightFingerprintCrop(sourceCanvas) {
    const w = sourceCanvas.width;
    const h = sourceCanvas.height;
    const ctx = sourceCanvas.getContext('2d');
    const imgData = ctx.getImageData(0, 0, w, h);
    const data = imgData.data;

    // Sample average background gray value from corners to detect paper cards vs dark surfaces
    const corners = [
        getPixelGray(data, w, 2, 2),
        getPixelGray(data, w, w - 3, 2),
        getPixelGray(data, w, 2, h - 3),
        getPixelGray(data, w, w - 3, h - 3)
    ];
    const bgGray = corners.reduce((a, b) => a + b, 0) / 4;
    
    // Light background (white card) -> ridges are dark
    // Dark background (glass/wood surface) -> ridges are light (developed white powder)
    const isLightBg = bgGray > 128;
    const diffThreshold = 40; // minimum difference from background

    let minX = w, maxX = 0, minY = h, maxY = 0;
    let found = false;

    // Scan the canvas grid (stepping by 2 pixels for speed)
    for (let y = 0; y < h; y += 2) {
        for (let x = 0; x < w; x += 2) {
            const idx = (y * w + x) * 4;
            const r = data[idx];
            const g = data[idx + 1];
            const b = data[idx + 2];
            const gray = 0.299 * r + 0.587 * g + 0.114 * b;

            let isRidge = false;
            if (isLightBg) {
                isRidge = gray < bgGray - diffThreshold;
            } else {
                isRidge = gray > bgGray + diffThreshold;
            }

            if (isRidge) {
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
                found = true;
            }
        }
    }

    if (!found) {
        return sourceCanvas; // Return original if no ridge coordinates matched
    }

    // Add safe padding margin of 20 pixels around print bounds
    const margin = 20;
    minX = Math.max(0, minX - margin);
    maxX = Math.min(w, maxX + margin);
    minY = Math.max(0, minY - margin);
    maxY = Math.min(h, maxY + margin);

    const cropW = maxX - minX;
    const cropH = maxY - minY;

    // Ensure tight cropped area is of sensible size
    if (cropW < 50 || cropH < 50) {
        return sourceCanvas;
    }

    const croppedCanvas = document.createElement('canvas');
    croppedCanvas.width = cropW;
    croppedCanvas.height = cropH;
    const croppedCtx = croppedCanvas.getContext('2d');

    // Draw only the bounding box to final canvas
    croppedCtx.drawImage(sourceCanvas, minX, minY, cropW, cropH, 0, 0, cropW, cropH);
    return croppedCanvas;
}

function getPixelGray(data, w, x, y) {
    const idx = (y * w + x) * 4;
    return 0.299 * data[idx] + 0.587 * data[idx+1] + 0.114 * data[idx+2];
}

// Sound feedback on clear focus trigger using Web Audio API
function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(1000, audioCtx.currentTime); // 1000 Hz beep tone
        gainNode.gain.setValueAtTime(0.08, audioCtx.currentTime); // keep volume subtle
        oscillator.start();
        oscillator.stop(audioCtx.currentTime + 0.12); // play for 120ms
    } catch (e) {
        console.warn("Audio Context audio feedback not supported or blocked by browser policies.");
    }
}

// Open camera modal triggers
btnStartWebcam.addEventListener('click', () => {
    startWebcam();
});

// Cancel camera modal
document.getElementById('btnCancelCamera').addEventListener('click', () => {
    stopWebcam();
    document.getElementById('cameraModal').style.display = 'none';
});

// Toggle facingMode (front/back camera)
document.getElementById('btnSwitchCamera').addEventListener('click', () => {
    currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
    startWebcam();
});

// Capture frame and crop specifically inside the guide oval
document.getElementById('btnCapturePhoto').addEventListener('click', () => {
    if (!cameraStream || isCameraProcessing) return;

    const video = document.getElementById('cameraVideo');
    const videoW = video.videoWidth;
    const videoH = video.videoHeight;
    const viewW = window.innerWidth;
    const viewH = window.innerHeight;

    if (!videoW || !videoH) {
        alert("Camera stream is not fully initialized. Please try again.");
        return;
    }

    isCameraProcessing = true;
    chosen.textContent = "Processing captured fingerprint...";

    // Oval guide dimensions in viewport pixels (matching CSS: width: 40vh, height: 60vh)
    const width = 0.40 * viewH;
    const height = 0.60 * viewH;
    const left = (viewW - width) / 2;
    const top = (viewH - height) / 2;

    // Cover scale factor & offsets mapping viewport space to video space
    const scale = Math.max(viewW / videoW, viewH / videoH);
    const offsetX = (videoW * scale - viewW) / 2;
    const offsetY = (videoH * scale - viewH) / 2;

    // Convert coordinates
    const cropX = (left + offsetX) / scale;
    const cropY = (top + offsetY) / scale;
    const cropW = width / scale;
    const cropH = height / scale;

    const canvas = document.createElement('canvas');
    canvas.width = cropW;
    canvas.height = cropH;
    const ctx = canvas.getContext('2d');
    
    // Draw cropped region from video stream
    ctx.drawImage(video, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

    // Verify if the captured area contains a valid fingerprint pattern
    const testCanvas = document.createElement('canvas');
    testCanvas.width = 150;
    testCanvas.height = 225;
    const testCtx = testCanvas.getContext('2d');
    testCtx.drawImage(canvas, 0, 0, cropW, cropH, 0, 0, 150, 225);

    const pattern = validateFingerprintPattern(testCtx, 150, 225);
    if (!pattern.isValid) {
        updateCameraStatus('invalid', "No fingerprint ridge pattern detected. Please retake.");
        
        // Show fallback option: Submit anyway for faculty review
        const proceed = confirm(
            "No fingerprint ridge pattern detected. Please align the latent fingerprint inside the guide.\n\n" +
            "Do you want to submit anyway for faculty review?"
        );
        if (!proceed) {
            isCameraProcessing = false;
            chosen.textContent = "";
            updateCameraStatus('searching', "Align the latent fingerprint inside the guide.");
            return;
        }
    }

    // Release camera stream and hide modal immediately
    stopWebcam();
    document.getElementById('cameraModal').style.display = 'none';

    // Crop canvas tightly around the fingerprint ridges to remove blank margins
    const tightCanvas = getTightFingerprintCrop(canvas);

    tightCanvas.toBlob(function(blob) {
        isCameraProcessing = false;
        if (!blob) {
            alert("Failed to capture fingerprint image.");
            chosen.textContent = "";
            return;
        }

        const filename = "fingerprint_capture_" + Date.now() + ".jpg";
        const capturedFile = new File([blob], filename, { type: 'image/jpeg' });

        // Bind processed file to form input element
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(capturedFile);
        inp.files = dataTransfer.files;

        chosen.textContent = `${filename} (Camera Captured — Cropped)`;

        // Load preview
        const reader = new FileReader();
        reader.onload = e => {
            webcamCapturePreview.src = e.target.result;
            webcamCapturePreview.style.display = 'block';
            previewPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(capturedFile);
    }, 'image/jpeg', 0.95);
});

// Trigger local file selection
btnUploadTrigger.addEventListener('click', () => {
    inp.click();
});

// Preview selected local files
inp.addEventListener('change', () => {
    if (inp.files && inp.files[0]) {
        const file = inp.files[0];
        chosen.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        
        const reader = new FileReader();
        reader.onload = e => {
            webcamCapturePreview.src = e.target.result;
            webcamCapturePreview.style.display = 'block';
            previewPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    } else {
        chosen.textContent = '';
        webcamCapturePreview.style.display = 'none';
        previewPlaceholder.style.display = 'flex';
    }
});

// Drag and drop setup on preview container
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
        const event = new Event('change');
        inp.dispatchEvent(event);
    }
});

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
    if (isCameraProcessing) {
        showNotification('error', 'Please wait until image capture is finished.');
        return;
    }

    if (!inp.files.length) {
        showNotification('error', 'Please select or capture a fingerprint image to upload.');
        return;
    }

    const btn = document.getElementById('btn-upload-image');
    const btnText = document.getElementById('btnText');
    const originalText = btnText.textContent;
    
    btnText.textContent = 'Uploading & Evaluating...';
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
            webcamCapturePreview.style.display = 'none';
            previewPlaceholder.style.display = 'flex';
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
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
