<?php
// maintenance_check.php - Global check for Maintenance Mode

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bypass check completely for Super Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    
    // Prevent infinite redirect loop if current page is maintenance.php
    if (strpos($_SERVER['SCRIPT_NAME'], 'maintenance.php') === false) {
        
        // Ensure PDO connection is established
        if (!isset($pdo)) {
            $config_path = __DIR__ . '/config.php';
            if (file_exists($config_path)) {
                require_once $config_path;
            }
        }
        
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
                $stmt->execute();
                $m_mode = $stmt->fetchColumn();
                
                if ($m_mode === '1') {
                    // Resolve relative base path to maintenance.php dynamically
                    $root_dir = str_replace('\\', '/', __DIR__);
                    $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                    
                    $base_path = '';
                    if (strpos($root_dir, $doc_root) === 0) {
                        $base_path = substr($root_dir, strlen($doc_root));
                    }
                    $base_path = rtrim($base_path, '/');
                    
                    header('Location: ' . $base_path . '/maintenance.php');
                    exit;
                }
            } catch (PDOException $e) {
                // Silent fail to prevent crash if table is temporarily locked or not setup
            }
        }
    }
}
