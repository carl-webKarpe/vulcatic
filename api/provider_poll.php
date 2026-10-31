<?php
// GET  ->  what a shop owner / skilled local's screen needs: new requests and current work.
require __DIR__ . '/../includes/api.php';
$user = api_user('provider');
$provider = provider_for_user($user['id']);
$isShop = $provider['provider_type'] === 'shop';

// Roadside requests need the switch on; shop-visit bookings arrive whenever the shop takes walk-ins.
$stmt = db()->prepare("SELECT o.id AS offer_id, o.offered_at, j.id AS job_id, j.job_code, j.mode, j.vehicle_type, j.problem, j.address_text,
                              j.directions, j.notes, j.customer_lat, j.customer_lng, j.municipality, u.full_name AS customer, u.motorist_type,
                              COALESCE(ps.price, s.base_price) AS price, o.distance_km,
                              TIMESTAMPDIFF(SECOND, o.offered_at, NOW()) AS age_s
                       FROM job_offers o
                       JOIN jobs j ON j.id = o.job_id
                       JOIN services s ON s.id = j.service_id
                       JOIN users u ON u.id = j.customer_id
                       LEFT JOIN provider_services ps ON ps.provider_id = o.provider_id AND ps.service_id = j.service_id
                       WHERE o.provider_id = ? AND o.status = 'pending' AND j.status IN ('searching','matched')
                       ORDER BY o.offered_at");
$stmt->execute([$provider['id']]);
$offers = [];
foreach ($stmt->fetchAll() as $o) {
    $left = offer_timeout($o['mode']) - (int) $o['age_s'];
    if ($left <= 0) continue;
    if ($o['mode'] === 'onsite') {
        if (!$provider['is_online']) continue;
        // Distance from where the vulcator is now, not when the offer was sent.
        $km = distance_km((float) $provider['latitude'], (float) $provider['longitude'], (float) $o['customer_lat'], (float) $o['customer_lng']);
        if ($km > search_radius_for($o['municipality']) + 3) continue;
        $o['distance_km'] = round($km, 2);
    }
    $offers[] = [
        'offer_id' => (int) $o['offer_id'], 'key' => $o['offer_id'] . '@' . $o['offered_at'], 'job_id' => (int) $o['job_id'],
        'code' => $o['job_code'], 'mode' => $o['mode'], 'customer' => $o['customer'], 'initials' => initials($o['customer']),
        'customer_type' => MOTORIST_TYPES[$o['motorist_type'] ?? ''] ?? null,
        'service' => PROBLEMS[$o['problem'] ?? 'other'][0], 'vehicle' => ucfirst($o['vehicle_type']),
        'address' => $o['address_text'], 'directions' => $o['directions'], 'notes' => $o['notes'],
        'municipality' => $o['municipality'], 'price' => (float) $o['price'], 'distance_km' => (float) $o['distance_km'],
        'seconds_left' => $left, 'lat' => (float) $o['customer_lat'], 'lng' => (float) $o['customer_lng'],
    ];
}

$stmt = db()->prepare("SELECT COUNT(*) AS active, SUM(assigned_user_id IS NULL AND status = 'accepted') AS unassigned,
                              SUM(assignment_status = 'pending') AS waiting_employee
                       FROM jobs WHERE provider_id = ? AND status IN ('accepted','on_the_way','arrived','in_progress','completed')");
$stmt->execute([$provider['id']]);
$work = $stmt->fetch();

json_out([
    'online'        => (bool) $provider['is_online'],
    'verified'      => $provider['verification_status'] === 'verified',
    'is_shop'       => $isShop,
    // Skilled locals jump straight into their one job; shops manage several on the Bookings page.
    'active_job_id' => $isShop ? null : active_job_id_for_provider($provider['id']),
    'active_jobs'   => (int) $work['active'],
    'unassigned'    => (int) $work['unassigned'],
    'offers'        => $offers,
]);
