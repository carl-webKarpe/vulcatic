<?php
// POST {lat, lng}  ->  vulcator's live position (sent while online or on a job).
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('provider');

$lat = input_float('lat');
$lng = input_float('lng');
if (!valid_lat_lng($lat, $lng)) json_error('Bad location.');

db()->prepare('UPDATE providers SET latitude = ?, longitude = ?, last_location_at = NOW() WHERE user_id = ?')
    ->execute([$lat, $lng, $user['id']]);
json_out(['ok' => true]);
