<?php
// faculty/ajax_filter_reports.php — Faculty AJAX Filter Reports
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'faculty_researcher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$f_student = $_GET['student_id'] ?? '';
$f_powder  = $_GET['powder']     ?? '';
$f_surface = $_GET['surface']    ?? '';
$f_from    = $_GET['from']       ?? '';
$f_to      = $_GET['to']         ?? '';

try {
    $where = ["ft.status='approved'"];
    $params = [];

    if (!empty($f_student)) {
        $where[] = "ft.student_id = ?";
        $params[] = $f_student;
    }
    if (!empty($f_powder)) {
        $where[] = "ft.powder_type = ?";
        $params[] = $f_powder;
    }
    if (!empty($f_surface)) {
        $where[] = "ft.surface_type = ?";
        $params[] = $f_surface;
    }
    if (!empty($f_from)) {
        $where[] = "DATE(ft.submitted_at) >= ?";
        $params[] = $f_from;
    }
    if (!empty($f_to)) {
        $where[] = "DATE(ft.submitted_at) <= ?";
        $params[] = $f_to;
    }

    $sql = "
        SELECT ft.*, u.full_name AS student_name,
               fr.remarks AS faculty_remarks, fr.decision, fr.created_at AS review_date
        FROM fingerprint_tests ft
        JOIN users u ON u.id = ft.student_id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.decision='approved'
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ft.submitted_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Reports filtered successfully.',
        'data' => [
            'records' => $records
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
