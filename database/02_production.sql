-- =====================================================================
-- Vulcatic - PRODUCTION setup (use this instead of 02_seed.sql when publishing)
-- Adds settings, services and ONE admin account. No sample people or jobs.
--
-- First admin login:  phone 09170000000  /  password  ChangeMe2026
-- You'll be asked to choose a new password right after logging in.
-- Then go to Profile and change the phone number to your real one.
-- Run AFTER 01_schema.sql.
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO app_settings (setting_key, setting_value) VALUES
  ('platform_fee_rate',       '0.05'),
  ('convenience_fee',         '0.00'),
  ('default_search_radius_km','5'),
  ('offer_timeout_seconds',   '60'),
  ('currency',                'PHP');

INSERT INTO services (id, name, description, base_price) VALUES
  (1, 'Flat tire repair',  'Patch / plug a punctured tire',        150.00),
  (2, 'Tire vulcanizing',  'Hot-patch vulcanizing of inner tube',  120.00),
  (3, 'Tire replacement',  'Swap with spare or new tire (labor)',  200.00),
  (4, 'Air refill',        'Pump and pressure check',               20.00),
  (5, 'Battery jump-start','Jump-start a dead battery',            250.00);

INSERT INTO users (role, full_name, phone, password_hash, must_change_password, terms_accepted_at) VALUES
  ('admin', 'Vulcatic Admin', '09170000000', '$2y$10$yrtnfqnVJgTQf/AvP0gdq.uu3Em/byatr49/V.AMw0F0p46gnfgxW', 1, NOW());
