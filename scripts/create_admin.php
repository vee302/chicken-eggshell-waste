<?php
// Explicit first-admin provisioning; never accessible through the website.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$email = strtolower(trim($argv[1] ?? ''));
$name = trim($argv[2] ?? 'System Administrator');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || $name === '' || strlen($name) > 150) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php EMAIL [NAME] < password-input\n");
    exit(1);
}
if (stream_isatty(STDIN)) {
    fwrite(STDERR, "Pipe the password through standard input using the hidden prompt documented in README.md.\n");
    exit(1);
}
$password = rtrim(fgets(STDIN) ?: '', "\r\n");
if (strlen($password) < 12 || strlen($password) > 72 || trim($password) !== $password ||
    !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
    fwrite(STDERR, "Use 12–72 bytes with uppercase, lowercase, a number and a symbol; no surrounding whitespace.\n");
    exit(1);
}

require_once __DIR__ . '/../config.php';
$locked = false;
$exitCode = 0;
try {
    $locked = (int)$pdo->query("SELECT GET_LOCK('green_forensics_admin_bootstrap', 10)")->fetchColumn() === 1;
    if (!$locked) {
        throw new RuntimeException('Another administrator setup is in progress.');
    }
    if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn() > 0) {
        throw new RuntimeException('An administrator already exists. Use the existing account management flow.');
    }
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, 'super_admin', 'active')");
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Administrator created. No default password was used.\n");
} catch (PDOException $e) {
    fwrite(STDERR, "Administrator creation failed. Check database configuration and whether the email is already registered.\n");
    $exitCode = 1;
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if ($locked) {
        $pdo->query("SELECT RELEASE_LOCK('green_forensics_admin_bootstrap')");
    }
    unset($password);
}
exit($exitCode);
