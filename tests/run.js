/* ============================================================================
   E-GASANG — API bridge test  (optional; needs Node.js, no packages)

   Runs the real application JavaScript from index.html inside a tiny DOM shim
   and talks to tests/mock-api.js, which reproduces the JSON (including the
   string-number quirks) that api/*.php returns. It verifies:
     · start-up health check + "Database connected" badge
     · login, and data hydration for both admin and resident
     · dashboard figures coming from the settings table
     · writes (add resident / announcement / request / settings) reaching the API
     · graceful fallback to demo data when MySQL is down or PHP is missing

   Usage:
     node tests/run.js                 # connected database
     MODE=dberror node tests/run.js    # MySQL stopped
     MODE=static  node tests/run.js    # static-only hosting, no PHP
     MODE=notables node tests/run.js   # database.sql not imported

   XAMPP users do not need this — `check.php` verifies the real installation.
   ========================================================================= */
const fs = require('fs');
const path = require('path');
const { start } = require('./mock-api');

const MODE = process.env.MODE || 'ok';
const HTML_PATH = process.env.HTML || path.join(__dirname, '..', 'index.html');

let pass = 0, fail = 0;
const results = [];
function check(name, cond, extra) {
  const line = cond ? ('  ✅ ' + name) : ('  ❌ ' + name + (extra !== undefined ? '  →  ' + JSON.stringify(extra) : ''));
  if (cond) pass++; else fail++;
  results.push(line);
  console.log(line);
}
const sleep = ms => new Promise(r => setTimeout(r, ms));

// ── minimal DOM shim ────────────────────────────────────────────────
function makeDocument() {
  const registry = new Map();
  function makeEl(id) {
    const cls = new Set();
    const t = { id: id || '', value: '', checked: false, innerHTML: '', textContent: '', className: '', style: {}, dataset: {}, children: [], _ch: null, _cls: cls };
    const classList = {
      add: (...c) => { c.forEach(x => cls.add(x)); },
      remove: (...c) => { c.forEach(x => cls.delete(x)); },
      toggle: (c, f) => { if (f === undefined) { cls.has(c) ? cls.delete(c) : cls.add(c); } else { f ? cls.add(c) : cls.delete(c); } return cls.has(c); },
      contains: c => cls.has(c),
    };
    const noop = () => {};
    return new Proxy(t, {
      get(tg, p) {
        if (p === 'classList') return classList;
        if (p === 'style') return tg.style;
        if (p === 'dataset') return tg.dataset;
        if (p === 'children') return tg.children;
        if (p in tg) return tg[p];
        if (p === 'querySelector') return () => makeEl('anon');
        if (p === 'closest') return () => null;
        if (p === 'querySelectorAll' || p === 'getElementsByTagName') return () => [];
        if (p === 'contains') return () => false;
        if (p === 'getAttribute') return () => null;
        if (p === 'remove') return noop;
        if (p === 'focus' || p === 'blur') return noop;
        if (typeof p === 'symbol') return undefined;
        return noop;
      },
      set(tg, p, v) { tg[p] = v; return true; },
    });
  }
  const doc = {
    _el: makeEl,
    getElementById: id => { if (!registry.has(id)) registry.set(id, makeEl(id)); return registry.get(id); },
    querySelector: () => makeEl('q'),
    querySelectorAll: () => [],
    createElement: tag => makeEl(tag),
    addEventListener: () => {},
    removeEventListener: () => {},
    documentElement: makeEl('html'),
    body: makeEl('body'),
  };
  doc._registry = registry;
  return doc;
}

class ChartStub { constructor(el) { this.el = el; } destroy() {} }
class QRCodeStub { constructor() {} }
QRCodeStub.CorrectLevel = { L: 1, M: 0, Q: 3, H: 2 };

(async () => {
  const api = await start(MODE);
  const BASE = 'http://127.0.0.1:' + api.port;

  const html = fs.readFileSync(HTML_PATH, 'utf8');
  const m = html.match(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/);
  if (!m) { console.error('No inline script found'); process.exit(2); }
  let js = m[1].replace("const API_BASE='api/'", "const API_BASE='" + BASE + "/api/'");   // test-only: absolute base
  js += "\n__X.S=S; __X.DBX=DBX; __X.apiCall=apiCall; __X.checkBackend=checkBackend;"
     + " __X.doLogin=doLogin; __X.doRegister=doRegister; __X.loadData=loadData; __X.refreshFromDb=refreshFromDb;"
     + " __X.setDbBadge=setDbBadge; __X.saveResident=saveResident; __X.saveAnnc=saveAnnc; __X.saveSettings=saveSettings;"
     + " __X.submitReq=submitReq; __X.updateReqStatus=updateReqStatus; __X.toggleUser=toggleUser; __X.markAllRead=markAllRead;"
     + " __X.setRole=setRole; __X.getW=function(){return W;}; __X.setW=function(v){W=v;};\n";

  const doc = makeDocument();
  const getComputedStyle = () => ({ getPropertyValue: () => '#10b981' });
  let uncaught = null;
  process.on('uncaughtException', e => { uncaught = e; console.log('  ⚠ uncaughtException: ' + e.message); });
  process.on('unhandledRejection', e => { uncaught = e; console.log('  ⚠ unhandledRejection: ' + (e && (e.stack || e.message || e))); });

  globalThis.requestAnimationFrame = cb => setTimeout(() => cb(Date.now()), 0);
  globalThis.cancelAnimationFrame = id => clearTimeout(id);
  globalThis.getComputedStyle = getComputedStyle;
  let T = null;
  const X = {};
  try {
    new Function('document', 'window', 'Chart', 'QRCode', 'getComputedStyle', '__X', js)(
      doc, {}, ChartStub, QRCodeStub, getComputedStyle, X);
  } catch (e) {
    console.log('❌ the page script threw while loading: ' + e.message + '\n' + e.stack);
    process.exit(1);
  }
  T = X;
  Object.defineProperty(T, 'W', { get: () => X.getW(), set: v => X.setW(v), configurable: true });
  if (!T.S || !T.DBX) { console.log('❌ the page script never reached the export lines'); process.exit(1); }
  const guard = setTimeout(() => { console.log('❌ harness timed out (a call never resolved)'); process.exit(3); }, 25000);
  const el = id => doc.getElementById(id);
  const cls = id => Array.from(el(id)._cls).join(',');

  await sleep(80);   // let the start-up health check settle

  console.log('\nMODE=' + MODE);
  if (MODE === 'ok') {
    check('health check marks the database online', T.DBX.online === true, { online: T.DBX.online, reason: T.DBX.reason });
    check('login badge shows connected (class ok)', el('dbStatus')._cls.has('ok'), cls('dbStatus'));
    check('badge text names the database', el('dbStatusTxt').textContent.includes('egasang_db'), el('dbStatusTxt').textContent);

    // ---- login against the mock MySQL ----
    el('loginEmail').value = 'admin@barangay-gasang.gov.ph';
    el('loginPass').value = 'password';
    await T.doLogin();
    check('login uses the API user (admin)', T.S.user && T.S.user.role === 'admin' && T.S.user.name === 'Maria Santos', T.S.user);
    check('residents hydrated from database', T.S.residents.length === 3 && T.S.residents[0].full_name === 'DB Resident One', T.S.residents.length);
    check('announcements hydrated', T.S.announcements.length === 2);
    check('requests hydrated with joined names', T.S.requests.length === 2 && T.S.requests[0].resident_name === 'DB Resident One' && T.S.requests[0].doc_type_name === 'Barangay Clearance');
    check('document types hydrated', T.S.docTypes.length === 2);
    check('settings hydrated from MySQL', T.S.settings.population_total === '9999', T.S.settings.population_total);
    check('users hydrated (admin only endpoint)', T.S.users.length === 2);
    check('TINYINT strings coerced to numbers (is_pwd)', typeof T.S.residents[0].is_pwd === 'number' && T.S.residents[0].is_pwd === 0, T.S.residents[0].is_pwd);
    check('object-shaped requirements_arr becomes an array', Array.isArray(T.S.docTypes[1].requirements_arr), T.S.docTypes[1].requirements_arr);
    check('notifications + unread count', T.S.notifications.length === 2 && T.S.unreadNotif === 1, T.S.unreadNotif);
    const dash = el('page-dashboard').innerHTML;
    check('dashboard shows DB population setting (9,999)', dash.includes('9,999'), dash.slice(0, 80));
    check('dashboard shows DB vaccinated setting (4,321)', dash.includes('4,321'));
    check('admin shell rendered without throwing', el('page-residents').innerHTML.length > 200 && el('page-users').innerHTML.length > 100);
    check('request tab badge from DB data', el('reqBadge').textContent === 1, el('reqBadge').textContent);

    // ---- writes ----
    el('rEditId').value = ''; el('rName').value = 'Test Resident'; el('rDob').value = '1990-01-01';
    el('rGender').value = 'Male'; el('rCivil').value = 'Single'; el('rAddress').value = 'Zone 4, Purok 1';
    el('rZone').value = 'Zone 4'; el('rPurok').value = 'Purok 1'; el('rContact').value = '0917 555 0000';
    el('rOccupation').value = 'Teacher'; el('rEmail').value = 'test@example.com'; el('rVacc').value = 'Vaccinated';
    el('rPwd').checked = false; el('rSenior').checked = false; el('rVoter').checked = true;
    el('r4ps').checked = false; el('rPregnant').checked = false;
    T.saveResident();
    await sleep(150);
    const addRec = api.recorded.filter(r => r.kind === 'residents:add').pop();
    check('add resident POSTed to MySQL', !!addRec && addRec.payload.full_name === 'Test Resident', addRec && addRec.payload);
    check('resident payload carries flags as 0/1', addRec && addRec.payload.is_voter === 1 && addRec.payload.is_pwd === 0, addRec && addRec.payload);
    check('resident payload includes required fields', addRec && addRec.payload.zone === 'Zone 4' && addRec.payload.civil_status === 'Single' && addRec.payload.date_of_birth === '1990-01-01');

    el('anncEditId').value = ''; el('anncTitle').value = 'Test Announcement'; el('anncContent').value = 'Body text';
    el('anncType').value = 'event'; el('anncDate').value = '2026-10-12'; el('anncPinned').checked = true;
    T.saveAnnc();
    await sleep(150);
    const anncRec = api.recorded.filter(r => r.kind === 'announcements:add').pop();
    check('announcement POSTed to MySQL', !!anncRec && anncRec.payload.title === 'Test Announcement' && anncRec.payload.is_pinned === 1, anncRec && anncRec.payload);

    el('set_barangay_name').value = 'Barangay Gasang (edited)';
    T.saveSettings();
    await sleep(150);
    const setRec = api.recorded.filter(r => r.kind === 'settings:update').pop();
    check('settings POSTed to MySQL', !!setRec && setRec.payload.barangay_name === 'Barangay Gasang (edited)', setRec && setRec.payload);

    // ---- resident request wizard (uses the server-issued reference number) ----
    if (!T.S.residents.length) { console.log('   DEBUG login state: ' + JSON.stringify({ online: T.DBX.online, reachable: T.DBX.reachable, reason: T.DBX.reason, user: T.S.user, recordings: api.recorded.map(r => r.kind) })); process.exit(1); }
    T.S.user = { id: 5, name: 'Juan Dela Cruz', role: 'resident', resident_id: 'BRGY-2026-00001' };
    T.S.resident = T.S.residents[0];
    T.W = { step: 3, docId: 1, purpose: 'Scholarship application', payment: 'gcash', uploads: {} };
    await T.submitReq();
    await sleep(150);
    const reqRec = api.recorded.filter(r => r.kind === 'requests:add').pop();
    check('document request POSTed to MySQL', !!reqRec && reqRec.payload.document_type_id === 1 && reqRec.payload.resident_id === 'BRGY-2026-00001', reqRec && reqRec.payload);
    check('request payload carries purpose + payment method', reqRec && reqRec.payload.purpose === 'Scholarship application' && reqRec.payload.payment_method === 'gcash');
    check('success screen shows the DB reference number #1042', el('rRequestNew').innerHTML.includes('#1042'), 'not found');
    check('resident portal re-rendered without throwing', el('rHome').innerHTML.length > 100);

    T.markAllRead();
    await sleep(120);
    check('mark-all-read POSTed', api.recorded.some(r => r.kind === 'notifications:mark_read'));
    // ---- resident sign-in through the API (the other half of the app) ----
    T.setRole('resident');
    el('loginEmail').value = 'juan@email.com';
    el('loginPass').value = 'password';
    await T.doLogin();
    check('resident login via API', !!(T.S.user && T.S.user.role === 'resident'), T.S.user);
    check('resident profile loaded from residents table', !!(T.S.resident && T.S.resident.resident_id === 'BRGY-2026-00001'), T.S.resident && T.S.resident.resident_id);
    check('resident sees only their own requests', T.S.myRequests.length === 1 && T.S.myRequests[0].resident_id === 'BRGY-2026-00001', T.S.myRequests.length);
    check('resident portal rendered', el('rHome').innerHTML.length > 100 && el('rRequests').innerHTML.length > 50);
    check('resident notifications panel rendered', el('notifList').innerHTML.length > 20 || el('notifList').innerHTML === '');
    check('no uncaught errors during the whole run', uncaught === null, uncaught && String(uncaught));
  }

  if (MODE === 'dberror' || MODE === 'static') {
    check('database marked offline', T.DBX.online === false, { online: T.DBX.online, reason: T.DBX.reason });
    check('badge warns the user (err/warn class)', el('dbStatus')._cls.has('err') || el('dbStatus')._cls.has('warn'), cls('dbStatus'));
    check('badge text explains the problem', el('dbStatusTxt').textContent.length > 10, el('dbStatusTxt').textContent);
    el('loginEmail').value = 'admin@barangay-gasang.gov.ph';
    el('loginPass').value = 'password';
    await T.doLogin();
    check('login still works on demo data', T.S.user && T.S.user.name === 'Maria Santos' && T.S.residents.length === 18, T.S.residents.length);
    check('admin pages render in demo mode', el('page-dashboard').innerHTML.includes('2,350') || el('page-dashboard').innerHTML.includes('Total Population'));
    check('no uncaught errors in offline mode', uncaught === null, uncaught && String(uncaught));
  }

  if (MODE === 'notables') {
    check('connected but tables missing → offline', T.DBX.online === false);
    check('badge explains the missing tables', el('dbStatusTxt').textContent.toLowerCase().includes('missing') || T.DBX.reason.toLowerCase().includes('missing'), el('dbStatusTxt').textContent + ' | ' + T.DBX.reason);
    check('no uncaught errors', uncaught === null, uncaught && String(uncaught));
  }

  console.log(results.join('\n'));
  console.log('\n' + (fail ? '❌ ' : '✅ ') + pass + ' passed, ' + fail + ' failed');
  api.close();
  process.exit(fail ? 1 : 0);
})()
.catch(e => { console.log('❌ harness error: ' + (e && e.stack || e)); process.exit(1); });
