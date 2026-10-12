// Mock of the E-GASANG PHP API — mirrors the exact JSON shapes and types that
// api/*.php returns, so the frontend bridge can be exercised without PHP/MySQL.
const http = require('http');
const { URL } = require('url');

// Deliberately mimic MySQL/PDO quirks: TINYINT and counts arrive as STRINGS.
const DB = {
  residents: [
    { id: '1', resident_id: 'BRGY-2026-00001', full_name: 'DB Resident One', date_of_birth: '1985-05-15', age: '40', gender: 'Male', address: 'Zone 1, Purok 2', zone: 'Zone 1', purok: 'Purok 2', contact_number: '0917 000 0001', email: null, civil_status: 'Married', occupation: 'Farmer', vaccination_status: 'Vaccinated', is_pregnant: '0', is_pwd: '0', is_senior_citizen: '1', is_voter: '1', is_4ps_beneficiary: '0', date_registered: '2026-01-10', status: 'Active' },
    { id: '2', resident_id: 'BRGY-2026-00002', full_name: 'DB Resident Two', date_of_birth: '1998-03-02', age: '28', gender: 'Female', address: 'Zone 2, Purok 5', zone: 'Zone 2', purok: 'Purok 5', contact_number: '0917 000 0002', email: 'two@example.com', civil_status: 'Single', occupation: 'Nurse', vaccination_status: 'Not vaccinated', is_pregnant: '1', is_pwd: '1', is_senior_citizen: '0', is_voter: '1', is_4ps_beneficiary: '1', date_registered: '2026-02-11', status: 'Active' },
    { id: '3', resident_id: 'BRGY-2026-00003', full_name: 'DB Resident Three', date_of_birth: '1960-07-18', age: '66', gender: 'Male', address: 'Zone 3, Purok 1', zone: 'Zone 3', purok: null, contact_number: null, email: null, civil_status: 'Widowed', occupation: null, vaccination_status: 'Vaccinated', is_pregnant: '0', is_pwd: '0', is_senior_citizen: '1', is_voter: '0', is_4ps_beneficiary: '0', date_registered: '2026-03-05', status: 'Inactive' },
  ],
  announcements: [
    { id: '1', title: 'DB Announcement Pinned', type: 'utility', content: 'Water interruption.', date_posted: '2026-04-30 12:00:00', is_pinned: '1', is_active: '1', posted_by_name: 'Maria Santos' },
    { id: '2', title: 'DB Announcement Plain', type: 'event', content: 'Clean-up drive.', date_posted: '2026-04-21 08:00:00', is_pinned: '0', is_active: '1', posted_by_name: 'Maria Santos' },
  ],
  document_types: [
    { id: '1', name: 'Barangay Clearance', description: 'Clearance.', requirements: 'Valid ID\nProof of Residency', requirements_arr: ['Valid ID', 'Proof of Residency'], processing_time: '1-2 business days', fee: '₱50.00', is_enabled: '1', sort_order: '1' },
    // Simulates the old array_filter() key-gap bug: JSON object instead of array,
    // and no `requirements` string to fall back to. Must not crash the UI.
    { id: '2', name: 'Odd Requirements Doc', description: 'Object-shaped array.', requirements: '', requirements_arr: { '0': 'One', '2': 'Three' }, processing_time: 'Same day', fee: 'Free', is_enabled: '1' },
  ],
  document_requests: [
    { id: '1', request_number: '#1001', resident_id: 'BRGY-2026-00001', resident_name: 'DB Resident One', document_type_id: '1', doc_type_name: 'Barangay Clearance', doc_fee: '₱50.00', status: 'pending', purpose: 'Employment', fee: '₱50.00', payment_method: 'cash', payment_status: 'unpaid', admin_notes: null, requested_on: '2026-04-20', update_on: '2026-04-20', created_at: '2026-04-20 09:00:00' },
    { id: '2', request_number: '#1002', resident_id: 'BRGY-2026-00002', resident_name: 'DB Resident Two', document_type_id: '1', doc_type_name: 'Barangay Clearance', doc_fee: '₱50.00', status: 'ready', purpose: 'Scholarship', fee: '₱50.00', payment_method: 'gcash', payment_status: 'paid', admin_notes: 'Approved', requested_on: '2026-04-22', update_on: '2026-04-24', created_at: '2026-04-22 10:00:00' },
  ],
  notifications: [
    { id: '1', title: 'Request ready', message: 'Your document is ready for pickup!', type: 'request', related_id: '2', is_read: '0', created_at: '2026-04-24 11:00:00' },
    { id: '2', title: 'Request submitted', message: 'Your request has been submitted.', type: 'request', related_id: '1', is_read: '1', created_at: '2026-04-20 09:00:01' },
  ],
  users: [
    { id: '1', name: 'Maria Santos', email: 'admin@barangay-gasang.gov.ph', role: 'admin', resident_id: null, status: 'active', last_active: null, created_at: '2026-01-01 00:00:00' },
    { id: '5', name: 'Juan Dela Cruz', email: 'juan@email.com', role: 'resident', resident_id: 'BRGY-2026-00001', status: 'active', last_active: null, created_at: '2026-01-10 00:00:00' },
  ],
  settings: {
    barangay_name: 'Barangay Gasang', municipality: 'San Isidro', province: 'Batangas',
    region: 'Region IV-A (CALABARZON)', contact_number: '(02) 8123-4567',
    email: 'barangay.gasang@sanisidro.gov.ph', captain_name: 'Hon. Roberto A. Dela Cruz',
    secretary_name: 'Senyora Maria T. Santos', population_total: '9999', households_total: '777',
    vaccinated_count: '4321', senior_citizens: '555', pregnant_women: '35', pwd_count: '45',
  },
  logs: [],
};

const ok = (res, data, msg) => json(res, 200, { success: true, message: msg || 'OK', data });
const err = (res, msg, code) => json(res, code || 400, { success: false, message: msg, data: null });
function json(res, code, obj) {
  const body = JSON.stringify(obj);
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': Buffer.byteLength(body) });
  res.end(body);
}
function html404(res) {
  const body = '<!DOCTYPE html><html><body><h1>404: NOT_FOUND</h1><p>Code: NOT_FOUND</p></body></html>';
  res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8', 'Content-Length': Buffer.byteLength(body) });
  res.end(body);
}

function start(mode) {
  const recorded = [];
  const server = http.createServer((req, res) => {
    const u = new URL(req.url, 'http://127.0.0.1');
    const path = u.pathname;
    let body = '';
    req.on('data', (c) => { body += c; });
    req.on('end', () => {
      let payload = null;
      try { payload = body ? JSON.parse(body) : null; } catch (e) { payload = body; }

      if (mode === 'static') return html404(res);            // e.g. hosting without PHP
      if (mode === 'dberror') return err(res, 'DB Error: SQLSTATE[HY000] [2002] Connection refused', 500);   // MySQL stopped

      if (path.endsWith('/health.php')) {
        if (mode === 'notables') {
          return ok(res, { php: '8.2.12', db: true, tables_ok: false, database: 'egasang_db', server: '8.0.35',
            tables: { users: 5 }, missing: ['residents', 'announcements'], message: 'Connected to MySQL, but these tables are missing: residents, announcements. Import database.sql into egasang_db.' });
        }
        return ok(res, { php: '8.2.12', db: true, tables_ok: true, database: 'egasang_db', server: '8.0.35',
          tables: { users: 5, residents: 3, announcements: 2, document_types: 2, document_requests: 2, notifications: 2, activity_logs: 4, settings: 14 },
          missing: [], message: '' });
      }

      if (path.endsWith('/auth.php')) {
        const action = u.searchParams.get('action');
        if (action === 'login') {
          recorded.push({ kind: 'login', payload });
          if (payload && payload.email === 'admin@barangay-gasang.gov.ph' && payload.password === 'password') {
            return ok(res, { user: { id: 1, name: 'Maria Santos', email: payload.email, role: 'admin', resident_id: null, avatar: null }, resident: null, notif_count: 0 }, 'Login successful');
          }
          if (payload && payload.email === 'juan@email.com' && payload.password === 'password') {
            const r = DB.residents[0];
            return ok(res, { user: { id: 5, name: 'Juan Dela Cruz', email: payload.email, role: 'resident', resident_id: r.resident_id, avatar: null }, resident: r, notif_count: 1 }, 'Login successful');
          }
          return err(res, 'Invalid email or password.', 400);
        }
        if (action === 'register') { recorded.push({ kind: 'register', payload }); return json(res, 201, { success: true, message: 'Registration successful', data: { resident_id: 'BRGY-2026-00019' } }); }
        if (action === 'logout') return ok(res, null, 'Logged out');
        if (action === 'me') return ok(res, { user: DB.users[0], resident: null });
        return err(res, 'Unknown action', 404);
      }

      if (path.endsWith('/api.php')) {
        const resource = u.searchParams.get('resource'), action = u.searchParams.get('action') || 'list';
        if (resource) recorded.push({ kind: resource + ':' + action, payload });
        if (resource === 'residents' && action === 'list') return ok(res, { residents: DB.residents, total: 3, page: 1, limit: 50, total_pages: 1 });
        if (resource === 'residents' && (action === 'add' || action === 'update')) { DB.residents.push(Object.assign({ id: String(DB.residents.length + 1), resident_id: 'BRGY-2026-00099', status: 'Active', date_registered: '2026-10-12' }, payload)); return json(res, 201, { success: true, message: 'Resident added', data: { resident_id: 'BRGY-2026-00099', id: 99 } }); }
        if (resource === 'residents' && action === 'delete') return ok(res, null, 'Deactivated');
        if (resource === 'announcements' && action === 'list') return ok(res, DB.announcements);
        if (resource === 'announcements') return json(res, 201, { success: true, message: 'Posted', data: { id: 3 } });
        if (resource === 'requests' && action === 'list') return ok(res, DB.document_requests);
        if (resource === 'requests' && action === 'add') return json(res, 201, { success: true, message: 'Request submitted', data: { id: 42, request_number: '#1042' } });
        if (resource === 'requests' && action === 'update_status') return ok(res, null, 'Updated');
        if (resource === 'doctypes' && action === 'list') return ok(res, DB.document_types);
        if (resource === 'doctypes') return ok(res, null, 'Toggled');
        if (resource === 'settings' && action === 'get') return ok(res, DB.settings);
        if (resource === 'settings') return ok(res, null, 'Saved');
        if (resource === 'notifications' && action === 'list') return ok(res, { notifications: DB.notifications, unread: '1' });
        if (resource === 'notifications') return ok(res, null, 'Marked read');
        if (resource === 'users' && action === 'list') return ok(res, DB.users);
        if (resource === 'users') return json(res, 201, { success: true, message: 'User added', data: null });
        if (resource === 'dashboard') {
          return ok(res, {
            residents_total: 3, new_this_month: 0, vaccinated: 4321, senior_citizens: 555, pregnant_women: 35,
            requests_total: 2, requests_pending: 1, announcements_active: 2, households: 777,
            population_by_zone: [{ zone: 'Zone 1', count: 1 }, { zone: 'Zone 2', count: 1 }, { zone: 'Zone 3', count: 1 }],
            status_dist: [{ status: 'pending', count: 1 }, { status: 'ready', count: 1 }],
            recent_requests: DB.document_requests, recent_announcements: DB.announcements,
          });
        }
        if (resource === 'logs') return ok(res, DB.logs);
        return err(res, 'Unknown resource: ' + resource, 404);
      }

      return html404(res);
    });
  });
  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port, recorded, close: () => server.close() }));
  });
}
module.exports = { start };
