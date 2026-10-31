-- =====================================================================
-- Vulcatic - sample data for testing (matches the Figma mockups)
-- All test accounts use the password:  password123
-- Coordinates are placeholders (Manila area) - change them to your town.
-- Run AFTER 01_schema.sql.
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO app_settings (setting_key, setting_value) VALUES
  ('platform_fee_rate',      '0.05'),   -- 5% -> P150 job = P7.50 fee, P142.50 net
  ('convenience_fee',        '0.00'),
  ('default_search_radius_km','5'),
  ('offer_timeout_seconds',  '60'),
  ('currency',               'PHP');

INSERT INTO users (id, role, full_name, email, phone, password_hash) VALUES
  (1, 'admin',    'Vulcatic Admin', 'admin@vulcatic.test', '09170000000', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (2, 'customer', 'Juan Cruz',      'juan@vulcatic.test',  '09171111111', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (3, 'customer', 'Maria Reyes',    'maria@vulcatic.test', '09172222222', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (4, 'customer', 'Carlo Lim',      'carlo@vulcatic.test', '09173333333', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (5, 'provider', 'Jose Dela Cruz', 'jose@vulcatic.test',  '09174444444', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (6, 'provider', 'John Doe',       'john@vulcatic.test',  '09175555555', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (7, 'provider', 'Coco Mamin',     'coco@vulcatic.test',  '09176666666', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (8, 'provider', 'Pedro Santos',   'pedro@vulcatic.test', '09177777777', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK');

INSERT INTO providers (id, user_id, provider_type, display_name, address, latitude, longitude,
                       offers_onsite, accepts_walk_in, is_online, opening_hours,
                       verification_status, gcash_number, last_location_at) VALUES
  (1, 5, 'individual', 'Jose Dela Cruz',          'Barangay 12, Cemetery Street', 14.6001000, 120.9850000, 1, 0, 1, NULL,                 'verified', '09174444444', NOW()),
  (2, 6, 'shop',       'John Doe Vulcanizing',    'Barangay 14, Rizal Avenue',    14.6025000, 120.9831000, 1, 1, 1, 'Mon-Sun 7:00-19:00', 'verified', '09175555555', NOW()),
  (3, 7, 'individual', 'Coco Mamin',              'Barangay 9, Mabini Street',    14.5978000, 120.9819000, 1, 0, 1, NULL,                 'verified', NULL,          NOW()),
  (4, 8, 'shop',       'Santos Tire & Vulcanizing','National Highway, Km 3',      14.6110000, 120.9950000, 0, 1, 0, 'Mon-Sat 8:00-18:00', 'pending',  NULL,          NULL);

INSERT INTO services (id, name, description, base_price) VALUES
  (1, 'Flat tire repair',  'Patch / plug a punctured tire',        150.00),
  (2, 'Tire vulcanizing',  'Hot-patch vulcanizing of inner tube',  120.00),
  (3, 'Tire replacement',  'Swap with spare or new tire (labor)',  200.00),
  (4, 'Air refill',        'Pump and pressure check',               20.00),
  (5, 'Battery jump-start','Jump-start a dead battery',            250.00);

INSERT INTO provider_services (provider_id, service_id, price) VALUES
  (1, 1, 150.00), (1, 4, 20.00),
  (2, 1, 150.00), (2, 2, 120.00), (2, 3, 200.00), (2, 4, 20.00),
  (3, 1, 140.00), (3, 2, 110.00),
  (4, 1, 130.00), (4, 2, 100.00), (4, 3, 180.00), (4, 4, 15.00), (4, 5, 250.00);

-- One finished, paid and rated job (Juan + Jose, the payment-success screen)
INSERT INTO jobs (id, job_code, customer_id, provider_id, service_id, vehicle_type, mode,
                  customer_lat, customer_lng, address_text, status,
                  service_fee, convenience_fee, total_amount, platform_fee, provider_earning,
                  requested_at, accepted_at, arrived_at, completed_at) VALUES
  (1, 'VLC-20261031-0041', 2, 1, 1, 'motorcycle', 'onsite',
   14.5995000, 120.9842000, 'Barangay 12, Cemetery Street', 'paid',
   150.00, 0.00, 150.00, 7.50, 142.50,
   NOW() - INTERVAL 40 MINUTE, NOW() - INTERVAL 38 MINUTE,
   NOW() - INTERVAL 32 MINUTE, NOW() - INTERVAL 20 MINUTE),
-- Two more completed jobs today for the "Recent jobs" list
  (2, 'VLC-20261031-0038', 3, 1, 1, 'motorcycle', 'onsite',
   14.6010000, 120.9860000, 'Barangay 13, Luna Street', 'paid',
   150.00, 0.00, 150.00, 7.50, 142.50,
   NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 179 MINUTE,
   NOW() - INTERVAL 170 MINUTE, NOW() - INTERVAL 160 MINUTE),
  (3, 'VLC-20261031-0035', 4, 1, 4, 'car', 'onsite',
   14.5985000, 120.9835000, 'Barangay 12, Bonifacio Street', 'paid',
   20.00, 0.00, 20.00, 1.00, 19.00,
   NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 299 MINUTE,
   NOW() - INTERVAL 290 MINUTE, NOW() - INTERVAL 285 MINUTE),
-- A job still searching for a provider (the "Finding help..." screen)
  (4, 'VLC-20261031-0042', 4, NULL, 1, 'motorcycle', 'onsite',
   14.5990000, 120.9845000, 'Barangay 12, Cemetery Street', 'searching',
   150.00, 0.00, 150.00, 7.50, 142.50,
   NOW(), NULL, NULL, NULL);

INSERT INTO job_offers (job_id, provider_id, distance_km, status, offered_at, responded_at) VALUES
  (1, 1, 0.12, 'accepted', NOW() - INTERVAL 40 MINUTE, NOW() - INTERVAL 38 MINUTE),
  (1, 3, 0.25, 'expired',  NOW() - INTERVAL 40 MINUTE, NULL),
  (4, 1, 0.07, 'pending',  NOW(), NULL),
  (4, 2, 0.38, 'pending',  NOW(), NULL),
  (4, 3, 0.29, 'pending',  NOW(), NULL);

INSERT INTO job_status_logs (job_id, status, note, created_at) VALUES
  (1, 'searching',   'Location detected',             NOW() - INTERVAL 40 MINUTE),
  (1, 'matched',     'Vulcator matched',              NOW() - INTERVAL 39 MINUTE),
  (1, 'accepted',    'Jose accepted your request',    NOW() - INTERVAL 38 MINUTE),
  (1, 'on_the_way',  'On the way',                    NOW() - INTERVAL 38 MINUTE),
  (1, 'arrived',     'Arrived at customer',           NOW() - INTERVAL 32 MINUTE),
  (1, 'completed',   'Fixed the tire',                NOW() - INTERVAL 20 MINUTE),
  (1, 'paid',        'Cash payment received',         NOW() - INTERVAL 19 MINUTE),
  (4, 'searching',   'Location detected',             NOW());

INSERT INTO payments (job_id, method, amount, status, reference_no, paid_at) VALUES
  (1, 'cash',  150.00, 'paid', NULL,            NOW() - INTERVAL 19 MINUTE),
  (2, 'gcash', 150.00, 'paid', '1029384756123', NOW() - INTERVAL 159 MINUTE),
  (3, 'cash',   20.00, 'paid', NULL,            NOW() - INTERVAL 284 MINUTE);

INSERT INTO ratings (job_id, customer_id, provider_id, stars, comment) VALUES
  (1, 2, 1, 5, 'Fast and friendly, arrived in 6 minutes!'),
  (2, 3, 1, 4, 'Good service.');

-- keep providers.rating_avg / rating_count in sync (the PHP app does this
-- after every new rating, because shared hosting does not allow triggers)
UPDATE providers p
JOIN (SELECT provider_id, AVG(stars) AS avg_stars, COUNT(*) AS cnt
      FROM ratings GROUP BY provider_id) r ON r.provider_id = p.id
SET p.rating_avg = r.avg_stars, p.rating_count = r.cnt;
