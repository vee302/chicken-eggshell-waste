<?php
// admin/admin_sms_logs.php — SMS Gateway Delivery Logs Viewer
require_once "../config.php";
require_once "auth.php";
check_admin_auth();

$active_page = 'sms_logs';

// Fetch summary stats
$total_sms = $delivered_sms = $failed_sms = $relayed_sms = 0;
try {
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('delivered', 'sent', 'queued') THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'cloud_relayed' THEN 1 ELSE 0 END) as relayed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM sms_logs
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    if ($stats) {
        $total_sms     = (int)($stats['total'] ?? 0);
        $delivered_sms = (int)($stats['delivered'] ?? 0);
        $relayed_sms   = (int)($stats['relayed'] ?? 0);
        $failed_sms    = (int)($stats['failed'] ?? 0);
    }
} catch (PDOException $e) {}

// Fetch recent 100 SMS logs
$logs = [];
try {
    $stmt = $pdo->query("
        SELECT sl.*, u.full_name AS recipient_name
        FROM sms_logs sl
        LEFT JOIN users u ON u.contact_number = sl.phone OR u.contact_number = REPLACE(sl.phone, '+63', '0')
        ORDER BY sl.created_at DESC
        LIMIT 100
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Delivery Logs — Green Forensics Admin</title>
    <link rel="stylesheet" href="../css/admin_style.css?v=1.0">
    <style>
        .badge-delivered, .badge-sent { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 0.8rem; }
        .badge-cloud_relayed { background: #cce5ff; color: #004085; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 0.8rem; }
        .badge-failed { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 0.8rem; }
        .badge-queued { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 0.8rem; }
        .sms-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .sms-stat-card { background: #fff; border-radius: 10px; padding: 1.25rem; border: 1px solid #e9ecef; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .sms-stat-val { font-size: 1.6rem; font-weight: 700; color: #1b4332; margin-top: 4px; }
        .sms-stat-lbl { font-size: 0.82rem; color: #6c757d; font-weight: 600; }
        .table-wrap { background: #fff; border-radius: 12px; border: 1px solid #e9ecef; overflow: hidden; }
        .table-custom { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .table-custom th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-weight: 700; color: #1b4332; border-bottom: 1px solid #e9ecef; }
        .table-custom td { padding: 12px 16px; border-bottom: 1px solid #f1f3f5; color: #212529; }
    </style>
</head>
<body>
    <div class="admin-app-layout">
        <?php require_once "sidebar.php"; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <h2>📱 SMS Gateway & Delivery Logs</h2>
                <button onclick="location.reload()" class="btn btn-sm btn-primary" style="padding:8px 16px;background:#2d6a4f;border:none;border-radius:6px;color:#fff;cursor:pointer;font-weight:600;">Refresh Logs</button>
            </header>

            <div class="admin-content" style="padding: 2rem;">
                
                <div class="sms-stats-grid">
                    <div class="sms-stat-card">
                        <div class="sms-stat-lbl">Total SMS Sent</div>
                        <div class="sms-stat-val"><?= $total_sms ?></div>
                    </div>
                    <div class="sms-stat-card">
                        <div class="sms-stat-lbl">Delivered</div>
                        <div class="sms-stat-val" style="color:#2d6a4f;"><?= $delivered_sms ?></div>
                    </div>
                    <div class="sms-stat-card">
                        <div class="sms-stat-lbl">Cloud Relayed</div>
                        <div class="sms-stat-val" style="color:#004085;"><?= $relayed_sms ?></div>
                    </div>
                    <div class="sms-stat-card">
                        <div class="sms-stat-lbl">Failed</div>
                        <div class="sms-stat-val" style="color:#dc3545;"><?= $failed_sms ?></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Recipient / Phone</th>
                                <th>Message Content</th>
                                <th>Provider / Gateway</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;padding:2rem;color:#6c757d;">No SMS delivery logs recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['recipient_name'] ?? 'User') ?></strong><br>
                                            <span style="font-size:0.8rem;color:#6c757d;"><?= htmlspecialchars($log['phone']) ?></span>
                                        </td>
                                        <td style="max-width:350px;word-break:break-word;"><?= htmlspecialchars($log['message']) ?></td>
                                        <td><span style="font-size:0.82rem;font-weight:600;color:#495057;"><?= htmlspecialchars($log['provider'] ?? 'Traccar SMS') ?></span></td>
                                        <td>
                                            <?php
                                            $st = strtolower($log['status']);
                                            $badge_class = 'badge-queued';
                                            if (in_array($st, ['delivered', 'sent', 'success'])) $badge_class = 'badge-delivered';
                                            elseif ($st === 'cloud_relayed') $badge_class = 'badge-cloud_relayed';
                                            elseif ($st === 'failed') $badge_class = 'badge-failed';
                                            ?>
                                            <span class="<?= $badge_class ?>"><?= strtoupper(str_replace('_', ' ', $log['status'])) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
