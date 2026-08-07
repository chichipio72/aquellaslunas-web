<?php

declare(strict_types=1);

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\FixtureSatelliteTleProvider;
use AstronomyEngine\Satellite\SatelliteTransitEvent;
use AstronomyEngine\Satellite\SatelliteTransitSearchResult;
use AstronomyEngine\Satellite\SatelliteTransitService;
use AstronomyEngine\Satellite\SatelliteTransitServiceResult;

const HOME_SATELLITE_LOCAL_TEST_MODES = [
    'historical',
    'synthetic-tonight-transit',
    'synthetic-tonight-near-pass',
    'synthetic-upcoming-lunar-transit',
    'synthetic-upcoming-lunar-near-pass',
    'synthetic-upcoming-solar-transit',
    'synthetic-upcoming-solar-near-pass',
    'synthetic-priority',
    'synthetic-lunar',
    'synthetic-solar',
    'synthetic-none',
    'synthetic-error',
    'synthetic-near-lunar',
    'synthetic-near-solar',
];

/** @return array<string,string> */
function homeSatelliteLocalTestReference(): array
{
    if (!isLocalEnvironment() || !canUseSiteDebugTools()) return [];
    return [
        'historical' => 'Tiangong histórico real',
        'synthetic-tonight-transit' => 'tránsito lunar en Esta noche',
        'synthetic-tonight-near-pass' => 'near_pass lunar en Esta noche',
        'synthetic-upcoming-lunar-transit' => 'tránsito lunar en Lo próximo',
        'synthetic-upcoming-lunar-near-pass' => 'near_pass lunar en Lo próximo',
        'synthetic-upcoming-solar-transit' => 'tránsito solar en Lo próximo',
        'synthetic-upcoming-solar-near-pass' => 'near_pass solar en Lo próximo',
        'synthetic-priority' => 'prioridad y límite editorial',
        'synthetic-none' => 'sin eventos',
        'synthetic-error' => 'fallo aislado',
    ];
}

function homeSatelliteLocalTestMode(mixed $value): ?string
{
    if (!isLocalEnvironment() || !is_string($value)) {
        return null;
    }
    $mode = strtolower(trim($value));
    return in_array($mode, HOME_SATELLITE_LOCAL_TEST_MODES, true) ? $mode : null;
}

/**
 * Builds a page-local test profile. The caller must still use the normal
 * homeSatelliteContext() path, so historical mode exercises the real 48-hour
 * detector instead of injecting an event.
 *
 * @return array{mode:?string,location:array<string,mixed>,now:DateTimeImmutable,runner:?callable}
 */
function homeSatelliteLocalTestSetup(?string $mode, array $location, DateTimeImmutable $now): array
{
    if (!isLocalEnvironment() || $mode === null || !in_array($mode, HOME_SATELLITE_LOCAL_TEST_MODES, true)) {
        return ['mode' => null, 'location' => $location, 'now' => $now, 'runner' => null];
    }

    $location = array_replace($location, [
        'mode' => 'local_satellite_fixture',
        'confirmed' => true,
        'name' => 'Caso histórico Tiangong',
        'latitude' => -34.53,
        'longitude' => -58.48,
        'timezone' => 'America/Argentina/Buenos_Aires',
    ]);
    $now = new DateTimeImmutable('2026-07-25 18:00:00', new DateTimeZone($location['timezone']));
    if ($mode === 'historical') {
        $fixtureDirectory = dirname(__DIR__) . '/tests/fixtures/satellite';
        $service = new SatelliteTransitService(new FixtureSatelliteTleProvider($fixtureDirectory));
        $runner = static fn(AstronomyObserver $observer, DateTimeImmutable $start, int $hours,
            array $satellites, array $targets): SatelliteTransitServiceResult =>
            $service->search($observer, $start, $hours, $satellites, $targets);
    } elseif ($mode === 'synthetic-error') {
        $runner = static function (): SatelliteTransitServiceResult {
            throw new RuntimeException('Fallo sintético local del servicio satelital.');
        };
    } else {
        $runner = static fn(AstronomyObserver $observer, DateTimeImmutable $start, int $hours,
            array $satellites, array $targets): SatelliteTransitServiceResult =>
            homeSatelliteSyntheticResult($mode, $observer, $start, $hours, $targets);
    }

    return ['mode' => $mode, 'location' => $location, 'now' => $now, 'runner' => $runner];
}

/** @param list<string> $targets */
function homeSatelliteSyntheticResult(string $mode, AstronomyObserver $observer, DateTimeImmutable $start,
    int $hours, array $targets): SatelliteTransitServiceResult
{
    $events = [];
    if ($mode !== 'synthetic-none') {
        $aliases = [
            'synthetic-lunar' => 'synthetic-tonight-transit',
            'synthetic-solar' => 'synthetic-upcoming-solar-transit',
            'synthetic-near-lunar' => 'synthetic-tonight-near-pass',
            'synthetic-near-solar' => 'synthetic-upcoming-solar-near-pass',
        ];
        $resolvedMode = $aliases[$mode] ?? $mode;
        $definitions = match ($resolvedMode) {
            'synthetic-tonight-transit' => [['moon', 'tiangong', 'transit', '2026-07-26 05:25:00', 0.08, 0.25]],
            'synthetic-tonight-near-pass' => [['moon', 'iss', 'near_pass', '2026-07-26 05:10:00', 3.20, 0.25]],
            'synthetic-upcoming-lunar-transit' => [['moon', 'iss', 'transit', '2026-07-26 12:10:00', 0.08, 0.25]],
            'synthetic-upcoming-lunar-near-pass' => [['moon', 'tiangong', 'near_pass', '2026-07-26 12:20:00', 3.20, 0.25]],
            'synthetic-upcoming-solar-transit' => [['sun', 'tiangong', 'transit', '2026-07-26 10:15:00', 0.08, 0.266]],
            'synthetic-upcoming-solar-near-pass' => [['sun', 'iss', 'near_pass', '2026-07-26 10:20:00', 3.22, 0.266]],
            'synthetic-priority' => [
                ['moon', 'iss', 'near_pass', '2026-07-26 04:50:00', 3.20, 0.25],
                ['moon', 'tiangong', 'near_pass', '2026-07-26 05:10:00', 2.20, 0.25],
                ['moon', 'iss', 'very_close', '2026-07-26 12:00:00', 0.40, 0.25],
                ['sun', 'tiangong', 'near_pass', '2026-07-26 10:20:00', 3.20, 0.266],
            ],
            default => [],
        };
        foreach ($definitions as [$body, $satellite, $classification, $instant, $separation, $radius]) {
            $events[] = homeSatelliteSyntheticEvent($body, $satellite, $classification,
                new DateTimeImmutable($instant, $start->getTimezone()), $separation, $radius);
        }
    }
    $end = $start->modify('+' . $hours . ' hours');
    $search = new SatelliteTransitSearchResult($start, $end, $observer, $targets, $events,
        ['total_samples' => 0, 'total_ms' => 0.0], 0.0);
    return new SatelliteTransitServiceResult($search, [], [], [
        'tle_resolution_ms' => 0.0,
        'total_ms' => 0.0,
        'service_total_ms' => 0.0,
    ]);
}

function homeSatelliteSyntheticEvent(string $body, string $satellite, string $classification,
    DateTimeImmutable $maximum, float $separation, float $radius): SatelliteTransitEvent
{
    $transit = $classification === 'transit';
    $name = $satellite === 'iss' ? 'ISS (ZARYA)' : 'CSS (TIANHE)';
    return new SatelliteTransitEvent(
        $body, $satellite, $name, $classification, $maximum,
        $transit ? $maximum->modify('-1 second') : null,
        $transit ? $maximum->modify('+1 second') : null,
        $transit ? 2.0 : null,
        $separation, $radius, $radius - $separation,
        30.0, 80.0, 30.1, 80.1, 700.0,
        'SYNTHETIC LOCAL TEST — NO TLE', 'SYNTHETIC LOCAL TEST — NO TLE',
        $maximum,
        $body === 'sun' ? [SatelliteTransitService::SOLAR_SAFETY_WARNING] : [],
    );
}
