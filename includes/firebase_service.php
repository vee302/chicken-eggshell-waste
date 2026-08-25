<?php
// includes/firebase_service.php - GreenForensics Firebase Authentication & Cloud Integration Service

require_once __DIR__ . '/../config.php';

/**
 * Render Firebase Authentication JavaScript Config & SDK Scripts
 */
function render_firebase_scripts()
{
    $firebaseEnabled = env('FIREBASE_ENABLED', true);
    if (!$firebaseEnabled) return;

    $config = [
        'apiKey'            => env('FIREBASE_API_KEY', ''),
        'authDomain'        => env('FIREBASE_AUTH_DOMAIN', ''),
        'projectId'         => env('FIREBASE_PROJECT_ID', ''),
        'storageBucket'     => env('FIREBASE_STORAGE_BUCKET', ''),
        'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID', ''),
        'appId'             => env('FIREBASE_APP_ID', '')
    ];

    $jsonConfig = json_encode($config);
    ?>
    <!-- Firebase App & Auth SDK (v10 Compat) -->
    <script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-auth-compat.js"></script>
    <script>
        window.FIREBASE_CONFIG = <?php echo $jsonConfig; ?>;
    </script>
    <script src="<?php echo env('APP_URL', ''); ?>/assets/js/firebase_config.js"></script>
    <?php
}
