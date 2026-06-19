<?php
// faculty/ajax_get_student_records.php — Faculty AJAX Fetch Student Records
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'faculty_researcher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$search  = trim($_GET['search']  ?? '');
$f_pwd   = $_GET['powder']  ?? '';
$f_surf  = $_GET['surface'] ?? '';
$f_stat  = $_GET['status']  ?? '';

try {
    $where = ['1=1'];
    $params = [];

    if ($search) {
        $where[] = 'u.full_name LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($f_pwd) {
        $where[] = 'ft.powder_type = ?';
        $params[] = $f_pwd;
    }
    if ($f_surf) {
        $where[] = 'ft.surface_type = ?';
        $params[] = $f_surf;
    }
    if ($f_stat) {
        $where[] = 'ft.status = ?';
        $params[] = $f_stat;
    }

    $stmt = $pdo->prepare("
        SELECT ft.*, u.full_name AS student_name,
               fac.full_name AS validator_name,
               COALESCE(ft.faculty_remarks, fr.remarks) AS faculty_remarks,
               fr.created_at AS validation_date
        FROM fingerprint_tests ft
        JOIN users u ON u.id = ft.student_id
        LEFT JOIN faculty_remarks fr ON fr.test_id = ft.id AND fr.id = (
            SELECT MAX(fr2.id) FROM faculty_remarks fr2 WHERE fr2.test_id = ft.id
        )
        LEFT JOIN users fac ON ft.validated_by = fac.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ft.submitted_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Student records retrieved successfully.',
        'data' => [
            'records' => $rows
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
