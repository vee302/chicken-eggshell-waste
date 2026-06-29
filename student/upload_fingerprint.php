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
        SELECT ft.*, COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks, faculty.full_name AS faculty_validator
        FROM fingerprint_tests ft
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE ft.student_id = ? AND ft.image_path IS NOT NULL AND ft.image_path != '' 
        ORDER BY ft.submitted_at DESC LIMIT 20
    ");
    $stmt->execute([$student_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($images as &$img) {
        $img['image_exists'] = false;
        if (!empty($img['image_path'])) {
            $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $img['image_path'];
            if (file_exists($filePath)) {
                $img['image_exists'] = true;
            }
        }
    }
    unset($img);
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

        /* Detail Modal styling matching super admin */
        .detail-overlay { display:none; position:fixed; inset:0; background:rgba(27, 67, 50, 0.45); backdrop-filter: blur(4px); z-index:9999; align-items:center; justify-content:center; }
        .detail-overlay.open { display:flex; }
        .detail-modal { background:#fff; border-radius:16px; max-width:600px; width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); border: 1px solid rgba(27,67,50,0.1); }
        .detail-modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:var(--dark-green); color:#fff; }
        .detail-modal-header h3 { color:#fff; font-size:1.05rem; font-weight:700; margin:0; }
        .detail-modal-body { padding:1.5rem; }
        .detail-row { display:flex; gap:.5rem; margin-bottom:.75rem; font-size:.85rem; }
        .detail-label { min-width:160px; font-weight:600; color:var(--dark-green); }
        .detail-value { color:#5f5f5f; flex:1; }
        .modal-close-btn { background:none; border:none; font-size:1.4rem; cursor:pointer; color:#fff; opacity:0.8; line-height:1; }
        .modal-close-btn:hover { opacity:1; }
        .section-divider { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#6B8F71; border-bottom:1px solid #D2E2D5; padding-bottom:.35rem; margin:1.25rem 0 .6rem; }
        .section-divider:first-child { margin-top: 0; }
        
        .score-box { background: var(--cream); border-radius:8px; padding:10px 15px; margin-bottom:1rem; border:1px solid rgba(45,106,79,0.08); }
        .score-title { font-size:0.75rem; font-weight:700; color:var(--medium-green); margin-bottom:6px; text-transform:uppercase; }
        .score-values { display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; text-align:center; }
        .score-val { font-size:1.15rem; font-weight:800; color:var(--dark-green); }
        .score-lbl { font-size:0.65rem; color:var(--gray); font-weight:600; text-transform:uppercase; }

        /* Dark theme Detailed Quality Inspection modal scoped under #detailOverlay */
        #detailOverlay .detail-modal {
            background: #10261D !important; /* Charcoal forest green background */
            color: #F4F4F0 !important; /* Off-white text */
            border: 1px solid rgba(167, 201, 177, 0.18) !important; /* Sage border */
            max-width: 800px !important;
            width: 95% !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            border-radius: 16px !important;
        }
        #detailOverlay .detail-modal-header {
            background: #123524 !important; /* Dark header panel */
            border-bottom: 1px solid rgba(167, 201, 177, 0.18) !important;
            color: #F4F4F0 !important;
            padding: 1.1rem 1.5rem !important;
            border-top-left-radius: 15px !important;
            border-top-right-radius: 15px !important;
        }
        #detailOverlay .detail-modal-header h3 {
            color: #F4F4F0 !important;
            font-size: 1.2rem !important;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            margin: 0;
        }
        #detailOverlay .modal-close-btn {
            color: rgba(244, 244, 240, 0.70) !important;
            background: none !important;
            border: none !important;
            font-size: 1.6rem !important;
            cursor: pointer !important;
            opacity: 0.8 !important;
        }
        #detailOverlay .modal-close-btn:hover {
            color: #F4F4F0 !important;
            opacity: 1 !important;
        }
        #detailOverlay .detail-modal-body {
            padding: 1.5rem !important;
        }

        /* Layout Grid */
        .inspect-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .inspect-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        /* Column Titles */
        .column-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: rgba(244, 244, 240, 0.70);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(167, 201, 177, 0.18);
            padding-bottom: 0.5rem;
        }

        /* Image Preview Box */
        .inspect-img-box {
            background: #0d1e17; /* Slate green */
            border: 1px solid rgba(167, 201, 177, 0.18);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 250px;
        }
        .inspect-img-box img {
            max-height: 250px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        .inspect-img-caption {
            font-size: 0.75rem;
            color: rgba(244, 244, 240, 0.50);
            text-align: center;
            line-height: 1.5;
            margin-top: 0.5rem;
        }

        /* Coefficient Section */
        .coefficient-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            background: #163B2A; /* Card background */
            padding: 14px 20px;
            border-radius: 12px;
            border: 1px solid rgba(167, 201, 177, 0.18);
        }
        .overall-score-huge {
            font-size: 3.5rem;
            font-weight: 800;
            color: #2FBF71; /* Accent green */
            line-height: 1;
            font-feature-settings: "tnum";
        }
        .overall-score-badge-wrap {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .quality-badge {
            background: rgba(47, 191, 113, 0.15);
            color: #2FBF71;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            border: 1px solid rgba(47, 191, 113, 0.30);
            display: inline-block;
            text-align: center;
            width: fit-content;
            letter-spacing: 0.05em;
        }
        .quality-badge-desc {
            font-size: 0.75rem;
            color: rgba(244, 244, 240, 0.70);
        }

        /* Dark Progress Bars */
        .metric-item {
            margin-bottom: 1.25rem;
        }
        .metric-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 700;
            color: #F4F4F0;
            margin-bottom: 6px;
        }
        .metric-info span:last-child {
            color: #2FBF71;
        }
        .metric-bar-track {
            height: 6px;
            background: #0d1e17;
            border-radius: 3px;
            overflow: hidden;
            width: 100%;
        }
        .metric-bar-fill {
            height: 100%;
            background: #2FBF71;
            border-radius: 3px;
            transition: width 0.8s ease-out;
            width: 0%;
        }

        /* Lab Analysis Notes Box */
        .analysis-notes-box {
            background: #163B2A;
            border: 1px solid rgba(167, 201, 177, 0.18);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .analysis-notes-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #F4F4F0;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(167, 201, 177, 0.18);
            padding-bottom: 0.6rem;
        }
        .notes-content-wrap {
            margin-bottom: 1.5rem;
        }
        .notes-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: rgba(244, 244, 240, 0.70);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .notes-text {
            font-size: 0.88rem;
            color: #F4F4F0;
            line-height: 1.55;
            background: #0d1e17;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            min-height: 45px;
            border-left: 4px solid #2FBF71;
            border-top: none;
            border-right: none;
            border-bottom: none;
        }

        /* Info Details Grid */
        .info-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem 2.5rem;
            font-size: 0.82rem;
        }
        @media (max-width: 600px) {
            .info-details-grid {
                grid-template-columns: 1fr;
            }
        }
        .info-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(167, 201, 177, 0.10);
            align-items: center;
        }
        .info-detail-label {
            color: rgba(244, 244, 240, 0.70);
            font-weight: 600;
        }
        .info-detail-value {
            color: #F4F4F0;
            font-weight: 700;
            text-align: right;
        }

        /* Student chip */
        .student-chip {
            background: rgba(47, 191, 113, 0.12);
            color: #2FBF71;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid rgba(47, 191, 113, 0.25);
            text-transform: lowercase;
        }
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
                            <?php if ($img['image_path'] && $img['image_exists']): ?>
                                <img src="../view_fingerprint.php?test_id=<?= $img['id'] ?>" alt="Fingerprint image">
                            <?php else: ?>
                                <div style="height:130px; background:#f4f6f0; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--gray); font-size:0.72rem; text-align:center; padding:10px;">
                                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gray); margin-bottom: 6px;">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                    <span>No image preview available</span>
                                </div>
                            <?php endif; ?>
                            <div class="image-thumb-info">
                                <div class="image-thumb-label" style="font-size: 0.8rem; font-weight:700; color:var(--dark-green);"><?= htmlspecialchars($img['trial_id']) ?></div>
                                <div class="image-thumb-label" title="<?= htmlspecialchars($img['image_label'] ?: 'No Label') ?>"><?= htmlspecialchars($img['image_label'] ?: 'Untitled') ?></div>
                                <div style="font-size:0.7rem; color:var(--gray); text-transform:capitalize; margin-bottom: 2px;">
                                    <?= htmlspecialchars($img['powder_type']) ?> | <?= htmlspecialchars($img['surface_type']) ?>
                                </div>
                                <div class="image-thumb-date" style="font-size:0.68rem;"><?= date('M d, Y', strtotime($img['submitted_at'])) ?></div>
                                <div style="margin-top: 4px; display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                                    <span class="badge badge-<?= $img['status'] ?>" style="font-size: 0.65rem; padding: 2px 6px; flex-shrink: 0;">
                                        <?= $img['status'] === 'pending_validation' ? 'Pending Validation' : ($img['status'] === 'needs_revision' ? 'Needs Revision' : ucfirst($img['status'])) ?>
                                    </span>
                                </div>
                                <div style="margin-top: 8px;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick='openDetailModal(<?= htmlspecialchars(json_encode($img), ENT_QUOTES, "UTF-8") ?>)' style="width: 100%; padding: 0.35rem 0.5rem; font-size: 0.72rem; border-radius: 6px; justify-content: center;">View Details</button>
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
    
    // Explicitly set flags for modal usage
    data.image_exists = true;
    
    card.innerHTML = `
        <img src="../view_fingerprint.php?test_id=${data.id}" alt="Fingerprint image">
        <div class="image-thumb-info">
            <div class="image-thumb-label" style="font-size: 0.8rem; font-weight:700; color:var(--dark-green);">${data.trial_id}</div>
            <div class="image-thumb-label" title="${data.image_label || 'Untitled'}">${data.image_label || 'Untitled'}</div>
            <div style="font-size:0.7rem; color:var(--gray); text-transform:capitalize; margin-bottom: 2px;">
                ${data.powder_type} | ${data.surface_type}
            </div>
            <div class="image-thumb-date" style="font-size:0.68rem;">Just now</div>
            <div style="margin-top: 4px; display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                <span class="badge badge-pending_validation" style="font-size: 0.65rem; padding: 2px 6px; flex-shrink: 0;">
                    Pending Validation
                </span>
            </div>
            <div style="margin-top: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" style="width: 100%; padding: 0.35rem 0.5rem; font-size: 0.72rem; border-radius: 6px; justify-content: center;">View Details</button>
            </div>
        </div>
    `;
    
    // Bind modal trigger to card button
    const btn = card.querySelector('button');
    if (btn) {
        btn.onclick = () => openDetailModal(data);
    }
    
    gallery.insertBefore(card, gallery.firstChild);

    // Update count span
    const countSpan = document.querySelector('.card-title-wrap span');
    if (countSpan) {
        const count = gallery.querySelectorAll('.image-thumb').length;
        countSpan.textContent = `${count} image${count !== 1 ? 's' : ''}`;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function openDetailModal(row) {
    document.getElementById('detailOverlay').classList.add('open');

    const username = row.student_email ? row.student_email.split('@')[0] : (row.student_name ? row.student_name.toLowerCase().replace(/\s+/g, '') : 'student');
    document.getElementById('det-student-chip').textContent = username;

    document.getElementById('det-trial-id').textContent = row.trial_id || 'TR-' + String(row.id).padStart(4, '0');
    document.getElementById('det-powder').textContent = row.powder_type || '';
    document.getElementById('det-surface').textContent = row.surface_type || '';
    document.getElementById('det-label').textContent = row.image_label || 'Untitled';
    
    const evalDate = row.ai_evaluated_at ? new Date(row.ai_evaluated_at.replace(/-/g, "/")).toLocaleString() : (row.submitted_at ? new Date(row.submitted_at.replace(/-/g, "/")).toLocaleString() : '—');
    document.getElementById('det-evaluation-date').textContent = evalDate;

    // Image filename with [View Image] link
    const imgFilename = row.image_path ? row.image_path.split('/').pop() : '—';
    const imgPathEl = document.getElementById('det-image-path');
    if (imgPathEl) {
        if (row.image_path && row.image_exists) {
            imgPathEl.innerHTML = `${imgFilename} <a href="../view_fingerprint.php?test_id=${row.id}" target="_blank" style="color: #2FBF71; text-decoration: underline; margin-left: 8px; font-size: 0.75rem; font-weight: 600;">[View Image]</a>`;
        } else {
            imgPathEl.textContent = imgFilename;
        }
    }

    // Image viewer logic
    const img = document.getElementById('det-img');
    const imgWrapper = document.getElementById('det-img-wrapper');
    const imgMissing = document.getElementById('det-img-missing');
    
    if (row.image_path && row.image_exists) {
        img.src = '../view_fingerprint.php?test_id=' + row.id;
        imgWrapper.style.display = 'flex';
        if (imgMissing) imgMissing.style.display = 'none';
    } else {
        imgWrapper.style.display = 'none';
        if (imgMissing) imgMissing.style.display = 'block';
    }

    // AI Preliminary Result Metrics
    const aiAccuracy = row.ai_accuracy_score !== null ? parseFloat(row.ai_accuracy_score) : (row.accuracy_score !== null ? parseFloat(row.accuracy_score) : 0);
    const aiClarity = row.ridge_clarity_score !== null ? parseFloat(row.ridge_clarity_score) : 0;
    const aiVisibility = row.visibility_score !== null ? parseFloat(row.visibility_score) : 0;
    const aiAdhesion = row.adhesion_score !== null ? parseFloat(row.adhesion_score) : 0;
    const aiContrast = row.contrast_score !== null ? parseFloat(row.contrast_score) : 0;

    // Faculty Final Evaluation Metrics (fallback to AI scores for older approved records)
    const hasFacultyScores = row.faculty_final_score !== null;
    const fAccuracy = hasFacultyScores ? parseFloat(row.faculty_final_score) : aiAccuracy;
    const fClarity = hasFacultyScores && row.faculty_ridge_clarity_score !== null ? parseFloat(row.faculty_ridge_clarity_score) : aiClarity;
    const fVisibility = hasFacultyScores && row.faculty_visibility_score !== null ? parseFloat(row.faculty_visibility_score) : aiVisibility;
    const fAdhesion = hasFacultyScores && row.faculty_adhesion_score !== null ? parseFloat(row.faculty_adhesion_score) : aiAdhesion;
    const fContrast = hasFacultyScores && row.faculty_contrast_score !== null ? parseFloat(row.faculty_contrast_score) : aiContrast;

    // Render comparison list or details
    const aiDetailsHtml = `
        <div style="margin-top: 1rem; border-top: 1px solid rgba(167, 201, 177, 0.18); padding-top: 0.85rem;">
            <div style="font-size: 0.72rem; font-weight: 700; color: rgba(244, 244, 240, 0.70); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem;">AI Preliminary Results (Read-Only)</div>
            <div style="display: flex; flex-direction: column; gap: 4px; font-size: 0.8rem; color: #F4F4F0;">
                <div style="display: flex; justify-content: space-between;"><span>AI Accuracy:</span> <strong>${aiAccuracy > 0 ? aiAccuracy.toFixed(1) + '%' : '—'}</strong></div>
                <div style="display: flex; justify-content: space-between;"><span>AI Ridge Clarity:</span> <span>${aiClarity > 0 ? aiClarity.toFixed(1) + '%' : '—'}</span></div>
                <div style="display: flex; justify-content: space-between;"><span>AI Visibility:</span> <span>${aiVisibility > 0 ? aiVisibility.toFixed(1) + '%' : '—'}</span></div>
                <div style="display: flex; justify-content: space-between;"><span>AI Adhesion:</span> <span>${aiAdhesion > 0 ? aiAdhesion.toFixed(1) + '%' : '—'}</span></div>
                <div style="display: flex; justify-content: space-between;"><span>AI Contrast:</span> <span>${aiContrast > 0 ? aiContrast.toFixed(1) + '%' : '—'}</span></div>
            </div>
        </div>
    `;

    const extraAiContainer = document.getElementById('det-ai-prelim-container');
    if (extraAiContainer) {
        extraAiContainer.innerHTML = aiDetailsHtml;
    }

    // Update main progress bars to show Faculty Final score if approved, otherwise show placeholder or hide
    const overallScoreHuge = document.getElementById('det-val-accuracy-huge');
    const badgeEl = document.getElementById('det-val-quality-badge');
    const badgeDesc = document.getElementById('det-quality-badge-desc');

    if (row.status === 'approved') {
        overallScoreHuge.style.display = 'block';
        document.getElementById('det-metrics-container').style.display = 'block';

        overallScoreHuge.textContent = Math.round(fAccuracy) + '%';
        badgeEl.textContent = 'APPROVED';
        badgeEl.style.color = '#2FBF71';
        badgeEl.style.borderColor = 'rgba(47, 191, 113, 0.25)';
        badgeEl.style.background = 'rgba(47, 191, 113, 0.12)';
        if (badgeDesc) badgeDesc.textContent = 'Faculty Final Score';

        // Set text labels
        document.getElementById('det-val-clarity').textContent = fClarity > 0 ? fClarity.toFixed(1) + '%' : '—';
        document.getElementById('det-val-contrast').textContent = fContrast > 0 ? fContrast.toFixed(1) + '%' : '—';
        document.getElementById('det-val-visibility').textContent = fVisibility > 0 ? fVisibility.toFixed(1) + '%' : '—';
        document.getElementById('det-val-sharpness').textContent = fClarity > 0 ? fClarity.toFixed(1) + '%' : '—';
        document.getElementById('det-val-adhesion').textContent = fAdhesion > 0 ? fAdhesion.toFixed(1) + '%' : '—';

        // Set progress bar widths
        document.getElementById('det-fill-clarity').style.width = fClarity + '%';
        document.getElementById('det-fill-contrast').style.width = fContrast + '%';
        document.getElementById('det-fill-visibility').style.width = fVisibility + '%';
        document.getElementById('det-fill-sharpness').style.width = fClarity + '%';
        document.getElementById('det-fill-adhesion').style.width = fAdhesion + '%';
        
    } else {
        overallScoreHuge.style.display = 'none';
        document.getElementById('det-metrics-container').style.display = 'none';
        overallScoreHuge.textContent = '—';
        
        if (row.status === 'pending_validation') {
            badgeEl.textContent = 'AWAITING REVIEW';
            badgeEl.style.color = '#f59e0b';
            badgeEl.style.borderColor = 'rgba(245, 158, 11, 0.25)';
            badgeEl.style.background = 'rgba(245, 158, 11, 0.12)';
            if (badgeDesc) badgeDesc.textContent = 'Awaiting Faculty Validation';
        } else if (row.status === 'rejected') {
            badgeEl.textContent = 'REJECTED';
            badgeEl.style.color = '#ef4444';
            badgeEl.style.borderColor = 'rgba(239, 68, 68, 0.25)';
            badgeEl.style.background = 'rgba(239, 68, 68, 0.12)';
            if (badgeDesc) badgeDesc.textContent = 'Rejected';
        } else if (row.status === 'needs_revision') {
            badgeEl.textContent = 'REVISION NEEDED';
            badgeEl.style.color = '#3b82f6';
            badgeEl.style.borderColor = 'rgba(59, 130, 246, 0.25)';
            badgeEl.style.background = 'rgba(59, 130, 246, 0.12)';
            if (badgeDesc) badgeDesc.textContent = 'Needs Revision';
        }

        // Set progress bars to 0% as they are not approved yet
        document.getElementById('det-val-clarity').textContent = '—';
        document.getElementById('det-val-contrast').textContent = '—';
        document.getElementById('det-val-visibility').textContent = '—';
        document.getElementById('det-val-sharpness').textContent = '—';
        document.getElementById('det-val-adhesion').textContent = '—';

        document.getElementById('det-fill-clarity').style.width = '0%';
        document.getElementById('det-fill-contrast').style.width = '0%';
        document.getElementById('det-fill-visibility').style.width = '0%';
        document.getElementById('det-fill-sharpness').style.width = '0%';
        document.getElementById('det-fill-adhesion').style.width = '0%';
    }

    document.getElementById('det-ai-score').textContent = aiAccuracy > 0 ? aiAccuracy.toFixed(1) + '%' : 'Awaiting AI Evaluation';

    // Conditional elements based on status
    const statusVal = document.getElementById('det-status');
    const reviewerRow = document.getElementById('det-reviewer-row');
    const validatedAtRow = document.getElementById('det-validated-date-row');
    const remarksRow = document.getElementById('det-remarks');
    const remarksLabel = document.getElementById('det-remarks-label');
    const facultyScoreRow = document.getElementById('det-faculty-row');

    // Simple remarks formatter helper
    function formatFacultyRemarks(remarks) {
        if (!remarks) return 'No remarks provided.';
        const clean = remarks.trim().toLowerCase();
        if (clean === 'good' || clean === 'ok' || clean === 'okay') {
            return 'The fingerprint image shows acceptable quality for forensic evaluation.';
        }
        if (clean === 'poor' || clean === 'blurry') {
            return 'The fingerprint image has insufficient ridge clarity and is unclear for standard evaluation.';
        }
        if (clean === 'rejected') {
            return 'The submitted print was rejected due to quality standard issues.';
        }
        if (clean === 'excellent') {
            return 'The fingerprint shows excellent ridge flow clarity and visibility.';
        }
        return escapeHtml(remarks).replace(/\n/g, '<br>');
    }

    if (row.status === 'pending_validation') {
        statusVal.innerHTML = '<span class="badge badge-pending_validation">Pending Validation</span>';
        reviewerRow.style.display = 'none';
        validatedAtRow.style.display = 'none';
        facultyScoreRow.style.display = 'flex';
        
        document.getElementById('det-faculty-score-label').textContent = 'Faculty Final Score:';
        document.getElementById('det-faculty-score').textContent = 'Awaiting Faculty Validation';
        
        remarksLabel.textContent = 'Notes:';
        remarksRow.innerHTML = 'This record is still awaiting faculty review.';
    } else {
        reviewerRow.style.display = 'flex';
        validatedAtRow.style.display = 'flex';
        
        document.getElementById('det-reviewer').textContent = row.faculty_validator || 'Faculty Reviewer';
        document.getElementById('det-validated-at').textContent = row.validated_at ? new Date(row.validated_at.replace(/-/g, "/")).toLocaleString() : '—';
        
        remarksLabel.textContent = 'Faculty Remarks:';
        remarksRow.innerHTML = formatFacultyRemarks(row.faculty_remarks);

        if (row.status === 'approved') {
            statusVal.innerHTML = '<span class="badge badge-approved">Approved</span>';
            facultyScoreRow.style.display = 'flex';
            document.getElementById('det-faculty-score-label').textContent = 'Faculty Final Score:';
            document.getElementById('det-faculty-score').textContent = fAccuracy.toFixed(1) + '%';
        } else if (row.status === 'rejected') {
            statusVal.innerHTML = '<span class="badge badge-rejected">Rejected</span>';
            facultyScoreRow.style.display = 'none';
            remarksRow.innerHTML += `<div style="margin-top: 12px; padding: 10px 14px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; border-radius: 6px; color: #fca5a5; font-size: 0.82rem;">
                <strong>Action Needed:</strong> Please upload a clearer fingerprint image for reevaluation.
            </div>`;
        } else if (row.status === 'needs_revision') {
            statusVal.innerHTML = '<span class="badge badge-needs_revision">Needs Revision</span>';
            facultyScoreRow.style.display = 'none';
            remarksRow.innerHTML += `<div style="margin-top: 12px; padding: 10px 14px; background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; border-radius: 6px; color: #93c5fd; font-size: 0.82rem;">
                <strong>Action Needed:</strong> Revise the details or re-upload a clearer image according to feedback.
            </div>`;
        }
    }
}

function closeDetailModal() {
    document.getElementById('detailOverlay').classList.remove('open');
}

document.getElementById('detailOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('detailOverlay')) closeDetailModal();
});
</script>

<!-- VIEW DETAILS MODAL -->
<div class="detail-overlay" id="detailOverlay">
    <div class="detail-modal">
        <div class="detail-modal-header">
            <h3>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981; margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Detailed Quality Inspection
            </h3>
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="student-chip" id="det-student-chip">student</span>
                <button class="modal-close-btn" onclick="closeDetailModal()">&times;</button>
            </div>
        </div>
        <div class="detail-modal-body">
            <div id="modalContent">
                <div class="inspect-grid">
                    <!-- Left Column: Minutiae Mapping -->
                    <div>
                        <div class="column-title">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            MINUTIAE MAPPING
                        </div>
                        <div class="inspect-img-box" id="det-img-wrapper">
                            <img src="" alt="Fingerprint Preview" id="det-img">
                        </div>
                        <div style="text-align:center; color: var(--gray); font-weight:600; margin-bottom:1rem; display:none;" id="det-img-missing">
                            No image preview available
                        </div>
                        <div class="inspect-img-caption">
                            Fingerprint image preview used for quality inspection.
                        </div>
                    </div>

                    <!-- Right Column: Evaluation Coefficient -->
                    <div>
                        <div class="column-title">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#2FBF71;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            EVALUATION COEFFICIENT
                        </div>
                        
                        <div class="coefficient-header" id="det-coefficient-container">
                            <div class="overall-score-huge" id="det-val-accuracy-huge">—</div>
                            <div class="overall-score-badge-wrap">
                                <span class="quality-badge" id="det-val-quality-badge">GOOD</span>
                                <span class="quality-badge-desc" id="det-quality-badge-desc">Faculty Final Score</span>
                            </div>
                        </div>

                        <!-- Progress Bars -->
                        <div id="det-metrics-container">
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span>Ridge Clarity</span>
                                    <span id="det-val-clarity">—</span>
                                </div>
                                <div class="metric-bar-track">
                                    <div class="metric-bar-fill" id="det-fill-clarity"></div>
                                </div>
                            </div>

                            <div class="metric-item">
                                <div class="metric-info">
                                    <span>Contrast Quality</span>
                                    <span id="det-val-contrast">—</span>
                                </div>
                                <div class="metric-bar-track">
                                    <div class="metric-bar-fill" id="det-fill-contrast"></div>
                                </div>
                            </div>

                            <div class="metric-item">
                                <div class="metric-info">
                                    <span>Minutiae Visibility</span>
                                    <span id="det-val-visibility">—</span>
                                </div>
                                <div class="metric-bar-track">
                                    <div class="metric-bar-fill" id="det-fill-visibility"></div>
                                </div>
                            </div>

                            <div class="metric-item">
                                <div class="metric-info">
                                    <span>Fingerprint Sharpness</span>
                                    <span id="det-val-sharpness">—</span>
                                </div>
                                <div class="metric-bar-track">
                                    <div class="metric-bar-fill" id="det-fill-sharpness"></div>
                                </div>
                            </div>

                            <div class="metric-item">
                                <div class="metric-info">
                                    <span>Adhesion Quality</span>
                                    <span id="det-val-adhesion">—</span>
                                </div>
                                <div class="metric-bar-track">
                                    <div class="metric-bar-fill" id="det-fill-adhesion"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AI Preliminary Results Container -->
                        <div id="det-ai-prelim-container"></div>
                    </div>
                </div>

                <!-- Bottom Section: Lab Analysis Notes -->
                <div class="analysis-notes-box">
                    <div class="analysis-notes-title">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Lab Analysis Notes
                    </div>

                    <div class="notes-content-wrap">
                        <div class="notes-label" id="det-remarks-label">Faculty Remarks:</div>
                        <div class="notes-text" id="det-remarks"></div>
                    </div>

                    <!-- Details Grid -->
                    <div class="info-details-grid">
                        <div class="info-detail-row">
                            <span class="info-detail-label">Trial ID:</span>
                            <span class="info-detail-value" id="det-trial-id"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Powder Type:</span>
                            <span class="info-detail-value" id="det-powder" style="text-transform: capitalize;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Surface Type:</span>
                            <span class="info-detail-value" id="det-surface" style="text-transform: capitalize;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Image Label:</span>
                            <span class="info-detail-value" id="det-label"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Status:</span>
                            <span class="info-detail-value" id="det-status"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">AI Preliminary Score:</span>
                            <span class="info-detail-value" id="det-ai-score"></span>
                        </div>
                        <div class="info-detail-row" id="det-faculty-row">
                            <span class="info-detail-label" id="det-faculty-score-label">Faculty Final Score:</span>
                            <span class="info-detail-value" id="det-faculty-score"></span>
                        </div>
                        <div class="info-detail-row" id="det-reviewer-row">
                            <span class="info-detail-label">Faculty Reviewer:</span>
                            <span class="info-detail-value" id="det-reviewer"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Image File:</span>
                            <span class="info-detail-value" id="det-image-path" style="font-family: monospace; font-size: 0.75rem; color:#2FBF71; word-break: break-all;"></span>
                        </div>
                        <div class="info-detail-row">
                            <span class="info-detail-label">Evaluation Date:</span>
                            <span class="info-detail-value" id="det-evaluation-date"></span>
                        </div>
                        <div class="info-detail-row" id="det-validated-date-row">
                            <span class="info-detail-label">Validation Date:</span>
                            <span class="info-detail-value" id="det-validated-at"></span>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 1.25rem; font-size: 0.78rem; color: rgba(244, 244, 240, 0.5); font-style: italic;" class="no-print">
                    This result is read-only and based on faculty-approved evaluation.
                </div>

                <div style="display:flex; gap:10px; margin-top:1rem;" class="no-print">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailModal()" style="flex:1; background:#163B2A; border-color:rgba(167, 201, 177, 0.25); color:#F4F4F0;">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
</body>
</html>
