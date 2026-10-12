<?php
/**
 * E-GASANG — Local Setup Check
 * ---------------------------------------------------------------------------
 * Open this page in your browser after installing the project:
 *     http://localhost/egasang/check.php
 *
 * It verifies, in order:
 *   1. PHP version and the extensions the project needs
 *   2. The project files are present and web-readable
 *   3. Database settings (api/config.php, or environment variables)
 *   4. A live MySQL connection, using the SAME credentials the API uses
 *   5. Each expected table exists, with a row count
 *   6. The seeded login accounts really work (verifies the bcrypt password)
 *   7. PHP sessions can be written (logins need this)
 *
 * It is READ-ONLY — it never inserts, updates or deletes anything, and it does
 * not require a login, so it is safe to open while troubleshooting.
 * Delete this file before putting the project on a public web host.
 */

ob_start();                                     // capture headers set by config.php
require_once __DIR__ . '/api/config.php';       // reuse the API's DB settings
ob_end_clean();
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

// ── collect results ────────────────────────────────────────────────
$OK = 'ok'; $WARN = 'warn'; $FAIL = 'fail';
$sections = [];
$add = function ($section, $label, $status, $detail = '', $hint = '') use (&$sections) {
    $sections[$section][] = ['label' => $label, 'status' => $status, 'detail' => $detail, 'hint' => $hint];
};

$isHttps    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$projectDir = __DIR__;
$selfUrl    = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';

// 1 ── PHP ──────────────────────────────────────────────────────────
$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
$add('PHP', 'PHP version', $phpOk ? $OK : $FAIL, PHP_VERSION,
     $phpOk ? '' : 'Install/upgrade to PHP 7.4 or newer (XAMPP 8.x is recommended).');
$add('PHP', 'PHP 8.x', version_compare(PHP_VERSION, '8.0.0', '>=') ? $OK : $WARN, '',
     'The project targets PHP 8.x; 7.4 also works.');

$extensions = [
    'pdo'       => ['required', 'Core database layer'],
    'pdo_mysql' => ['required', 'MySQL driver — without it every API call fails with "could not find driver"'],
    'json'      => ['required', 'The API replies in JSON'],
    'session'   => ['required', 'Login sessions'],
    'mbstring'  => ['optional', 'Safe handling of Filipino/UTF-8 text'],
    'openssl'   => ['optional', 'TLS connections to a remote MySQL server'],
];
foreach ($extensions as $ext => $meta) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? $OK : ($meta[0] === 'required' ? $FAIL : $WARN);
    $hint   = '';
    if (!$loaded) {
        $hint = 'Enable extension=' . $ext . ' in php.ini and restart Apache.'
              . ($ext === 'pdo_mysql' ? ' In XAMPP: open php.ini, find ";extension=pdo_mysql", remove the ";" and restart Apache.' : '');
    }
    $add('PHP', 'extension: ' . $ext . ' (' . $meta[0] . ')', $status, $meta[1], $hint);
}

// 2 ── Files ───────────────────────────────────────────────────────
$files = [
    'index.html'          => 'Frontend (single-page application)',
    'api/config.php'      => 'Database settings',
    'api/api.php'         => 'Main API router',
    'api/auth.php'        => 'Login / register / logout',
    'api/health.php'      => 'Status endpoint used by the login screen',
    'database.sql'        => 'MySQL schema + sample data',
];
foreach ($files as $file => $label) {
    $path = $projectDir . '/' . $file;
    $add('Files', $file, is_readable($path) ? $OK : $FAIL, $label,
         is_readable($path) ? '' : 'Missing from ' . $projectDir . ' — re-copy the complete egasang folder.');
}
$uploads = $projectDir . '/api/uploads';
$add('Files', 'api/uploads/ writable', is_writable($uploads) ? $OK : $WARN, 'Storage for uploaded requirements',
     is_writable($uploads) ? '' : 'Not writable — uploading requirement documents will fail');

// 3 ── Configuration ───────────────────────────────────────────────
$envUsed = getenv('DB_HOST') !== false;
$add('Configuration', 'Settings source', $OK,
     $envUsed ? 'Environment variables (DB_HOST is set)' : 'Defaults in api/config.php',
     $envUsed ? '' : 'Fine for XAMPP. Set DB_HOST/DB_USER/DB_PASS/DB_NAME environment variables only when using a remote database.');
$add('Configuration', 'Host / port', $OK, DB_HOST . ':' . DB_PORT, '');
$add('Configuration', 'Database name', $OK, DB_NAME, '');
$add('Configuration', 'User', $OK, DB_USER, '');
$add('Configuration', 'Password', DB_PASS === '' ? $WARN : $OK, DB_PASS === '' ? '(empty)' : '(set)',
     DB_PASS === '' ? 'Normal on XAMPP (root with no password). Use a password on any shared host.' : '');

// 4 ── MySQL connection (independent of getDB(), which exits on failure) ──
$pdo = null; $dbError = ''; $serverVersion = '';
try {
    $pdo = new PDO(dbDsn(), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $serverVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $add('Database', 'Connection to ' . DB_NAME, $OK, 'MySQL/MariaDB ' . $serverVersion, '');
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $add('Database', 'Connection to ' . DB_NAME, $FAIL, $dbError,
         'Start MySQL in the XAMPP Control Panel, create the database and import database.sql (see README.md). '
       . 'If the message says "could not find driver", enable the pdo_mysql extension.');
}

// 5 ── Tables ──────────────────────────────────────────────────────
$expected = [
    'users'             => 'Login accounts (admin / staff / resident)',
    'residents'         => 'Resident registry',
    'announcements'     => 'Community announcements',
    'document_types'    => 'Document services offered',
    'document_requests' => 'Filed document requests',
    'notifications'     => 'In-app notifications',
    'activity_logs'     => 'Admin audit trail',
    'settings'          => 'Barangay information + statistics',
];
$counts = [];
if ($pdo) {
    foreach ($expected as $table => $label) {
        try {
            $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $add('Tables', $table, $counts[$table] > 0 ? $OK : $WARN,
                 $counts[$table] . ' row(s) — ' . $label,
                 $counts[$table] > 0 ? '' : 'Table exists but is empty — import database.sql for the sample data.');
        } catch (Throwable $e) {
            $add('Tables', $table, $FAIL, 'Missing table — ' . $label,
                 'Import database.sql: phpMyAdmin → select ' . DB_NAME . ' → Import → choose database.sql → Go.');
        }
    }
}

// 6 ── Seed accounts (proves the password hash matches "password") ──
$accounts = [
    'admin@barangay-gasang.gov.ph' => 'admin',
    'juan@email.com'               => 'resident',
];
if ($pdo && isset($counts['users'])) {
    foreach ($accounts as $email => $expectedRole) {
        try {
            $st = $pdo->prepare("SELECT name, role, password, status FROM users WHERE email = ? LIMIT 1");
            $st->execute([$email]);
            $row = $st->fetch();
            if (!$row) {
                $add('Login accounts', $email, $FAIL, 'No such user',
                     'Import database.sql to create the sample accounts.');
                continue;
            }
            $pwOk = password_verify('password', $row['password']);
            $add('Login accounts', $email, $pwOk ? $OK : $FAIL,
                 $row['name'] . ' · role: ' . $row['role'] . ' · status: ' . $row['status']
                 . ' · password "password" ' . ($pwOk ? 'verified' : 'does NOT match'),
                 $pwOk ? '' : 'The stored password hash is not for "password". Reset it in User Management or re-import database.sql.');
            if ($row['role'] !== $expectedRole) {
                $add('Login accounts', $email . ' role', $WARN, 'Expected ' . $expectedRole . ', found ' . $row['role'], '');
            }
        } catch (Throwable $e) {
            $add('Login accounts', $email, $FAIL, $e->getMessage(), '');
        }
    }
}

// 7 ── Sessions ────────────────────────────────────────────────────
if (function_exists('session_status')) {
    $savePath = session_save_path();
    $add('Sessions', 'session.save_path', ($savePath === '' || is_writable($savePath)) ? $OK : $WARN,
         $savePath === '' ? '(PHP default temp folder)' : $savePath,
         ($savePath === '' || is_writable($savePath)) ? '' : 'Not writable — users will be logged out immediately after signing in.');
}

// ── verdict ────────────────────────────────────────────────────────
$fails = 0; $warns = 0;
foreach ($sections as $items) {
    foreach ($items as $i) { if ($i['status'] === $FAIL) $fails++; elseif ($i['status'] === $WARN) $warns++; }
}
$verdict = $fails ? 'fail' : ($warns ? 'warn' : 'ok');
$verdictText = [
    'ok'   => 'All checks passed — the system is ready to demonstrate.',
    'warn' => 'Working, with ' . $warns . ' warning(s) to look at below.',
    'fail' => $fails . ' problem(s) found — fix the red items below, then reload this page.',
][$verdict];

$pill = function ($status) {
    $map = ['ok' => ['OK', '#10b981'], 'warn' => ['CHECK', '#f59e0b'], 'fail' => ['FAIL', '#f87171']];
    $m = $map[$status];
    return '<span style="display:inline-block;min-width:52px;text-align:center;font-size:10px;font-weight:800;letter-spacing:.06em;'
         . 'padding:3px 8px;border-radius:20px;background:' . $m[1] . '22;color:' . $m[1] . ';border:1px solid ' . $m[1] . '55">'
         . $m[0] . '</span>';
};
$colors = ['ok' => '#10b981', 'warn' => '#f59e0b', 'fail' => '#f87171'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>E-GASANG — Local Setup Check</title>
<style>
  :root{--bg:#0b1220;--surface:#111a2b;--border:#1f2b42;--text:#e8eefc;--muted:#8fa0bf;--accent:#10b981}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:14px/1.6 -apple-system,"Segoe UI",Inter,Roboto,Arial,sans-serif;padding:28px 18px 60px}
  .wrap{max-width:940px;margin:0 auto}
  h1{font-size:21px;margin:0 0 4px}
  .sub{color:var(--muted);font-size:13px;margin-bottom:18px}
  .verdict{border-radius:12px;padding:14px 16px;margin-bottom:18px;font-weight:700;display:flex;gap:10px;align-items:center;
           border:1px solid <?= $colors[$verdict] ?>55;background:<?= $colors[$verdict] ?>14;color:<?= $colors[$verdict] ?>}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:14px}
  .card h2{font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:var(--accent);margin:0 0 10px}
  table{width:100%;border-collapse:collapse}
  td{padding:7px 8px;border-bottom:1px solid var(--border);vertical-align:top;font-size:12.5px}
  tr:last-child td{border-bottom:none}
  td.lbl{width:230px;color:var(--text)}
  td.det{color:var(--muted)}
  .hint{color:#fbbf24;font-size:11.5px;margin-top:3px}
  .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}
  a.btn{display:inline-block;padding:9px 15px;border-radius:9px;background:var(--accent);color:#04160f;font-weight:700;text-decoration:none;font-size:13px}
  a.btn.ghost{background:transparent;border:1px solid var(--border);color:var(--text)}
  code{background:#0a1120;border:1px solid var(--border);border-radius:5px;padding:1px 6px;font-size:12px;color:#a5f3d0}
  ol{margin:8px 0 0 18px;padding:0;color:var(--muted);font-size:12.5px}
  ol li{margin-bottom:4px}
</style>
</head>
<body>
<div class="wrap">
  <h1>E-GASANG — Local Setup Check</h1>
  <div class="sub">Barangay Management System · dependency, database and login verification
    · <?= $esc(date('M j, Y g:i A')) ?></div>

  <div class="verdict"><span><?= $pill($verdict) ?></span><span><?= $esc($verdictText) ?></span></div>

  <div class="card">
    <h2>Open the system</h2>
    <div class="actions">
      <a class="btn" href="<?= $esc($selfUrl) ?>index.html">Open E-GASANG (index.html)</a>
      <a class="btn ghost" href="<?= $esc($selfUrl) ?>api/health.php">api/health.php (JSON status)</a>
      <a class="btn ghost" href="<?= $esc($selfUrl) ?>check.php">Reload this check</a>
    </div>
  </div>

<?php foreach ($sections as $name => $items): ?>
  <div class="card">
    <h2><?= $esc($name) ?></h2>
    <table>
<?php foreach ($items as $i): ?>
      <tr>
        <td><?= $pill($i['status']) ?></td>
        <td class="lbl"><?= $esc($i['label']) ?></td>
        <td class="det"><?= $esc($i['detail']) ?><?php if ($i['hint']): ?><div class="hint">→ <?= $esc($i['hint']) ?></div><?php endif; ?></td>
      </tr>
<?php endforeach; ?>
    </table>
  </div>
<?php endforeach; ?>

  <div class="card">
    <h2>How to demonstrate the database connection</h2>
    <ol>
      <li>Keep this page open in one tab — it reads straight from MySQL using the API's own credentials.</li>
      <li>Open <code><?= $esc($selfUrl) ?>index.html</code> and sign in with
          <code>admin@barangay-gasang.gov.ph</code> / <code>password</code>.
          The login screen shows a green <em>Database connected</em> badge when the API is reachable.</li>
      <li>Add a resident (Residents → + Add Resident), then reload this page — the
          <code>residents</code> row count goes up, and the new row is visible in phpMyAdmin.</li>
      <li>Optional: submit a document request from the resident portal, then check the
          <code>document_requests</code> and <code>notifications</code> tables.</li>
      <li>Stop MySQL in the XAMPP Control Panel, reload <code>index.html</code> and try to sign in:
          the badge turns amber and the app falls back to its built-in demo data instead of breaking.</li>
    </ol>
  </div>

  <div class="sub" style="margin-top:16px">
    Read-only diagnostics · no data is written by this page ·
    <code><?= $esc($projectDir) ?></code>
  </div>
</div>
</body>
</html>
