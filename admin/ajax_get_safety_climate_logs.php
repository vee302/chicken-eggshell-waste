<?php
// admin/ajax_get_safety_climate_logs.php — Admin AJAX Get Safety & Climate Logs with Filtering
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$search     = trim($_GET['search'] ?? '');
$powder     = trim($_GET['powder'] ?? '');
$surface    = trim($_GET['surface'] ?? '');
$irritation = trim($_GET['irritation'] ?? '');
$log_date   = trim($_GET['date'] ?? '');

try {
    $where_clauses = ["1=1"];
    $params = [];

    if ($search !== '') {
        $where_clauses[] = "u.full_name LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    if ($powder !== '') {
        $where_clauses[] = "scl.powder_type = :powder";
        $params[':powder'] = $powder;
    }
    if ($surface !== '') {
        $where_clauses[] = "scl.surface_type = :surface";
        $params[':surface'] = $surface;
    }
    if ($irritation !== '') {
        $where_clauses[] = "scl.irritation_status = :irritation";
        $params[':irritation'] = $irritation;
    }
    if ($log_date !== '') {
        $where_clauses[] = "DATE(scl.created_at) = :log_date";
        $params[':log_date'] = $log_date;
    }

    $sql = "
        SELECT scl.*, u.full_name AS student_name, ft.trial_id AS trial_code,
               fac.full_name AS validator_name,
               DATE_FORMAT(scl.created_at, '%M %d, %Y %H:%i') AS formatted_date
        FROM safety_climate_log scl
        JOIN users u ON u.id = scl.student_id
        LEFT JOIN fingerprint_tests ft ON ft.id = scl.trial_id
        LEFT JOIN users fac ON ft.validated_by = fac.id
        WHERE " . implode(" AND ", $where_clauses) . "
        ORDER BY scl.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
