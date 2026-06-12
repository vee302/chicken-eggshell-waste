<?php
// partner/ajax_filter_approved_reports.php — Partner AJAX Filter Approved Reports & Trials
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'alumni_police_partner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$powder_filter = $_GET['powder'] ?? '';
$surface_filter = $_GET['surface'] ?? '';

try {
    $where = ["ft.status = 'approved'"];
    $params = [];

    if (!empty($powder_filter) && $powder_filter !== 'all') {
        $where[] = "ft.powder_type = ?";
        $params[] = $powder_filter;
    }
    if (!empty($surface_filter) && $surface_filter !== 'all') {
        $where[] = "ft.surface_type = ?";
        $params[] = $surface_filter;
    }

    $sql = "
        SELECT 
            ft.*, 
            student.full_name AS student_name, 
            faculty.full_name AS faculty_validator,
            fr.remarks AS validation_remarks
        FROM fingerprint_tests ft
        LEFT JOIN users student ON ft.student_id = student.id
        LEFT JOIN users faculty ON ft.validated_by = faculty.id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.decision = 'approved'
        WHERE " . implode(" AND ", $where) . "
        ORDER BY ft.validated_at DESC, ft.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Approved trials filtered successfully.',
        'data' => [
            'trials' => $trials
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
