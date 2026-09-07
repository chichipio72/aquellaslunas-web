<?php

declare(strict_types=1);

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MoonDiskAppearanceCalculator;

/** @return array{instant:DateTimeImmutable,source:string,site_context:DateTimeImmutable,site_context_source:string} */
function moonThreeJsResolveClock(string $timezone, string $clockMode): array
{
    $siteContext = get_current_datetime($timezone);
    $sessionWallTime = astronomySimulatedLocalWallTime();
    $siteSource = $sessionWallTime !== null
        ? 'simulation_session'
        : (astronomyDebugClockValue() !== null ? 'debug_now' : 'system_now');

    if ($clockMode === 'system') {
        return [
            'instant' => new DateTimeImmutable('now', new DateTimeZone($timezone)),
            'source' => 'system_now',
            'site_context' => $siteContext,
            'site_context_source' => $siteSource,
        ];
    }

    return [
        'instant' => $siteContext,
        'source' => $siteSource,
        'site_context' => $siteContext,
        'site_context_source' => $siteSource,
    ];
}

/** @return array<string,mixed> */
function moonThreeJsAstronomyPayload(
    DateTimeImmutable $instant,
    AstronomyObserver $observer,
    array $clock,
    string $locationName = 'Buenos Aires / CABA',
): array
{
    $geometry = (new MoonDiskAppearanceCalculator())->calculate($instant, $observer);
    $cycleFraction = $geometry['phase']['cycle_angle_degrees'] / 360.0;

    return [
        'datetime' => [
            'local' => $instant->format(DATE_ATOM),
            'utc' => $instant->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'timezone' => $observer->timezone->getName(),
            'simulated' => $clock['source'] !== 'system_now',
            'source' => $clock['source'],
        ],
        'location' => [
            'name' => $locationName,
            'latitude' => $observer->latitudeDegrees,
            'longitude' => $observer->longitudeDegrees,
            'elevation_meters' => $observer->elevationMeters,
        ],
        ...$geometry,
        'feature_audit' => moonThreeJsFeatureAudit($geometry['surface_geometry']),
        'debug' => [
            'clock_source' => $clock['source'],
            'site_context_source' => $clock['site_context_source'],
            'site_context_local' => $clock['site_context']->format(DATE_ATOM),
            'day_offset' => (int) ($clock['day_offset'] ?? 0),
            'cycle_fraction_raw' => $cycleFraction,
            'illuminated_fraction_raw' => $geometry['phase']['illumination_fraction'],
            'phase_name_calculated' => $geometry['phase']['name'],
            'waxing_calculated' => $geometry['phase']['waxing'],
        ],
        'metadata' => [
            'calculation_model' => 'meeus-portable-lunar-disk-v1',
            'refraction' => 'none',
            'cache_policy' => 'private, no-store, no-cache, must-revalidate, max-age=0',
        ],
    ];
}

/** @param array<string,mixed> $surfaceGeometry @return array<int,array<string,mixed>> */
function moonThreeJsFeatureAudit(array $surfaceGeometry): array
{
    // Centros IAU/USGS, longitudes positivas al este. Sobre la esfera de esta
    // prueba, latitud planetográfica y planetocéntrica son equivalentes.
    $features = [
        ['name' => 'Taruntius', 'type' => 'cráter', 'longitude_degrees' => 46.54, 'latitude_degrees' => 5.50],
        ['name' => 'Mare Fecunditatis', 'type' => 'mar', 'longitude_degrees' => 53.67, 'latitude_degrees' => -7.83],
        ['name' => 'Langrenus', 'type' => 'cráter', 'longitude_degrees' => 61.04, 'latitude_degrees' => -8.86],
    ];
    $observer = $surfaceGeometry['subobserver']['body_fixed_unit_vector'];
    $sun = $surfaceGeometry['subsolar']['body_fixed_unit_vector'];

    foreach ($features as &$feature) {
        $point = moonThreeJsBodyVector($feature['longitude_degrees'], $feature['latitude_degrees']);
        $observerDot = moonThreeJsDot($point, $observer);
        $solarDot = moonThreeJsDot($point, $sun);
        $feature['visible'] = $observerDot > 0.0;
        $feature['illuminated_on_reference_sphere'] = $solarDot > 0.0;
        $feature['emission_angle_degrees'] = rad2deg(acos(max(-1.0, min(1.0, $observerDot))));
        $feature['solar_incidence_angle_degrees'] = rad2deg(acos(max(-1.0, min(1.0, $solarDot))));
        $feature['signed_distance_from_terminator_degrees'] = 90.0 - $feature['solar_incidence_angle_degrees'];
    }
    unset($feature);

    return $features;
}

/** @return array{x:float,y:float,z:float} */
function moonThreeJsBodyVector(float $longitude, float $latitude): array
{
    $lon = deg2rad($longitude);
    $lat = deg2rad($latitude);
    return ['x' => cos($lat) * sin($lon), 'y' => sin($lat), 'z' => cos($lat) * cos($lon)];
}

/** @param array{x:float,y:float,z:float} $a @param array{x:float,y:float,z:float} $b */
function moonThreeJsDot(array $a, array $b): float
{
    return $a['x'] * $b['x'] + $a['y'] * $b['y'] + $a['z'] * $b['z'];
}
