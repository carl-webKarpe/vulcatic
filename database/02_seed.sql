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


-- More history over the last two weeks across Siargao towns, for the admin dashboard.
INSERT INTO jobs (id, job_code, customer_id, provider_id, service_id, vehicle_type, mode,
                  customer_lat, customer_lng, address_text, municipality, status,
                  service_fee, convenience_fee, total_amount, platform_fee, provider_earning,
                  requested_at, accepted_at, arrived_at, completed_at) VALUES
  (4, 'VLC-SAMPLE-0004', 4, 1, 1, 'motorcycle', 'onsite', 9.7887379, 126.1523863, 'Tourism Road', 'General Luna', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 13 DAY + INTERVAL 963 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 963 MINUTE + INTERVAL 2 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 963 MINUTE + INTERVAL 6 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 963 MINUTE + INTERVAL 16 MINUTE),
  (5, 'VLC-SAMPLE-0005', 2, 3, 1, 'motorcycle', 'onsite', 9.7915675, 126.1566640, 'Tourism Road', 'General Luna', 'paid', 140.00, 0.00, 140.00, 7.00, 133.00, CURDATE() - INTERVAL 13 DAY + INTERVAL 602 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 602 MINUTE + INTERVAL 2 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 602 MINUTE + INTERVAL 10 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 602 MINUTE + INTERVAL 31 MINUTE),
  (6, 'VLC-SAMPLE-0006', 4, 2, 4, 'motorcycle', 'onsite', 9.7551692, 126.0546969, 'Dapa public market', 'Dapa', 'paid', 20.00, 0.00, 20.00, 1.00, 19.00, CURDATE() - INTERVAL 13 DAY + INTERVAL 631 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 631 MINUTE + INTERVAL 4 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 631 MINUTE + INTERVAL 13 MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL 631 MINUTE + INTERVAL 35 MINUTE),
  (7, 'VLC-SAMPLE-0007', 3, 2, 1, 'motorcycle', 'onsite', 9.8626030, 125.9719609, 'Del Carmen town proper', 'Del Carmen', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 12 DAY + INTERVAL 964 MINUTE, CURDATE() - INTERVAL 12 DAY + INTERVAL 964 MINUTE + INTERVAL 1 MINUTE, CURDATE() - INTERVAL 12 DAY + INTERVAL 964 MINUTE + INTERVAL 13 MINUTE, CURDATE() - INTERVAL 12 DAY + INTERVAL 964 MINUTE + INTERVAL 34 MINUTE),
  (8, 'VLC-SAMPLE-0008', 4, NULL, 1, 'motorcycle', 'onsite', 9.7623435, 126.0537550, 'Union junction', 'Dapa', 'cancelled', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 11 DAY + INTERVAL 497 MINUTE, NULL, NULL, NULL),
  (9, 'VLC-SAMPLE-0009', 3, 2, 1, 'motorcycle', 'onsite', 9.8709172, 125.9745754, 'Near Sayak Airport road', 'Del Carmen', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 11 DAY + INTERVAL 449 MINUTE, CURDATE() - INTERVAL 11 DAY + INTERVAL 449 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 11 DAY + INTERVAL 449 MINUTE + INTERVAL 9 MINUTE, CURDATE() - INTERVAL 11 DAY + INTERVAL 449 MINUTE + INTERVAL 20 MINUTE),
  (10, 'VLC-SAMPLE-0010', 2, 2, 2, 'car', 'onsite', 9.7586914, 126.0559714, 'Dapa public market', 'Dapa', 'paid', 120.00, 0.00, 120.00, 6.00, 114.00, CURDATE() - INTERVAL 11 DAY + INTERVAL 815 MINUTE, CURDATE() - INTERVAL 11 DAY + INTERVAL 815 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 11 DAY + INTERVAL 815 MINUTE + INTERVAL 9 MINUTE, CURDATE() - INTERVAL 11 DAY + INTERVAL 815 MINUTE + INTERVAL 30 MINUTE),
  (11, 'VLC-SAMPLE-0011', 2, 1, 1, 'motorcycle', 'onsite', 9.7861146, 126.1538557, 'Malinao road', 'General Luna', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 10 DAY + INTERVAL 971 MINUTE, CURDATE() - INTERVAL 10 DAY + INTERVAL 971 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 10 DAY + INTERVAL 971 MINUTE + INTERVAL 11 MINUTE, CURDATE() - INTERVAL 10 DAY + INTERVAL 971 MINUTE + INTERVAL 19 MINUTE),
  (12, 'VLC-SAMPLE-0012', 4, 6, 1, 'car', 'onsite', 9.9454027, 126.0902397, 'San Isidro town proper', 'San Isidro', 'paid', 160.00, 0.00, 160.00, 8.00, 152.00, CURDATE() - INTERVAL 10 DAY + INTERVAL 1071 MINUTE, CURDATE() - INTERVAL 10 DAY + INTERVAL 1071 MINUTE + INTERVAL 4 MINUTE, CURDATE() - INTERVAL 10 DAY + INTERVAL 1071 MINUTE + INTERVAL 14 MINUTE, CURDATE() - INTERVAL 10 DAY + INTERVAL 1071 MINUTE + INTERVAL 34 MINUTE),
  (13, 'VLC-SAMPLE-0013', 2, 1, 4, 'motorcycle', 'onsite', 9.7880806, 126.1524206, 'Cloud 9 boardwalk', 'General Luna', 'paid', 20.00, 0.00, 20.00, 1.00, 19.00, CURDATE() - INTERVAL 9 DAY + INTERVAL 503 MINUTE, CURDATE() - INTERVAL 9 DAY + INTERVAL 503 MINUTE + INTERVAL 1 MINUTE, CURDATE() - INTERVAL 9 DAY + INTERVAL 503 MINUTE + INTERVAL 6 MINUTE, CURDATE() - INTERVAL 9 DAY + INTERVAL 503 MINUTE + INTERVAL 20 MINUTE),
  (14, 'VLC-SAMPLE-0014', 3, 3, 2, 'motorcycle', 'onsite', 9.7941872, 126.1599448, 'General Luna town proper', 'General Luna', 'paid', 110.00, 0.00, 110.00, 5.50, 104.50, CURDATE() - INTERVAL 9 DAY + INTERVAL 665 MINUTE, CURDATE() - INTERVAL 9 DAY + INTERVAL 665 MINUTE + INTERVAL 2 MINUTE, CURDATE() - INTERVAL 9 DAY + INTERVAL 665 MINUTE + INTERVAL 7 MINUTE, CURDATE() - INTERVAL 9 DAY + INTERVAL 665 MINUTE + INTERVAL 25 MINUTE),
  (15, 'VLC-SAMPLE-0015', 4, 3, 1, 'motorcycle', 'onsite', 9.7922808, 126.1593132, 'Catangnan bridge', 'General Luna', 'paid', 140.00, 0.00, 140.00, 7.00, 133.00, CURDATE() - INTERVAL 8 DAY + INTERVAL 1134 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 1134 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 1134 MINUTE + INTERVAL 15 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 1134 MINUTE + INTERVAL 34 MINUTE),
  (16, 'VLC-SAMPLE-0016', 2, 1, 4, 'motorcycle', 'onsite', 9.7938200, 126.1579190, 'Cloud 9 boardwalk', 'General Luna', 'paid', 20.00, 0.00, 20.00, 1.00, 19.00, CURDATE() - INTERVAL 8 DAY + INTERVAL 862 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 862 MINUTE + INTERVAL 1 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 862 MINUTE + INTERVAL 5 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 862 MINUTE + INTERVAL 21 MINUTE),
  (17, 'VLC-SAMPLE-0017', 2, 2, 1, 'motorcycle', 'onsite', 9.7654600, 126.0519171, 'Union junction', 'Dapa', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 8 DAY + INTERVAL 852 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 852 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 852 MINUTE + INTERVAL 10 MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL 852 MINUTE + INTERVAL 33 MINUTE),
  (18, 'VLC-SAMPLE-0018', 3, 1, 1, 'motorcycle', 'onsite', 9.7940158, 126.1529592, 'Tourism Road', 'General Luna', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 7 DAY + INTERVAL 567 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 567 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 567 MINUTE + INTERVAL 8 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 567 MINUTE + INTERVAL 28 MINUTE),
  (19, 'VLC-SAMPLE-0019', 4, 1, 4, 'motorcycle', 'onsite', 9.7858138, 126.1592388, 'Tourism Road', 'General Luna', 'paid', 20.00, 0.00, 20.00, 1.00, 19.00, CURDATE() - INTERVAL 7 DAY + INTERVAL 990 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 990 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 990 MINUTE + INTERVAL 9 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 990 MINUTE + INTERVAL 34 MINUTE),
  (20, 'VLC-SAMPLE-0020', 3, 1, 1, 'motorcycle', 'onsite', 9.7939139, 126.1536883, 'Cloud 9 boardwalk', 'General Luna', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 7 DAY + INTERVAL 915 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 915 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 915 MINUTE + INTERVAL 11 MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL 915 MINUTE + INTERVAL 36 MINUTE),
  (21, 'VLC-SAMPLE-0021', 2, 1, 1, 'van', 'onsite', 9.7913027, 126.1582083, 'Malinao road', 'General Luna', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 6 DAY + INTERVAL 879 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 879 MINUTE + INTERVAL 1 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 879 MINUTE + INTERVAL 13 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 879 MINUTE + INTERVAL 22 MINUTE),
  (22, 'VLC-SAMPLE-0022', 2, 3, 1, 'van', 'onsite', 9.7869819, 126.1542153, 'Malinao road', 'General Luna', 'paid', 140.00, 0.00, 140.00, 7.00, 133.00, CURDATE() - INTERVAL 6 DAY + INTERVAL 468 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 468 MINUTE + INTERVAL 1 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 468 MINUTE + INTERVAL 12 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 468 MINUTE + INTERVAL 30 MINUTE),
  (23, 'VLC-SAMPLE-0023', 3, 6, 1, 'car', 'onsite', 9.9452980, 126.0905937, 'Road to Pacifico', 'San Isidro', 'paid', 160.00, 0.00, 160.00, 8.00, 152.00, CURDATE() - INTERVAL 6 DAY + INTERVAL 566 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 566 MINUTE + INTERVAL 1 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 566 MINUTE + INTERVAL 11 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 566 MINUTE + INTERVAL 33 MINUTE),
  (24, 'VLC-SAMPLE-0024', 3, 1, 1, 'motorcycle', 'onsite', 9.7952741, 126.1571477, 'Cloud 9 boardwalk', 'General Luna', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 6 DAY + INTERVAL 569 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 569 MINUTE + INTERVAL 2 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 569 MINUTE + INTERVAL 7 MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL 569 MINUTE + INTERVAL 27 MINUTE),
  (25, 'VLC-SAMPLE-0025', 2, 2, 1, 'motorcycle', 'onsite', 9.7590553, 126.0518529, 'Union junction', 'Dapa', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 5 DAY + INTERVAL 755 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 755 MINUTE + INTERVAL 4 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 755 MINUTE + INTERVAL 15 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 755 MINUTE + INTERVAL 23 MINUTE),
  (26, 'VLC-SAMPLE-0026', 2, 3, 1, 'tricycle', 'onsite', 9.7850087, 126.1541754, 'Malinao road', 'General Luna', 'paid', 140.00, 0.00, 140.00, 7.00, 133.00, CURDATE() - INTERVAL 5 DAY + INTERVAL 834 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 834 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 834 MINUTE + INTERVAL 13 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 834 MINUTE + INTERVAL 25 MINUTE),
  (27, 'VLC-SAMPLE-0027', 2, 2, 1, 'motorcycle', 'onsite', 9.7647434, 126.0511514, 'Dapa public market', 'Dapa', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 5 DAY + INTERVAL 518 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 518 MINUTE + INTERVAL 2 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 518 MINUTE + INTERVAL 7 MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL 518 MINUTE + INTERVAL 23 MINUTE),
  (28, 'VLC-SAMPLE-0028', 2, NULL, 4, 'motorcycle', 'onsite', 9.7651200, 126.0511429, 'Dapa port', 'Dapa', 'cancelled', 20.00, 0.00, 20.00, 1.00, 19.00, CURDATE() - INTERVAL 5 DAY + INTERVAL 1095 MINUTE, NULL, NULL, NULL),
  (29, 'VLC-SAMPLE-0029', 4, NULL, 1, 'van', 'onsite', 9.9431140, 126.0873197, 'Road to Pacifico', 'San Isidro', 'cancelled', 160.00, 0.00, 160.00, 8.00, 152.00, CURDATE() - INTERVAL 4 DAY + INTERVAL 771 MINUTE, NULL, NULL, NULL),
  (30, 'VLC-SAMPLE-0030', 4, 5, 2, 'motorcycle', 'onsite', 9.8609482, 126.1005764, 'Road to Magpupungko', 'Pilar', 'paid', 120.00, 0.00, 120.00, 6.00, 114.00, CURDATE() - INTERVAL 4 DAY + INTERVAL 953 MINUTE, CURDATE() - INTERVAL 4 DAY + INTERVAL 953 MINUTE + INTERVAL 4 MINUTE, CURDATE() - INTERVAL 4 DAY + INTERVAL 953 MINUTE + INTERVAL 16 MINUTE, CURDATE() - INTERVAL 4 DAY + INTERVAL 953 MINUTE + INTERVAL 33 MINUTE),
  (31, 'VLC-SAMPLE-0031', 2, NULL, 1, 'van', 'onsite', 9.7888564, 126.1547804, 'General Luna town proper', 'General Luna', 'cancelled', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 4 DAY + INTERVAL 520 MINUTE, NULL, NULL, NULL),
  (32, 'VLC-SAMPLE-0032', 2, 1, 1, 'motorcycle', 'onsite', 9.7923122, 126.1523619, 'Malinao road', 'General Luna', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 3 DAY + INTERVAL 840 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 840 MINUTE + INTERVAL 3 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 840 MINUTE + INTERVAL 12 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 840 MINUTE + INTERVAL 30 MINUTE),
  (33, 'VLC-SAMPLE-0033', 2, 2, 1, 'motorcycle', 'onsite', 9.7561955, 126.0516827, 'Dapa port', 'Dapa', 'paid', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 3 DAY + INTERVAL 941 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 941 MINUTE + INTERVAL 2 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 941 MINUTE + INTERVAL 9 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 941 MINUTE + INTERVAL 33 MINUTE),
  (34, 'VLC-SAMPLE-0034', 2, 2, 4, 'tricycle', 'onsite', 9.7542699, 126.0514340, 'Union junction', 'Dapa', 'paid', 20.00, 0.00, 20.00, 1.00, 19.00, CURDATE() - INTERVAL 3 DAY + INTERVAL 954 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 954 MINUTE + INTERVAL 2 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 954 MINUTE + INTERVAL 16 MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL 954 MINUTE + INTERVAL 36 MINUTE),
  (35, 'VLC-SAMPLE-0035', 4, NULL, 4, 'motorcycle', 'onsite', 9.9415280, 126.0908708, 'Road to Pacifico', 'San Isidro', 'cancelled', 25.00, 0.00, 25.00, 1.25, 23.75, CURDATE() - INTERVAL 2 DAY + INTERVAL 936 MINUTE, NULL, NULL, NULL),
  (36, 'VLC-SAMPLE-0036', 2, 1, 4, 'van', 'onsite', 9.7885194, 126.1556111, 'Tourism Road', 'General Luna', 'paid', 20.00, 0.00, 20.00, 1.00, 19.00, CURDATE() - INTERVAL 1 DAY + INTERVAL 1035 MINUTE, CURDATE() - INTERVAL 1 DAY + INTERVAL 1035 MINUTE + INTERVAL 4 MINUTE, CURDATE() - INTERVAL 1 DAY + INTERVAL 1035 MINUTE + INTERVAL 12 MINUTE, CURDATE() - INTERVAL 1 DAY + INTERVAL 1035 MINUTE + INTERVAL 20 MINUTE),
  (37, 'VLC-SAMPLE-0037', 3, NULL, 1, 'motorcycle', 'onsite', 9.8610263, 126.0975956, 'Pilar town proper', 'Pilar', 'cancelled', 150.00, 0.00, 150.00, 7.50, 142.50, CURDATE() - INTERVAL 1 DAY + INTERVAL 647 MINUTE, NULL, NULL, NULL),
  (38, 'VLC-SAMPLE-0038', 3, 3, 1, 'motorcycle', 'onsite', 9.7849297, 126.1531794, 'Tourism Road', 'General Luna', 'paid', 140.00, 0.00, 140.00, 7.00, 133.00, CURDATE() - INTERVAL 1 DAY + INTERVAL 968 MINUTE, CURDATE() - INTERVAL 1 DAY + INTERVAL 968 MINUTE + INTERVAL 1 MINUTE, CURDATE() - INTERVAL 1 DAY + INTERVAL 968 MINUTE + INTERVAL 12 MINUTE, CURDATE() - INTERVAL 1 DAY + INTERVAL 968 MINUTE + INTERVAL 21 MINUTE);

INSERT INTO payments (job_id, method, amount, status, reference_no, paid_at) VALUES
  (4, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 13 DAY + INTERVAL 963 MINUTE + INTERVAL 17 MINUTE),
  (5, 'gcash', 140.00, 'paid', '2813252412811', CURDATE() - INTERVAL 13 DAY + INTERVAL 602 MINUTE + INTERVAL 32 MINUTE),
  (6, 'cash', 20.00, 'paid', NULL, CURDATE() - INTERVAL 13 DAY + INTERVAL 631 MINUTE + INTERVAL 36 MINUTE),
  (7, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 12 DAY + INTERVAL 964 MINUTE + INTERVAL 35 MINUTE),
  (9, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 11 DAY + INTERVAL 449 MINUTE + INTERVAL 21 MINUTE),
  (10, 'cash', 120.00, 'paid', NULL, CURDATE() - INTERVAL 11 DAY + INTERVAL 815 MINUTE + INTERVAL 31 MINUTE),
  (11, 'gcash', 150.00, 'paid', '6607364738321', CURDATE() - INTERVAL 10 DAY + INTERVAL 971 MINUTE + INTERVAL 20 MINUTE),
  (12, 'cash', 160.00, 'paid', NULL, CURDATE() - INTERVAL 10 DAY + INTERVAL 1071 MINUTE + INTERVAL 35 MINUTE),
  (13, 'cash', 20.00, 'paid', NULL, CURDATE() - INTERVAL 9 DAY + INTERVAL 503 MINUTE + INTERVAL 21 MINUTE),
  (14, 'gcash', 110.00, 'paid', '4607871724019', CURDATE() - INTERVAL 9 DAY + INTERVAL 665 MINUTE + INTERVAL 26 MINUTE),
  (15, 'gcash', 140.00, 'paid', '6800364916875', CURDATE() - INTERVAL 8 DAY + INTERVAL 1134 MINUTE + INTERVAL 35 MINUTE),
  (16, 'cash', 20.00, 'paid', NULL, CURDATE() - INTERVAL 8 DAY + INTERVAL 862 MINUTE + INTERVAL 22 MINUTE),
  (17, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 8 DAY + INTERVAL 852 MINUTE + INTERVAL 34 MINUTE),
  (18, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 7 DAY + INTERVAL 567 MINUTE + INTERVAL 29 MINUTE),
  (19, 'gcash', 20.00, 'paid', '2806676595781', CURDATE() - INTERVAL 7 DAY + INTERVAL 990 MINUTE + INTERVAL 35 MINUTE),
  (20, 'gcash', 150.00, 'paid', '3299962069173', CURDATE() - INTERVAL 7 DAY + INTERVAL 915 MINUTE + INTERVAL 37 MINUTE),
  (21, 'gcash', 150.00, 'paid', '2867350806059', CURDATE() - INTERVAL 6 DAY + INTERVAL 879 MINUTE + INTERVAL 23 MINUTE),
  (22, 'cash', 140.00, 'paid', NULL, CURDATE() - INTERVAL 6 DAY + INTERVAL 468 MINUTE + INTERVAL 31 MINUTE),
  (23, 'cash', 160.00, 'paid', NULL, CURDATE() - INTERVAL 6 DAY + INTERVAL 566 MINUTE + INTERVAL 34 MINUTE),
  (24, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 6 DAY + INTERVAL 569 MINUTE + INTERVAL 28 MINUTE),
  (25, 'gcash', 150.00, 'paid', '2133702894829', CURDATE() - INTERVAL 5 DAY + INTERVAL 755 MINUTE + INTERVAL 24 MINUTE),
  (26, 'gcash', 140.00, 'paid', '2573362692983', CURDATE() - INTERVAL 5 DAY + INTERVAL 834 MINUTE + INTERVAL 26 MINUTE),
  (27, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 5 DAY + INTERVAL 518 MINUTE + INTERVAL 24 MINUTE),
  (30, 'cash', 120.00, 'paid', NULL, CURDATE() - INTERVAL 4 DAY + INTERVAL 953 MINUTE + INTERVAL 34 MINUTE),
  (32, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 3 DAY + INTERVAL 840 MINUTE + INTERVAL 31 MINUTE),
  (33, 'cash', 150.00, 'paid', NULL, CURDATE() - INTERVAL 3 DAY + INTERVAL 941 MINUTE + INTERVAL 34 MINUTE),
  (34, 'cash', 20.00, 'paid', NULL, CURDATE() - INTERVAL 3 DAY + INTERVAL 954 MINUTE + INTERVAL 37 MINUTE),
  (36, 'gcash', 20.00, 'paid', '2161900280419', CURDATE() - INTERVAL 1 DAY + INTERVAL 1035 MINUTE + INTERVAL 21 MINUTE),
  (38, 'gcash', 140.00, 'paid', '6117408926999', CURDATE() - INTERVAL 1 DAY + INTERVAL 968 MINUTE + INTERVAL 22 MINUTE);

INSERT INTO ratings (job_id, customer_id, provider_id, stars, comment) VALUES
  (4, 4, 1, 4, 'Very fast, salamat!'),
  (5, 2, 3, 5, 'Arrived quickly.'),
  (6, 4, 2, 5, 'Saved my surf trip.'),
  (7, 3, 2, 4, 'Arrived quickly.'),
  (10, 2, 2, 5, NULL),
  (12, 4, 6, 5, 'Saved my surf trip.'),
  (13, 2, 1, 4, NULL),
  (15, 4, 3, 5, NULL),
  (19, 4, 1, 5, 'Saved my surf trip.'),
  (20, 3, 1, 4, NULL),
  (22, 2, 3, 5, NULL),
  (23, 3, 6, 4, 'Friendly and fair price.'),
  (25, 2, 2, 5, 'Good job.'),
  (26, 2, 3, 5, 'Good job.'),
  (32, 2, 1, 5, 'Friendly and fair price.'),
  (33, 2, 2, 4, NULL),
  (34, 2, 2, 5, NULL);

-- (WHERE id > 0 keeps MySQL Workbench's Safe Updates mode happy)
UPDATE users SET terms_accepted_at = NOW() - INTERVAL 30 DAY WHERE id > 0;

-- keep providers.rating_avg / rating_count in sync (the PHP app does this
-- after every new rating, because shared hosting does not allow triggers)
UPDATE providers
SET rating_count = (SELECT COUNT(*) FROM ratings r WHERE r.provider_id = providers.id),
    rating_avg   = COALESCE((SELECT ROUND(AVG(stars), 2) FROM ratings r WHERE r.provider_id = providers.id), 0)
WHERE id > 0;
