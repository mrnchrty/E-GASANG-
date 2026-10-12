<?php
// E-GASANG — API index.
// The project's .htaccess forwards unknown /api/... paths here so that a typo
// returns a helpful JSON message instead of Apache's HTML 404 page.
require_once __DIR__ . '/config.php';

jsonSuccess([
    'endpoints' => [
        'GET  api/health.php'                          => 'Database / server status (no login needed)',
        'POST api/auth.php?action=login'               => 'body: {email, password, role}',
        'POST api/auth.php?action=register'            => 'body: {full_name, email, password, date_of_birth, gender, address, ...}',
        'GET  api/auth.php?action=me'                  => 'currently logged-in user',
        'POST api/auth.php?action=logout'              => 'end the session',
        'GET  api/api.php?resource=dashboard'          => 'admin + resident dashboard counters',
        'GET  api/api.php?resource=residents&action=list&page=1&limit=20&q=&zone=&status=',
        'POST api/api.php?resource=residents&action=add|update|delete',
        'GET  api/api.php?resource=announcements&action=list&type=&active=',
        'POST api/api.php?resource=announcements&action=add|update|toggle|delete',
        'GET  api/api.php?resource=requests&action=list|stats',
        'POST api/api.php?resource=requests&action=add|update_status',
        'GET  api/api.php?resource=doctypes&action=list&enabled=',
        'POST api/api.php?resource=doctypes&action=add|update|toggle',
        'GET  api/api.php?resource=settings&action=get',
        'POST api/api.php?resource=settings&action=update',
        'GET  api/api.php?resource=users&action=list',
        'POST api/api.php?resource=users&action=add|toggle',
        'GET  api/api.php?resource=notifications&action=list',
        'POST api/api.php?resource=notifications&action=mark_read',
        'GET  api/api.php?resource=logs&limit=50',
    ],
    'example' => 'api/api.php?resource=residents&action=list',
], 'E-GASANG API is running');
