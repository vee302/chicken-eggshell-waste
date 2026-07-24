<?php
// admin/ajax_get_trial_records.php — Super Admin AJAX Fetch Trial Records
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$search_student = isset($_GET["student"]) ? trim($_GET["student"]) : "";
$filter_powder = isset($_GET["powder"]) ? trim($_GET["powder"]) : "";
$filter_surface = isset($_GET["surface"]) ? trim($_GET["surface"]) : "";
$filter_status = isset($_GET["status"]) ? trim($_GET["status"]) : "";

try {
    $query_str = "
        SELECT 
            ft.*,
            student.full_name AS student_name,
            faculty.full_name AS faculty_validator,
            faculty.full_name AS validator_name,
            frm.remarks AS validation_remarks,
            frm.created_at AS validation_date
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks frm ON ft.id = frm.test_id AND frm.id = (
            SELECT MAX(frm2.id) FROM faculty_remarks frm2 WHERE frm2.test_id = ft.id
        )
        WHERE 1=1
    ";

    $params = [];

    if (!empty($search_student)) {
        $query_str .= " AND student.full_name LIKE ?";
        $params[] = '%' . $search_student . '%';
    }

    if (!empty($filter_powder)) {
        $query_str .= " AND ft.powder_type = ?";
        $params[] = $filter_powder;
    }

    if (!empty($filter_surface)) {
        $query_str .= " AND ft.surface_type = ?";
        $params[] = $filter_surface;
    }

    if (!empty($filter_status)) {
        $query_str .= " AND ft.status = ?";
        $params[] = $filter_status;
    }

    $query_str .= " ORDER BY ft.id DESC";

    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($records as &$rec) {
        $rec['image_exists'] = false;
        if (!empty($rec['image_path'])) {
            $filename = basename($rec['image_path']);
            $filePath = dirname(__DIR__) . '/uploads/trial_records/' . $filename;
            if (!file_exists($filePath)) {
                $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . $filename;
            }
            if (file_exists($filePath)) {
                $rec['image_exists'] = true;
            }
        }
    }
    unset($rec);

    echo json_encode([
        'success' => true,
        'message' => 'Trial records retrieved successfully.',
        'data' => [
            'records' => $records
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
