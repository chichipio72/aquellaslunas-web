<?php

header('Content-Type: application/json');
echo json_encode([
    'date' => '2026-07-25',
    'timezone' => 'America/Argentina/Buenos_Aires',
    'detail' => 'full',
    'night' => [
        'start' => '2026-07-25T18:35:00-03:00',
        'end' => '2026-07-26T07:25:00-03:00',
        'polar_state' => 'normal',
    ],
    'planets' => [[
        'id' => 'mars',
        'name' => 'Marte',
        'object_kind' => 'planet',
        'visibility_status' => 'visible_later',
        'visibility_start' => '2026-07-26T00:35:00-03:00',
        'observation_aid' => 'naked_eye',
        'constellation' => ['id' => 'tauro', 'name' => 'Tauro', 'iau_abbreviation' => 'Tau'],
    ]],
    'moon' => [
        'id' => 'moon',
        'name' => 'Luna',
        'object_kind' => 'moon',
        'visibility_status' => 'visible_now',
        'visibility_end' => '2026-07-26T04:49:00-03:00',
        'direction' => 'este',
        'near_moon' => true,
        'moon_proximity' => 'very_close',
        'observation_aid' => 'naked_eye',
        'constellation' => ['id' => 'geminis', 'name' => 'Géminis', 'iau_abbreviation' => 'Gem'],
    ],
    'stars' => [
        [
            'id' => 'sirius',
            'name' => 'Sirio',
            'object_kind' => 'star',
            'visibility_status' => 'visible_now',
            'visibility_end' => '2026-07-25T23:40:00-03:00',
            'direction' => 'noroeste',
            'near_moon' => true,
            'moon_proximity' => 'close',
            'observation_aid' => 'naked_eye',
            'constellation' => ['id' => 'can_mayor', 'name' => 'Can Mayor', 'iau_abbreviation' => 'CMa'],
        ],
        [
            'id' => 'vega',
            'name' => 'Vega',
            'object_kind' => 'star',
            'visibility_status' => 'visible_later',
            'visibility_start' => '2026-07-26T01:15:00-03:00',
            'observation_aid' => 'naked_eye',
        ],
    ],
    'deep_sky_objects' => [],
    'summary' => ['visible_planet_count' => 1, 'has_visible_planets' => true, 'night_available' => true],
]);
