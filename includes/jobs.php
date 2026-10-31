<?php
// Job matching and status rules, shared by the pages and the API.

require_once __DIR__ . '/siargao.php';
//
// Status flow (what motorists see in brackets):
//   searching    no vulcator has the request right now      [Pending]
//   matched      sent to vulcator(s), waiting for an answer  [Pending]
//   accepted     a shop / vulcator took the job              [Accepted]
//   on_the_way   driving to the motorist (roadside only)     [Provider En Route]
//   arrived      with the motorist / motorist at the shop    [Arrived]
//   in_progress  repair started                               [Service In Progress]
//   completed    repair done, waiting for payment            [Completed]
//   paid         payment received                             [Completed]
//   cancelled                                                 [Cancelled]
//
// Two kinds of request (jobs.mode):
//   onsite   roadside repair: sent to the nearest free vulcators, first to accept wins
//   walk_in  shop visit: sent only to the shop the motorist picked
//
// Shops can have employees: the owner accepts a job, assigns it to an employee
// (jobs.assigned_user_id), the employee accepts and updates the status.

const JOB_FLOW    = ['searching', 'matched', 'accepted', 'on_the_way', 'arrived', 'in_progress', 'completed', 'paid'];
const JOB_ACTIVE  = ['searching', 'matched', 'accepted', 'on_the_way', 'arrived', 'in_progress', 'completed'];
const JOB_ASSIGNED = ['accepted', 'on_the_way', 'arrived', 'in_progress', 'completed'];

const STATUS_LABELS = [
    'searching' => 'Pending', 'matched' => 'Pending', 'accepted' => 'Accepted', 'on_the_way' => 'Provider En Route',
    'arrived' => 'Arrived', 'in_progress' => 'Service In Progress', 'completed' => 'Completed', 'paid' => 'Completed',
    'cancelled' => 'Cancelled',
];

// Step 3 of the request: the problem, and the service it's priced as.
const PROBLEMS = [
    'flat_tire' => ['Flat tire', 1],
    'puncture'  => ['Tire puncture', 2],
    'other'     => ['Other tire-related issue', 1],
];

const MOTORIST_TYPES = [
    'local' => 'Local resident', 'tourist' => 'Tourist', 'rider' => 'Motorcycle rider',
    'rental' => 'Motorcycle rental customer', 'private_owner' => 'Private vehicle owner',
];

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
              AND (p.provider_type = 'shop' OR NOT EXISTS (SELECT 1 FROM jobs j WHERE j.provider_id = p.id
                              AND j.status IN ('accepted','on_the_way','arrived','in_progress','completed')))
            HAVING distance_km <= ?
            ORDER BY distance_km
            LIMIT $limit";
    $stmt = db()->prepare($sql);
    $stmt->execute([
        $job['customer_lat'], $job['customer_lng'], $job['customer_lat'],
        $job['service_id'], $job['customer_id'], $job['id'],
        search_radius_for($job['municipality']),
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
        ->execute([$job['id'], offer_timeout($job['mode'])]);

    $stmt = db()->prepare("SELECT COUNT(*) FROM job_offers WHERE job_id = ? AND status = 'pending'");
    $stmt->execute([$job['id']]);
    if ((int) $stmt->fetchColumn() > 0) return;

    if ($job['mode'] === 'walk_in') {
        // A shop visit goes to one shop only. If it said no or didn't answer, tell the motorist.
        $stmt = db()->prepare("SELECT status FROM job_offers WHERE job_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$job['id']]);
        $reason = $stmt->fetchColumn() === 'declined' ? 'The shop can\'t take this booking right now' : 'The shop didn\'t answer in time';
        db()->prepare("UPDATE jobs SET status = 'cancelled', cancel_reason = ? WHERE id = ? AND status IN ('searching','matched')")
            ->execute([$reason, $job['id']]);
        log_status($job['id'], 'cancelled', $reason);
        return;
    }
    offer_job($job);
}

// Seconds a shop / vulcator has to answer: longer for shop visits (they may be busy at the shop).
function offer_timeout(string $mode): int
{
    return $mode === 'walk_in'
        ? (int) setting('walkin_offer_timeout_seconds', '300')
        : (int) setting('offer_timeout_seconds', '60');
}

// Who is looking at a job: 'customer', 'owner' (shop owner / skilled local), 'employee', or null.
function job_role(array $job, array $user): ?string
{
    if ((int) $job['customer_id'] === (int) $user['id']) return 'customer';
    if ($user['role'] === 'employee' && (int) $job['assigned_user_id'] === (int) $user['id']) return 'employee';
    if ($user['role'] === 'provider' && $job['provider_id']) {
        $p = provider_for_user($user['id']);
        if ($p && (int) $p['id'] === (int) $job['provider_id']) return 'owner';
    }
    return null;
}

// Can this person move the job forward? Owner always; an employee once they accepted the assignment.
function can_work_job(array $job, ?string $role): bool
{
    if ($role === 'owner') return true;
    return $role === 'employee' && $job['assignment_status'] === 'accepted';
}

// Minutes left on the ETA the provider gave (null if they didn't give one).
function eta_left(array $job): ?int
{
    if ($job['eta_minutes'] === null || !$job['eta_set_at'] || $job['status'] !== 'on_the_way') return null;
    $passed = (int) floor((time() - strtotime($job['eta_set_at'])) / 60);
    return max(1, (int) $job['eta_minutes'] - $passed);
}

function job_messages(int $jobId, int $viewerId): array
{
    $stmt = db()->prepare('SELECT m.id, m.body, m.created_at, m.sender_id, u.full_name, u.role FROM job_messages m
                           JOIN users u ON u.id = m.sender_id WHERE m.job_id = ? ORDER BY m.id DESC LIMIT 60');
    $stmt->execute([$jobId]);
    return array_reverse(array_map(fn ($m) => [
        'id' => (int) $m['id'], 'body' => $m['body'], 'mine' => (int) $m['sender_id'] === $viewerId,
        'name' => first_name($m['full_name']), 'from_customer' => $m['role'] === 'customer',
        'time' => date('g:i A', strtotime($m['created_at'])),
    ], $stmt->fetchAll()));
}

// Active employees of a shop.
function shop_employees(int $providerId, bool $onDutyOnly = false): array
{
    $stmt = db()->prepare("SELECT u.id, u.full_name, u.phone, u.on_duty, u.is_active,
                                  (SELECT COUNT(*) FROM jobs j WHERE j.assigned_user_id = u.id
                                   AND j.status IN ('accepted','on_the_way','arrived','in_progress','completed')) AS active_jobs
                           FROM users u WHERE u.role = 'employee' AND u.employer_provider_id = ? AND u.is_active = 1"
                          . ($onDutyOnly ? ' AND u.on_duty = 1' : '') . ' ORDER BY u.on_duty DESC, u.full_name');
    $stmt->execute([$providerId]);
    return $stmt->fetchAll();
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
                           AND status IN ('searching','matched','accepted','on_the_way','arrived','in_progress','completed')
                           ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    return ($id = $stmt->fetchColumn()) ? (int) $id : null;
}

function active_job_id_for_provider(int $providerId): ?int
{
    $stmt = db()->prepare("SELECT id FROM jobs WHERE provider_id = ?
                           AND status IN ('accepted','on_the_way','arrived','in_progress','completed')
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
                                      p.rating_count, p.gcash_number, p.address, p.opening_hours, u.phone
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
                'address'      => $p['address'],
                'hours'        => $p['opening_hours'],
            ];
        }
    }

    $stmt = db()->prepare('SELECT full_name, phone, motorist_type FROM users WHERE id = ?');
    $stmt->execute([$job['customer_id']]);
    $customer = $stmt->fetch();

    $assignee = null;
    if ($job['assigned_user_id']) {
        $stmt = db()->prepare('SELECT id, full_name, phone FROM users WHERE id = ?');
        $stmt->execute([$job['assigned_user_id']]);
        if ($a = $stmt->fetch()) {
            $assignee = ['id' => (int) $a['id'], 'name' => $a['full_name'], 'first' => first_name($a['full_name']),
                         'phone' => $a['phone'], 'status' => $job['assignment_status']];
        }
    }

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
        'status_label' => STATUS_LABELS[$job['status']] . ($job['status'] === 'completed' ? ' · awaiting payment' : ''),
        'mode'        => $job['mode'],
        'problem'     => $job['problem'],
        'problem_label' => PROBLEMS[$job['problem'] ?? 'other'][0],
        'directions'  => $job['directions'],
        'eta_min'     => eta_left($job),
        'assignee'    => $assignee,
        // Who the motorist should call: the employee doing the job, else the shop / vulcator.
        'contact_phone' => $assignee && $job['assignment_status'] === 'accepted' ? $assignee['phone'] : null,
        'step'        => array_search($job['status'], JOB_FLOW, true),
        'service'     => $job['service_name'],
        'vehicle'     => ucfirst($job['vehicle_type']),
        'address'     => $job['address_text'],
        'municipality' => $job['municipality'],
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
                          'initials' => initials($customer['full_name']), 'phone' => $customer['phone'],
                          'type' => MOTORIST_TYPES[$customer['motorist_type'] ?? ''] ?? null],
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
                   JOIN (SELECT provider_id, ROUND(AVG(stars), 2) a, COUNT(*) c FROM ratings WHERE provider_id = ? GROUP BY provider_id) r
                     ON r.provider_id = p.id
                   SET p.rating_avg = r.a, p.rating_count = r.c')->execute([$providerId]);
}
