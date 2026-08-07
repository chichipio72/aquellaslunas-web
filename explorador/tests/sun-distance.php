<?php

declare(strict_types=1);

use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\SolarPosition;
use Explorador\SeriesBuilder;
use Explorador\SeriesPlanner;
use Explorador\SeriesRequest;
use Explorador\VariableCatalog;

require_once dirname(__DIR__) . '/includes/astronomy-engine-autoload.php';
require_once dirname(__DIR__) . '/includes/VariableCatalog.php';
require_once dirname(__DIR__) . '/includes/PhaseEventDateProvider.php';
require_once dirname(__DIR__) . '/includes/SeriesRequest.php';
require_once dirname(__DIR__) . '/includes/SeriesPlanner.php';
require_once dirname(__DIR__) . '/includes/SeriesBuilder.php';

function sunDistanceCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function sunDistanceBuild(string $from, string $to, ?array $selectedDates = null): array
{
    $catalog = new VariableCatalog();
    $request = SeriesRequest::fromParameters([
        'fecha_desde' => $from, 'fecha_hasta' => $to,
        'lat' => '-34.6037', 'lon' => '-58.3816',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'campos' => 'sun_altitude,sun_azimuth,sun_distance_km',
        'fases' => $selectedDates === null ? '' : 'full_moon',
    ], $catalog);
    $plan = (new SeriesPlanner($catalog))->plan($request->fields);
    sunDistanceCheck($plan['providers'] === ['sun_instant'], 'Las variables solares no comparten proveedor.');
    return (new SeriesBuilder($catalog))->build($request, $plan, null, $selectedDates);
}

$position = (new MeeusSolarPositionCalculator())->calculate(
    new DateTimeImmutable('2026-01-03 00:00:00', new DateTimeZone('UTC')), 0.0, 0.0
);
sunDistanceCheck(abs($position->earthSunDistanceKilometers()
    - $position->earthSunDistanceAu * SolarPosition::ASTRONOMICAL_UNIT_KILOMETERS) < 0.000001,
    'La conversión pública AU a km es inconsistente.');
sunDistanceCheck($position->earthSunDistanceKilometers() > 147_000_000
    && $position->earthSunDistanceKilometers() < 153_000_000, 'La distancia solar está fuera del rango físico esperado.');

$threeDays = sunDistanceBuild('2026-01-01', '2026-01-03');
sunDistanceCheck(count($threeDays['rows']) === 3, 'El rango de tres días es incorrecto.');
foreach ($threeDays['rows'] as $row) {
    sunDistanceCheck(is_int($row['sun_distance_km']) || is_float($row['sun_distance_km']),
        'La distancia solar no es numérica.');
}

$year = sunDistanceBuild('2025-01-01', '2025-12-31');
sunDistanceCheck(count($year['rows']) === 365, 'El rango anual es incorrecto.');
$distances = array_column($year['rows'], 'sun_distance_km');
sunDistanceCheck(max($distances) - min($distances) > 4_000_000, 'El ciclo anual de distancia solar no aparece.');

$fullMoon = sunDistanceBuild('2026-01-01', '2026-02-28', [[
    'date' => '2026-02-01', 'phase' => 'full_moon', 'phase_label' => 'Luna llena',
    'instant' => '2026-02-01T19:00:00-03:00',
]]);
sunDistanceCheck(count($fullMoon['rows']) === 1 && $fullMoon['rows'][0]['main_phase'] === 'Luna llena',
    'El filtro por Luna llena no conserva la distancia solar.');

$definition = (new VariableCatalog())->get('sun_distance_km');
sunDistanceCheck($definition['scaleGroup'] === 'solar_distance' && $definition['precision'] === 0
    && $definition['unit'] === 'km' && !isset($definition['extrema_supported']),
    'El contrato de catálogo de la distancia solar es incorrecto.');
$scaleGroups = require dirname(__DIR__) . '/catalog/scale-groups.php';
sunDistanceCheck(($scaleGroups['solar_distance']['label'] ?? '') === 'Distancia solar',
    'El título del eje solar es incorrecto.');
$javascript = file_get_contents(dirname(__DIR__) . '/assets/explorador.js');
sunDistanceCheck(is_string($javascript)
    && str_contains($javascript, 'solar_distance: 1000')
    && str_contains($javascript, 'solar_distance: 0'),
    'El eje solar no conserva ticks enteros con separador de miles.');

echo "Sun distance smoke tests: OK\n";
