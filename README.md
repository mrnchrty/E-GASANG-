# E-GASANG — Smart Barangay Management System

Batangas State University — Mabini Campus · IT 221 (Information Management) & GED 102 (Purposive Communication) · Group 1: De Roy, Javier, Manigbas, Minerva

A barangay management system for **Barangay Gasang, San Isidro, Batangas**: resident registry, announcements, document requests, QR identification, reports and analytics — plus a mobile-style portal for residents.

> **This file is the local (XAMPP) deployment guide.** It replaces `README.txt` (the original submission note, kept for reference). For hosting the frontend on Vercel, see **`VERCEL-DEPLOY.md`**.

---

## 1. How the system is put together

```
egasang/
├── index.html          Frontend — the whole UI (admin dashboard + resident portal)
├── check.php           Setup check: dependencies, database, tables, login accounts
├── api/
│   ├── config.php      Database settings  ← the only file you may need to edit
│   ├── auth.php        login / register / logout / me
│   ├── api.php         main API (residents, announcements, requests, doc types,
│   │                    settings, dashboard, users, notifications, logs)
│   ├── health.php      Status JSON used by the "Database" badge on the login screen
│   ├── index.php       Helpful JSON reply for unknown /api/… paths
│   └── uploads/        Storage for requirement documents
├── database.sql        Schema + sample data (8 tables)
└── .htaccess           Apache config (headers, session hardening, /api routing)
```

The frontend talks to MySQL **through the PHP API** (`api/*.php`). At start-up it calls
`api/health.php`; if MySQL answers, the login screen shows a green
**“Database connected”** badge and every list and every change goes to the database.
If MySQL or Apache is not running, the badge turns amber/red and the app falls back to
its **built-in demo data** instead of breaking — so a demo can never dead-end.

---

## 2. Requirements

| Requirement | Notes |
|---|---|
| **XAMPP 8.x** (Apache + MySQL/MariaDB) | XAMPP 7.4 also works; anything with PHP ≥ 7.4 |
| **PHP extension `pdo_mysql`** | On by default in XAMPP. `check.php` verifies it |
| A browser | Chrome/Edge/Firefox |
| Internet connection | Only for Chart.js, QRCode.js and the Inter font (loaded from CDNs) |

No Composer, no Node, no build step. Nothing to install beyond XAMPP.

---

## 3. Setup (5 steps, ~5 minutes)

### Step 1 — Copy the project into htdocs

```
C:\xampp\htdocs\egasang\
```

The folder must contain `index.html`, `check.php`, `database.sql` and the `api/` folder directly.

### Step 2 — Start the servers

Open the **XAMPP Control Panel** → **Start** `Apache` → **Start** `MySQL`.

### Step 3 — Create the database and import the data

1. Go to <http://localhost/phpmyadmin>
2. Click **New** → database name **`egasang_db`** → **Create**
3. Select `egasang_db` → **Import** tab → **Choose File** → `egasang/database.sql` → **Go**

*(Command line alternative: `C:\xampp\mysql\bin\mysql -u root < C:\xampp\htdocs\egasang\database.sql` — this also creates the database.)*

### Step 4 — Check the configuration (usually nothing to change)

`api/config.php` already targets a stock XAMPP install:

```php
DB_HOST = localhost     DB_NAME = egasang_db
DB_USER = root          DB_PASS = (empty)
```

Change them only if your MySQL uses a password (shared hosts, remote database), or set
the environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
(and `DB_SSL_CA` for TLS-only providers) instead of editing the file.

### Step 5 — Verify, then open the system

| What | URL |
|---|---|
| **Setup check (run this first)** | <http://localhost/egasang/check.php> |
| The system itself | <http://localhost/egasang/> |
| API status JSON | <http://localhost/egasang/api/health.php> |
| phpMyAdmin (to show the tables) | <http://localhost/phpmyadmin> |

`check.php` verifies PHP version, extensions (`pdo_mysql`!), the project files, the
database connection, all 8 tables with row counts, and whether the sample passwords
really match — every line is marked OK / CHECK / FAIL with the fix next to it.
**All green = ready to demonstrate.**

---

## 4. Accounts

All sample passwords are `password` (stored as bcrypt hashes in the `users` table —
`check.php` verifies this).

| Role | Email | Password | Sign-in tab |
|---|---|---|---|
| Admin | `admin@barangay-gasang.gov.ph` | `password` | **Admin** |
| Staff | `jose.reyes@barangay-gasang.gov.ph` | `password` | **Admin** |
| Staff | `ana.lim@barangay-gasang.gov.ph` | `password` | **Admin** |
| Resident | `juan@email.com` | `password` | **Resident** |
| Resident | `maria@email.com` | `password` | **Resident** |

The sign-in tab must match the account type — the app tells you which tab to use if you mix them.
The Resident tab loads `juan@email.com` for you, the Admin tab loads `admin@barangay-gasang.gov.ph`.
(If the database is offline, any of these emails still work in demo mode with any password.)

---

## 5. Demonstration script (site + database connection)

A 5-minute walkthrough that shows the pages working **and** the database connection:

**A. Show the database is live (before logging in)**

1. Open `http://localhost/egasang/check.php` — green verdict, PHP version, `pdo_mysql` loaded,
   connection to `egasang_db`, 8 tables with row counts, passwords verified.
2. Open the system: the login screen shows **“Database connected — egasang_db · MySQL 8.x”**.

**B. Admin side**

3. Sign in with the **Admin** tab → `admin@barangay-gasang.gov.ph` / `password`.
4. **Dashboard** — population, vaccinated, senior citizens and households come straight from the
   `settings` table; the population-by-zone chart is a live `GROUP BY zone` over `residents`.
5. **Residents** — 18 seeded residents, search box, zone/status filters, pagination.
   *Add Resident* → save → the new row is written to `residents` (the API mints the
   `BRGY-<year>-#####` ID, the `age` column is calculated by MySQL).
6. **Announcements** — post/edit/pin; **Service & Requests** — change a request status
   (this writes `document_requests` + inserts a `notifications` row for the resident).
7. **Document Types**, **Settings**, **User Management** — all edits are saved to MySQL.

**C. Prove the write reached MySQL**

8. In phpMyAdmin open `egasang_db` → `residents` → **Browse** → the resident you added is there
   (or reload `check.php` and watch the row count increase).
9. `document_requests` shows the new request, `notifications` shows the message created for the resident.

**D. Resident side**

10. Log out → **Resident** tab → `juan@email.com` / `password`.
11. Home, Announcements (filter chips), Requests + status timeline, QR ID, Profile,
    Services Guide, My Records, Emergency hotlines.
12. *Request a document* (4-step wizard) → submit → the reference number comes from the
    database (`#10xx`), and it appears in the admin's **Service & Requests** page, ready to
    be updated through pending → processing → ready → completed.

**E. Show that the system is robust (optional, ~30 seconds)**

13. Stop MySQL in the XAMPP Control Panel → reload the app → the badge turns red with the
    reason and the demo data keeps the site usable. Start MySQL again and it reconnects.

---

## 6. Features

**Admin / staff** — dashboard with charts · residents CRUD + search/filter/pagination · announcements
(8 categories, pin/unpin, activate/deactivate) · service & requests with status workflow · data
monitoring & population analytics · 6 report types · document types CRUD · settings · user management.

**Resident portal** — home with announcements · category-filtered announcements · request tracking with
status timeline · QR identification · profile & password · services guide with requirements ·
issued-document records · emergency hotlines · notifications · self-registration.

---

## 7. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `check.php` shows **FAIL — connection** | MySQL not started in the XAMPP Control Panel, or `egasang_db` not created |
| `check.php` shows **FAIL — pdo_mysql** | Open `C:\xampp\php\php.ini`, remove the `;` from `;extension=pdo_mysql`, restart Apache |
| Tables reported **missing** | `database.sql` was not imported into `egasang_db` (Step 3) |
| Login says *Invalid email or password* | The DB accounts use the password `password`; the hash must match (re-import `database.sql`) |
| Badge says **Demo mode / Database unavailable** | The frontend is open but MySQL/Apache is down, or you opened `index.html` from disk (`file://`). Use `http://localhost/egasang/` |
| **Port 80 already in use** | Another web server (IIS, Skype, or another Apache) holds the port: stop it, or change Apache to 8080 and use `http://localhost:8080/egasang/` |
| **Port 3306 already in use** | Another MySQL service is running: stop it in `services.msc`, or change the port in `my.ini` **and** in `api/config.php` (`DB_PORT`) |
| MySQL will not start | Delete `C:\xampp\mysql\data\ibdata1`-style leftovers only as a last resort; first check `C:\xampp\mysql\data\mysql_error.log` |
| Page loads unstyled / charts missing | The CDNs (cdnjs, Google Fonts) are unreachable — the app needs internet at load time |
| `500 Internal Server Error` | A stray `.htaccess` directive or PHP-FPM instead of mod_php; the shipped `.htaccess` is wrapped in `<IfModule>` guards, so delete it only if it is known to conflict |
| `Access denied for user 'root'@'localhost'` | Your MySQL root password is not empty — set it in `api/config.php` |
| Sessions drop after login | The PHP temp folder is not writable (`check.php` ▶ Sessions section) |

---

## 8. Database tables

| Table | Holds |
|---|---|
| `users` | Login accounts (admin / staff / resident) |
| `residents` | Resident registry — `age` is a MySQL-generated column |
| `announcements` | Community announcements (type, pinned, active) |
| `document_types` | Services offered, fee, processing time, requirements list |
| `document_requests` | Filed requests — status, fee, payment, notes |
| `notifications` | Per-user messages created by the API |
| `activity_logs` | Admin audit trail |
| `settings` | Barangay information **and** the statistics shown on the dashboard (`population_total`, `households_total`, `vaccinated_count`, …) |

Editing those `settings` values changes the dashboard figures — they are read from MySQL,
not hard-coded in the page.

---

## 9. Hosting elsewhere

* **Frontend on Vercel** (drag-and-drop, 2 minutes): see `VERCEL-DEPLOY.md`. Without PHP the app
  runs in demo mode with an amber badge — that is expected. The same document explains how to host
  the PHP + MySQL backend with a hosted database.
* **Another PHP/MySQL host** (InfinityFree, cPanel, Railway…): upload the folder, import
  `database.sql`, then set the `DB_*` environment variables or edit `api/config.php`.

### Security note for public hosting

`check.php` is a diagnostics page without a login. **Delete it** (and keep the database password
in `api/config.php` or environment variables, never in the frontend) before putting this project on
a public server.

---

## 10. Automated regression check (optional, for developers)

`check.php` verifies the real XAMPP installation. If you change `index.html` and want to prove the
frontend ↔ API wiring still works, run the bundled harness — **Node.js only, no packages to install**:

```bash
node tests/run.js                 # connected database
MODE=dberror node tests/run.js    # MySQL stopped  → demo fallback
MODE=static  node tests/run.js    # static hosting, no PHP → demo fallback
MODE=notables node tests/run.js   # database.sql not imported
```

It executes the real application JavaScript from `index.html` against a mock of the PHP API
(`tests/mock-api.js`) and asserts login, hydration, database-driven dashboard figures, all write
paths and the degraded-mode fallbacks. Current status: **33/33 passing** connected, and
6/6, 6/6, 3/3 for the three degraded modes.

---

*Made with HTML5, CSS3 and vanilla JavaScript on the front end; PHP 8 with PDO and MySQL on the back end.*
