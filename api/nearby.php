<?php
// GET ?lat=&lng=  ->  verified vulcators and shops near a point, for the map.
require __DIR__ . '/../includes/api.php';
api_user();

$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
if (!valid_lat_lng($lat === false ? null : $lat, $lng === false ? null : $lng)) json_error('Location needed.');

$dist = distance_sql('p.latitude', 'p.longitude');
$stmt = db()->prepare("SELECT p.id, p.display_name, p.provider_type, p.address, p.latitude, p.longitude,
                              p.is_online, p.offers_onsite, p.accepts_walk_in, p.opening_hours,
                              p.rating_avg, p.rating_count, $dist AS distance_km
                       FROM providers p
                       WHERE p.verification_status = 'verified'
                         AND (p.is_online = 1 OR p.accepts_walk_in = 1)
                       HAVING distance_km <= 15
                       ORDER BY distance_km
                       LIMIT 40");
$stmt->execute([$lat, $lng, $lat]);

$radius = (float) setting('default_search_radius_km', '5');
$items = array_map(fn ($p) => [
    'id'        => (int) $p['id'],
    'name'      => $p['display_name'],
    'type'      => $p['provider_type'],
    'address'   => $p['address'],
    'hours'     => $p['opening_hours'],
    'lat'       => (float) $p['latitude'],
    'lng'       => (float) $p['longitude'],
    'online'    => (bool) $p['is_online'],
    'onsite'    => (bool) $p['offers_onsite'],
    'walk_in'   => (bool) $p['accepts_walk_in'],
    'rating'    => (float) $p['rating_avg'],
    'rating_count' => (int) $p['rating_count'],
    'distance_km'  => round($p['distance_km'], 2),
    'can_come'  => $p['is_online'] && $p['offers_onsite'] && $p['distance_km'] <= $radius,
], $stmt->fetchAll());

json_out(['providers' => $items, 'radius_km' => $radius]);
