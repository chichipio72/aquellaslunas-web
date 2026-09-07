<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\LunarLibrationCalculator;
use AstronomyEngine\MoonDiskAppearanceCalculator;

function moonDiskAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function moonDiskClose(float $actual, float $expected, float $tolerance, string $label): void
{
    moonDiskAssert(abs($actual - $expected) <= $tolerance, sprintf(
        '%s: expected %.9f ± %.4f, got %.9f', $label, $expected, $tolerance, $actual
    ));
}

$observer = new AstronomyObserver(-34.53, -58.48, 'America/Argentina/Buenos_Aires');
$librationCalculator = new LunarLibrationCalculator();
$diskCalculator = new MoonDiskAppearanceCalculator();
$fixtures = [
    // UTC, Horizons NP angle, subobserver lon/lat, subsolar lon/lat, illumination, subsolar PA.
    ['2026-08-17T11:30:00Z', 20.8368, 6.417668, 4.804081, 125.972091, 0.135561, 0.2543412, 293.71],
    ['2026-08-20T11:30:00Z', 12.0755, 3.624361, 6.053847, 89.304273, 0.075834, 0.5375239, 281.69],
    ['2026-08-24T11:30:00Z', -7.0035, -1.712005, 3.700143, 40.497334, -0.011334, 0.8695692, 258.91],
    ['2026-09-05T11:30:00Z', -1.6158, -0.694878, -6.841030, -105.785356, -0.376780, 0.3711492, 90.61],
];

foreach ($fixtures as [$iso, $referenceAxisPosition, $referenceLongitude, $referenceLatitude, $solarLongitude, $solarLatitude, $illumination, $solarPa]) {
    $instant = new DateTimeImmutable($iso);
    $geocentric = $librationCalculator->calculate($instant);
    $disk = $diskCalculator->calculate($instant, $observer);
    moonDiskClose($disk['orientation']['axis_position_angle_degrees'], $referenceAxisPosition, 0.08, "$iso topocentric pole P");
    moonDiskClose($disk['libration']['longitude_degrees'], $referenceLongitude, 0.06, "$iso topocentric longitude");
    moonDiskClose($disk['libration']['latitude_degrees'], $referenceLatitude, 0.06, "$iso topocentric latitude");
    moonDiskClose($disk['surface_geometry']['subsolar']['longitude_degrees'], $solarLongitude, 0.06, "$iso subsolar longitude");
    moonDiskClose($disk['surface_geometry']['subsolar']['latitude_degrees'], $solarLatitude, 0.06, "$iso subsolar latitude");
    moonDiskClose($disk['phase']['illumination_fraction'], $illumination, 0.001, "$iso geometric illumination");
    moonDiskClose($disk['orientation']['subsolar_position_angle_degrees'], $solarPa, 0.08, "$iso subsolar position angle");
    moonDiskClose(
        $disk['surface_geometry']['illumination_fraction_from_surface_geometry'],
        $disk['phase']['illumination_fraction'],
        1e-12,
        "$iso phase must emerge from surface geometry",
    );
    moonDiskClose(
        $disk['orientation']['bright_limb_angle_degrees'],
        $disk['orientation']['bright_limb_legacy_horizontal_degrees'],
        0.001,
        "$iso vector and horizontal bright limb",
    );
    moonDiskAssert($disk['phase']['illumination_fraction'] >= 0.0 && $disk['phase']['illumination_fraction'] <= 1.0, "$iso invalid illumination");
    moonDiskAssert(abs($disk['libration']['longitude_degrees']) < 15.0 && abs($disk['libration']['latitude_degrees']) < 15.0, "$iso invalid libration range");
    moonDiskAssert($disk['orientation']['lunar_north_screen_angle_degrees'] >= -180.0 && $disk['orientation']['lunar_north_screen_angle_degrees'] <= 180.0, "$iso invalid screen orientation");
}

fwrite(STDOUT, "moon disk appearance tests: ok\n");
