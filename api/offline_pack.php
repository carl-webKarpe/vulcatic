<?php
// GET  ->  every approved shop and on-site vulcator on Siargao, small enough to keep
// on the phone. The offline shop finder uses it when there's no signal.
require __DIR__ . '/../includes/api.php';
api_user();

$rows = db()->query("SELECT p.id, p.display_name, p.provider_type, p.address, p.municipality, p.latitude, p.longitude,
                            p.offers_onsite, p.accepts_walk_in, p.opening_hours, p.rating_avg, p.rating_count, u.phone
                     FROM providers p JOIN users u ON u.id = p.user_id
                     WHERE p.verification_status = 'verified' AND u.is_active = 1
                     ORDER BY p.municipality, p.display_name")->fetchAll();

json_out([
    'saved_at'  => date('c'),
    'area'      => 'Siargao Island',
    'providers' => array_map(fn ($p) => [
        'id'      => (int) $p['id'],
        'name'    => $p['display_name'],
        'type'    => $p['provider_type'],
        'address' => $p['address'],
        'town'    => $p['municipality'],
        'lat'     => (float) $p['latitude'],
        'lng'     => (float) $p['longitude'],
        'onsite'  => (bool) $p['offers_onsite'],
        'walk_in' => (bool) $p['accepts_walk_in'],
        'hours'   => $p['opening_hours'],
        'rating'  => (float) $p['rating_avg'],
        'rating_count' => (int) $p['rating_count'],
        'phone'   => $p['phone'],
    ], $rows),
]);
