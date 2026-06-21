<?php
// student/export_fingerprint_report_word.php — Export Trial Report to MS Word
require_once '../config.php';
require_once 'auth.php';
check_student_auth();

$student_id = $_SESSION['user_id'] ?? 0;
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

if ($test_id <= 0) {
    http_response_code(400);
    die("<h1>Bad Request</h1><p>Invalid trial ID.</p>");
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
        http_response_code(403);
        die("<h1>Unauthorized</h1><p>You do not have permission to view or export this record.</p>");
    }

    // Embed fingerprint image as Base64 if file exists, belongs to this trial, and has safe image MIME type
    $safe_image = false;
    $img_base64 = '';
    if (!empty($trial['image_path'])) {
        $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $trial['image_path'];
        if (file_exists($filePath)) {
            $mime = mime_content_type($filePath);
            if ($mime && strpos($mime, 'image/') === 0) {
                $safe_image = true;
                $img_data = file_get_contents($filePath);
                $img_base64 = 'data:' . $mime . ';base64,' . base64_encode($img_data);
            }
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    die("<h1>Database Error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

function safeFloat($value, $default = 0.0) {
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_numeric($value)) {
        return (float)$value;
    }
    return $default;
}

function formatScore($value) {
    if ($value === null || $value === '') {
        return 'N/A';
    }
    if (!is_numeric($value)) {
        return 'N/A';
    }
    return number_format((float)$value, 1) . '%';
}

// Format trial ID for filename and contents
$trial_id_str = $trial['trial_id'] ?: 'TR-' . str_pad($trial['id'], 4, '0', STR_PAD_LEFT);
$filename = "Fingerprint_Evaluation_Report_" . $trial_id_str . ".doc";

// Set Word-compatible headers
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Determine status label
$status_label = '';
if ($trial['status'] === 'pending_validation') {
    $status_label = 'Pending Validation';
} elseif ($trial['status'] === 'needs_revision') {
    $status_label = 'Needs Revision';
} else {
    $status_label = ucfirst($trial['status']);
}

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <title>Fingerprint Evaluation Report - <?= htmlspecialchars($trial_id_str) ?></title>
    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        body {
            font-family: 'Arial', sans-serif;
            color: #12372a;
            line-height: 1.4;
            margin: 20px;
        }
        
        .report-header {
            text-align: center;
            border-bottom: 2px solid #12372a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        
        .report-header h1 {
            margin: 0;
            font-size: 18pt;
            font-weight: bold;
            color: #12372a;
        }
        
        .report-header h2 {
            margin: 5px 0 0 0;
            font-size: 14pt;
            color: #2d6a4f;
        }
        
        .report-header p {
            margin: 3px 0 0 0;
            font-size: 10pt;
            color: #52b788;
            font-style: italic;
        }

        .report-content {
            width: 100%;
        }

        /* Information Table layout */
        .info-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .info-table th {
            text-align: left;
            font-weight: bold;
            color: #2d6a4f;
            border-bottom: 1px solid #d1d5db;
            padding: 8px 5px;
            font-size: 10pt;
            width: 30%;
        }

        .info-table td {
            border-bottom: 1px solid #d1d5db;
            padding: 8px 5px;
            font-size: 10pt;
            color: #374151;
        }

        .image-cell {
            text-align: center;
            vertical-align: middle;
            padding: 10px;
            border: 1px dashed #cccccc;
            background-color: #fafafa;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #12372a;
            border-bottom: 1.5px solid #2d6a4f;
            padding-bottom: 4px;
            margin-top: 25px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        /* Metric Grid Table (Word compatible) */
        .score-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .score-table th, .score-table td {
            border: 1px solid #d2e2d5;
            padding: 8px;
            text-align: center;
            font-size: 10pt;
        }

        .score-table th {
            background-color: #d2e2d5;
            color: #1b4332;
            font-weight: bold;
        }

        .score-table td {
            background-color: #f4f7f5;
        }

        .remarks-box {
            background-color: #fafafa;
            border: 1px solid #e5e7eb;
            padding: 12px;
            font-size: 10pt;
            margin-top: 8px;
        }
    </style>
</head>
<body>

    <div class="report-header">
        <h1>Green Forensics Evaluating System</h1>
        <h2>Fingerprint Evaluation Report</h2>
        <p>Innovative Sustainable Fingerprint Powder Using Chicken Eggshell Waste</p>
    </div>

    <div class="report-content">
        <!-- Main details layout with details on left and image on right -->
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 60%; vertical-align: top; border: none; padding-right: 15px;">
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
                            <td><strong><?= htmlspecialchars($status_label) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Faculty Reviewer</th>
                            <td><?= htmlspecialchars($trial['faculty_reviewer'] ?: '—') ?></td>
                        </tr>
                        <tr>
                            <th>Validation Date</th>
                            <td><?= $trial['validated_at'] ? date('F d, Y h:i A', strtotime($trial['validated_at'])) : '—' ?></td>
                        </tr>
                    </table>
                </td>
                <td style="width: 40%; vertical-align: top; border: none; text-align: center;">
                    <div class="image-cell">
                        <?php if ($safe_image): ?>
                            <img src="<?= $img_base64 ?>" alt="Fingerprint" style="width: 180pt; height: auto;" width="240">
                        <?php else: ?>
                            <p style="color: #ef4444; font-size: 9pt; font-weight: bold; margin: 50px 0;">Fingerprint image not available.</p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Pending validation notice -->
        <?php if ($trial['status'] === 'pending_validation'): ?>
            <div class="remarks-box" style="border-left: 4px solid #d97706; background-color: #fffbeb;">
                <strong>Awaiting Faculty Validation</strong>
                This fingerprint trial has been uploaded and preliminary metrics are calculated. Official ratings and faculty approval are currently pending review.
            </div>
        <?php endif; ?>

        <!-- Official Faculty Final Evaluation -->
        <?php if ($trial['status'] === 'approved'): 
            $f_score = safeFloat($trial['faculty_final_score']);
            $f_clarity = safeFloat($trial['faculty_ridge_clarity_score']);
            $f_contrast = safeFloat($trial['faculty_contrast_score']);
            $f_visibility = safeFloat($trial['faculty_visibility_score']);
            $f_sharpness = safeFloat($trial['faculty_ridge_clarity_score']); // fallback
            $f_adhesion = safeFloat($trial['faculty_adhesion_score']);
        ?>
            <div class="section-title">Official Faculty Final Evaluation</div>
            <table class="score-table">
                <tr>
                    <th colspan="2" style="font-size: 11pt; padding: 10px;">Official Accuracy Score</th>
                    <th colspan="3" style="font-size: 14pt; padding: 10px; color: #1b4332; background-color: #d2e2d5; font-weight: bold;"><?= formatScore($trial['faculty_final_score']) ?></th>
                </tr>
                <tr>
                    <th>Ridge Clarity</th>
                    <th>Contrast Quality</th>
                    <th>Minutiae Visibility</th>
                    <th>Fingerprint Sharpness</th>
                    <th>Adhesion Quality</th>
                </tr>
                <tr>
                    <td><?= formatScore($trial['faculty_ridge_clarity_score']) ?></td>
                    <td><?= formatScore($trial['faculty_contrast_score']) ?></td>
                    <td><?= formatScore($trial['faculty_visibility_score']) ?></td>
                    <td><?= formatScore($trial['faculty_ridge_clarity_score']) ?></td>
                    <td><?= formatScore($trial['faculty_adhesion_score']) ?></td>
                </tr>
            </table>
        <?php endif; ?>

        <!-- AI Preliminary Results -->
        <div class="section-title">AI Preliminary Results (Read-Only Reference)</div>
        <table class="score-table">
            <?php
            $ai_acc = $trial['ai_accuracy_score'] !== null ? $trial['ai_accuracy_score'] : $trial['accuracy_score'];
            $ai_clarity = $trial['ridge_clarity_score'];
            $ai_visibility = $trial['visibility_score'];
            $ai_adhesion = $trial['adhesion_score'];
            $ai_contrast = $trial['contrast_score'];
            ?>
            <tr>
                <th>AI Accuracy</th>
                <th>AI Ridge Clarity</th>
                <th>AI Visibility</th>
                <th>AI Adhesion</th>
                <th>AI Contrast</th>
            </tr>
            <tr>
                <td><?= formatScore($ai_acc) ?></td>
                <td><?= formatScore($ai_clarity) ?></td>
                <td><?= formatScore($ai_visibility) ?></td>
                <td><?= formatScore($ai_adhesion) ?></td>
                <td><?= formatScore($ai_contrast) ?></td>
            </tr>
        </table>

        <!-- Faculty Remarks -->
        <?php if ($trial['status'] !== 'pending_validation'): ?>
            <div class="section-title">Validation Remarks</div>
            <div class="remarks-box">
                <strong>Faculty Review Remarks / Feedback:</strong>
                <p style="margin: 5px 0 10px 0; font-style: italic; color: #4b5563;"><?= $trial['faculty_remarks'] ? nl2br(htmlspecialchars($trial['faculty_remarks'])) : 'No remarks or feedback provided by the faculty reviewer.' ?></p>
                <table style="width: 100%; border: none; margin-top: 10px; border-top: 1px solid #e5e7eb; padding-top: 5px;">
                    <tr>
                        <td style="border: none; font-size: 8.5pt; color: #6b7280; width: 50%;"><strong>Validated By:</strong> <?= htmlspecialchars($trial['faculty_reviewer'] ?: 'Faculty Reviewer') ?></td>
                        <td style="border: none; font-size: 8.5pt; color: #6b7280; text-align: right; width: 50%;"><strong>Validated At:</strong> <?= $trial['validated_at'] ? date('F d, Y h:i A', strtotime($trial['validated_at'])) : '—' ?></td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
