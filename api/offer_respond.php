<?php
// POST {offer_id, action: accept|decline}  ->  shop owner / skilled local answers a request.
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('provider');
$provider = provider_for_user($user['id']);

$stmt = db()->prepare("SELECT o.*, j.mode FROM job_offers o JOIN jobs j ON j.id = o.job_id
                       WHERE o.id = ? AND o.provider_id = ? AND o.status = 'pending'");
$stmt->execute([(int) (input()['offer_id'] ?? 0), $provider['id']]);
$offer = $stmt->fetch();
if (!$offer || strtotime($offer['offered_at']) < time() - offer_timeout($offer['mode'])) {
    json_error('This request is no longer available.', 410);
}

if ((input()['action'] ?? '') !== 'accept') {
    db()->prepare("UPDATE job_offers SET status = 'declined', responded_at = NOW() WHERE id = ?")->execute([$offer['id']]);
    json_out(['ok' => true]);
}

$isShop = $provider['provider_type'] === 'shop';
// A skilled local works alone: one job at a time. Shops can run several with their team.
if (!$isShop && active_job_id_for_provider($provider['id'])) json_error('Finish your current job first.');

$job = load_job((int) $offer['job_id']);
if ($job['mode'] === 'onsite') {
    $km = distance_km((float) $provider['latitude'], (float) $provider['longitude'], (float) $job['customer_lat'], (float) $job['customer_lng']);
    if ($km > search_radius_for($job['municipality']) + 3) {
        db()->prepare("UPDATE job_offers SET status = 'expired' WHERE id = ?")->execute([$offer['id']]);
        json_error('You are too far from this customer now (' . round($km, 1) . ' km).', 409);
    }
}
$stmt = db()->prepare('SELECT price FROM provider_services WHERE provider_id = ? AND service_id = ?');
$stmt->execute([$provider['id'], $job['service_id']]);
$price = $stmt->fetchColumn();
$fees  = job_fees($price !== false ? (float) $price : (float) $job['service_fee']);

$pdo = db();
$pdo->beginTransaction();
// Only the first to accept gets the job.
$stmt = $pdo->prepare("UPDATE jobs SET provider_id = ?, status = 'accepted', accepted_at = NOW(),
                              service_fee = ?, convenience_fee = ?, total_amount = ?, platform_fee = ?, provider_earning = ?
                       WHERE id = ? AND status IN ('searching','matched')");
$stmt->execute([$provider['id'], $fees['service_fee'], $fees['convenience_fee'], $fees['total_amount'],
                $fees['platform_fee'], $fees['provider_earning'], $job['id']]);
if ($stmt->rowCount() === 0) {
    $pdo->rollBack();
    db()->prepare("UPDATE job_offers SET status = 'expired' WHERE id = ?")->execute([$offer['id']]);
    json_error('Another vulcator already took this job.', 409);
}
$pdo->prepare("UPDATE job_offers SET status = 'accepted', responded_at = NOW() WHERE id = ?")->execute([$offer['id']]);
$pdo->prepare("UPDATE job_offers SET status = 'expired' WHERE job_id = ? AND id <> ? AND status = 'pending'")
    ->execute([$job['id'], $offer['id']]);
log_status($job['id'], 'accepted', $job['mode'] === 'walk_in'
    ? $provider['display_name'] . ' confirmed your visit. Head to the shop.'
    : $provider['display_name'] . ' accepted your request');
$pdo->commit();

json_out(['ok' => true, 'job_id' => (int) $job['id'], 'is_shop' => $isShop]);
