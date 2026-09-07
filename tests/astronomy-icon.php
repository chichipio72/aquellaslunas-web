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
    [['type' => 'lunar_nodes', 'subtype' => 'ascending_node'], 'node-ascending'],
    [['type' => 'lunar_nodes', 'subtype' => 'descending_node'], 'node-descending'],
    [['type' => 'lunar_nodes', 'subtype' => 'unknown'], 'generic'],
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

ob_start();
renderAstronomyIcon(['type' => 'lunar_nodes', 'subtype' => 'ascending_node'], -34.53, 'today-event-icon');
$ascendingNode = ob_get_clean();
if (!str_contains($ascendingNode, 'astro-icon--node-ascending') || !str_contains($ascendingNode, 'today-event-icon')) {
    throw new RuntimeException('El nodo ascendente no usa su variante vectorial en las superficies compartidas.');
}

ob_start();
renderAstronomyIcon(['type' => 'lunar_nodes', 'subtype' => 'descending_node'], -34.53, 'home-v2-event__icon');
$descendingNode = ob_get_clean();
if (!str_contains($descendingNode, 'astro-icon--node-descending') || !str_contains($descendingNode, 'home-v2-event__icon')) {
    throw new RuntimeException('El nodo descendente no usa su variante vectorial en portada.');
}

echo "Sistema global de iconos astronómicos: OK\n";
