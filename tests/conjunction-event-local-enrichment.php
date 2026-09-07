<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/astronomy-events.php';

function conjunctionEnrichmentAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database = [[
    'type' => 'conjunction', 'subtype' => 'mars',
    'datetime' => '2026-10-05T06:11:35+00:00', 'title' => 'Conjunción Luna–Marte',
    'details' => ['planet' => 'mars', 'separation_degrees' => 1.09, '_source' => 'database'],
]];
$portable = [[
    'type' => 'conjunction', 'subtype' => 'mars',
    'datetime' => '2026-10-05T06:13:00+00:00', 'title' => 'Conjunción de la Luna con mars',
    'details' => [
        'planet' => 'mars', 'object_kind' => 'planet',
        'visibility_classification' => 'visible_nearby',
        'best_visible_time' => '2026-10-05T08:51:35+00:00',
        'visible_window_start' => '2026-10-05T07:41:35+00:00',
        'visible_window_end' => '2026-10-05T08:51:35+00:00',
        'moon_altitude_degrees' => -4.2,
    ],
]];

$result = astronomyEnrichConjunctionItems($database, $portable);
conjunctionEnrichmentAssert($result[0]['datetime'] === $database[0]['datetime'], 'Se reemplazó el instante canónico de MariaDB.');
conjunctionEnrichmentAssert($result[0]['details']['separation_degrees'] === 1.09, 'Se reemplazó un dato global canónico.');
conjunctionEnrichmentAssert($result[0]['details']['visibility_classification'] === 'visible_nearby', 'No se completó la clasificación local.');
conjunctionEnrichmentAssert($result[0]['details']['best_visible_time'] === '2026-10-05T08:51:35+00:00', 'No se completó el momento observable cercano.');
conjunctionEnrichmentAssert($result[0]['details']['local_calculation_source'] === 'php', 'No se identificó el enriquecimiento local.');
$visual = astronomyEnrichConjunctionVisualGeometry($result, -34.6037, -58.3816);
conjunctionEnrichmentAssert(($visual[0]['details']['visual_geometry_time'] ?? null) === '2026-10-05T08:51:35+00:00', 'La geometría visual no usa best_visible_time.');
conjunctionEnrichmentAssert(($visual[0]['details']['visual_relative_x_degrees'] ?? 0) < 0 && ($visual[0]['details']['visual_relative_y_degrees'] ?? 0) < 0, 'La dirección relativa real de Marte no fue preservada.');

$distant = $portable;
$distant[0]['datetime'] = '2026-10-06T06:13:00+00:00';
$unchanged = astronomyEnrichConjunctionItems($database, $distant);
conjunctionEnrichmentAssert(!isset($unchanged[0]['details']['visibility_classification']), 'Se cruzaron encuentros distintos del mismo planeta.');

echo "OK conjunction event local enrichment\n";
