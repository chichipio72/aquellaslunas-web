<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/current-datetime.php';
require_once __DIR__ . '/../includes/location-preferences.php';
require_once __DIR__ . '/../pruebas/luna-threejs/astronomy-data.php';

use AstronomyEngine\Facade\AstronomyObserver;

function moonThreeJsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$timezone = 'America/Argentina/Buenos_Aires';
$observer = new AstronomyObserver(-34.53, -58.48, $timezone, 0.0);
$instant = new DateTimeImmutable('2026-08-17T08:30:00-03:00');
$clock = [
    'source' => 'fixed_test',
    'site_context' => $instant,
    'site_context_source' => 'fixed_test',
    'day_offset' => 0,
];
$payload = moonThreeJsAstronomyPayload($instant, $observer, $clock);

moonThreeJsAssert($payload['datetime']['local'] === '2026-08-17T08:30:00-03:00', 'Local timestamp or timezone changed.');
moonThreeJsAssert($payload['datetime']['utc'] === '2026-08-17T11:30:00+00:00', 'UTC conversion changed.');
moonThreeJsAssert($payload['phase']['name'] === 'creciente', 'Expected waxing crescent.');
moonThreeJsAssert($payload['phase']['waxing'] === true, 'Expected waxing=true.');
moonThreeJsAssert(abs($payload['phase']['illumination_fraction'] - 0.25434) < 0.002, 'Expected about 25.43% topocentric illumination.');
moonThreeJsAssert(abs($payload['debug']['cycle_fraction_raw'] - $payload['phase']['cycle_angle_degrees'] / 360.0) < 1e-12, 'Cycle fraction was mixed with illumination.');
moonThreeJsAssert($payload['debug']['illuminated_fraction_raw'] === $payload['phase']['illumination_fraction'], 'Raw illumination was transformed.');

$incidence = deg2rad($payload['phase']['incidence_angle_degrees']);
$renderIllumination = (1.0 + cos($incidence)) / 2.0;
moonThreeJsAssert(abs($renderIllumination - $payload['phase']['illumination_fraction']) < 1e-12, 'Three.js light incidence does not preserve illumination.');

// Reproduce the exact stale-clock symptom that motivated this regression test.
$staleInstant = new DateTimeImmutable('2026-08-01T08:30:00-03:00');
$staleClock = [
    'source' => 'simulation_session',
    'site_context' => $staleInstant,
    'site_context_source' => 'simulation_session',
];
$stalePayload = moonThreeJsAstronomyPayload($staleInstant, $observer, $staleClock);
moonThreeJsAssert($stalePayload['phase']['name'] === 'gibosa menguante', 'Stale-date control phase changed unexpectedly.');
moonThreeJsAssert(abs($stalePayload['phase']['legacy_geocentric_illumination_fraction'] - 0.9199) < 0.002, 'Stale-date control should preserve the original 91.99% diagnosis.');
moonThreeJsAssert(abs($stalePayload['phase']['illumination_fraction'] - 0.9221) < 0.002, 'Stale-date topocentric geometry should produce about 92.21%.');

$moonJs = file_get_contents(__DIR__ . '/../pruebas/luna-threejs/moon.js');
moonThreeJsAssert(is_string($moonJs) && str_contains($moonJs, "clock: 'system', day_offset:"), 'Astronomical dates must explicitly request the real system clock and day offset.');
moonThreeJsAssert(str_contains($moonJs, "parameters.set('local_datetime'"), 'Custom photographic cases must send their exact local datetime.');
moonThreeJsAssert(str_contains($moonJs, 'subsolar.body_fixed_unit_vector'), 'Three.js must light from the explicit subsolar vector.');
moonThreeJsAssert(str_contains($moonJs, 'subobserver.longitude_degrees'), 'Three.js must orient from the explicit subobserver point.');
moonThreeJsAssert(str_contains($moonJs, 'moon.rotation.y = -Math.PI / 2'), 'NASA texture lon=0 must remain centered toward the camera.');

echo "moon-threejs-astronomy: OK\n";
