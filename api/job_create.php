<?php
// POST {lat, lng, service_id, vehicle_type, address, notes}  ->  creates a request and alerts vulcators.
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('customer');

if ($id = active_job_id_for_customer($user['id'])) {
    json_out(['job_id' => $id, 'existing' => true]);
}

$lat = input_float('lat');
$lng = input_float('lng');
if (!valid_lat_lng($lat, $lng)) json_error('We need your location. Allow location access or tap the map.');

$vehicles = ['motorcycle', 'car', 'tricycle', 'bicycle', 'van', 'truck', 'other'];
$vehicle  = in_array(input()['vehicle_type'] ?? '', $vehicles, true) ? input()['vehicle_type'] : null;
if (!$vehicle) json_error('Choose your vehicle.');

$stmt = db()->prepare('SELECT id, base_price FROM services WHERE id = ? AND is_active = 1');
$stmt->execute([(int) (input()['service_id'] ?? 0)]);
$service = $stmt->fetch();
if (!$service) json_error('Choose a service.');

$address = mb_substr(trim((string) (input()['address'] ?? '')), 0, 255) ?: null;
$notes   = mb_substr(trim((string) (input()['notes'] ?? '')), 0, 500) ?: null;
$fees    = job_fees((float) $service['base_price']); // estimate; set to the vulcator's price on accept

$pdo = db();
$pdo->beginTransaction();
$pdo->prepare('INSERT INTO jobs (job_code, customer_id, service_id, vehicle_type, mode, customer_lat, customer_lng,
                                 address_text, notes, status, service_fee, convenience_fee, total_amount,
                                 platform_fee, provider_earning)
               VALUES (?, ?, ?, ?, \'onsite\', ?, ?, ?, ?, \'searching\', ?, ?, ?, ?, ?)')
    ->execute([new_job_code(), $user['id'], $service['id'], $vehicle, $lat, $lng, $address, $notes,
               $fees['service_fee'], $fees['convenience_fee'], $fees['total_amount'], $fees['platform_fee'], $fees['provider_earning']]);
$jobId = (int) $pdo->lastInsertId();
log_status($jobId, 'searching', 'Location detected');
$sent = offer_job(load_job($jobId));
$pdo->commit();

json_out(['job_id' => $jobId, 'notified' => $sent]);
