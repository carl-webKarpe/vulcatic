-- =====================================================================
-- Vulcatic - database schema
-- Compatible with MySQL 8 (Workbench) and MariaDB 10.x (InfinityFree /
-- phpMyAdmin). Uses no stored procedures, triggers, views or DEFINERs,
-- because shared hosting does not allow them.
--
-- Workbench: run 00_create_database.sql first, then this file.
-- phpMyAdmin: select your database, then Import this file.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS job_messages;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS job_status_logs;
DROP TABLE IF EXISTS job_offers;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS provider_services;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS providers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS password_reset_requests;
DROP TABLE IF EXISTS login_attempts;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- users: everyone who logs in (customers, providers/"vulcators", admins)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role           ENUM('customer','provider','employee','admin') NOT NULL DEFAULT 'customer',
                 -- customer = motorist, provider = shop owner or skilled local, employee = works for a shop
  full_name      VARCHAR(120) NOT NULL,
  email          VARCHAR(160) NULL,
  phone          VARCHAR(20)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,          -- PHP password_hash()
  avatar_url     VARCHAR(255) NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  motorist_type  ENUM('local','tourist','rider','rental','private_owner') NULL,  -- motorists only
  employer_provider_id INT UNSIGNED NULL,             -- employees: the shop they work for
  on_duty        TINYINT(1) NOT NULL DEFAULT 0,       -- employees: available for assignments
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,  -- set after an admin password reset
  terms_accepted_at    DATETIME NULL,                  -- agreed to Terms + Privacy Policy
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_phone (phone),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_employer (employer_provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- providers: a vulcanizing shop OR a skilled local resident
-- latitude/longitude = shop location, or live position while online
-- ---------------------------------------------------------------------
CREATE TABLE providers (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id              INT UNSIGNED NOT NULL,
  provider_type        ENUM('shop','individual') NOT NULL,
  display_name         VARCHAR(120) NOT NULL,      -- shop name or person's name
  address              VARCHAR(255) NULL,
  latitude             DECIMAL(10,7) NOT NULL,
  longitude            DECIMAL(10,7) NOT NULL,
  offers_onsite        TINYINT(1) NOT NULL DEFAULT 1,   -- travels to stranded user
  accepts_walk_in      TINYINT(1) NOT NULL DEFAULT 1,   -- user goes to the shop
  is_online            TINYINT(1) NOT NULL DEFAULT 0,   -- "Currently online" toggle
  municipality         VARCHAR(40) NULL,                 -- Siargao town, e.g. 'General Luna'
  service_radius_km    DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  opening_hours        VARCHAR(120) NULL,               -- e.g. "Mon-Sun 7:00-19:00"
  verification_status  ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  gcash_number         VARCHAR(20) NULL,                -- for GCash QR / manual pay
  rating_avg           DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  rating_count         INT UNSIGNED NOT NULL DEFAULT 0,
  last_location_at     DATETIME NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_providers_user (user_id),
  KEY idx_providers_online (is_online, verification_status),
  KEY idx_providers_location (latitude, longitude),
  KEY idx_providers_municipality (municipality),
  CONSTRAINT fk_providers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- services: catalog (flat tire repair, vulcanizing, ...)
-- ---------------------------------------------------------------------
CREATE TABLE services (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)  NOT NULL,
  description  VARCHAR(255) NULL,
  base_price   DECIMAL(10,2) NOT NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- which services each provider offers, with their own price
CREATE TABLE provider_services (
  provider_id  INT UNSIGNED NOT NULL,
  service_id   INT UNSIGNED NOT NULL,
  price        DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (provider_id, service_id),
  CONSTRAINT fk_ps_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_service  FOREIGN KEY (service_id)  REFERENCES services (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- jobs: one repair request from a customer
-- status flow (matches the "Request status" screens):
--   searching -> matched -> accepted -> on_the_way -> arrived
--   -> in_progress -> completed -> paid      (or cancelled at any point)
-- ---------------------------------------------------------------------
CREATE TABLE jobs (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_code          VARCHAR(30) NOT NULL,              -- e.g. VLC-20261031-0041
  customer_id       INT UNSIGNED NOT NULL,
  provider_id       INT UNSIGNED NULL,                 -- shop or skilled local, set once accepted
  assigned_user_id  INT UNSIGNED NULL,                 -- shop employee doing the job (NULL = owner / skilled local)
  assignment_status ENUM('pending','accepted') NULL,   -- employee's answer to the assignment
  service_id        INT UNSIGNED NOT NULL,
  vehicle_type      ENUM('motorcycle','car','tricycle','bicycle','van','truck','other') NOT NULL,
  problem           ENUM('flat_tire','puncture','other') NULL,
  mode              ENUM('onsite','walk_in') NOT NULL DEFAULT 'onsite',
  customer_lat      DECIMAL(10,7) NOT NULL,
  customer_lng      DECIMAL(10,7) NOT NULL,
  address_text      VARCHAR(255) NULL,                 -- landmark, e.g. "Tourism Road, near Cloud 9"
  municipality      VARCHAR(40) NULL,                  -- Siargao town the request came from
  directions        VARCHAR(255) NULL,                 -- extra directions from the motorist
  notes             VARCHAR(500) NULL,
  status            ENUM('searching','matched','accepted','on_the_way','arrived',
                         'in_progress','completed','paid','cancelled')
                    NOT NULL DEFAULT 'searching',
  cancel_reason     VARCHAR(255) NULL,
  service_fee       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  convenience_fee   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- what the customer pays
  platform_fee      DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- Vulcatic's cut
  provider_earning  DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- "Net earned"
  requested_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at       DATETIME NULL,
  arrived_at        DATETIME NULL,
  started_at        DATETIME NULL,                     -- service in progress
  eta_minutes       SMALLINT UNSIGNED NULL,            -- only when the provider gives one
  eta_set_at        DATETIME NULL,
  completed_at      DATETIME NULL,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jobs_code (job_code),
  KEY idx_jobs_customer (customer_id, requested_at),
  KEY idx_jobs_provider (provider_id, status, completed_at),
  KEY idx_jobs_status (status),
  KEY idx_jobs_municipality (municipality),
  KEY idx_jobs_assigned (assigned_user_id, status),
  CONSTRAINT fk_jobs_customer FOREIGN KEY (customer_id) REFERENCES users (id),
  CONSTRAINT fk_jobs_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE SET NULL,
  CONSTRAINT fk_jobs_assigned FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_jobs_service  FOREIGN KEY (service_id)  REFERENCES services (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- job_offers: request sent to nearby providers ("New job request nearby!")
-- the first provider to accept gets the job; the others expire
-- ---------------------------------------------------------------------
CREATE TABLE job_offers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id        INT UNSIGNED NOT NULL,
  provider_id   INT UNSIGNED NOT NULL,
  distance_km   DECIMAL(6,2) NULL,
  status        ENUM('pending','accepted','declined','expired') NOT NULL DEFAULT 'pending',
  offered_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at  DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_offer (job_id, provider_id),
  KEY idx_offers_provider (provider_id, status),
  CONSTRAINT fk_offers_job      FOREIGN KEY (job_id)      REFERENCES jobs (id)      ON DELETE CASCADE,
  CONSTRAINT fk_offers_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- timeline for the "Request status" / "Job checklist" panels
CREATE TABLE job_status_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id      INT UNSIGNED NOT NULL,
  status      VARCHAR(30) NOT NULL,
  note        VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logs_job (job_id, created_at),
  CONSTRAINT fk_logs_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- payments: Cash, GCash, Credit/Debit card
-- ---------------------------------------------------------------------
CREATE TABLE payments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id        INT UNSIGNED NOT NULL,
  method        ENUM('cash','gcash','card') NOT NULL,
  amount        DECIMAL(10,2) NOT NULL,
  status        ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  reference_no  VARCHAR(80) NULL,                -- GCash ref no. / gateway id
  paid_at       DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_job (job_id),
  CONSTRAINT fk_payments_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ratings: "Rate your vulcator" (one per job)
-- ---------------------------------------------------------------------
CREATE TABLE ratings (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id       INT UNSIGNED NOT NULL,
  customer_id  INT UNSIGNED NOT NULL,
  provider_id  INT UNSIGNED NOT NULL,
  stars        TINYINT UNSIGNED NOT NULL,
  comment      VARCHAR(500) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ratings_job (job_id),
  KEY idx_ratings_provider (provider_id),
  CONSTRAINT chk_ratings_stars CHECK (stars BETWEEN 1 AND 5),
  CONSTRAINT fk_ratings_job      FOREIGN KEY (job_id)      REFERENCES jobs (id)      ON DELETE CASCADE,
  CONSTRAINT fk_ratings_customer FOREIGN KEY (customer_id) REFERENCES users (id),
  CONSTRAINT fk_ratings_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- app_settings: values the app reads instead of hard-coding
-- ---------------------------------------------------------------------
CREATE TABLE app_settings (
  setting_key    VARCHAR(60)  NOT NULL,
  setting_value  VARCHAR(255) NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- password_reset_requests: "Forgot password?" (InfinityFree can't send
-- email/SMS reliably, so an admin sets a temporary password and tells the
-- user by call or text)
-- ---------------------------------------------------------------------
CREATE TABLE password_reset_requests (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,               -- NULL if the number has no account
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

-- ---------------------------------------------------------------------
-- login_attempts: failed logins, to slow down password guessing
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  login         VARCHAR(160) NOT NULL,
  ip            VARCHAR(45)  NOT NULL,
  attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_login (login, attempted_at),
  KEY idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- job_messages: in-app chat between the motorist and the shop/provider
-- ---------------------------------------------------------------------
CREATE TABLE job_messages (
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

-- Employees belong to a shop (added here because providers is created after users)
ALTER TABLE users ADD CONSTRAINT fk_users_employer FOREIGN KEY (employer_provider_id) REFERENCES providers (id) ON DELETE SET NULL;
