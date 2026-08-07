<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\SatelliteLunarTransitDetector;
use AstronomyEngine\Satellite\TleParser;

function satelliteTransitAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function satelliteTransitFixtures(array $ids): array
{
    $parser = new TleParser(); $result = [];
    foreach ($ids as $id) {
        $lines = file(__DIR__ . '/fixtures/satellite/' . $id . '.tle', FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || count($lines) !== 3) throw new RuntimeException('Missing TLE fixture.');
        $result[$id] = $parser->parse($lines[0], $lines[1], $lines[2]);
    }
    return $result;
}

foreach ([[0.25, 0.25, 'transit'], [0.250001, 0.25, 'very_close'], [0.5, 0.25, 'very_close'],
    [0.500001, 0.25, 'close'], [1.25, 0.25, 'close'], [1.250001, 0.25, 'none']] as [$separation, $radius, $expected]) {
    satelliteTransitAssert(SatelliteLunarTransitDetector::classify($separation, $radius) === $expected, 'Classification boundary failed.');
}

$observer = new AstronomyObserver(-34.53, -58.48, 'America/Argentina/Buenos_Aires');
$all = satelliteTransitFixtures(['iss', 'tiangong']);
$empty = (new SatelliteLunarTransitDetector())->search(
    $observer, new DateTimeImmutable('2026-07-25 12:00:00', new DateTimeZone('UTC')), 1, $all
);
satelliteTransitAssert($empty->events === [], 'The known empty window returned an event.');
satelliteTransitAssert($empty->metrics['events'] === 0 && $empty->metrics['total_samples'] > 0, 'Empty search metrics are incomplete.');

$historical = (new SatelliteLunarTransitDetector())->search(
    $observer, new DateTimeImmutable('2026-07-26 00:00:00', $observer->timezone), 12,
    ['tiangong' => $all['tiangong']]
);
satelliteTransitAssert(count($historical->events) === 1, 'The historical Tiangong candidate was lost.');
$event = $historical->events[0];
satelliteTransitAssert($event->satellite === 'tiangong' && $event->classification === 'transit', 'The historical event is not a Tiangong transit.');
satelliteTransitAssert($event->maximum->format('Y-m-d H:i') === '2026-07-26 05:25', 'Historical maximum moved outside the expected minute.');
satelliteTransitAssert(abs($event->minimumSeparationDegrees - 0.088129) < 0.01, 'PHP/Python minimum separation differs by at least 0.01 degree.');
satelliteTransitAssert(abs($event->moonApparentRadiusDegrees - 0.245843) < 0.001, 'PHP/Python lunar radius differs by at least 0.001 degree.');
satelliteTransitAssert($event->entry && $event->exit && $event->entry < $event->maximum && $event->maximum < $event->exit, 'Transit contacts are not ordered.');
satelliteTransitAssert($event->durationSeconds !== null && abs($event->durationSeconds - 4.44) < 0.1, 'PHP/Python duration differs by at least 0.1 second.');
satelliteTransitAssert($event->moonAltitudeDegrees > 0 && $event->satelliteAltitudeDegrees > 0, 'A below-horizon event was returned.');
satelliteTransitAssert($historical->dut1Seconds === 0.0, 'DUT1=0 is not explicit in the result.');
foreach (['propagation_ms','topocentric_ms','lunar_ms','coarse_search_ms','fine_search_ms','refinement_ms','contacts_ms','total_ms',
    'coarse_samples','fine_samples','refinement_samples','contact_samples','total_samples','pass_runs','prefilter_candidates','refined_candidates'] as $metric) {
    satelliteTransitAssert(array_key_exists($metric, $historical->metrics), 'Missing metric ' . $metric);
}
$serialized = $historical->data()['events'][0];
satelliteTransitAssert($serialized['tle']['line1'] === $all['tiangong']->line1 && $serialized['entry'] !== null && $serialized['exit'] !== null,
    'Serialized event omitted TLE or contacts.');

try {
    (new SatelliteLunarTransitDetector())->search($observer, new DateTimeImmutable('now'), 49, $all);
    throw new RuntimeException('A search longer than 48 hours was accepted.');
} catch (InvalidArgumentException) {
}

echo json_encode(['status' => 'OK', 'empty_events' => 0, 'historical_event' => $serialized,
    'python_reference' => ['minimum_separation_degrees' => 0.088129, 'moon_radius_degrees' => 0.245843, 'duration_seconds' => 4.44],
    'metrics' => $historical->metrics], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
