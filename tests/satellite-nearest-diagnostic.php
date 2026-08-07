<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\FixtureSatelliteTleProvider;
use AstronomyEngine\Satellite\MoonTransitTargetProvider;
use AstronomyEngine\Satellite\SatelliteAngularTransitDetector;

function nearestDiagnosticAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$resolved = (new FixtureSatelliteTleProvider(__DIR__ . '/fixtures/satellite'))->resolve('tiangong');
$observer = new AstronomyObserver(-34.53, -55.0, 'America/Argentina/Buenos_Aires', 28.0);
$start = new DateTimeImmutable('2026-07-25T18:00:00-03:00');
$target = new MoonTransitTargetProvider();
$detector = new SatelliteAngularTransitDetector();
$nearest = $detector->nearestApproaches($observer, $start, 12, ['tiangong' => $resolved->tle], [$target]);

nearestDiagnosticAssert(count($nearest) === 1, 'El diagnóstico no devolvió un mínimo por satélite y objetivo.');
$minimum = $nearest[0];
nearestDiagnosticAssert($minimum->classification === 'none' && $minimum->minimumSeparationDegrees > 3.0,
    'El diagnóstico descartó un mínimo exterior a close.');
nearestDiagnosticAssert($minimum->maximum >= $start && $minimum->maximum <= $start->modify('+12 hours')
    && is_finite($minimum->satelliteAltitudeDegrees) && is_finite($minimum->targetAltitudeDegrees)
    && $minimum->satelliteDistanceKilometers > 0.0,
    'El mínimo diagnóstico no contiene geometría completa.');
nearestDiagnosticAssert($minimum->tleLine1 === $resolved->tle->line1
    && $minimum->tleLine2 === $resolved->tle->line2 && $minimum->tleEpochUtc == $resolved->tle->epochUtc,
    'El mínimo diagnóstico no informa el TLE utilizado.');
nearestDiagnosticAssert($minimum->warnings !== [], 'No se advirtió que el TLE no era contemporáneo al evento.');

$publicSearch = $detector->search($observer, $start, 12, ['tiangong' => $resolved->tle], [$target]);
nearestDiagnosticAssert($publicSearch->events === [],
    'El mínimo none del diagnóstico se filtró al resultado operativo.');
nearestDiagnosticAssert(SatelliteAngularTransitDetector::classify(0.25, 0.25) === 'transit'
    && SatelliteAngularTransitDetector::classify(0.5, 0.25) === 'very_close'
    && SatelliteAngularTransitDetector::classify(1.25, 0.25) === 'close'
    && SatelliteAngularTransitDetector::classify(3.25, 0.25) === 'near_pass'
    && SatelliteAngularTransitDetector::classify(3.251, 0.25) === 'none'
    && SatelliteAngularTransitDetector::classify(3.2367254909, 0.2657943890) === 'near_pass',
    'Los umbrales públicos cambiaron al agregar el diagnóstico.');

echo json_encode([
    'status' => 'OK',
    'classification' => $minimum->classification,
    'separation_degrees' => $minimum->minimumSeparationDegrees,
    'operational_events' => count($publicSearch->events),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
