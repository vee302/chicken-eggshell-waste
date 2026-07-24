<?php
// student/ajax_get_student_records.php — Student AJAX Fetch Records
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'criminology_student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$student_id = $_SESSION['user_id'] ?? 0;
$filter_status = $_GET['status'] ?? '';
$filter_powder = $_GET['powder'] ?? '';
$filter_surface = $_GET['surface'] ?? '';

try {
    $where = ['ft.student_id = ?'];
    $params = [$student_id];

    if (!empty($filter_status)) {
        $where[] = 'ft.status = ?';
        $params[] = $filter_status;
    }
    if (!empty($filter_powder)) {
        $where[] = 'ft.powder_type = ?';
        $params[] = $filter_powder;
    }
    if (!empty($filter_surface)) {
        $where[] = 'ft.surface_type = ?';
        $params[] = $filter_surface;
    }

    $sql = "
        SELECT ft.*, COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks, faculty.full_name AS faculty_validator,
               student.full_name AS student_name, student.email AS student_email
        FROM fingerprint_tests ft
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY ft.submitted_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($records as &$r) {
        $r['image_exists'] = false;
        if (!empty($r['image_path'])) {
            $filePath = dirname(__DIR__) . '/uploads/fingerprints/' . basename($r['image_path']);
            if (file_exists($filePath)) {
                $r['image_exists'] = true;
            }
        }
        $r['enhanced_image_exists'] = false;
        if (!empty($r['enhanced_image_path'])) {
            $enhPath = dirname(__DIR__) . '/uploads/fingerprint_enhanced/' . basename($r['enhanced_image_path']);
            if (file_exists($enhPath)) {
                $r['enhanced_image_exists'] = true;
            }
        }
    }
    unset($r);

    echo json_encode([
        'success' => true,
        'message' => 'Student records retrieved successfully.',
        'data' => [
            'records' => $records
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
