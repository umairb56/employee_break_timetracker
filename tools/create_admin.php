<?php
// Command-line only:  php tools/create_admin.php [username] [password]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/bootstrap.php';
$ask = function (string $q) { echo $q; return trim((string)fgets(STDIN)); };
$user = $argv[1] ?? $ask('Admin username: ');
$pass = $argv[2] ?? $ask('Admin password (min 12 chars; visible while typing): ');
if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $user)) exit("Invalid username.\n");
if (strlen($pass) < 12) exit("Password must be at least 12 characters.\n");
try {
    db()->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')")->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
    echo "Admin '$user' created.\n";
} catch (PDOException $e) {
    exit($e->getCode() === '23000' ? "That username already exists.\n" : "Database error: " . $e->getMessage() . "\n");
}
