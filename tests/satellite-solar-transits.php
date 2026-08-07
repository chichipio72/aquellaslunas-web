<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\Satellite\FixtureSatelliteTleProvider;
use AstronomyEngine\Satellite\SatelliteTransitService;

function solarTransitAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$fixtures = new FixtureSatelliteTleProvider(__DIR__ . '/fixtures/satellite');
$service = new SatelliteTransitService($fixtures);
$observer = new AstronomyObserver(41.87, 12.49, 'UTC');

$empty = $service->search($observer, new DateTimeImmutable('2026-07-24T00:00:00Z'), 1, ['iss', 'tiangong'], ['sun']);
solarTransitAssert($empty->search->events === [], 'Known empty solar window returned an event.');

$solar = $service->search($observer, new DateTimeImmutable('2026-07-24T12:00:00Z'), 8, ['tiangong'], ['sun']);
solarTransitAssert(count($solar->search->events) === 1, 'Historical solar transit was not found.');
$event = $solar->search->events[0];
solarTransitAssert($event->targetBody === 'sun' && $event->satellite === 'tiangong' && $event->classification === 'transit',
    'Historical event has the wrong target, satellite, or classification.');
solarTransitAssert($event->entry !== null && $event->exit !== null && $event->entry < $event->maximum && $event->maximum < $event->exit,
    'Solar contacts are missing or unordered.');
solarTransitAssert($event->durationSeconds !== null && abs($event->durationSeconds - 1.49) < 0.08,
    'Solar transit duration moved outside tolerance.');
solarTransitAssert(abs($event->minimumSeparationDegrees - 0.03061) < 0.002 && abs($event->targetApparentRadiusDegrees - 0.262304) < 0.0001,
    'Solar transit geometry moved outside tolerance.');
solarTransitAssert($event->targetAltitudeDegrees > 0.0 && $event->satelliteAltitudeDegrees > 0.0,
    'Below-horizon solar event was returned.');
solarTransitAssert(in_array(SatelliteTransitService::SOLAR_SAFETY_WARNING, $event->warnings, true),
    'Solar event omitted the safety warning.');

// Shift the complete sampling grid: the 1.49 s event must still be bracketed
// by the adaptive 2 s pass grid and recovered by local refinement.
$shifted = $service->search($observer, new DateTimeImmutable('2026-07-24T12:00:00.750000Z'), 8, ['tiangong'], ['sun']);
solarTransitAssert(count($shifted->search->events) === 1, 'Fast solar transit was lost after shifting the sample grid.');
solarTransitAssert(abs((float) $shifted->search->events[0]->maximum->format('U.u') - (float) $event->maximum->format('U.u')) < 0.02,
    'Shifted-grid refinement changed the solar maximum.');

$sun = new MeeusSolarPositionCalculator();
$january = $sun->calculate(new DateTimeImmutable('2026-01-04T12:00:00Z'), 0.0, 0.0);
$july = $sun->calculate(new DateTimeImmutable('2026-07-04T12:00:00Z'), 0.0, 0.0);
solarTransitAssert($january->apparentRadiusDegrees > $july->apparentRadiusDegrees
    && $january->apparentRadiusDegrees - $july->apparentRadiusDegrees > 0.008,
    'Variable solar angular radius is not represented.');

$combined = $service->search($observer, new DateTimeImmutable('2026-07-24T00:00:00Z'), 48,
    ['iss', 'tiangong'], ['moon', 'sun']);
solarTransitAssert(in_array('moon', $combined->search->targets, true) && in_array('sun', $combined->search->targets, true),
    'Combined search omitted a target body.');
solarTransitAssert(array_filter($combined->search->events, static fn($candidate): bool => $candidate->targetBody === 'sun') !== [],
    'Combined search lost the solar event.');
foreach (['propagation_ms','topocentric_ms','moon_ms','sun_ms','coarse_samples','fine_samples','total_samples','service_total_ms'] as $metric) {
    solarTransitAssert(array_key_exists($metric, $combined->metrics), 'Combined metrics omitted ' . $metric . '.');
}
$serializedCombined = $combined->data();
solarTransitAssert(isset($serializedCombined['events'][0]['metrics']['service_total_ms']),
    'Serialized event omitted its principal execution metrics.');

echo json_encode([
    'status' => 'OK',
    'solar_transit' => $event->data(),
    'shifted_grid_maximum_difference_seconds' => abs((float) $shifted->search->events[0]->maximum->format('U.u') - (float) $event->maximum->format('U.u')),
    'solar_radius_degrees' => ['january' => $january->apparentRadiusDegrees, 'july' => $july->apparentRadiusDegrees],
    'combined_48h' => ['events' => array_map(static fn($candidate): array => $candidate->data(), $combined->search->events),
        'metrics' => $combined->metrics],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
