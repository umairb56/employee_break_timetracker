<?php
// Core: config, session, DB, CSRF, auth, audit, helpers.
$GLOBALS['config'] = require __DIR__ . '/../config/config.php';
error_reporting(E_ALL);
ini_set('display_errors', $GLOBALS['config']['debug'] ? '1' : '0');
ini_set('log_errors', '1');
define('APP_NAME', $GLOBALS['config']['app_name']);

if (PHP_SAPI !== 'cli') {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('BTSESSID');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    if ($https) header('Strict-Transport-Security: max-age=31536000');
}

function url(string $p = ''): string { return rtrim($GLOBALS['config']['base_path'], '/') . '/' . ltrim($p, '/'); }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $p): never { header('Location: ' . url($p)); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function jsonOut(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($d);
    exit;
}

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $c = $GLOBALS['config']['db'];
        $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function setting(string $k): string {
    static $all = null;
    if ($all === null) $all = db()->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    return $all[$k] ?? '';
}

// ---- CSRF ----
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrfField(): string { return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">'; }
function csrfCheck(bool $json = false): void {
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals(csrfToken(), $t)) {
        if ($json) jsonOut(['error' => 'Security token expired. Please reload the page.'], 403);
        http_response_code(403);
        exit('Invalid security token. Go back, reload the page and try again.');
    }
}

// ---- Auth (checked on the server for every request) ----
function currentUser(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare("SELECT u.id, u.username, u.role, e.id AS employee_id, e.employee_code, e.full_name,
                                    p.daily_allowance_minutes
                             FROM users u
                             LEFT JOIN employees e ON e.user_id = u.id
                             LEFT JOIN break_policies p ON p.id = e.policy_id
                             WHERE u.id = ? AND u.is_active = 1");
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        if (!$u) { $_SESSION = []; }   // account deactivated or removed
    }
    return $u;
}
function requireRole(string $role, bool $json = false): array {
    $u = currentUser();
    if (!$u) {
        if ($json) jsonOut(['error' => 'Not logged in'], 401);
        redirect('login.php');
    }
    if ($u['role'] !== $role) {
        if ($json) jsonOut(['error' => 'Forbidden'], 403);
        http_response_code(403);
        exit('Access denied.');
    }
    return $u;
}

function audit(string $action, string $entity, ?int $id, array $details = [], ?string $reason = null): void {
    db()->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, reason, ip_address)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([currentUser()['id'] ?? null, $action, $entity, $id,
                   json_encode($details, JSON_UNESCAPED_UNICODE), $reason, $_SERVER['REMOTE_ADDR'] ?? null]);
}

require __DIR__ . '/breaks.php';
