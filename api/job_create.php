<?php
// POST {lat, lng, vehicle_type, problem, details, address, directions, mode: onsite|walk_in, shop_id}
// Creates a roadside request (sent to the nearest free vulcators) or a shop visit (sent to one shop).
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('customer');

if ($id = active_job_id_for_customer($user['id'])) {
    json_out(['job_id' => $id, 'existing' => true]);
}

$in = input();
$lat = input_float('lat');
$lng = input_float('lng');
if (!valid_lat_lng($lat, $lng)) json_error('We need your location. Allow location access or tap the map.');
$municipality = municipality_for($lat, $lng);
if (!$municipality) json_error(OUTSIDE_AREA_MESSAGE);

$vehicles = ['motorcycle', 'car', 'tricycle', 'bicycle', 'van', 'truck', 'other'];
$vehicle  = in_array($in['vehicle_type'] ?? '', $vehicles, true) ? $in['vehicle_type'] : null;
if (!$vehicle) json_error('Choose your vehicle.');
$problem = array_key_exists($in['problem'] ?? '', PROBLEMS) ? $in['problem'] : null;
if (!$problem) json_error('Tell us what happened to the tire.');
$mode = ($in['mode'] ?? '') === 'walk_in' ? 'walk_in' : 'onsite';

$serviceId = PROBLEMS[$problem][1];
$stmt = db()->prepare('SELECT id, base_price FROM services WHERE id = ? AND is_active = 1');
$stmt->execute([$serviceId]);
$service = $stmt->fetch();
if (!$service) json_error('This service is not available right now.');

$clean = fn ($k, $max) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max) ?: null;
$address    = $clean('address', 255);
$directions = $clean('directions', 255);
$details    = $clean('details', 500);

$price = (float) $service['base_price'];
$shop = null;
if ($mode === 'walk_in') {
    $stmt = db()->prepare("SELECT p.*, ps.price FROM providers p JOIN users u ON u.id = p.user_id
                           LEFT JOIN provider_services ps ON ps.provider_id = p.id AND ps.service_id = ?
                           WHERE p.id = ? AND p.verification_status = 'verified' AND p.accepts_walk_in = 1 AND u.is_active = 1");
    $stmt->execute([$serviceId, (int) ($in['shop_id'] ?? 0)]);
    $shop = $stmt->fetch();
    if (!$shop) json_error('Choose a shop to visit.');
    if ($shop['price'] !== null) $price = (float) $shop['price'];
}
$fees = job_fees($price); // for roadside: an estimate until a vulcator accepts

$pdo = db();
$pdo->beginTransaction();
$pdo->prepare('INSERT INTO jobs (job_code, customer_id, service_id, vehicle_type, problem, mode, customer_lat, customer_lng,
                                 address_text, municipality, directions, notes, status, service_fee, convenience_fee,
                                 total_amount, platform_fee, provider_earning)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'searching\', ?, ?, ?, ?, ?)')
    ->execute([new_job_code(), $user['id'], $serviceId, $vehicle, $problem, $mode, $lat, $lng, $address, $municipality,
               $directions, $details, $fees['service_fee'], $fees['convenience_fee'], $fees['total_amount'],
               $fees['platform_fee'], $fees['provider_earning']]);
$jobId = (int) $pdo->lastInsertId();
log_status($jobId, 'searching', 'Request sent');

if ($mode === 'walk_in') {
    $km = distance_km($lat, $lng, (float) $shop['latitude'], (float) $shop['longitude']);
    $pdo->prepare('INSERT INTO job_offers (job_id, provider_id, distance_km) VALUES (?, ?, ?)')->execute([$jobId, $shop['id'], round($km, 2)]);
    $pdo->prepare("UPDATE jobs SET status = 'matched' WHERE id = ?")->execute([$jobId]);
    log_status($jobId, 'matched', 'Waiting for ' . $shop['display_name'] . ' to confirm your visit');
    $sent = 1;
} else {
    $sent = offer_job(load_job($jobId));
}
$pdo->commit();

json_out(['job_id' => $jobId, 'notified' => $sent]);
