<?php

declare(strict_types=1);

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\Satellite\CachedCelesTrakTleProvider;
use AstronomyEngine\Satellite\CelesTrakTleDownloader;
use AstronomyEngine\Satellite\SatelliteTransitService;
use AstronomyEngine\Satellite\SatelliteTopocentricCalculator;
use AstronomyEngine\Satellite\Sgp4\Sgp4Propagator;

/** @return array<string,mixed> */
function satelliteStationsVisualizationPayload(array $location, DateTimeImmutable $instant): array
{
    $utc = $instant->setTimezone(new DateTimeZone('UTC'));
    $observer = new AstronomyObserver(
        (float) $location['latitude'], (float) $location['longitude'], (string) $location['timezone'],
        (float) ($location['elevation_meters'] ?? 0.0),
    );
    $cachePath = (string) (getenv('ASTRONOMY_TLE_CACHE_PATH')
        ?: dirname(__DIR__) . '/astronomy-engine/cache/satellite/tle-cache.json');
    $ttl = (int) (getenv('ASTRONOMY_TLE_CACHE_TTL_SECONDS')
        ?: CachedCelesTrakTleProvider::DEFAULT_TTL_SECONDS);
    $provider = new CachedCelesTrakTleProvider($cachePath, $ttl, new CelesTrakTleDownloader(3), null, false);
    $resolved = [];
    foreach (['iss', 'tiangong'] as $id) $resolved[$id] = $provider->resolve($id);

    $start = $utc->modify('-90 minutes');
    $samples = [];
    $passes = [];
    foreach ($resolved as $id => $item) {
        $propagator = new Sgp4Propagator($item->tle);
        $series = [];
        for ($offset = 0; $offset <= 10800; $offset += 30) {
            $time = $start->modify('+' . $offset . ' seconds');
            $state = $propagator->propagate($time);
            $series[] = [
                'datetime' => $time->format('Y-m-d\TH:i:s\Z'),
                'ecef_km' => satelliteStationsTemeToEarthFixed($state->positionKilometers, $time),
            ];
        }
        $samples[$id] = [
            'label' => $id === 'iss' ? 'ISS' : 'Tiangong',
            'color' => $id === 'iss' ? '#f3c75d' : '#68c9ff',
            'samples' => $series,
            'tle' => [
                'epoch_utc' => $item->tle->epochUtc->format(DateTimeInterface::ATOM),
                'cache_status' => $item->cacheStatus,
                'confidence' => $item->confidence,
            ],
        ];
        foreach (satelliteStationsUpcomingPasses($id, $propagator, $observer, $utc, 48) as $pass) $passes[] = $pass;
    }

    $moonCalculator = new MeeusLunarCalculator();
    $sunCalculator = new MeeusSolarPositionCalculator();
    $moonSeries = [];
    $sunSeries = [];
    for ($offset = 0; $offset <= 10800; $offset += 120) {
        $time = $start->modify('+' . $offset . ' seconds');
        $moon = $moonCalculator->calculate($time, 0.0, 0.0);
        $moonSeries[] = [
            'datetime' => $time->format('Y-m-d\TH:i:s\Z'),
            'direction_ecef' => satelliteStationsEquatorialToEarthFixed(
                $moon->rightAscensionDegrees, $moon->declinationDegrees, $time
            ),
        ];
        $sun = $sunCalculator->calculate($time, 0.0, 0.0);
        $sunSeries[] = [
            'datetime' => $time->format('Y-m-d\TH:i:s\Z'),
            'direction_ecef' => satelliteStationsEquatorialToEarthFixed(
                $sun->rightAscensionDegrees, $sun->declinationDegrees, $time
            ),
        ];
    }

    $service = new SatelliteTransitService($provider);
    $transits = $service->search($observer, $utc, 48, ['iss', 'tiangong'], ['moon', 'sun']);
    $bands = [];
    foreach ($transits->search->events as $event) {
        if ($event->targetBody === 'moon' && $event->maximum <= $utc->modify('+3 hours')) {
            $bands[] = [
                'satellite' => $event->satellite,
                'maximum_utc' => $event->maximum->format(DateTimeInterface::ATOM),
                'center_latitude' => (float) $location['latitude'],
                'center_longitude' => (float) $location['longitude'],
                'half_width_degrees' => 2.2,
            ];
        }
        $eventTimestamp = $event->maximum->getTimestamp();
        foreach ($passes as &$pass) {
            if ($pass['satellite'] !== $event->satellite || $eventTimestamp < $pass['start_timestamp'] || $eventTimestamp > $pass['end_timestamp']) continue;
            $pass['approaches'][] = [
                'body' => $event->targetBody,
                'classification' => $event->classification,
                'maximum' => $event->maximum->format(DateTimeInterface::ATOM),
            ];
        }
        unset($pass);
    }
    usort($passes, static fn(array $first, array $second): int => $first['start_timestamp'] <=> $second['start_timestamp']);
    foreach ($passes as &$pass) unset($pass['start_timestamp'], $pass['end_timestamp']);
    unset($pass);

    return [
        'instant' => $utc->format(DateTimeInterface::ATOM),
        'timezone' => (string) $location['timezone'],
        'range' => ['start' => $start->format(DateTimeInterface::ATOM), 'end' => $start->modify('+3 hours')->format(DateTimeInterface::ATOM)],
        'earth_radius_km' => 6378.137,
        'observer' => ['latitude' => (float) $location['latitude'], 'longitude' => (float) $location['longitude'], 'name' => (string) $location['name']],
        'satellites' => $samples,
        'moon' => $moonSeries,
        'sun' => $sunSeries,
        'passes' => $passes,
        'transit_bands' => $bands,
        'band_note' => 'La banda es una guía visual centrada en la ubicación del observador; no representa una franja cartográfica calculada.',
    ];
}

/** @return list<array<string,mixed>> */
function satelliteStationsUpcomingPasses(string $satellite, Sgp4Propagator $propagator,
    AstronomyObserver $observer, DateTimeImmutable $start, int $hours): array
{
    $calculator = new SatelliteTopocentricCalculator();
    $samples = [];
    for ($offset = 0; $offset <= $hours * 3600; $offset += 30) {
        $time = $start->modify('+' . $offset . ' seconds');
        $position = $calculator->calculate($propagator->propagate($time), $observer);
        $samples[] = ['time' => $time, 'altitude' => $position->altitudeDegrees, 'azimuth' => $position->azimuthDegrees];
    }
    $passes = []; $runStart = null;
    for ($index = 0, $count = count($samples); $index < $count; $index++) {
        $above = $samples[$index]['altitude'] >= 0.0;
        if ($above && $runStart === null) $runStart = $index;
        if ($runStart === null || ($above && $index !== $count - 1)) continue;
        $runEnd = $above ? $index : $index - 1;
        $maximumIndex = $runStart;
        for ($candidate = $runStart + 1; $candidate <= $runEnd; $candidate++) {
            if ($samples[$candidate]['altitude'] > $samples[$maximumIndex]['altitude']) $maximumIndex = $candidate;
        }
        if ($samples[$maximumIndex]['altitude'] >= 10.0) {
            $startSample = $samples[$runStart]; $endSample = $samples[$runEnd];
            if ($runStart > 0) $startSample = satelliteStationsHorizonCrossing($samples[$runStart - 1], $samples[$runStart]);
            if ($runEnd + 1 < $count) $endSample = satelliteStationsHorizonCrossing($samples[$runEnd], $samples[$runEnd + 1]);
            $zone = $observer->timezone;
            $passes[] = [
                'satellite' => $satellite,
                'start' => $startSample['time']->setTimezone($zone)->format(DateTimeInterface::ATOM),
                'maximum' => $samples[$maximumIndex]['time']->setTimezone($zone)->format(DateTimeInterface::ATOM),
                'end' => $endSample['time']->setTimezone($zone)->format(DateTimeInterface::ATOM),
                'start_timestamp' => $startSample['time']->getTimestamp(),
                'end_timestamp' => $endSample['time']->getTimestamp(),
                'maximum_altitude_degrees' => round($samples[$maximumIndex]['altitude'], 1),
                'appearance_azimuth_degrees' => round($startSample['azimuth']),
                'disappearance_azimuth_degrees' => round($endSample['azimuth']),
                'approaches' => [],
            ];
        }
        $runStart = null;
    }
    return $passes;
}

/** @param array{time:DateTimeImmutable,altitude:float,azimuth:float} $first @param array{time:DateTimeImmutable,altitude:float,azimuth:float} $second @return array{time:DateTimeImmutable,altitude:float,azimuth:float} */
function satelliteStationsHorizonCrossing(array $first, array $second): array
{
    $span = $second['altitude'] - $first['altitude'];
    $fraction = abs($span) > 1.0e-9 ? max(0.0, min(1.0, -$first['altitude'] / $span)) : 0.5;
    $timestamp = (float) $first['time']->format('U.u') + ((float) $second['time']->format('U.u') - (float) $first['time']->format('U.u')) * $fraction;
    $azimuthDelta = fmod($second['azimuth'] - $first['azimuth'] + 540.0, 360.0) - 180.0;
    $azimuth = fmod($first['azimuth'] + $azimuthDelta * $fraction + 360.0, 360.0);
    return ['time' => (new DateTimeImmutable('@' . (string) (int) round($timestamp)))->setTimezone(new DateTimeZone('UTC')), 'altitude' => 0.0, 'azimuth' => $azimuth];
}

/** @param array{float,float,float} $teme @return array{x:float,y:float,z:float} */
function satelliteStationsTemeToEarthFixed(array $teme, DateTimeImmutable $time): array
{
    $theta = satelliteStationsGmstRadians($time);
    return [
        'x' => cos($theta) * $teme[0] + sin($theta) * $teme[1],
        'y' => -sin($theta) * $teme[0] + cos($theta) * $teme[1],
        'z' => $teme[2],
    ];
}

/** @return array{x:float,y:float,z:float} */
function satelliteStationsEquatorialToEarthFixed(float $ra, float $declination, DateTimeImmutable $time): array
{
    $hourAngle = satelliteStationsGmstRadians($time) - deg2rad($ra);
    $declination = deg2rad($declination);
    return ['x' => cos($declination) * cos($hourAngle), 'y' => -cos($declination) * sin($hourAngle), 'z' => sin($declination)];
}

function satelliteStationsGmstRadians(DateTimeImmutable $time): float
{
    $jd = 2440587.5 + (float) $time->setTimezone(new DateTimeZone('UTC'))->format('U.u') / 86400.0;
    $t = ($jd - 2451545.0) / 36525.0;
    $degrees = 280.46061837 + 360.98564736629 * ($jd - 2451545.0) + 0.000387933 * $t ** 2 - $t ** 3 / 38710000.0;
    $radians = fmod(deg2rad($degrees), 2.0 * M_PI);
    return $radians < 0 ? $radians + 2.0 * M_PI : $radians;
}
