<?php

require_once __DIR__ . '/../includes/astronomy-icon.php';

$cases = [
    [['type' => 'moon_phase', 'subtype' => 'new_moon'], 'moon-new'],
    [['type' => 'moon_phase', 'subtype' => 'first_quarter'], 'moon-first-quarter'],
    [['type' => 'moon_phase', 'subtype' => 'full_moon'], 'moon-full'],
    [['type' => 'moon_phase', 'subtype' => 'last_quarter'], 'moon-last-quarter'],
    [['type' => 'earthshine'], 'moon-earthshine'],
    [['type' => 'conjunction'], 'conjunction'],
    [['type' => 'conjunction', 'details' => ['objects' => ['Luna', 'Marte', 'Pléyades']]], 'conjunction-cluster'],
    [['type' => 'conjunction', 'subtype' => 'pleiades', 'details' => ['planet' => 'pleiades']], 'conjunction-cluster'],
    [['type' => 'libration', 'subtype' => 'libration_east'], 'libration-east'],
    [['type' => 'libration', 'subtype' => 'libration_west'], 'libration-west'],
    [['type' => 'libration', 'subtype' => 'libration_north'], 'libration-north'],
    [['type' => 'libration', 'subtype' => 'libration_south'], 'libration-south'],
    [['type' => 'apsis', 'subtype' => 'perigee'], 'perigee'],
    [['type' => 'apsis', 'subtype' => 'apogee'], 'apogee'],
    [['type' => 'eclipse', 'subtype' => 'lunar_eclipse'], 'eclipse'],
    [['type' => 'eclipse', 'subtype' => 'solar_eclipse'], 'eclipse'],
    [['type' => 'unknown'], 'generic'],
];

foreach ($cases as [$event, $expected]) {
    if (astronomyIconKey($event) !== $expected) {
        throw new RuntimeException('Mapeo incorrecto para ' . json_encode($event));
    }
}

ob_start();
renderAstronomyIcon(['type' => 'moon_phase', 'subtype' => 'first_quarter'], -34.53);
$south = ob_get_clean();
if (!str_contains($south, 'astro-icon--south') || !str_contains($south, 'aria-hidden="true"')) {
    throw new RuntimeException('Orientación o accesibilidad incorrecta para hemisferio sur.');
}

ob_start();
renderAstronomyIcon(['type' => 'moon_phase', 'subtype' => 'first_quarter'], 40.0);
$north = ob_get_clean();
if (!str_contains($north, 'astro-icon--north')) {
    throw new RuntimeException('Orientación incorrecta para hemisferio norte.');
}

echo "Sistema global de iconos astronómicos: OK\n";
