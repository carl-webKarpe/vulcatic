<?php
// Vulcatic's service area: Siargao Island (Surigao del Norte).
// Centers are approximate town centers (poblacion). Check them on a map
// before going live and adjust if a pin looks off.
//
// radius_km = how far we look for a free vulcator for a request in that town.
// The busy south has many vulcators close together; the quiet north is spread out.

const SIARGAO_TOWNS = [
    'General Luna' => ['lat' =>  9.7836, 'lng' => 126.1563, 'radius_km' => 5,  'phase' => 1],
    'Dapa'         => ['lat' =>  9.7587, 'lng' => 126.0525, 'radius_km' => 5,  'phase' => 1],
    'Pilar'        => ['lat' =>  9.8634, 'lng' => 126.1004, 'radius_km' => 10, 'phase' => 2],
    'Del Carmen'   => ['lat' =>  9.8689, 'lng' => 125.9697, 'radius_km' => 10, 'phase' => 2],
    'San Isidro'   => ['lat' =>  9.9378, 'lng' => 126.0892, 'radius_km' => 12, 'phase' => 3],
    'San Benito'   => ['lat' =>  9.9573, 'lng' => 126.0067, 'radius_km' => 12, 'phase' => 3],
    'Burgos'       => ['lat' => 10.0125, 'lng' => 126.0714, 'radius_km' => 12, 'phase' => 3],
    'Santa Monica' => ['lat' => 10.0190, 'lng' => 126.0373, 'radius_km' => 12, 'phase' => 3],
];

// Rough box around Siargao (Socorro / Bucas Grande is left out).
const SIARGAO_BOUNDS = ['south' => 9.70, 'north' => 10.08, 'west' => 125.92, 'east' => 126.20];

// Where the map opens before we know the user's location: General Luna.
const SIARGAO_CENTER = [9.7900, 126.1500];

function in_service_area(float $lat, float $lng): bool
{
    $b = SIARGAO_BOUNDS;
    return $lat >= $b['south'] && $lat <= $b['north'] && $lng >= $b['west'] && $lng <= $b['east'];
}

// Nearest Siargao town to a point, or null if outside the service area.
function municipality_for(float $lat, float $lng): ?string
{
    if (!in_service_area($lat, $lng)) return null;
    $best = null;
    $bestKm = INF;
    foreach (SIARGAO_TOWNS as $name => $t) {
        $km = distance_km($lat, $lng, $t['lat'], $t['lng']);
        if ($km < $bestKm) { $bestKm = $km; $best = $name; }
    }
    return $best;
}

function search_radius_for(?string $municipality): float
{
    return (float) (SIARGAO_TOWNS[$municipality]['radius_km'] ?? setting('default_search_radius_km', '5'));
}

const OUTSIDE_AREA_MESSAGE = 'Vulcatic currently serves Siargao Island only. Move the pin to your spot on Siargao.';
