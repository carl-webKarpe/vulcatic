<?php
// GET  ->  what a vulcator's screen needs: new job offers and their current job.
require __DIR__ . '/../includes/api.php';
$user = api_user('provider');
$provider = provider_for_user($user['id']);

$offers = [];
if ($provider['is_online']) {
    // Hide offers that timed out or whose job was taken / cancelled.
    $stmt = db()->prepare("SELECT o.id AS offer_id, o.offered_at, j.id AS job_id, j.job_code, j.vehicle_type, j.address_text, j.notes,
                                  j.customer_lat, j.customer_lng, s.name AS service, u.full_name AS customer,
                                  COALESCE(ps.price, s.base_price) AS price, o.distance_km,
                                  GREATEST(0, ? - TIMESTAMPDIFF(SECOND, o.offered_at, NOW())) AS seconds_left
                           FROM job_offers o
                           JOIN jobs j ON j.id = o.job_id
                           JOIN services s ON s.id = j.service_id
                           JOIN users u ON u.id = j.customer_id
                           LEFT JOIN provider_services ps ON ps.provider_id = o.provider_id AND ps.service_id = j.service_id
                           WHERE o.provider_id = ? AND o.status = 'pending' AND j.status IN ('searching','matched')
                             AND o.offered_at >= NOW() - INTERVAL ? SECOND
                           ORDER BY o.offered_at");
    $timeout = (int) setting('offer_timeout_seconds', '60');
    $stmt->execute([$timeout, $provider['id'], $timeout]);
    foreach ($stmt->fetchAll() as $o) {
        $offers[] = [
            'offer_id' => (int) $o['offer_id'], 'key' => $o['offer_id'] . '@' . $o['offered_at'], 'job_id' => (int) $o['job_id'], 'code' => $o['job_code'],
            'customer' => $o['customer'], 'initials' => initials($o['customer']), 'service' => $o['service'],
            'vehicle' => ucfirst($o['vehicle_type']), 'address' => $o['address_text'], 'notes' => $o['notes'],
            'price' => (float) $o['price'], 'distance_km' => (float) $o['distance_km'],
            'eta_min' => eta_minutes((float) $o['distance_km']), 'seconds_left' => (int) $o['seconds_left'],
            'lat' => (float) $o['customer_lat'], 'lng' => (float) $o['customer_lng'],
        ];
    }
}

json_out([
    'online'        => (bool) $provider['is_online'],
    'verified'      => $provider['verification_status'] === 'verified',
    'active_job_id' => active_job_id_for_provider($provider['id']),
    'offers'        => $offers,
]);
