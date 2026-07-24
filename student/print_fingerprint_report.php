<?php
// student/print_fingerprint_report.php — Print Trial Report
ob_start();
ini_set('display_errors', '0');
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

// Themed error display function that cleans output buffer
function showThemedError($error_message, $http_code = 500)
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($http_code);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Green Forensics</title>
        <style>
            body {
                background-color: #f3f4f6;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                font-family: system-ui, -apple-system, sans-serif;
            }

            .error-card {
                background: white;
                padding: 2.5rem;
                border-radius: 16px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
                max-width: 450px;
                width: 90%;
                text-align: center;
                border-top: 5px solid #1b4332;
            }

            .error-icon {
                color: #ef4444;
                margin-bottom: 1.25rem;
                display: flex;
                justify-content: center;
            }

            .error-title {
                color: #1b4332;
                font-size: 1.25rem;
                font-weight: 700;
                margin-bottom: 0.75rem;
            }

            .error-desc {
                color: #4b5563;
                font-size: 0.9rem;
                line-height: 1.5;
                margin-bottom: 1.5rem;
            }

            .btn-back {
                display: inline-block;
                background: #1b4332;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                font-weight: 600;
                border-radius: 8px;
                font-size: 0.88rem;
                transition: background 0.2s;
            }

            .btn-back:hover {
                background: #2d6a4f;
            }
        </style>
    </head>

    <body>
        <div class="error-card">
            <div class="error-icon">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
            </div>
            <div class="error-title">Evaluation Report Error</div>
            <div class="error-desc"><?= htmlspecialchars($error_message) ?></div>
            <a href="student_dashboard.php" class="btn-back">Go back to Dashboard</a>
        </div>
    </body>

    </html>
    <?php
    exit;
}

$student_id = $_SESSION['user_id'] ?? 0;
$test_id = isset($_GET['test_id']) ? (int) $_GET['test_id'] : 0;

if ($test_id <= 0) {
    error_log("print_fingerprint_report.php: Invalid test_id = " . $test_id);
    showThemedError("Unable to generate Fingerprint Evaluation Report. Please try again later or contact the System Administrator.", 400);
}

try {
    // Select record details ensuring the logged-in student owns it
    $stmt = $pdo->prepare("
        SELECT 
            ft.*, 
            COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks, 
            faculty.full_name AS faculty_reviewer,
            student.full_name AS student_name,
            student.email AS student_email
        FROM fingerprint_tests ft
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE ft.id = ? AND ft.student_id = ?
    ");
    $stmt->execute([$test_id, $student_id]);
    $trial = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trial) {
        error_log("print_fingerprint_report.php: Unauthorized access or test not found. test_id = $test_id, student_id = $student_id");
        showThemedError("Unable to generate Fingerprint Evaluation Report. Please try again later or contact the System Administrator.", 403);
    }

    // Verify image file existence
    $image_exists = false;
    if (!empty($trial['image_path'])) {
        $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $trial['image_path'];
        if (file_exists($filePath)) {
            $image_exists = true;
        }
    }
} catch (PDOException $e) {
    error_log("print_fingerprint_report.php Database Error: " . $e->getMessage());
    showThemedError("Unable to load the report at this time.", 500);
} catch (Exception $e) {
    error_log("print_fingerprint_report.php Exception: " . $e->getMessage());
    showThemedError("Unable to generate Fingerprint Evaluation Report. Please try again later or contact the System Administrator.", 500);
}

function safeFloat($value, $default = 0.0)
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_numeric($value)) {
        return (float) $value;
    }
    return $default;
}

function formatScore($value)
{
    if ($value === null || $value === '') {
        return 'N/A';
    }
    if (!is_numeric($value)) {
        return 'N/A';
    }
    return number_format((float) $value, 1) . '%';
}

// Format trial ID
$trial_id_str = $trial['trial_id'] ?: 'TR-' . str_pad($trial['id'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Report - <?= htmlspecialchars($trial_id_str) ?></title>
    <style>
        /* Base Styling for screen preview */
        body {
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            font-family: 'Inter', Arial, sans-serif;
            color: #1f2937;
        }

        .print-btn-container {
            max-width: 794px;
            margin: 0 auto 15px auto;
            text-align: right;
        }

        .btn-print {
            background-color: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: background 0.2s;
        }

        .btn-print:hover {
            background-color: #059669;
        }

        .print-report {
            background: white;
            width: 100%;
            max-width: 794px;
            /* A4 width at 96 DPI */
            margin: 0 auto;
            padding: 40px;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 8px;
            color: #12372a;
        }

        /* Document Header */
        .report-header {
            text-align: center;
            border-bottom: 2px solid #12372a;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .report-header h1 {
            margin: 0;
            font-size: 1.6rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #12372a;
        }

        .report-header h2 {
            margin: 5px 0;
            font-size: 1.25rem;
            color: #2d6a4f;
            font-weight: 600;
        }

        .report-header p {
            margin: 5px 0 0 0;
            font-size: 0.85rem;
            color: #52b788;
            font-style: italic;
        }

        /* Two column layout for details and image */
        .report-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        /* Information Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .info-table th,
        .info-table td {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-table th {
            color: #2d6a4f;
            font-weight: 600;
            width: 35%;
        }

        .info-table td {
            color: #4b5563;
        }

        /* Image Display */
        .image-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9fafb;
            height: 100%;
            min-height: 220px;
            box-sizing: border-box;
        }

        .image-container img {
            max-width: 240px;
            max-height: 240px;
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .image-missing-text {
            color: #ef4444;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
        }

        /* Evaluation Scores */
        .section-title {
            font-size: 1rem;
            color: #12372a;
            border-bottom: 1px solid #2d6a4f;
            padding-bottom: 6px;
            margin: 25px 0 12px 0;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .scores-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .score-card {
            background-color: #f4f7f5;
            border: 1px solid #d2e2d5;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
        }

        .score-card.main-score {
            background-color: #d2e2d5;
            border-color: #b5cbb9;
            grid-column: span 3;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
        }

        .score-card-label {
            font-size: 0.78rem;
            color: #2d6a4f;
            font-weight: 600;
            text-transform: uppercase;
        }

        .score-card-value {
            font-size: 1.15rem;
            font-weight: 700;
            color: #12372a;
            margin-top: 4px;
        }

        .score-card.main-score .score-card-value {
            font-size: 1.8rem;
            color: #1b4332;
            margin-top: 0;
        }

        .score-card.main-score .score-card-label {
            font-size: 0.9rem;
        }

        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending_validation {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .status-needs_revision {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        /* Remarks Box */
        .remarks-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            font-size: 0.88rem;
            line-height: 1.5;
            color: #374151;
            margin-top: 10px;
        }

        .remarks-box strong {
            color: #12372a;
            display: block;
            margin-bottom: 6px;
        }

        @page {
            size: A4 portrait;
            margin: 6mm;
        }

        @media print {

            html,
            body {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                background: white;
                font-family: Arial, sans-serif;
                color: #12372a;
            }

            .print-report {
                width: 198mm;
                min-height: 285mm;
                margin: 0 auto;
                padding: 6mm;
                box-sizing: border-box;
                box-shadow: none;
                transform: none;
            }

            .no-print,
            button,
            .btn,
            .support-assistant,
            .chat-widget,
            .sidebar,
            .modal-overlay,
            .print-btn-container,
            .dashboard-bg,
            .dashboard-background {
                display: none !important;
            }

            /* Optimized layout, larger and highly readable */
            .report-header {
                padding-bottom: 12px;
                margin-bottom: 15px;
                border-bottom: 2px solid #12372a;
            }

            .report-header h1 {
                font-size: 20px !important;
            }

            .report-header h2 {
                font-size: 14px !important;
                margin: 4px 0 2px 0;
            }

            .report-header p {
                font-size: 13px !important;
                margin: 2px 0 0 0;
            }

            .report-grid {
                grid-template-columns: 1.2fr 0.8fr;
                gap: 20px;
                margin-bottom: 15px;
            }

            .info-table th,
            .info-table td {
                padding: 6px 8px;
                font-size: 12px !important;
            }

            .image-container {
                padding: 12px;
                min-height: 200px;
            }

            .image-container img {
                max-width: 210px !important;
                max-height: 230px !important;
                object-fit: contain !important;
            }

            .section-title {
                font-size: 13px !important;
                padding-bottom: 4px;
                margin: 15px 0 10px 0;
                border-bottom: 1px solid #2d6a4f;
            }

            .scores-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
                margin-bottom: 12px;
            }

            .score-card {
                padding: 10px 8px;
                border-radius: 4px;
            }

            .score-card-label {
                font-size: 10px !important;
            }

            .score-card-value {
                font-size: 20px !important;
            }

            .score-card.main-score {
                padding: 12px 20px;
            }

            .score-card.main-score .score-card-label {
                font-size: 13px !important;
            }

            .score-card.main-score .score-card-value {
                font-size: 24px !important;
            }

            .remarks-box {
                padding: 12px;
                font-size: 12px !important;
                margin-top: 8px;
                border-radius: 4px;
            }

            .remarks-text {
                max-height: 120px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                font-size: 12px !important;
                display: -webkit-box;
                -webkit-line-clamp: 5;
                -webkit-box-orient: vertical;
            }

            /* Prevent page breaks */
            .print-report,
            .report-section,
            .info-section,
            .metric-grid,
            .remarks-section {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }

        /* Screen mobile friendly styling */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
                background-color: #f3f4f6;
            }

            .print-report {
                padding: 20px;
                box-shadow: none;
                border-radius: 8px;
                max-width: 100%;
            }

            .report-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .image-container {
                min-height: auto;
                padding: 15px;
            }

            .image-container img {
                max-width: 100%;
                height: auto;
            }

            .scores-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .score-card.main-score {
                grid-column: span 2;
            }

            .print-btn-container {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000;
                margin: 0;
            }

            .btn-print {
                background-color: #1b4332;
                color: white;
                border: none;
                padding: 12px 24px;
                font-size: 0.95rem;
                font-weight: 700;
                border-radius: 50px;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
                display: flex;
                align-items: center;
                gap: 8px;
            }
        }
    </style>
</head>

<body>

    <div class="print-btn-container no-print">
        <button class="btn-print" onclick="window.print()">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round"
                style="display:inline-block; vertical-align:middle; margin-right:4px;">
                <polyline points="6 9 6 2 18 2 18 9" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <rect x="6" y="14" width="12" height="8" />
            </svg>
            Print / Download PDF
        </button>
    </div>

    <div class="print-report">
        <!-- Header -->
        <div class="report-header">
            <h1>Green Forensics Evaluating System</h1>
            <h2>Fingerprint Evaluation Report</h2>
            <p>Innovative Sustainable Fingerprint Powder Using Chicken Eggshell Waste</p>
        </div>

        <!-- Grid layout for Details & Image -->
        <div class="report-grid info-section">
            <!-- Details Table -->
            <div>
                <table class="info-table">
                    <tr>
                        <th>Trial ID</th>
                        <td><strong><?= htmlspecialchars($trial_id_str) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Student Name</th>
                        <td><?= htmlspecialchars($trial['student_name'] ?: 'Criminology Student') ?></td>
                    </tr>
                    <tr>
                        <th>Powder Type</th>
                        <td style="text-transform: capitalize;"><?= htmlspecialchars($trial['powder_type']) ?></td>
                    </tr>
                    <tr>
                        <th>Surface Type</th>
                        <td style="text-transform: capitalize;"><?= htmlspecialchars($trial['surface_type']) ?></td>
                    </tr>
                    <tr>
                        <th>Image Label</th>
                        <td><?= htmlspecialchars($trial['image_label'] ?: 'Untitled') ?></td>
                    </tr>
                    <tr>
                        <th>Date Submitted</th>
                        <td><?= date('F d, Y h:i A', strtotime($trial['submitted_at'])) ?></td>
                    </tr>
                    <tr>
                        <th>Validation Status</th>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($trial['status']) ?>">
                                <?php
                                if ($trial['status'] === 'pending_validation') {
                                    echo 'Pending Validation';
                                } elseif ($trial['status'] === 'needs_revision') {
                                    echo 'Needs Revision';
                                } else {
                                    echo ucfirst($trial['status']);
                                }
                                ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Faculty Reviewer</th>
                        <td><?= htmlspecialchars($trial['faculty_reviewer'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th>Validation Date</th>
                        <td><?= $trial['validated_at'] ? date('F d, Y h:i A', strtotime($trial['validated_at'])) : '—' ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Fingerprint Image -->
            <div class="image-container">
                <?php if ($image_exists): ?>
                    <img id="report-image" src="../view_fingerprint.php?test_id=<?= $trial['id'] ?>"
                        alt="Fingerprint Image">
                <?php else: ?>
                    <div
                        style="display:flex; flex-direction:column; align-items:center; justify-content:center; color:#9ca3af; text-align:center; height:100%; width:100%;">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round"
                            style="margin-bottom:8px;color:#d1d5db; display:block; margin-left:auto; margin-right:auto;">
                            <path
                                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                            <line x1="12" y1="22.08" x2="12" y2="12" />
                        </svg>
                        <div class="image-missing-text" style="color:#6b7280; font-size:0.8rem; font-weight:600;">
                            Fingerprint Image Not Available</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Conditional Status Renderings -->
        <?php if ($trial['status'] === 'pending_validation'): ?>
            <div class="report-section remarks-section">
                <div class="remarks-box" style="border-left: 4px solid #d97706; background-color: #fffbeb;">
                    <strong>Awaiting Faculty Validation</strong>
                    This fingerprint trial has been uploaded and preliminary metrics are calculated. Official ratings and
                    faculty approval are currently pending review.
                </div>
            </div>
        <?php endif; ?>

        <!-- Official Faculty Final Evaluation (Only for Approved records, shown FIRST) -->
        <?php if ($trial['status'] === 'approved'):
            $f_score = safeFloat($trial['faculty_final_score']);
            $f_clarity = safeFloat($trial['faculty_ridge_clarity_score']);
            $f_contrast = safeFloat($trial['faculty_contrast_score']);
            $f_visibility = safeFloat($trial['faculty_visibility_score']);
            $f_sharpness = safeFloat($trial['faculty_ridge_clarity_score']); // fallback
            $f_adhesion = safeFloat($trial['faculty_adhesion_score']);
            ?>
            <div class="report-section">
                <div class="section-title">Official Faculty Final Evaluation</div>
                <div class="scores-grid metric-grid">
                    <div class="score-card main-score">
                        <span class="score-card-label">Official Accuracy Score</span>
                        <span class="score-card-value"><?= formatScore($trial['faculty_final_score']) ?></span>
                    </div>
                    <div class="score-card">
                        <div class="score-card-label">Ridge Clarity</div>
                        <div class="score-card-value"><?= formatScore($trial['faculty_ridge_clarity_score']) ?></div>
                    </div>
                    <div class="score-card">
                        <div class="score-card-label">Contrast Quality</div>
                        <div class="score-card-value"><?= formatScore($trial['faculty_contrast_score']) ?></div>
                    </div>
                    <div class="score-card">
                        <div class="score-card-label">Minutiae Visibility</div>
                        <div class="score-card-value"><?= formatScore($trial['faculty_visibility_score']) ?></div>
                    </div>
                    <div class="score-card">
                        <div class="score-card-label">Fingerprint Sharpness</div>
                        <div class="score-card-value"><?= formatScore($trial['faculty_ridge_clarity_score']) ?></div>
                    </div>
                    <div class="score-card">
                        <div class="score-card-label">Adhesion Quality</div>
                        <div class="score-card-value"><?= formatScore($trial['faculty_adhesion_score']) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- AI Preliminary Results (Always shown as reference) -->
        <div class="report-section">
            <div class="section-title">AI Preliminary Results (Read-Only Reference)</div>
            <div class="scores-grid metric-grid">
                <?php
                $ai_acc = $trial['ai_accuracy_score'] !== null ? $trial['ai_accuracy_score'] : $trial['accuracy_score'];
                $ai_clarity = $trial['ridge_clarity_score'];
                $ai_visibility = $trial['visibility_score'];
                $ai_adhesion = $trial['adhesion_score'];
                $ai_contrast = $trial['contrast_score'];
                ?>
                <div class="score-card">
                    <div class="score-card-label">AI Accuracy</div>
                    <div class="score-card-value"><?= formatScore($ai_acc) ?></div>
                </div>
                <div class="score-card">
                    <div class="score-card-label">AI Ridge Clarity</div>
                    <div class="score-card-value"><?= formatScore($ai_clarity) ?></div>
                </div>
                <div class="score-card">
                    <div class="score-card-label">AI Visibility</div>
                    <div class="score-card-value"><?= formatScore($ai_visibility) ?></div>
                </div>
                <div class="score-card">
                    <div class="score-card-label">AI Adhesion</div>
                    <div class="score-card-value"><?= formatScore($ai_adhesion) ?></div>
                </div>
                <div class="score-card" style="grid-column: span 2;">
                    <div class="score-card-label">AI Contrast</div>
                    <div class="score-card-value"><?= formatScore($ai_contrast) ?></div>
                </div>
            </div>
        </div>

        <!-- Faculty Remarks Section (Shown if status is approved, rejected, or needs_revision) -->
        <?php if ($trial['status'] !== 'pending_validation'): ?>
            <div class="report-section remarks-section">
                <div class="section-title">Validation Remarks</div>
                <div class="remarks-box">
                    <strong>Faculty Review Remarks / Feedback:</strong>
                    <p class="remarks-text" style="margin: 0; white-space: pre-wrap;">
                        <?= $trial['faculty_remarks'] ? htmlspecialchars($trial['faculty_remarks']) : 'No remarks or feedback provided by the faculty reviewer.' ?>
                    </p>
                    <div
                        style="margin-top: 15px; font-size: 0.8rem; color: #4b5563; border-top: 1px solid #e5e7eb; padding-top: 8px; display: flex; justify-content: space-between;">
                        <span><strong>Validated By:</strong>
                            <?= htmlspecialchars($trial['faculty_reviewer'] ?: 'Faculty Reviewer') ?></span>
                        <span><strong>Validated At:</strong>
                            <?= $trial['validated_at'] ? date('F d, Y h:i A', strtotime($trial['validated_at'])) : '—' ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const isMobile = /Android|iPhone|iPad|iPod|Opera Mini|IEMobile/i.test(navigator.userAgent);

            if (!isMobile) {
                const img = document.getElementById('report-image');
                if (img) {
                    if (!img.complete) {
                        img.addEventListener('load', () => {
                            window.print();
                        });
                        img.addEventListener('error', () => {
                            console.error("Failed to load fingerprint image.");
                            window.print();
                        });
                    } else {
                        window.print();
                    }
                } else {
                    window.print();
                }
            }
        });
    </script>
</body>

</html>
<?php
ob_end_flush();
?>