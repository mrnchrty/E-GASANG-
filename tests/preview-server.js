/* ============================================================================
   E-GASANG — optional preview server (Node.js only, no packages)

   Serves the project folder over HTTP and answers /api/... from the mock backend
   in tests/mock-api.js, so you can show the interface *in its connected state*
   (green "Database connected" badge, database-driven dashboard) on a machine
   where XAMPP is not available.

   This is NOT part of the real system — the real system runs on XAMPP with MySQL
   and the PHP files in api/ (see README.md). Use it only for previews/screenshots.

   Usage:  node tests/preview-server.js [port]        (default 8000)
           then open http://localhost:8000/
   ========================================================================= */
const http = require('http');
const fs = require('fs');
const path = require('path');
const { start } = require('./mock-api');

const ROOT = path.join(__dirname, '..');
const PORT = parseInt(process.argv[2] || '8000', 10);
const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.json': 'application/json; charset=utf-8', '.txt': 'text/plain; charset=utf-8', '.sql': 'text/plain; charset=utf-8', '.php': 'text/plain; charset=utf-8', '.md': 'text/markdown; charset=utf-8', '.zip': 'application/zip' };

function send(res, code, body, type) {
  res.writeHead(code, { 'Content-Type': type || 'text/plain; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(body);
}

(async () => {
  const api = await start('ok');          // mock MySQL/PHP backend
  http.createServer((req, res) => {
    const url = req.url.split('?')[0];

    // 1) API calls are proxied to the mock backend (mirrors api/*.php responses)
    if (url.startsWith('/api/')) {
      const chunks = [];
      req.on('data', c => chunks.push(c));
      req.on('end', async () => {
        try {
          const body = chunks.length ? Buffer.concat(chunks) : undefined;
          const r = await fetch('http://127.0.0.1:' + api.port + req.url, {
            method: req.method,
            headers: body ? { 'Content-Type': 'application/json' } : {},
            body: req.method === 'GET' || req.method === 'HEAD' ? undefined : body,
          });
          const text = await r.text();
          send(res, r.status, text, r.headers.get('content-type') || 'application/json');
        } catch (e) {
          send(res, 500, JSON.stringify({ success: false, message: 'preview proxy error: ' + e.message, data: null }), 'application/json');
        }
      });
      return;
    }

    // 2) everything else is served from the project folder
    const rel = url === '/' ? 'index.html' : decodeURIComponent(url).replace(/^\/+/, '');
    const file = path.normalize(path.join(ROOT, rel));
    if (!file.startsWith(ROOT) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
      return send(res, 404, 'Not found: ' + rel);
    }
    if (rel === 'check.php') {
      return send(res, 200,
        'check.php needs a PHP server — run it on XAMPP (http://localhost/egasang/check.php).\n'
        + 'This preview server does not execute PHP; /api/... is answered by the mock backend.\n', 'text/plain; charset=utf-8');
    }
    send(res, 200, fs.readFileSync(file), MIME[path.extname(file).toLowerCase()]);
  }).listen(PORT, '0.0.0.0', () => {
    console.log('E-GASANG preview on http://localhost:' + PORT + '/  (mock database on /api/...)');
  });
})();
