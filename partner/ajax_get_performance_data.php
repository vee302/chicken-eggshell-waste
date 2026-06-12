<?php
// partner/ajax_get_performance_data.php — Partner AJAX Fetch Performance Data
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'alumni_police_partner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$filter_powder = $_GET['powder'] ?? '';
$filter_surface = $_GET['surface'] ?? '';
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';

try {
    $where = ["ft.status = 'approved'"];
    $params = [];

    if (!empty($filter_powder)) {
        $where[] = "ft.powder_type = ?";
        $params[] = $filter_powder;
    }
    if (!empty($filter_surface)) {
        $where[] = "ft.surface_type = ?";
        $params[] = $filter_surface;
    }
    if (!empty($filter_from)) {
        $where[] = "DATE(ft.validated_at) >= ?";
        $params[] = $filter_from;
    }
    if (!empty($filter_to)) {
        $where[] = "DATE(ft.validated_at) <= ?";
        $params[] = $filter_to;
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

    // Calculations
    $total_count = count($trials);
    $avg_accuracy = 0;
    $avg_clarity = 0;
    $avg_visibility = 0;
    $avg_adhesion = 0;

    if ($total_count > 0) {
        $sum_accuracy = 0;
        $sum_clarity = 0;
        $sum_visibility = 0;
        $sum_adhesion = 0;
        
        $count_accuracy = 0;
        $count_clarity = 0;
        $count_visibility = 0;
        $count_adhesion = 0;
        
        foreach ($trials as $t) {
            if ($t['accuracy_score'] !== null) {
                $sum_accuracy += $t['accuracy_score'];
                $count_accuracy++;
            }
            if ($t['ridge_clarity_score'] !== null) {
                $sum_clarity += $t['ridge_clarity_score'];
                $count_clarity++;
            }
            if ($t['visibility_score'] !== null) {
                $sum_visibility += $t['visibility_score'];
                $count_visibility++;
            }
            if ($t['adhesion_score'] !== null) {
                $sum_adhesion += $t['adhesion_score'];
                $count_adhesion++;
            }
        }
        
        $avg_accuracy = $count_accuracy ? ($sum_accuracy / $count_accuracy) : 0;
        $avg_clarity = $count_clarity ? ($sum_clarity / $count_clarity) : 0;
        $avg_visibility = $count_visibility ? ($sum_visibility / $count_visibility) : 0;
        $avg_adhesion = $count_adhesion ? ($sum_adhesion / $count_adhesion) : 0;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Performance data retrieved successfully.',
        'data' => [
            'trials' => $trials,
            'stats' => [
                'total_count' => $total_count,
                'avg_accuracy' => $avg_accuracy,
                'avg_clarity' => $avg_clarity,
                'avg_visibility' => $avg_visibility,
                'avg_adhesion' => $avg_adhesion
            ]
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
