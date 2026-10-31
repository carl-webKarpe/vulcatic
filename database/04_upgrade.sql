-- =====================================================================
-- Vulcatic - UPGRADE an existing database to the latest version
-- Adds any missing columns and tables. Keeps all your data.
-- Safe to run as many times as you like (it skips what already exists).
-- Works in MySQL Workbench (Safe Updates on) and phpMyAdmin.
-- =====================================================================

USE vulcatic;   -- on InfinityFree, remove this line and select your database first

-- Helper pattern: build the ALTER only if the column is missing, else do nothing.

-- users.must_change_password
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.terms_accepted_at
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL AFTER must_change_password',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'terms_accepted_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- providers.municipality
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE providers ADD COLUMN municipality VARCHAR(40) NULL AFTER address, ADD KEY idx_providers_municipality (municipality)',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'municipality');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs.municipality
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jobs ADD COLUMN municipality VARCHAR(40) NULL AFTER address_text, ADD KEY idx_jobs_municipality (municipality)',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'municipality');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS password_reset_requests (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  phone       VARCHAR(20)  NOT NULL,
  note        VARCHAR(255) NULL,
  status      ENUM('open','done','dismissed') NOT NULL DEFAULT 'open',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  handled_at  DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_reset_status (status, created_at),
  KEY idx_reset_phone (phone, created_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  login         VARCHAR(160) NOT NULL,
  ip            VARCHAR(45)  NOT NULL,
  attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_login (login, attempted_at),
  KEY idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- User types, shop employees, assignments, provider ETA, chat ----
ALTER TABLE users MODIFY role ENUM('customer','provider','employee','admin') NOT NULL DEFAULT 'customer';

-- users.motorist_type
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE users ADD COLUMN motorist_type ENUM(''local'',''tourist'',''rider'',''rental'',''private_owner'') NULL AFTER is_active',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'motorist_type');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.employer_provider_id
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE users ADD COLUMN employer_provider_id INT UNSIGNED NULL AFTER motorist_type, ADD KEY idx_users_employer (employer_provider_id)',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'employer_provider_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.on_duty
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE users ADD COLUMN on_duty TINYINT(1) NOT NULL DEFAULT 0 AFTER employer_provider_id',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'on_duty');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs.assigned_user_id
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jobs ADD COLUMN assigned_user_id INT UNSIGNED NULL AFTER provider_id, ADD KEY idx_jobs_assigned (assigned_user_id, status)',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'assigned_user_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs.assignment_status
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jobs ADD COLUMN assignment_status ENUM(''pending'',''accepted'') NULL AFTER assigned_user_id',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'assignment_status');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs.problem
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jobs ADD COLUMN problem ENUM(''flat_tire'',''puncture'',''other'') NULL AFTER vehicle_type',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'problem');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs.directions
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jobs ADD COLUMN directions VARCHAR(255) NULL AFTER municipality',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'directions');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs.started_at
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jobs ADD COLUMN started_at DATETIME NULL AFTER arrived_at',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'started_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs.eta_minutes
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jobs ADD COLUMN eta_minutes SMALLINT UNSIGNED NULL AFTER started_at',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'eta_minutes');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- jobs.eta_set_at
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jobs ADD COLUMN eta_set_at DATETIME NULL AFTER eta_minutes',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'eta_set_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS job_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id      INT UNSIGNED NOT NULL,
  sender_id   INT UNSIGNED NOT NULL,
  body        VARCHAR(500) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_messages_job (job_id, id),
  CONSTRAINT fk_messages_job    FOREIGN KEY (job_id)    REFERENCES jobs (id)  ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('walkin_offer_timeout_seconds', '300');
UPDATE jobs SET problem = CASE service_id WHEN 2 THEN 'puncture' WHEN 1 THEN 'flat_tire' ELSE 'other' END WHERE id > 0 AND problem IS NULL;

-- Existing accounts count as having agreed (they signed up before consent was added).
UPDATE users SET terms_accepted_at = created_at WHERE id > 0 AND terms_accepted_at IS NULL;

-- Show the result: every row should say OK.
SELECT 'users.must_change_password' AS item, IF(COUNT(*) = 1, 'OK', 'MISSING') AS status FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password'
UNION ALL SELECT 'users.terms_accepted_at', IF(COUNT(*) = 1, 'OK', 'MISSING') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'terms_accepted_at'
UNION ALL SELECT 'providers.municipality', IF(COUNT(*) = 1, 'OK', 'MISSING') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'municipality'
UNION ALL SELECT 'jobs.municipality', IF(COUNT(*) = 1, 'OK', 'MISSING') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'municipality'
UNION ALL SELECT 'table password_reset_requests', IF(COUNT(*) = 1, 'OK', 'MISSING') FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_reset_requests'
UNION ALL SELECT 'table login_attempts', IF(COUNT(*) = 1, 'OK', 'MISSING') FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_attempts'
UNION ALL SELECT 'users.employer_provider_id', IF(COUNT(*) = 1, 'OK', 'MISSING') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'employer_provider_id'
UNION ALL SELECT 'jobs.assigned_user_id + eta', IF(COUNT(*) = 2, 'OK', 'MISSING') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME IN ('assigned_user_id', 'eta_minutes')
UNION ALL SELECT 'table job_messages', IF(COUNT(*) = 1, 'OK', 'MISSING') FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_messages';
