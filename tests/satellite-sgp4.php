<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\SatelliteTopocentricCalculator;
use AstronomyEngine\Satellite\Sgp4\Sgp4Propagator;
use AstronomyEngine\Satellite\TleParser;

function satelliteAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function satelliteUtc(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $value, new DateTimeZone('UTC'));
    if (!$date) throw new RuntimeException('Invalid fixture timestamp: ' . $value);
    return $date;
}

function satelliteVectorMaximum(array $actual, array $expected): float
{
    return max(abs($actual[0] - $expected[0]), abs($actual[1] - $expected[1]), abs($actual[2] - $expected[2]));
}

$fixturePath = __DIR__ . '/fixtures/satellite/satellite-sgp4-reference.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true, 64, JSON_THROW_ON_ERROR);
satelliteAssert($fixture['conventions']['gravity_model'] === 'WGS72', 'The reference fixture does not use WGS72.');
$observerData = $fixture['observer'];
$observer = new AstronomyObserver(
    (float) $observerData['latitude_degrees'],
    (float) $observerData['longitude_degrees'],
    'UTC',
    (float) $observerData['elevation_m'],
);
$parser = new TleParser();
$topocentric = new SatelliteTopocentricCalculator();
$maximums = ['position_km' => 0.0, 'velocity_km_s' => 0.0, 'geocentric_km' => 0.0,
    'altitude_degrees' => 0.0, 'azimuth_degrees' => 0.0, 'observer_distance_km' => 0.0];
$cases = 0;

foreach ($fixture['satellites'] as $satellite) {
    $tlePath = __DIR__ . '/fixtures/satellite/' . $satellite['id'] . '.tle';
    $tleLines = file($tlePath, FILE_IGNORE_NEW_LINES);
    satelliteAssert(is_array($tleLines) && count($tleLines) === 3, 'Invalid local TLE fixture for ' . $satellite['id']);
    satelliteAssert($tleLines === [$satellite['tle']['name'], $satellite['tle']['line1'], $satellite['tle']['line2']],
        'The local TLE does not match the JSON fixture for ' . $satellite['id']);
    $tle = $parser->parse($tleLines[0], $tleLines[1], $tleLines[2]);
    satelliteAssert(abs((float) $tle->epochUtc->format('U.u') - (float) satelliteUtc($satellite['epoch_utc'])->format('U.u')) < 0.0001,
        'Parsed TLE epoch differs from the reference for ' . $satellite['id']);
    $propagator = new Sgp4Propagator($tle);
    foreach ($satellite['states'] as $expected) {
        $state = $propagator->propagate(satelliteUtc($expected['timestamp_utc']));
        $look = $topocentric->calculate($state, $observer);
        $maximums['position_km'] = max($maximums['position_km'], satelliteVectorMaximum($state->positionKilometers, $expected['teme_position_km']));
        $maximums['velocity_km_s'] = max($maximums['velocity_km_s'], satelliteVectorMaximum($state->velocityKilometersPerSecond, $expected['teme_velocity_km_s']));
        $maximums['geocentric_km'] = max($maximums['geocentric_km'], abs($state->geocentricDistanceKilometers() - $expected['geocentric_distance_km']));
        $maximums['altitude_degrees'] = max($maximums['altitude_degrees'], abs($look->altitudeDegrees - $expected['topocentric_altitude_degrees']));
        $azimuthDifference = abs($look->azimuthDegrees - $expected['topocentric_azimuth_degrees']);
        $maximums['azimuth_degrees'] = max($maximums['azimuth_degrees'], min($azimuthDifference, 360.0 - $azimuthDifference));
        $maximums['observer_distance_km'] = max($maximums['observer_distance_km'], abs($look->distanceKilometers - $expected['observer_distance_km']));
        $cases++;
    }
}

satelliteAssert($cases === 4, 'Not all reference cases were evaluated.');
satelliteAssert($maximums['position_km'] < 0.00001, 'TEME position exceeds the 1 cm tolerance.');
satelliteAssert($maximums['velocity_km_s'] < 0.00000001, 'TEME velocity exceeds the 0.01 mm/s tolerance.');
satelliteAssert($maximums['geocentric_km'] < 0.00001, 'Geocentric distance exceeds the 1 cm tolerance.');
// Skyfield applies its bundled UT1 estimate; the standalone PHP transform uses UTC with zero polar motion.
satelliteAssert($maximums['altitude_degrees'] < 0.001, 'Topocentric altitude exceeds tolerance.');
satelliteAssert($maximums['azimuth_degrees'] < 0.001, 'Topocentric azimuth exceeds tolerance.');
satelliteAssert($maximums['observer_distance_km'] < 0.05, 'Observer distance exceeds the 50 m tolerance.');

try {
    $invalid = $fixture['satellites'][0]['tle']['line1'];
    $invalid[68] = $invalid[68] === '0' ? '1' : '0';
    $parser->parse('invalid', $invalid, $fixture['satellites'][0]['tle']['line2']);
    throw new RuntimeException('An invalid TLE checksum was accepted.');
} catch (InvalidArgumentException) {
}

echo json_encode(['status' => 'OK', 'cases' => $cases, 'maximum_differences' => $maximums],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
