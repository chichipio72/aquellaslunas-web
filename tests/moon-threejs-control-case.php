<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/current-datetime.php';
require_once __DIR__ . '/../includes/location-preferences.php';
require_once __DIR__ . '/../pruebas/luna-threejs/astronomy-data.php';

use AstronomyEngine\Facade\AstronomyObserver;

function controlCaseClose(float $actual, float $expected, float $tolerance, string $label): void
{
    if (abs($actual - $expected) > $tolerance) {
        throw new RuntimeException(sprintf('%s: expected %.9f ± %.4f, got %.9f', $label, $expected, $tolerance, $actual));
    }
}

$instant = new DateTimeImmutable('2026-08-16T19:01:00-03:00');
$observer = new AstronomyObserver(
    -34.532989356165835,
    -58.537805002828605,
    'America/Argentina/Buenos_Aires',
);
$clock = [
    'source' => 'custom_local_datetime',
    'site_context' => $instant,
    'site_context_source' => 'fixed_test',
    'day_offset' => 0,
];
$payload = moonThreeJsAstronomyPayload($instant, $observer, $clock, 'Carapachay, Provincia de Buenos Aires');

// NASA/JPL Horizons: target 301, coord@399, quantities 4,10,14,15,16,17.
controlCaseClose($payload['phase']['illumination_fraction'], 0.1983387, 0.001, 'illumination');
controlCaseClose($payload['surface_geometry']['subobserver']['longitude_degrees'], 5.563651, 0.06, 'subobserver longitude');
controlCaseClose($payload['surface_geometry']['subobserver']['latitude_degrees'], 4.866258, 0.06, 'subobserver latitude');
controlCaseClose($payload['surface_geometry']['subsolar']['longitude_degrees'], 132.844759, 0.06, 'subsolar longitude');
controlCaseClose($payload['surface_geometry']['subsolar']['latitude_degrees'], 0.147355, 0.06, 'subsolar latitude');
controlCaseClose($payload['orientation']['axis_position_angle_degrees'], 21.7065, 0.08, 'north pole position angle');
controlCaseClose($payload['orientation']['subsolar_position_angle_degrees'], 295.59, 0.08, 'subsolar position angle');
controlCaseClose($payload['observer']['moon_altitude_degrees'], 43.737224, 0.02, 'Moon altitude');
controlCaseClose($payload['observer']['moon_azimuth_degrees'], 291.501522, 0.02, 'Moon azimuth');

$features = array_column($payload['feature_audit'], null, 'name');
controlCaseClose($features['Taruntius']['signed_distance_from_terminator_degrees'], 3.67533, 0.001, 'Taruntius terminator distance');
controlCaseClose($features['Mare Fecunditatis']['signed_distance_from_terminator_degrees'], 10.69002, 0.001, 'Mare Fecunditatis terminator distance');
controlCaseClose($features['Langrenus']['signed_distance_from_terminator_degrees'], 17.93461, 0.001, 'Langrenus terminator distance');

echo "moon-threejs-control-case: OK\n";
