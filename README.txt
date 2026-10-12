================================================================
 E-GASANG: Smart Barangay Management System
 Batangas State University - Mabini Campus
 IT 221 - Information Management | GED 102 - Purposive Communication
 Group 1: De Roy, Javier, Manigbas, Minerva
================================================================

QUICK SETUP GUIDE (XAMPP)
--------------------------

STEP 1 — Copy Project Files
  • Copy the entire "egasang" folder to:
    C:\xampp\htdocs\egasang\

STEP 2 — Start XAMPP
  • Open XAMPP Control Panel
  • Start "Apache" 
  • Start "MySQL"

STEP 3 — Create the Database
  • Open your browser → go to: http://localhost/phpmyadmin
  • Click "New" on the left sidebar
  • Type database name: egasang_db  → Click "Create"
  • Click "egasang_db" → click "Import" tab
  • Click "Choose File" → select: egasang/database.sql
  • Click "Go" at the bottom

STEP 4 — Configure Database (if needed)
  • Open: C:\xampp\htdocs\egasang\api\config.php
  • Check these settings:
      define('DB_HOST', 'localhost');       ← usually correct
      define('DB_NAME', 'egasang_db');     ← match what you created
      define('DB_USER', 'root');           ← default XAMPP
      define('DB_PASS', '');              ← default XAMPP = empty

STEP 5 — Open the System
  • Open browser → go to: http://localhost/egasang/

================================================================
LOGIN CREDENTIALS
================================================================

ADMIN ACCOUNT:
  Email:    admin@barangay-gasang.gov.ph
  Password: password

RESIDENT ACCOUNT:
  Email:    juan@email.com
  Password: password

NOTE: All user passwords in the database are "password"
      (hashed with bcrypt for security)

================================================================
PROJECT STRUCTURE
================================================================

egasang/
├── index.html          ← Main frontend (all pages in one file)
├── database.sql        ← Complete database with sample data
├── .htaccess           ← Apache configuration
├── README.txt          ← This file
└── api/
    ├── config.php      ← Database connection & helpers
    ├── auth.php        ← Login, register, logout
    ├── api.php         ← Main API (residents, announcements,
    │                       requests, document types, settings,
    │                       dashboard, users, notifications)
    └── uploads/        ← For uploaded requirement documents
        └── .htaccess   ← Security for uploads

================================================================
SYSTEM FEATURES
================================================================

ADMIN SIDE:
  ✓ Dashboard — stats, charts (population by zone, requests)
  ✓ Residents — full CRUD, search, filter, view profile
  ✓ Announcements — post/edit/delete, 8 categories, pin/unpin
  ✓ Service & Requests — update status, filter by status/tab
  ✓ Data Monitoring — population trend, age/gender charts
  ✓ Reports — 6 downloadable report types
  ✓ Document Types — add/edit/enable/disable
  ✓ Settings — barangay info, preferences, backup/restore
  ✓ User Management — add users, enable/disable accounts

RESIDENT SIDE (Mobile-style portal):
  ✓ Home — greeting, quick access, announcements, requests
  ✓ Announcements — filtered by category, detailed view
  ✓ Requests — submit, track with status timeline, view details
  ✓ QR Identification — QR code with resident info + scan log
  ✓ Profile — personal info, settings, change password, logout
  ✓ Services Guide — all document types with requirements
  ✓ My Records — issued documents history
  ✓ Emergency Hotlines — Police, Fire, Medical, Barangay
  ✓ Notifications — request status updates
  ✓ Register — new resident self-registration

DOCUMENT REQUEST WIZARD (4 Steps):
  Step 1: Select Service (document type)
  Step 2: Fill Details (auto-filled from profile + purpose)
  Step 3: Upload Requirements (per document checklist)
  Step 4: Review & Submit (with Cash/GCash payment choice)

================================================================
TECHNOLOGIES USED
================================================================

Frontend: HTML5, CSS3, JavaScript (Vanilla)
Backend:  PHP 8.x
Database: MySQL (via XAMPP)
Libraries:
  - Chart.js 4.4.1 (dashboard charts)
  - QRCode.js 1.0.0 (resident QR identification)
  - Google Fonts — Inter

================================================================
DATABASE TABLES
================================================================

users             — System accounts (admin/staff/resident)
residents         — Barangay resident registry
announcements     — Community announcements  
document_types    — Available document services
document_requests — Resident document requests
notifications     — In-app notifications
activity_logs     — Admin action audit trail
settings          — System configuration

================================================================
TROUBLESHOOTING
================================================================

"Page not found" error:
  → Make sure Apache is running in XAMPP
  → Check folder is at: htdocs/egasang/

"Database connection failed":
  → Make sure MySQL is running in XAMPP
  → Check config.php credentials
  → Make sure egasang_db was created

PHP errors showing:
  → Make sure you're using PHP 7.4+ (XAMPP 8.x recommended)

Charts not showing:
  → Make sure you have internet connection (CDN libraries)

================================================================
SUBMITTED TO:
  Mr. John Robert D. Atienza
  Mrs. Sherry Joy Del Mundo
  May 07, 2026
================================================================
