<?php
// E-GASANG — Backend health check (no login required).
// ----------------------------------------------------------------------------
// GET api/health.php
// Used by the login screen's "Database" badge and by check.php. Reports whether
// PHP can reach MySQL, which of the expected tables exist, and how many rows
// they hold. Read-only: it never writes anything.
// ----------------------------------------------------------------------------
require_once __DIR__ . '/config.php';

$tables = [
    'users'             => 'Users',
    'residents'         => 'Residents',
    'announcements'     => 'Announcements',
    'document_types'    => 'Document Types',
    'document_requests' => 'Document Requests',
    'notifications'     => 'Notifications',
    'activity_logs'     => 'Activity Logs',
    'settings'          => 'Settings',
];

$out = [
    'php'        => PHP_VERSION,
    'db'         => false,   // true when MySQL accepted the connection
    'tables_ok'  => false,   // true when every expected table exists
    'database'   => DB_NAME,
    'server'     => '',
    'tables'     => [],
    'missing'    => [],
    'message'    => '',
];

try {
    $db = getDB();
    $out['db']     = true;
    $out['server'] = (string) $db->getAttribute(PDO::ATTR_SERVER_VERSION);
    foreach ($tables as $name => $label) {
        try {
            $out['tables'][$name] = (int) $db->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
        } catch (Throwable $e) {
            $out['missing'][] = $name;
        }
    }
    $out['tables_ok'] = count($out['missing']) === 0;
    if (!$out['tables_ok']) {
        $out['message'] = 'Connected to MySQL, but these tables are missing: '
            . implode(', ', $out['missing']) . '. Import database.sql into ' . DB_NAME . '.';
    }
} catch (Throwable $e) {
    // Reached when the server responds but PDO cannot be set up at all
    // (for example the pdo_mysql extension is disabled). Connection failures
    // are already reported by getDB() as HTTP 500 with a JSON error body.
    $out['message'] = 'Cannot connect: ' . $e->getMessage();
}

jsonSuccess($out, ($out['db'] && $out['tables_ok']) ? 'Database OK' : 'Database unavailable');
