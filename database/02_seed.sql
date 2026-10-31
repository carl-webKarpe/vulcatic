-- =====================================================================
-- Vulcatic - sample data for testing on SIARGAO ISLAND
-- All test accounts use the password:  password123
-- Locations are approximate (near each town centre / main road).
-- Check them on the map and adjust if a pin lands in the wrong place.
-- All people and shop names are fictional.
-- Run AFTER 01_schema.sql.
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO app_settings (setting_key, setting_value) VALUES
  ('platform_fee_rate',       '0.05'),  -- 5% -> P150 job = P7.50 fee, P142.50 net
  ('convenience_fee',         '0.00'),
  ('default_search_radius_km','5'),     -- per-town radius lives in includes/siargao.php
  ('offer_timeout_seconds',   '60'),
  ('currency',                'PHP');

INSERT INTO users (id, role, full_name, email, phone, password_hash) VALUES
  (1,  'admin',    'Vulcatic Admin', 'admin@vulcatic.test', '09170000000', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (2,  'customer', 'Juan Cruz',      'juan@vulcatic.test',  '09171111111', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (3,  'customer', 'Maria Reyes',    'maria@vulcatic.test', '09172222222', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (4,  'customer', 'Carlo Lim',      'carlo@vulcatic.test', '09173333333', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (5,  'provider', 'Jose Dela Cruz', 'jose@vulcatic.test',  '09174444444', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (6,  'provider', 'John Doe',       'john@vulcatic.test',  '09175555555', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (7,  'provider', 'Coco Mamin',     'coco@vulcatic.test',  '09176666666', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (8,  'provider', 'Pedro Santos',   'pedro@vulcatic.test', '09177777777', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (9,  'provider', 'Lito Ganzon',    'lito@vulcatic.test',  '09178888888', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK'),
  (10, 'provider', 'Nonoy Ybanez',   'nonoy@vulcatic.test', '09179999999', '$2y$10$zA9tfkmTwu09cxozfVPuTu9Nxm9OusZXoGWv1s3DgaAzGAalgWdmK');

-- Phase 1 (General Luna + Dapa) is busy; phases 2-3 have one each to start.
INSERT INTO providers (id, user_id, provider_type, display_name, address, municipality, latitude, longitude,
                       offers_onsite, accepts_walk_in, is_online, opening_hours,
                       verification_status, gcash_number, last_location_at) VALUES
  (1, 5,  'individual', 'Jose Dela Cruz',          'Tourism Road',                 'General Luna',  9.7960000, 126.1590000, 1, 0, 1, NULL,                 'verified', '09174444444', NOW()),
  (2, 6,  'shop',       'John Doe Vulcanizing',    'Poblacion, near the port',     'Dapa',          9.7600000, 126.0510000, 1, 1, 1, 'Mon-Sun 7:00-19:00', 'verified', '09175555555', NOW()),
  (3, 7,  'individual', 'Coco Mamin',              'Catangnan, near Cloud 9',      'General Luna',  9.8080000, 126.1590000, 1, 0, 1, NULL,                 'verified', NULL,          NOW()),
  (4, 8,  'shop',       'Santos Tire & Vulcanizing','Airport road, near Sayak Airport', 'Del Carmen', 9.8600000, 126.0160000, 0, 1, 0, 'Mon-Sat 8:00-18:00', 'pending', NULL,     NULL),
  (5, 9,  'shop',       'Pilar Vulcanizing',       'Poblacion, road to Magpupungko','Pilar',        9.8640000, 126.1000000, 1, 1, 0, 'Mon-Sun 7:00-18:00', 'verified', '09178888888', NULL),
  (6, 10, 'individual', 'Nonoy Ybanez',            'Poblacion, road to Pacifico',  'San Isidro',    9.9380000, 126.0880000, 1, 0, 1, NULL,                 'verified', NULL,          NOW());

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
  (4, 1, 130.00), (4, 2, 100.00), (4, 3, 180.00), (4, 4, 15.00), (4, 5, 250.00),
  (5, 1, 150.00), (5, 2, 120.00), (5, 3, 200.00), (5, 4, 20.00),
  (6, 1, 160.00), (6, 4, 25.00);

-- Three finished jobs for Jose (General Luna), so his dashboard has history.
INSERT INTO jobs (id, job_code, customer_id, provider_id, service_id, vehicle_type, mode,
                  customer_lat, customer_lng, address_text, municipality, status,
                  service_fee, convenience_fee, total_amount, platform_fee, provider_earning,
                  requested_at, accepted_at, arrived_at, completed_at) VALUES
  (1, 'VLC-SAMPLE-0001', 2, 1, 1, 'motorcycle', 'onsite',
   9.7900000, 126.1580000, 'Tourism Road, near the surf shops', 'General Luna', 'paid',
   150.00, 0.00, 150.00, 7.50, 142.50,
   NOW() - INTERVAL 40 MINUTE, NOW() - INTERVAL 38 MINUTE,
   NOW() - INTERVAL 32 MINUTE, NOW() - INTERVAL 20 MINUTE),
  (2, 'VLC-SAMPLE-0002', 3, 1, 1, 'motorcycle', 'onsite',
   9.8050000, 126.1600000, 'Cloud 9 boardwalk entrance', 'General Luna', 'paid',
   150.00, 0.00, 150.00, 7.50, 142.50,
   NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 179 MINUTE,
   NOW() - INTERVAL 170 MINUTE, NOW() - INTERVAL 160 MINUTE),
  (3, 'VLC-SAMPLE-0003', 4, 1, 4, 'motorcycle', 'onsite',
   9.7850000, 126.1550000, 'General Luna town proper', 'General Luna', 'paid',
   20.00, 0.00, 20.00, 1.00, 19.00,
   NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 299 MINUTE,
   NOW() - INTERVAL 290 MINUTE, NOW() - INTERVAL 285 MINUTE);

INSERT INTO job_offers (job_id, provider_id, distance_km, status, offered_at, responded_at) VALUES
  (1, 1, 0.68, 'accepted', NOW() - INTERVAL 40 MINUTE, NOW() - INTERVAL 38 MINUTE),
  (1, 3, 2.00, 'expired',  NOW() - INTERVAL 40 MINUTE, NULL);

INSERT INTO job_status_logs (job_id, status, note, created_at) VALUES
  (1, 'searching',   'Location detected',             NOW() - INTERVAL 40 MINUTE),
  (1, 'matched',     '2 vulcator(s) nearby notified', NOW() - INTERVAL 39 MINUTE),
  (1, 'accepted',    'Jose accepted your request',    NOW() - INTERVAL 38 MINUTE),
  (1, 'on_the_way',  'On the way',                    NOW() - INTERVAL 38 MINUTE),
  (1, 'arrived',     'Arrived at customer',           NOW() - INTERVAL 32 MINUTE),
  (1, 'completed',   'Tire fixed',                    NOW() - INTERVAL 20 MINUTE),
  (1, 'paid',        'Cash payment received',         NOW() - INTERVAL 19 MINUTE);

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
