<?php
// export_dataset_csv.php — Export Complete Research Dataset to CSV (Excel & SPSS Compatible)
require_once "config.php";
require_once "includes/gdrive_service.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce authentication: Super Admin or Faculty Researcher only
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['super_admin', 'faculty_researcher'])) {
    http_response_code(403);
    echo "Unauthorized access. Only Faculty Researchers and Super Administrators can export research datasets.";
    exit;
}

$faculty_id = $_SESSION['user_id'] ?? 0;

// Filter inputs if passed via GET
$f_student = trim($_GET['student_id'] ?? '');
$f_powder  = trim($_GET['powder'] ?? '');
$f_surface = trim($_GET['surface'] ?? '');
$f_status  = trim($_GET['status'] ?? '');
$f_from    = trim($_GET['from'] ?? '');
$f_to      = trim($_GET['to'] ?? '');

$where_ft = ["1=1"];
$params_ft = [];

if ($f_student !== '') {
    $where_ft[] = "ft.student_id = ?";
    $params_ft[] = $f_student;
}
if ($f_powder !== '') {
    $where_ft[] = "ft.powder_type = ?";
    $params_ft[] = $f_powder;
}
if ($f_surface !== '') {
    $where_ft[] = "ft.surface_type = ?";
    $params_ft[] = $f_surface;
}
if ($f_status !== '') {
    $where_ft[] = "ft.status = ?";
    $params_ft[] = $f_status;
}
if ($f_from !== '') {
    $where_ft[] = "DATE(ft.submitted_at) >= ?";
    $params_ft[] = $f_from;
}
if ($f_to !== '') {
    $where_ft[] = "DATE(ft.submitted_at) <= ?";
    $params_ft[] = $f_to;
}

// Standalone safety logs filter parameters
$where_scl = ["scl.trial_id IS NULL"];
$params_scl = [];

if ($f_student !== '') {
    $where_scl[] = "scl.student_id = ?";
    $params_scl[] = $f_student;
}
if ($f_powder !== '') {
    $where_scl[] = "scl.powder_type = ?";
    $params_scl[] = $f_powder;
}
if ($f_surface !== '') {
    $where_scl[] = "scl.surface_type = ?";
    $params_scl[] = $f_surface;
}
if ($f_from !== '') {
    $where_scl[] = "DATE(scl.created_at) >= ?";
    $params_scl[] = $f_from;
}
if ($f_to !== '') {
    $where_scl[] = "DATE(scl.created_at) <= ?";
    $params_scl[] = $f_to;
}

try {
    // UNION ALL to include both Fingerprint Trial records AND Standalone Safety & Climate Logs
    $sql = "
        SELECT 
            'trial' AS record_type,
            ft.id AS primary_id,
            ft.trial_id AS trial_code,
            u_student.full_name AS student_name,
            u_student.email AS student_email,
            ft.powder_type,
            ft.surface_type,
            ft.ridge_clarity_score,
            ft.visibility_score,
            ft.adhesion_score,
            ft.contrast_score,
            ft.accuracy_score,
            ft.faculty_final_score,
            ft.evaluation_source,
            ft.status,
            u_val.full_name AS validator_name,
            ft.validated_at,
            COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks,
            scl.temperature,
            scl.humidity,
            scl.irritation_status,
            scl.health_feedback,
            ft.image_path,
            ft.gdrive_file_id,
            ft.submitted_at AS record_date
        FROM fingerprint_tests ft
        JOIN users u_student ON u_student.id = ft.student_id
        LEFT JOIN users u_val ON u_val.id = ft.validated_by
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        LEFT JOIN safety_climate_log scl ON scl.trial_id = ft.id
        WHERE " . implode(' AND ', $where_ft) . "

        UNION ALL

        SELECT 
            'standalone_safety_log' AS record_type,
            scl.id AS primary_id,
            CONCAT('SL-', LPAD(scl.id, 4, '0')) AS trial_code,
            u_student.full_name AS student_name,
            u_student.email AS student_email,
            scl.powder_type,
            scl.surface_type,
            NULL AS ridge_clarity_score,
            NULL AS visibility_score,
            NULL AS adhesion_score,
            NULL AS contrast_score,
            NULL AS accuracy_score,
            NULL AS faculty_final_score,
            'Standalone Safety Log' AS evaluation_source,
            'logged' AS status,
            'N/A' AS validator_name,
            NULL AS validated_at,
            scl.remarks AS faculty_remarks,
            scl.temperature,
            scl.humidity,
            scl.irritation_status,
            scl.health_feedback,
            NULL AS image_path,
            NULL AS gdrive_file_id,
            scl.created_at AS record_date
        FROM safety_climate_log scl
        JOIN users u_student ON u_student.id = scl.student_id
        WHERE " . implode(' AND ', $where_scl) . "

        ORDER BY record_date DESC
    ";

    $combined_params = array_merge($params_ft, $params_scl);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($combined_params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build base URL for image links fallback (clean path concatenation without double slashes)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $site_url = rtrim($protocol . $host . $script_dir, '/');

    // Clean active output buffer to prevent any PHP notices or whitespace from corrupting CSV top line
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Set headers for attachment download
    $filename = "green_forensics_dataset_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Output UTF-8 Byte Order Mark (BOM) so Excel renders non-ASCII characters cleanly
    fwrite($output, "\xEF\xBB\xBF");

    // CSV Header Columns
    fputcsv($output, [
        'Record Code',
        'Record Type',
        'Student Name',
        'Student Email',
        'Powder Type',
        'Surface Type',
        'Ridge Clarity Score (%)',
        'Visibility Score (%)',
        'Adhesion Score (%)',
        'Contrast Score (%)',
        'Accuracy Score (%)',
        'Faculty Final Score (%)',
        'Evaluation Source',
        'Validation Status',
        'Validated By',
        'Validated At',
        'Remarks / Notes',
        'Lab Temperature (°C)',
        'Lab Humidity (%)',
        'Irritation Status',
        'Health Feedback',
        'Record Date',
        'Google Drive / Media Link'
    ]);

    // Handle Empty Filter Result
    if (empty($records)) {
        fputcsv($output, [
            'NOTICE',
            'No trial or safety log records found matching the selected filter criteria.',
            'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A'
        ]);
    } else {
        // Populate rows
        foreach ($records as $row) {
            // Construct Google Drive view URL
            $gdrive_link = 'N/A';
            if (!empty($row['gdrive_file_id'])) {
                $gdrive_link = "https://drive.google.com/file/d/" . $row['gdrive_file_id'] . "/view";
            } elseif (!empty($row['image_path'])) {
                // If gdrive_file_id is missing, attempt to sync to Google Drive on-the-fly
                $local_path = dirname(__DIR__) . '/waste-eggshell/uploads/fingerprints/' . basename($row['image_path']);
                if (!file_exists($local_path)) {
                    $local_path = __DIR__ . '/uploads/fingerprints/' . basename($row['image_path']);
                }

                $new_gdrive_id = false;
                if (file_exists($local_path) && function_exists('gdrive_upload_file')) {
                    $new_gdrive_id = gdrive_upload_file($local_path, basename($row['image_path']));
                    if ($new_gdrive_id && !empty($row['primary_id']) && $row['record_type'] === 'trial') {
                        try {
                            $up_stmt = $pdo->prepare("UPDATE fingerprint_tests SET gdrive_file_id = ? WHERE id = ?");
                            $up_stmt->execute([$new_gdrive_id, $row['primary_id']]);
                        } catch (PDOException $e) {}
                    }
                }

                if ($new_gdrive_id) {
                    $gdrive_link = "https://drive.google.com/file/d/" . $new_gdrive_id . "/view";
                } else {
                    $gdrive_folder_id = env('GDRIVE_FOLDER_ID', '1ng2iHXR2KzHSBQTr-F60TwkxiVloRmym');
                    if (!empty($gdrive_folder_id)) {
                        $gdrive_link = "https://drive.google.com/drive/folders/" . $gdrive_folder_id;
                    } else {
                        $clean_image_path = ltrim(str_replace('\\', '/', $row['image_path']), '/');
                        $gdrive_link = $site_url . "/uploads/fingerprints/" . $clean_image_path;
                    }
                }
            }

            $rec_code = $row['trial_code'] ?? ('TR-' . str_pad($row['primary_id'], 4, '0', STR_PAD_LEFT));
            $rec_type = ($row['record_type'] === 'standalone_safety_log') ? 'Standalone Safety Log' : 'Fingerprint Trial';

            fputcsv($output, [
                $rec_code,
                $rec_type,
                $row['student_name'] ?? 'N/A',
                $row['student_email'] ?? 'N/A',
                strtoupper($row['powder_type'] ?? 'EGGSHELL'),
                ucfirst($row['surface_type'] ?? 'N/A'),
                $row['ridge_clarity_score'] !== null ? number_format($row['ridge_clarity_score'], 2) : 'N/A',
                $row['visibility_score'] !== null ? number_format($row['visibility_score'], 2) : 'N/A',
                $row['adhesion_score'] !== null ? number_format($row['adhesion_score'], 2) : 'N/A',
                $row['contrast_score'] !== null ? number_format($row['contrast_score'], 2) : 'N/A',
                $row['accuracy_score'] !== null ? number_format($row['accuracy_score'], 2) : 'N/A',
                $row['faculty_final_score'] !== null ? number_format($row['faculty_final_score'], 2) : 'N/A',
                $row['evaluation_source'] ?? 'AI Preliminary',
                ucwords(str_replace('_', ' ', $row['status'] ?? 'pending_validation')),
                $row['validator_name'] ?? 'N/A',
                $row['validated_at'] ?? 'N/A',
                $row['faculty_remarks'] ?? 'None',
                $row['temperature'] !== null ? number_format($row['temperature'], 2) : 'N/A',
                $row['humidity'] !== null ? number_format($row['humidity'], 2) : 'N/A',
                ucfirst($row['irritation_status'] ?? 'none'),
                $row['health_feedback'] ?? 'None',
                $row['record_date'] ?? 'N/A',
                $gdrive_link
            ]);
        }
    }

    fclose($output);

    // Log export activity
    try {
        $ip_address = $_SERVER["REMOTE_ADDR"] ?? '127.0.0.1';
        $user_agent = $_SERVER["HTTP_USER_AGENT"] ?? 'Unknown';
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_email, action, details, ip_address, user_agent) VALUES (?, ?, 'Export Dataset CSV', ?, ?, ?)");
        $log_stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['user_email'] ?? 'system',
            "Exported complete dataset CSV (" . count($records) . " records)",
            $ip_address,
            $user_agent
        ]);
    } catch (PDOException $e) {}

    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database error exporting dataset: " . $e->getMessage();
    exit;
}
