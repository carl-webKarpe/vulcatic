<?php
// Job matching and status rules, shared by the pages and the API.
//
// Status flow:
//   searching   no vulcator has the request right now
//   matched     sent to nearby vulcators, waiting for one to accept
//   accepted    a vulcator took the job
//   on_the_way  vulcator is driving to the customer
//   arrived     vulcator is with the customer
//   completed   tire fixed, waiting for payment
//   paid        payment received (job done)
//   cancelled   customer cancelled

const JOB_FLOW    = ['searching', 'matched', 'accepted', 'on_the_way', 'arrived', 'completed', 'paid'];
const JOB_ACTIVE  = ['searching', 'matched', 'accepted', 'on_the_way', 'arrived', 'completed'];
const JOB_ASSIGNED = ['accepted', 'on_the_way', 'arrived', 'completed'];

function setting(string $key, string $default = ''): string
{
    static $all = null;
    if ($all === null) {
        $all = db()->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $all[$key] ?? $default;
}

// Distance in km from (?, ?) to the given lat/lng columns. Bind: [lat, lng, lat].
function distance_sql(string $latCol, string $lngCol): string
{
    return "(6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS($latCol)) * COS(RADIANS($lngCol) - RADIANS(?))"
         . " + SIN(RADIANS(?)) * SIN(RADIANS($latCol)))))";
}

function distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = M_PI / 180;
    $a = sin(($lat2 - $lat1) * $r / 2) ** 2 + cos($lat1 * $r) * cos($lat2 * $r) * sin(($lng2 - $lng1) * $r / 2) ** 2;
    return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// Rough city driving ETA: 25 km/h average, at least 1 minute.
function eta_minutes(float $km): int
{
    return max(1, (int) ceil($km / 25 * 60));
}

function log_status(int $jobId, string $status, string $note): void
{
    db()->prepare('INSERT INTO job_status_logs (job_id, status, note) VALUES (?, ?, ?)')->execute([$jobId, $status, $note]);
}

function new_job_code(): string
{
    return 'VLC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

// Online, verified vulcators near the job who offer the service and are free.
function find_candidates(array $job, int $limit = 5): array
{
    $dist = distance_sql('p.latitude', 'p.longitude');
    $sql = "SELECT p.id, $dist AS distance_km
            FROM providers p
            JOIN provider_services ps ON ps.provider_id = p.id AND ps.service_id = ?
            WHERE p.is_online = 1 AND p.verification_status = 'verified' AND p.offers_onsite = 1
              AND p.user_id <> ?
              AND NOT EXISTS (SELECT 1 FROM job_offers o WHERE o.job_id = ? AND o.provider_id = p.id
                              AND o.status IN ('pending','declined'))
              AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.provider_id = p.id
                              AND j.status IN ('accepted','on_the_way','arrived','completed'))
            HAVING distance_km <= ?
            ORDER BY distance_km
            LIMIT $limit";
    $stmt = db()->prepare($sql);
    $stmt->execute([
        $job['customer_lat'], $job['customer_lng'], $job['customer_lat'],
        $job['service_id'], $job['customer_id'], $job['id'],
        (float) setting('default_search_radius_km', '5'),
    ]);
    return $stmt->fetchAll();
}

// Send the job to the nearest free vulcators. Returns how many got it.
// Vulcators who let an earlier offer time out get it again; those who declined don't.
function offer_job(array $job): int
{
    $candidates = find_candidates($job);
    $stmt = db()->prepare("INSERT INTO job_offers (job_id, provider_id, distance_km) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE status = 'pending', offered_at = NOW(), responded_at = NULL");
    foreach ($candidates as $c) {
        $stmt->execute([$job['id'], $c['id'], round($c['distance_km'], 2)]);
    }

    $newStatus = $candidates ? 'matched' : 'searching';
    if ($newStatus !== $job['status']) {
        db()->prepare("UPDATE jobs SET status = ? WHERE id = ? AND status IN ('searching','matched')")
            ->execute([$newStatus, $job['id']]);
        log_status($job['id'], $newStatus, $newStatus === 'matched'
            ? count($candidates) . ' vulcator(s) nearby notified'
            : 'No free vulcator nearby yet, still looking');
    }
    return count($candidates);
}

// Called while the customer waits (no cron on shared hosting):
// expire unanswered offers and try the next vulcators.
function maintain_job(array $job): void
{
    if (!in_array($job['status'], ['searching', 'matched'], true)) return;

    db()->prepare("UPDATE job_offers SET status = 'expired'
                   WHERE job_id = ? AND status = 'pending' AND offered_at < NOW() - INTERVAL ? SECOND")
        ->execute([$job['id'], (int) setting('offer_timeout_seconds', '60')]);

    $stmt = db()->prepare("SELECT COUNT(*) FROM job_offers WHERE job_id = ? AND status = 'pending'");
    $stmt->execute([$job['id']]);
    if ((int) $stmt->fetchColumn() === 0) {
        offer_job($job);
    }
}

function load_job(int $id): ?array
{
    $stmt = db()->prepare('SELECT j.*, s.name AS service_name
                           FROM jobs j JOIN services s ON s.id = j.service_id WHERE j.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function provider_for_user(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM providers WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function active_job_id_for_customer(int $userId): ?int
{
    $stmt = db()->prepare("SELECT id FROM jobs WHERE customer_id = ?
                           AND status IN ('searching','matched','accepted','on_the_way','arrived','completed')
                           ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    return ($id = $stmt->fetchColumn()) ? (int) $id : null;
}

function active_job_id_for_provider(int $providerId): ?int
{
    $stmt = db()->prepare("SELECT id FROM jobs WHERE provider_id = ?
                           AND status IN ('accepted','on_the_way','arrived','completed')
                           ORDER BY id DESC LIMIT 1");
    $stmt->execute([$providerId]);
    return ($id = $stmt->fetchColumn()) ? (int) $id : null;
}

// Everything both tracking screens need, in one JSON-friendly array.
function job_payload(array $job): array
{
    $provider = null;
    if ($job['provider_id']) {
        $stmt = db()->prepare('SELECT p.id, p.display_name, p.provider_type, p.latitude, p.longitude, p.rating_avg,
                                      p.rating_count, p.gcash_number, u.phone
                               FROM providers p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
        $stmt->execute([$job['provider_id']]);
        if ($p = $stmt->fetch()) {
            $km = distance_km((float) $p['latitude'], (float) $p['longitude'], (float) $job['customer_lat'], (float) $job['customer_lng']);
            $provider = [
                'name'         => $p['display_name'],
                'initials'     => initials($p['display_name']),
                'type'         => $p['provider_type'],
                'lat'          => (float) $p['latitude'],
                'lng'          => (float) $p['longitude'],
                'rating'       => (float) $p['rating_avg'],
                'rating_count' => (int) $p['rating_count'],
                'phone'        => $p['phone'],
                'gcash'        => $p['gcash_number'],
                'distance_km'  => round($km, 2),
                'eta_min'      => eta_minutes($km),
            ];
        }
    }

    $stmt = db()->prepare('SELECT full_name, phone FROM users WHERE id = ?');
    $stmt->execute([$job['customer_id']]);
    $customer = $stmt->fetch();

    $stmt = db()->prepare('SELECT method, amount, status, reference_no, paid_at FROM payments WHERE job_id = ?');
    $stmt->execute([$job['id']]);
    $payment = $stmt->fetch() ?: null;

    $stmt = db()->prepare('SELECT stars FROM ratings WHERE job_id = ?');
    $stmt->execute([$job['id']]);
    $stars = $stmt->fetchColumn();

    $stmt = db()->prepare("SELECT COUNT(*) FROM job_offers WHERE job_id = ? AND status = 'pending'");
    $stmt->execute([$job['id']]);

    return [
        'id'          => (int) $job['id'],
        'code'        => $job['job_code'],
        'status'      => $job['status'],
        'step'        => array_search($job['status'], JOB_FLOW, true),
        'service'     => $job['service_name'],
        'vehicle'     => ucfirst($job['vehicle_type']),
        'address'     => $job['address_text'],
        'notes'       => $job['notes'],
        'lat'         => (float) $job['customer_lat'],
        'lng'         => (float) $job['customer_lng'],
        'service_fee' => (float) $job['service_fee'],
        'convenience_fee' => (float) $job['convenience_fee'],
        'total'       => (float) $job['total_amount'],
        'platform_fee' => (float) $job['platform_fee'],
        'earning'     => (float) $job['provider_earning'],
        'duration_min' => $job['arrived_at'] && $job['completed_at']
            ? max(1, (int) round((strtotime($job['completed_at']) - strtotime($job['arrived_at'])) / 60)) : null,
        'pending_offers' => (int) $stmt->fetchColumn(),
        'provider'    => $provider,
        'customer'    => ['name' => $customer['full_name'], 'first' => first_name($customer['full_name']),
                          'initials' => initials($customer['full_name']), 'phone' => $customer['phone']],
        'payment'     => $payment ? ['method' => $payment['method'], 'amount' => (float) $payment['amount'],
                                     'status' => $payment['status'], 'reference' => $payment['reference_no']] : null,
        'rated'       => $stars !== false,
    ];
}

// Fees for a job once we know the vulcator's price.
function job_fees(float $serviceFee): array
{
    $convenience = (float) setting('convenience_fee', '0');
    $platform    = round($serviceFee * (float) setting('platform_fee_rate', '0.05'), 2);
    return [
        'service_fee'      => $serviceFee,
        'convenience_fee'  => $convenience,
        'total_amount'     => $serviceFee + $convenience,
        'platform_fee'     => $platform,
        'provider_earning' => $serviceFee - $platform,
    ];
}

// Recalculate a vulcator's star rating after a new rating.
function refresh_provider_rating(int $providerId): void
{
    db()->prepare('UPDATE providers p
                   JOIN (SELECT provider_id, AVG(stars) a, COUNT(*) c FROM ratings WHERE provider_id = ? GROUP BY provider_id) r
                     ON r.provider_id = p.id
                   SET p.rating_avg = r.a, p.rating_count = r.c')->execute([$providerId]);
}
