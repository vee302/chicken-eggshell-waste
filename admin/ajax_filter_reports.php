<?php
// admin/ajax_filter_reports.php — Super Admin AJAX Fetch/Filter Reports
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$filter_date = isset($_GET["date"]) ? trim($_GET["date"]) : "";
$filter_user = isset($_GET["user"]) ? trim($_GET["user"]) : "";

try {
    $query_str = "
        SELECT 
            r.id, 
            r.report_title, 
            r.report_filter, 
            r.generated_at,
            u.full_name AS compiled_by,
            u.role AS compiler_role
        FROM reports r
        JOIN users u ON r.generated_by = u.id
        WHERE 1=1
    ";

    $params = [];

    if (!empty($filter_date)) {
        $query_str .= " AND DATE(r.generated_at) = ?";
        $params[] = $filter_date;
    }

    if (!empty($filter_user)) {
        $query_str .= " AND u.full_name LIKE ?";
        $params[] = '%' . $filter_user . '%';
    }

    $query_str .= " ORDER BY r.id DESC";

    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Reports filtered successfully.',
        'data' => [
            'reports' => $reports
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
