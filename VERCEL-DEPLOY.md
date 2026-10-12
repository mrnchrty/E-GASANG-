# Hosting E-GASANG on Vercel

**Short answer:** Vercel can host this project in about 5 minutes — but only the
`index.html` frontend. Vercel is a static/frontend + serverless platform; it has
**no PHP and no MySQL**. The `api/` folder is a separate XAMPP-style backend that
the frontend never actually calls, so you can deploy now and lose nothing. There
are two paths below.

---

## 1. First, what's actually in this repo (this matters)

I inspected the uploaded archive. Here is the honest picture:

| File | What it is | Runs on Vercel? |
|---|---|---|
| `index.html` (2117 lines) | The **entire app**: UI + admin dashboard + resident portal + all logic + hardcoded demo data (`LD`) + hardcoded demo login (`demoUsers`) | ✅ Yes, as a static page |
| `api/config.php`, `api/auth.php`, `api/api.php` | PHP + MySQL backend | ⚠️ Only with a community PHP runtime (Path B) |
| `database.sql` | MySQL dump, 8 tables + sample rows | ❌ No — import it into a *hosted* MySQL |
| `.htaccess` | Apache config (rewrites, headers) | ❌ Ignored — Vercel isn't Apache (already ported, see §5) |
| `README.txt` | Your XAMPP instructions | — |

**The key finding:** `index.html` makes **zero network calls**. I searched the file —
there is not a single `fetch()` or `XMLHttpRequest` in it. The line
`const API = 'api';` on line 823 is dead code; login is matched against a
JavaScript object (`demoUsers`) and all records come from local arrays (`LD`).
Self-registration pushes into a JS array.

**Consequence:** deploying the frontend to Vercel gives you *exactly* the same
working demo you see at `http://localhost/egasang/` on XAMPP. The PHP/SQL layer
adds no functionality to the current build — it is not wired up yet.

---

## 2. PATH A — Deploy the frontend as a static site (recommended)

I already added what's needed:

- **`vercel.json`** — security headers + cache rules (a Vercel replacement for `.htaccess`).
- **`.vercelignore`** — keeps `api/`, `database.sql`, `README.txt` and the `.zip`
  **off** the public site. Without this, Vercel would publish `api/config.php` as a
  downloadable text file (i.e. your database credentials and PHP source).
- **`egasang-vercel-ready.zip`** — a flat, drag-and-drop-ready bundle
  (`index.html` + `vercel.json` at the top level).

### Option A1 — Drag & drop (fastest, no GitHub needed)

1. Run the build script to regenerate the bundle if you've edited `index.html`:

   ```bash
   ./deploy/make-bundle.sh
   ```

2. Go to **https://vercel.com/drop**
3. Drag **`egasang-vercel-ready.zip`** onto the page (or the extracted
   `egasang-vercel-ready/` folder).
4. Give the project a name → **Deploy**. You get `https://<name>.vercel.app`.

> Don't drag `egasang-complete-system.zip` — it has `index.html` one level deep and
> includes the PHP/SQL files, so Vercel would publish them and ask you which page
> to serve at the root.

### Option A2 — GitHub import (best if you'll keep editing)

1. Commit and push this repo (already set up for the branch
   `arena/cef656ea-e-gasang`, or your own `main`).
2. On Vercel → **Add New… → Project → Import Git Repository** → pick the repo.
3. On the configure screen use **exactly** these settings:

   | Field | Value |
   |---|---|
   | Framework Preset | **Other** |
   | Root Directory | `./` |
   | Build Command | *(leave empty — the "Override" switch ON and the box blank)* |
   | Output Directory | `.` *(the root, since there's no build step)* |
   | Install Command | *(leave empty)* |
   | Environment Variables | none needed |

4. **Deploy**. Every future `git push` redeploys automatically.

### Option A3 — Vercel CLI

```bash
npm i -g vercel     # Node 22 is available in this workspace
vercel login
vercel              # preview deploy → answers the same questions as A2
vercel --prod       # production deploy
```

### What works after a Path A deploy

- Admin demo login: `admin@barangay-gasang.gov.ph` / any password
- Resident demo login: `juan@email.com` / any password
- Dashboard, charts, resident portal, QR ID, request wizard, reports — all client-side
- ⚠️ Needs internet at runtime: Chart.js + QRCode.js load from `cdnjs.cloudflare.com`,
  fonts from Google Fonts, one image from `images.unsplash.com`.
- ⚠️ It is a **demo**: data resets on refresh (it lives in browser memory only).

---

## 3. PATH B — Add the PHP + MySQL backend on Vercel (advanced)

Only do this once the frontend is actually rewritten to call the API. Vercel does
not run PHP natively; you need the community runtime **`vercel-php`**
(current release `0.9.0`, PHP 8.5, includes `pdo_mysql` and `mysqli`).

### Steps

1. Use the PHP config instead of the static one:

   ```bash
   cp deploy/vercel.php.json vercel.json
   ```

2. Let the functions be uploaded — edit **`.vercelignore`** and delete the
   `api/` and `deploy/` lines.

3. Nothing to patch in the PHP — `api/config.php` already prefers environment
   variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, optional
   `DB_SSL_CA`) and falls back to the XAMPP defaults when they are unset.

4. Create a **hosted MySQL** (Vercel has no database of its own). Free options that
   work with `pdo_mysql`:
   - **Aiven for MySQL** — free tier, 1 GB storage / 1 GB RAM, no credit card
     (auto-powers-off after long inactivity).
   - **TiDB Cloud Serverless** — free tier, MySQL-compatible, requires TLS.
   - Paid/other: PlanetScale (no free tier since 2024), Railway, Clever Cloud,
     or your school/cPanel host *if* it allows remote MySQL connections.

5. Import the schema. Locally:

   ```bash
   mysql -h <host> -P 3306 -u <user> -p <db> < database.sql
   ```

6. In Vercel → **Settings → Environment Variables**, add `DB_HOST`, `DB_PORT`,
   `DB_NAME`, `DB_USER`, `DB_PASS` (plus `DB_SSL_CA=/var/task/api/ca.pem` if the provider
   requires TLS — commit its `ca.pem` into `api/`, and `CORS_ORIGIN=https://<your-app>.vercel.app`
   if the frontend is served from a different domain). Redeploy so the variables are picked up.

7. Smoke-test the functions:

   ```bash
   # database status
   curl https://<your-app>.vercel.app/api/health.php
   # login
   curl -X POST https://<your-app>.vercel.app/api/auth/login \
     -H 'Content-Type: application/json' \
     -d '{"email":"admin@barangay-gasang.gov.ph","password":"password","role":"admin"}'
   ```

8. Open the site: the login badge should read **“Database connected”**. If it stays
   amber, the badge text and `/api/health.php` tell you what is wrong (most often the
   tables were never imported, or `api/` is still listed in `.vercelignore`).

### Path B caveats — read before you commit to it

| Issue | Detail | Fix |
|---|---|---|
| **Sessions don't persist** | `auth.php` stores the login in `$_SESSION`. Serverless functions are stateless and `/tmp` is wiped between invocations, so users get logged out at random. | Switch to a signed cookie/JWT, or store sessions in Upstash Redis. |
| **Uploads vanish** | `api/uploads/` is read-only except `/tmp` (ephemeral) on Vercel. | Store files in Vercel Blob / S3 / Supabase Storage. Currently no server-side upload code exists anyway. |
| **`.htaccess` is dead** | Apache-only, so it does nothing on Vercel (rewrites, session hardening, security headers). | Ported to `vercel.json` (headers + the `/api/auth/:action` rewrite). `api/index.php` now exists, so the Apache rewrite no longer 404s either. |
| **CORS + cookies** | The default `Access-Control-Allow-Origin: *` cannot be combined with cookie credentials. | Set the `CORS_ORIGIN` environment variable to your exact site URL (`api/config.php` then sends that origin + `Allow-Credentials`). Simplest of all: serve the frontend and the API from the same Vercel domain. |
| **DB must accept the internet** | Vercel functions have dynamic egress IPs; localhost/private MySQL is unreachable, and many free hosts block remote connections. | Use a provider with a public endpoint; allow all IPs or the provider's proxy. |
| **Cold starts / limits** | Functions have a timeout and per-invocation memory cap. | Set `memory`/`maxDuration` (already 512 MB / 15 s in the template). |

---

## 4. If you just want the XAMPP experience online

Vercel is genuinely the wrong tool for a PHP + MySQL + Apache project like this.
If the goal is "the full system, on the internet", use:

- **InfinityFree / 000webhost / AwardSpace** — free PHP + MySQL cPanel hosting;
  literally upload the folder and import `database.sql` in phpMyAdmin.
- **Railway / Render / DigitalOcean App Platform** with a PHP service + managed
  MySQL — if you want a modern deploy pipeline.
- Keep **Vercel for the frontend** (Path A) and host the PHP API on one of the
  above — but mind the CORS/cookie rules in the table.

---

## 5. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Deploy succeeds but page is 404 | `index.html` isn't at the repo root. Set **Root Directory** to the folder holding it, or use the flat bundle from `deploy/make-bundle.sh`. |
| Vercel asks for a build command | You left Framework Preset as Next.js/Other-ish. Choose **Other**, blank build command, Output Directory `.`. |
| `api/config.php` is downloadable on the live site | `.vercelignore` was edited/removed. Restore it — never ship `api/` on a static deploy. |
| Charts or fonts missing | The CDNs are blocked/offline; the page needs internet at load time. |
| Login fails with a custom email | Expected: only the three accounts in `demoUsers` (line 967) exist client-side. |
| Function returns `DB Error: could not find driver` | PHP runtime missing `pdo_mysql`, or you deployed while `api/` was still in `.vercelignore`. |

---

## 6. Files added by me

| File | Purpose |
|---|---|
| `vercel.json` | Static deploy: security headers + cache policy (Vercel's `.htaccess` replacement). |
| `.vercelignore` | Stops the PHP source, SQL dump, README and zips from being published. |
| `deploy/vercel.php.json` | Config template for Path B (PHP runtime + `/api/auth/:action` rewrite). |
| `deploy/make-bundle.sh` | Builds the flat `egasang-vercel-ready.zip` for drag-and-drop deploys. |
| `.gitignore` | Ignores `.vercel`, `.env`, and the generated bundle. |

Also added since the first deployment pass (used by both local and cloud runs):
`check.php` (local setup check), `api/health.php` (status JSON for the login badge) and
`api/index.php` (JSON reply for unknown `/api/…` paths).
