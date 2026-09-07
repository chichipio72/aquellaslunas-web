<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/current-datetime.php';
require_once __DIR__ . '/../includes/tonight.php';
require_once __DIR__ . '/../includes/moon-crater-recommendations.php';

function craterRecommendationAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$timezone = 'America/Argentina/Buenos_Aires';
$now = new DateTimeImmutable('2026-08-18 18:30:00', new DateTimeZone($timezone));
$night = ['night' => [
    'start' => '2026-08-18T19:00:00-03:00',
    'end' => '2026-08-19T06:30:00-03:00',
]];
$location = ['latitude' => -34.6037, 'longitude' => -58.3816, 'elevation_meters' => 25, 'timezone' => $timezone];
$recommendations = moonCraterRecommendations($night, $location, $now, 5);

craterRecommendationAssert(count(moonCraterObservationCatalog()['craters']) === 1241, 'Cambió el universo evaluado de cráteres.');
craterRecommendationAssert(count($recommendations) >= 1 && count($recommendations) <= 5, 'La noche fija no produjo una selección acotada.');
foreach ($recommendations as $crater) {
    craterRecommendationAssert(str_contains($crater['url'], 'feature=iau%3A'), 'El enlace no abre una feature estable.');
    parse_str((string) parse_url($crater['url'], PHP_URL_QUERY), $urlParameters);
    craterRecommendationAssert(($urlParameters['fecha'] ?? null) === $crater['observation_time']->format('Y-m-d'), 'El enlace no conserva la fecha de observación.');
    craterRecommendationAssert(($urlParameters['hora'] ?? null) === $crater['observation_time']->format('H:i'), 'El enlace no conserva la hora de observación.');
    craterRecommendationAssert($crater['terminator_distance_degrees'] > 0 && $crater['terminator_distance_degrees'] <= 18, 'Se recomendó un cráter fuera de la franja iluminada.');
    craterRecommendationAssert($crater['limb_distance_degrees'] >= 8, 'Se recomendó un cráter demasiado próximo al limbo.');
}

echo 'moon-crater-recommendations: ' . implode(', ', array_column($recommendations, 'name')) . PHP_EOL;
