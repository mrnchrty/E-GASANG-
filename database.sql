-- ============================================================
-- E-GASANG: Database Schema for XAMPP/MySQL
-- Based on System ERD from the approved proposal
-- ============================================================

CREATE DATABASE IF NOT EXISTS egasang_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE egasang_db;

-- USERS table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','resident') NOT NULL DEFAULT 'resident',
  resident_id VARCHAR(50) NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RESIDENTS table
CREATE TABLE IF NOT EXISTS residents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resident_id VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  date_of_birth DATE NOT NULL,
  age INT GENERATED ALWAYS AS (TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) STORED,
  gender ENUM('Male','Female') NOT NULL,
  address TEXT NOT NULL,
  zone VARCHAR(50) NOT NULL,
  purok VARCHAR(50),
  contact_number VARCHAR(20),
  email VARCHAR(150),
  civil_status ENUM('Single','Married','Widowed','Separated') NOT NULL,
  occupation VARCHAR(100),
  vaccination_status VARCHAR(100) DEFAULT 'Not vaccinated',
  is_pregnant TINYINT(1) DEFAULT 0,
  is_pwd TINYINT(1) DEFAULT 0,
  is_senior_citizen TINYINT(1) DEFAULT 0,
  is_voter TINYINT(1) DEFAULT 0,
  is_4ps_beneficiary TINYINT(1) DEFAULT 0,
  date_registered DATE NOT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  status ENUM('Active','Inactive') DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ANNOUNCEMENTS table
CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  type ENUM('emergency','event','health','general','scholarship','livelihood','lost-found','utility') NOT NULL DEFAULT 'general',
  content TEXT NOT NULL,
  image_path VARCHAR(255),
  date_posted DATETIME NOT NULL,
  posted_by INT,
  is_pinned TINYINT(1) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- DOCUMENT_TYPES table
CREATE TABLE IF NOT EXISTS document_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  requirements TEXT,
  processing_time VARCHAR(50) DEFAULT '3-5 business days',
  fee VARCHAR(50) DEFAULT 'Free',
  is_enabled TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- DOCUMENT_REQUESTS table
CREATE TABLE IF NOT EXISTS document_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_number VARCHAR(50) NOT NULL UNIQUE,
  resident_id VARCHAR(50) NOT NULL,
  document_type_id INT NOT NULL,
  status ENUM('pending','processing','ready','completed','rejected') DEFAULT 'pending',
  purpose TEXT,
  fee VARCHAR(50) DEFAULT 'Free',
  payment_method ENUM('cash','gcash') DEFAULT 'cash',
  payment_status ENUM('unpaid','paid') DEFAULT 'unpaid',
  admin_notes TEXT,
  requested_on DATE NOT NULL,
  update_on DATE NOT NULL,
  processed_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (document_type_id) REFERENCES document_types(id),
  FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- NOTIFICATIONS table
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('request','announcement','system') DEFAULT 'system',
  is_read TINYINT(1) DEFAULT 0,
  related_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ACTIVITY_LOGS table
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  user_name VARCHAR(150),
  action VARCHAR(255) NOT NULL,
  target_type VARCHAR(100),
  target_id INT,
  details TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SETTINGS table
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Users (password: 'password' hashed with bcrypt)
INSERT INTO users (name, email, password, role, resident_id, status) VALUES
('Maria Santos', 'admin@barangay-gasang.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, 'active'),
('Jose Reyes', 'jose.reyes@barangay-gasang.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', NULL, 'active'),
('Ana Lim', 'ana.lim@barangay-gasang.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', NULL, 'active'),
('Pedro Cruz', 'pedro.cruz@barangay-gasang.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', NULL, 'inactive'),
('Juan Dela Cruz', 'juan@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'resident', 'BRGY-2026-01234', 'active'),
('Maria Clara Santos', 'maria@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'resident', 'BRGY-2026-01235', 'active');
-- NOTE: Default password = 'password'

-- Residents
INSERT INTO residents (resident_id, full_name, date_of_birth, gender, address, zone, purok, contact_number, civil_status, occupation, vaccination_status, is_voter, is_senior_citizen, is_pwd, date_registered, status) VALUES
('BRGY-2026-01234','Juan Dela Cruz','1985-05-15','Male','Zone 1, Purok 2, Barangay Gasang, San Isidro','Zone 1','Purok 2','0917 123 4567','Married','Farmer','Vaccinated',1,0,0,'2026-01-10','Active'),
('BRGY-2026-01235','Maria Clara Santos','1990-08-22','Female','Zone 2, Purok 5, Barangay Gasang, San Isidro','Zone 2','Purok 5','0918 234 5678','Single','Teacher','Vaccinated',1,0,0,'2026-01-15','Active'),
('BRGY-2026-01236','Pedro Penduko','1978-03-10','Male','Zone 3, Purok 1, Barangay Gasang, San Isidro','Zone 3','Purok 1','0919 345 6789','Married','Fisherman','Vaccinated',1,0,0,'2026-02-01','Active'),
('BRGY-2026-01237','Ana Reyes','2000-11-05','Female','Zone 1, Purok 3, Barangay Gasang, San Isidro','Zone 1','Purok 3','0920 456 7890','Single','Student','Vaccinated',0,0,0,'2026-02-10','Active'),
('BRGY-2026-01238','Carlos Magno','1965-07-18','Male','Zone 4, Purok 4, Barangay Gasang, San Isidro','Zone 4','Purok 4','0921 567 8901','Widowed','Retired','Vaccinated',1,1,0,'2026-02-15','Active'),
('BRGY-2026-01239','Elena Rodriguez','1988-01-25','Female','Zone 5, Purok 6, Barangay Gasang, San Isidro','Zone 5','Purok 6','0922 678 9012','Married','Vendor','Vaccinated',1,0,0,'2026-02-20','Active'),
('BRGY-2026-01240','Miguel Cruz','1995-09-12','Male','Zone 2, Purok 2, Barangay Gasang, San Isidro','Zone 2','Purok 2','0923 789 0123','Single','Driver','Vaccinated',1,0,0,'2026-03-01','Active'),
('BRGY-2026-01241','Sofia Garcia','1972-04-08','Female','Zone 3, Purok 3, Barangay Gasang, San Isidro','Zone 3','Purok 3','0924 890 1234','Separated','Dressmaker','Not vaccinated',1,0,1,'2026-03-05','Inactive'),
('BRGY-2026-01242','Ricardo Lim','1982-12-20','Male','Zone 4, Purok 1, Barangay Gasang, San Isidro','Zone 4','Purok 1','0925 901 2345','Married','Engineer','Vaccinated',1,0,0,'2026-03-10','Active'),
('BRGY-2026-01243','Carmen Diaz','1998-06-30','Female','Zone 5, Purok 5, Barangay Gasang, San Isidro','Zone 5','Purok 5','0926 012 3456','Single','Nurse','Vaccinated',1,0,0,'2026-03-15','Active'),
('BRGY-2026-01244','Roberto Santos','1960-02-14','Male','Zone 1, Purok 1, Barangay Gasang, San Isidro','Zone 1','Purok 1','0927 123 4567','Married','Carpenter','Vaccinated',1,1,0,'2026-03-20','Active'),
('BRGY-2026-01245','Lilia Fernandez','1958-09-30','Female','Zone 2, Purok 4, Barangay Gasang, San Isidro','Zone 2','Purok 4','0928 234 5678','Widowed','Housewife','Vaccinated',1,1,0,'2026-03-25','Active'),
('BRGY-2026-01246','Mark Torres','2003-04-12','Male','Zone 3, Purok 2, Barangay Gasang, San Isidro','Zone 3','Purok 2','0929 345 6789','Single','Student','Vaccinated',0,0,0,'2026-04-01','Active'),
('BRGY-2026-01247','Grace Villanueva','1992-07-18','Female','Zone 4, Purok 3, Barangay Gasang, San Isidro','Zone 4','Purok 3','0930 456 7890','Married','Accountant','Vaccinated',1,0,0,'2026-04-05','Active'),
('BRGY-2026-01248','Antonio Bautista','1975-11-22','Male','Zone 5, Purok 1, Barangay Gasang, San Isidro','Zone 5','Purok 1','0931 567 8901','Married','Mechanic','Not vaccinated',1,0,0,'2026-04-08','Active'),
('BRGY-2026-01249','Rosa Mendoza','1980-03-05','Female','Zone 1, Purok 4, Barangay Gasang, San Isidro','Zone 1','Purok 4','0932 678 9012','Single','Laundrywoman','Vaccinated',1,0,0,'2026-04-10','Active'),
('BRGY-2026-01250','Emmanuel Gonzales','1968-08-28','Male','Zone 2, Purok 1, Barangay Gasang, San Isidro','Zone 2','Purok 1','0933 789 0123','Married','Security Guard','Vaccinated',1,0,0,'2026-04-12','Active'),
('BRGY-2026-01251','Patricia Castro','1995-01-15','Female','Zone 3, Purok 4, Barangay Gasang, San Isidro','Zone 3','Purok 4','0934 890 1234','Single','Call Center Agent','Vaccinated',1,0,0,'2026-04-15','Active');

-- Document Types
INSERT INTO document_types (name, description, requirements, processing_time, fee, is_enabled, sort_order) VALUES
('Barangay Clearance','Official document certifying that the resident has no derogatory record in the barangay.','Valid ID (any government-issued ID)\nProof of Residency\nFilled out request form','1-2 business days','₱50.00',1,1),
('Certificate of Residency','Certifies that the individual is a bona fide resident of Barangay Gasang.','Valid ID\nProof of address (utility bill or Barangay ID)\nFilled out request form','Same day','₱30.00',1,2),
('Indigency Certificate','Certifies that the resident belongs to an indigent family for medical or financial assistance.','Valid ID\nSworn affidavit of indigency\nBarangay verification','1 business day','Free',1,3),
('Business Permit Endorsement','Permit to operate a small business within the barangay jurisdiction.','Valid ID\nDTI registration (if applicable)\nLocation sketch\nPhotos of business establishment','3-5 business days','₱500.00',1,4),
('Good Moral Certificate','Certifies good moral character and community standing.','Valid ID\nBarangay ID','1-2 business days','Free',1,5),
('Certificate of Indigency for Medical','Special indigency certificate for PhilHealth claims and hospital bills.','Valid ID\nMedical certificate or hospital billing statement','Same day','Free',1,6),
('Other Services','Explore other services offered by the barangay.','Valid ID\nRequest letter','Varies','Varies',1,7);

-- Announcements
INSERT INTO announcements (title, type, content, date_posted, posted_by, is_pinned, is_active) VALUES
('Barangay Clean-Up Drive','event',"Let's work together for a cleaner and greener community! Join us this Saturday, April 21, 2026 for our monthly clean-up drive starting at 8:00 AM. All household heads are encouraged to participate. Gloves and trash bags will be provided. Let's make Barangay Gasang the cleanest barangay in San Isidro!",'2026-04-21 08:00:00',1,0,1),
('Water Service Interruption','utility','Water supply will be interrupted due to maintenance from 8AM–5PM on April 30, 2026. Please store enough water for your household needs. Service is expected to resume by 5:00 PM. We apologize for the inconvenience.','2026-04-30 12:00:00',1,1,1),
('Free Vaccination Day','health','Free vaccines available for all residents at Barangay Hall. Bring your vaccination card. Vaccines include flu, pneumonia, and COVID-19 boosters. Priority for senior citizens and pregnant women.','2026-04-18 09:00:00',1,1,1),
('Barangay Assembly Meeting','general','All residents are invited to attend the quarterly barangay assembly. Important matters regarding road repairs, new street lights, and the upcoming fiesta will be discussed. Attendance of household heads is mandatory.','2026-04-15 08:00:00',1,0,1),
('DOST-SEI Scholarship Open','scholarship','DOST-SEI scholarship for AY 2026–2027 is now open. Eligible: graduating SHS students with GWA 90+. Deadline: May 15, 2026. Inquire at Barangay Hall for assistance.','2026-04-10 08:00:00',1,0,1),
('Free Bread & Pastry Training','livelihood','TESDA-accredited Free Bread and Pastry Production NC II Training starts May 5, 2026. Open to unemployed residents and OSY aged 18–45. Only 25 slots. Register at Barangay Hall.','2026-04-08 09:00:00',1,0,1),
('LOST: Black Labrador Dog','lost-found','Missing black Labrador "Brownie" near Zone 3. Male, 3 years old, red collar. Contact 0917-XXX-XXXX if found. Reward available.','2026-04-06 15:00:00',1,0,0),
('Typhoon Emergency Advisory','emergency','Signal No. 2 raised. Residents in low-lying areas must evacuate to Barangay Hall. Prepare emergency kits. Updates posted regularly.','2026-04-03 18:00:00',1,1,0);

-- Document Requests
INSERT INTO document_requests (request_number, resident_id, document_type_id, status, purpose, fee, payment_method, payment_status, requested_on, update_on) VALUES
('#1023','BRGY-2026-01234',1,'completed','For employment at ABC Company','₱50.00','cash','paid','2026-04-24','2026-04-28'),
('#1022','BRGY-2026-01234',2,'ready','For NBI clearance application','₱30.00','gcash','paid','2026-04-20','2026-04-23'),
('#1031','BRGY-2026-01234',1,'pending','For school enrollment','₱50.00','cash','unpaid','2026-05-03','2026-05-03'),
('#1032','BRGY-2026-01234',4,'pending','For sari-sari store permit','₱500.00','gcash','unpaid','2026-05-03','2026-05-03'),
('#1033','BRGY-2026-01234',1,'pending','For travel requirement','₱50.00','cash','unpaid','2026-05-03','2026-05-03'),
('#1019','BRGY-2026-01234',3,'pending','For medical assistance','Free','cash','unpaid','2026-04-23','2026-04-23'),
('#1018','BRGY-2026-01235',2,'processing','For scholarship application','₱30.00','cash','unpaid','2026-04-22','2026-04-24'),
('#1015','BRGY-2026-01237',1,'completed','Employment requirement','₱50.00','cash','paid','2026-04-15','2026-04-18'),
('#1010','BRGY-2026-01238',2,'processing','For government ID','₱30.00','cash','unpaid','2026-04-22','2026-04-24');

-- Notifications for resident juan
INSERT INTO notifications (user_id, title, message, type, is_read, related_id) VALUES
(5,'Digital certificate sent','A digital copy of your Barangay Clearance is now available.','request',0,1),
(5,'Request completed','Your request #1023 has been completed.','request',0,1),
(5,'Request processing','Your request #1022 is now being processed by the barangay office.','request',1,2),
(5,'Digital certificate sent','A digital copy of your Barangay Clearance is now available.','request',1,1);

-- Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('barangay_name','Barangay Gasang'),
('municipality','San Isidro'),
('province','Batangas'),
('region','Region IV-A (CALABARZON)'),
('contact_number','(02) 8123-4567'),
('email','barangay.gasang@sanisidro.gov.ph'),
('captain_name','Hon. Roberto A. Dela Cruz'),
('secretary_name','Senyora Maria T. Santos'),
('population_total','2350'),
('households_total','487'),
('vaccinated_count','1980'),
('senior_citizens','210'),
('pregnant_women','35'),
('pwd_count','45');

-- Activity Logs
INSERT INTO activity_logs (user_name, action, target_type, details) VALUES
('Maria Santos','Updated request #1022 to Ready','request','Document: Certificate of Residency'),
('Maria Santos','Posted announcement: Free Vaccination Day','announcement','Type: health'),
('Maria Santos','Updated request #1018 to Processing','request','Document: Certificate of Residency'),
('Maria Santos','Added new resident: Mark Torres','resident','Zone 3');
