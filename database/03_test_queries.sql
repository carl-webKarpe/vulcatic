-- =====================================================================
-- Vulcatic - test queries for MySQL Workbench
-- These are the queries the PHP backend will run for each screen.
-- Run 01_schema.sql and 02_seed.sql first. Tip: in Workbench, put the
-- cursor on one query and press Ctrl+Enter to run just that query.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. User Home / "Finding help": nearest ONLINE, VERIFIED providers
--    Haversine formula (km). Works on MySQL and MariaDB.
-- ---------------------------------------------------------------------
-- Point on Tourism Road, General Luna (Siargao)
SET @lat = 9.7900000, @lng = 126.1580000, @radius_km = 5, @service_id = 1;

SELECT p.id, p.display_name, p.provider_type, ps.price,
       p.rating_avg, p.rating_count,
       ROUND(6371 * ACOS(LEAST(1,
             COS(RADIANS(@lat)) * COS(RADIANS(p.latitude)) *
             COS(RADIANS(p.longitude) - RADIANS(@lng)) +
             SIN(RADIANS(@lat)) * SIN(RADIANS(p.latitude)))), 2) AS distance_km
FROM providers p
JOIN provider_services ps ON ps.provider_id = p.id AND ps.service_id = @service_id
WHERE p.is_online = 1
  AND p.verification_status = 'verified'
  AND p.offers_onsite = 1
  -- quick bounding box (~1 degree = 111 km) so the index is used
  AND p.latitude  BETWEEN @lat - (@radius_km / 111) AND @lat + (@radius_km / 111)
  AND p.longitude BETWEEN @lng - (@radius_km / (111 * COS(RADIANS(@lat))))
                      AND @lng + (@radius_km / (111 * COS(RADIANS(@lat))))
HAVING distance_km <= @radius_km
ORDER BY distance_km
LIMIT 10;

-- ---------------------------------------------------------------------
-- 2. Offline / walk-in: nearest SHOPS (online or not). The web app caches
--    this list so a user with no signal can still walk to the nearest shop.
-- ---------------------------------------------------------------------
SELECT p.id, p.display_name, p.address, p.opening_hours, p.latitude, p.longitude,
       ROUND(6371 * ACOS(LEAST(1,
             COS(RADIANS(@lat)) * COS(RADIANS(p.latitude)) *
             COS(RADIANS(p.longitude) - RADIANS(@lng)) +
             SIN(RADIANS(@lat)) * SIN(RADIANS(p.latitude)))), 2) AS distance_km
FROM providers p
WHERE p.accepts_walk_in = 1
  AND p.verification_status IN ('verified','pending')
ORDER BY distance_km
LIMIT 20;

-- ---------------------------------------------------------------------
-- 3. Vulcator Dashboard: "Jobs today" and "Earned today" for provider 1
-- ---------------------------------------------------------------------
SET @provider_id = 1;

SELECT COUNT(*)                            AS jobs_today,
       COALESCE(SUM(provider_earning), 0)  AS earned_today
FROM jobs
WHERE provider_id = @provider_id
  AND status IN ('completed','paid')
  AND completed_at >= CURDATE();

-- 4. Vulcator Dashboard: "Recent jobs"
SELECT j.job_code, u.full_name AS customer, s.name AS service, j.vehicle_type,
       j.provider_earning, j.status, j.completed_at
FROM jobs j
JOIN users u    ON u.id = j.customer_id
JOIN services s ON s.id = j.service_id
WHERE j.provider_id = @provider_id
ORDER BY j.requested_at DESC
LIMIT 5;

-- 5. Vulcator "New job request nearby!" popup: pending offers
SELECT o.id AS offer_id, j.id AS job_id, j.job_code, u.full_name AS customer,
       s.name AS service, j.vehicle_type, j.address_text, j.total_amount, o.distance_km
FROM job_offers o
JOIN jobs j     ON j.id = o.job_id
JOIN users u    ON u.id = j.customer_id
JOIN services s ON s.id = j.service_id
WHERE o.provider_id = @provider_id
  AND o.status = 'pending'
  AND j.status = 'searching';

-- ---------------------------------------------------------------------
-- 6. Accept a job (run as ONE transaction). The "AND status='searching'"
--    check makes sure only the first provider who taps Accept wins.
-- ---------------------------------------------------------------------
-- Create a request in the app first (log in as Juan, tap Find a vulcator),
-- then put its id here. Sample jobs 1-3 are already finished.
SET @job_id = 4;

START TRANSACTION;
UPDATE jobs
   SET provider_id = @provider_id, status = 'accepted', accepted_at = NOW()
 WHERE id = @job_id AND status = 'searching';
-- in PHP: if affected rows = 0, someone else got it -> ROLLBACK
UPDATE job_offers SET status = 'accepted', responded_at = NOW()
 WHERE job_id = @job_id AND provider_id = @provider_id;
UPDATE job_offers SET status = 'expired'
 WHERE job_id = @job_id AND provider_id <> @provider_id AND status = 'pending';
INSERT INTO job_status_logs (job_id, status, note) VALUES (@job_id, 'accepted', 'Jose accepted your request');
COMMIT;

-- ---------------------------------------------------------------------
-- 7. User "Request status" screen (the page polls this every few seconds)
-- ---------------------------------------------------------------------
SELECT j.job_code, j.status, p.display_name AS vulcator, p.rating_avg, p.rating_count,
       p.latitude AS vulcator_lat, p.longitude AS vulcator_lng, pu.phone AS vulcator_phone
FROM jobs j
LEFT JOIN providers p ON p.id = j.provider_id
LEFT JOIN users pu    ON pu.id = p.user_id
WHERE j.id = @job_id;

SELECT status, note, created_at FROM job_status_logs
WHERE job_id = @job_id ORDER BY created_at, id;

-- ---------------------------------------------------------------------
-- 8. "Pay for service" summary + receipt (job 1)
-- ---------------------------------------------------------------------
SELECT j.job_code, s.name AS service, p.display_name AS mechanic,
       TIMESTAMPDIFF(MINUTE, j.arrived_at, j.completed_at) AS duration_min,
       j.service_fee, j.convenience_fee, j.total_amount,
       pay.method, pay.status AS payment_status, pay.paid_at
FROM jobs j
JOIN services s       ON s.id = j.service_id
JOIN providers p      ON p.id = j.provider_id
LEFT JOIN payments pay ON pay.job_id = j.id
WHERE j.id = 1;

-- 9. Customer history
SET @customer_id = 2;
SELECT j.job_code, s.name AS service, p.display_name AS vulcator,
       j.total_amount, j.status, j.requested_at
FROM jobs j
JOIN services s       ON s.id = j.service_id
LEFT JOIN providers p ON p.id = j.provider_id
WHERE j.customer_id = @customer_id
ORDER BY j.requested_at DESC;
