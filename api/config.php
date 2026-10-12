<?php
// E-GASANG — Database Configuration & Helpers
// ----------------------------------------------------------------------------
// Defaults match a stock XAMPP install (localhost / root / empty password), so
// the project runs with no edits. Every setting can also be overridden with an
// environment variable — handy when the database is not on the same machine
// (hosted MySQL / cloud), without editing this file.
// ----------------------------------------------------------------------------

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'egasang_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Optional CA bundle for MySQL servers that require TLS (e.g. TiDB Cloud).
define('DB_SSL_CA', getenv('DB_SSL_CA') ?: '');

// Leave empty for localhost. Only set this (to your site's exact URL) when the
// frontend is hosted on a DIFFERENT domain than this API — see VERCEL-DEPLOY.md.
define('CORS_ORIGIN', getenv('CORS_ORIGIN') ?: '');

function dbDsn(): string {
    return 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ];
        if (DB_SSL_CA !== '' && file_exists(DB_SSL_CA)) {
            $opts[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
        }
        try {
            $pdo = new PDO(dbDsn(), DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            jsonError('DB Error: ' . $e->getMessage(), 500);
        }
    }
    return $pdo;
}

function jsonSuccess($data = null, string $msg = 'OK', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'message' => $msg, 'data' => $data]);
    exit;
}

function jsonError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg, 'data' => null]);
    exit;
}

function startSess(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime' => 7200, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function requireAuth(): array {
    startSess();
    if (empty($_SESSION['user'])) jsonError('Unauthorized', 401);
    return $_SESSION['user'];
}

function requireAdmin(): array {
    $u = requireAuth();
    if ($u['role'] === 'resident') jsonError('Admin only', 403);
    return $u;
}

function logAct(int $uid, string $uname, string $action, string $type = '', int $tid = 0, string $detail = ''): void {
    try {
        getDB()->prepare("INSERT INTO activity_logs(user_id,user_name,action,target_type,target_id,details)VALUES(?,?,?,?,?,?)")
             ->execute([$uid, $uname, $action, $type, $tid ?: null, $detail]);
    } catch (Exception $e) {}
}

header('Content-Type: application/json');
if (CORS_ORIGIN !== '') {
    // Credentialed cross-origin requests (cookies) require an exact origin —
    // a wildcard "*" is rejected by browsers for those requests.
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
