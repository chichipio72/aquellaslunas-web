<?php

declare(strict_types=1);

require_once __DIR__ . '/api-client.php';

use AstronomyEngine\ApproximatePlanetCalculator;
use AstronomyEngine\ConjunctionCatalog;
use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\MoonDiskAppearanceCalculator;
use AstronomyEngine\LunarDayCalculator;

/** Visibilidad continua del disco según la misma convención de limbo superior usada por LunarDayCalculator. */
function photographyMoonHorizonVisibility(float $geometricCenterAltitude, float $topocentricDistanceKilometers): array
{
    $semidiameter = LunarDayCalculator::apparentSemidiameterDegrees($topocentricDistanceKilometers);
    $refraction = LunarDayCalculator::STANDARD_REFRACTION_DEGREES;
    $apparentCenterAltitude = $geometricCenterAltitude + $refraction;
    $upperLimbAltitude = $apparentCenterAltitude + $semidiameter;
    $lowerLimbAltitude = $apparentCenterAltitude - $semidiameter;
    if ($lowerLimbAltitude >= 0.0) {
        $state = 'fully_visible';
        $fraction = 1.0;
    } elseif ($upperLimbAltitude <= 0.0) {
        $state = 'below_horizon';
        $fraction = 0.0;
    } else {
        $state = 'partially_visible';
        $centerRatio = max(-1.0, min(1.0, $apparentCenterAltitude / $semidiameter));
        $fraction = (acos(-$centerRatio) + $centerRatio * sqrt(max(0.0, 1.0 - $centerRatio * $centerRatio))) / M_PI;
    }
    return [
        'state' => $state,
        'fraction_above_horizon' => max(0.0, min(1.0, $fraction)),
        'geometric_center_altitude_degrees' => $geometricCenterAltitude,
        'apparent_center_altitude_degrees' => $apparentCenterAltitude,
        'upper_limb_altitude_degrees' => $upperLimbAltitude,
        'lower_limb_altitude_degrees' => $lowerLimbAltitude,
        'topocentric_semidiameter_degrees' => $semidiameter,
        'standard_refraction_degrees' => $refraction,
    ];
}

/** Estado físico continuo compartido por los renders Esquema y Simulado. */
function photographyAstronomicalScene(DateTimeImmutable $instant, array $location, array $requestedObjects = [], ?array $eclipseState = null, ?array $eventContext = null): array
{
    $observer = new AstronomyObserver(
        (float) $location['latitude'], (float) $location['longitude'],
        (string) $location['timezone'], (float) ($location['elevation_meters'] ?? 0.0)
    );
    $moonPosition = (new MeeusLunarCalculator())->calculate(
        $instant, $observer->latitudeDegrees, $observer->longitudeDegrees, $observer->elevationMeters
    );
    $sunPosition = (new MeeusSolarPositionCalculator())->calculate(
        $instant, $observer->latitudeDegrees, $observer->longitudeDegrees, $observer->elevationMeters
    );
    $appearance = (new MoonDiskAppearanceCalculator())->calculate($instant, $observer);
    $moonVisibility = photographyMoonHorizonVisibility($moonPosition->altitudeDegrees, $moonPosition->topocentricDistanceKilometers);
    $moonTopocentricSemidiameter = $moonVisibility['topocentric_semidiameter_degrees'];
    $moonDiameter = $moonTopocentricSemidiameter * 2.0;
    $horizonCenterAltitude = LunarDayCalculator::horizonCenterAltitudeDegrees($moonPosition->topocentricDistanceKilometers);
    $moonVisible = $moonVisibility['state'] !== 'below_horizon';
    $effectiveHorizonAltitude = -LunarDayCalculator::STANDARD_REFRACTION_DEGREES;
    $sunDiameter = $sunPosition->apparentRadiusDegrees * 2.0;
    $sunRelative = photographyRelativeCoordinates(
        $moonPosition->altitudeDegrees, $moonPosition->azimuthDegrees,
        $sunPosition->altitudeDegrees, $sunPosition->azimuthDegrees
    );

    $allowed = ['mercury', 'venus', 'mars', 'jupiter', 'saturn', 'aldebaran', 'pollux', 'regulus', 'spica', 'antares'];
    $selected = array_values(array_intersect($allowed, array_unique(array_map('strval', $requestedObjects))));
    $names = ConjunctionCatalog::names();
    $planetCalculator = new ApproximatePlanetCalculator();
    $targets = [];
    foreach (ConjunctionCatalog::targets() as $target) $targets[$target->id] = $target;
    $objects = [];
    foreach ($allowed as $id) {
        $target = $targets[$id];
        $equatorial = $target->kind === 'planet' ? $planetCalculator->target($id, $instant) : $planetCalculator->fixed($target, $instant);
        $horizontal = photographyEquatorialToHorizontal(
            $equatorial->rightAscensionDegrees, $equatorial->declinationDegrees, $instant, $observer
        );
        $relative = photographyRelativeCoordinates(
            $moonPosition->altitudeDegrees, $moonPosition->azimuthDegrees, $horizontal['altitude_degrees'], $horizontal['azimuth_degrees']
        );
        $objects[] = [
            'id' => $id, 'name' => $names[$id], 'kind' => $target->kind,
            'altitude_degrees' => $horizontal['altitude_degrees'], 'azimuth_degrees' => $horizontal['azimuth_degrees'],
            'above_horizon' => $horizontal['altitude_degrees'] > 0.0,
            'separation_from_moon_degrees' => $relative['separation_degrees'],
            'relative_x_degrees' => $relative['x_degrees'], 'relative_y_degrees' => $relative['y_degrees'],
            'selected' => in_array($id, $selected, true),
        ];
    }

    return [
        'schema_version' => 1,
        'instant' => $instant->format(DateTimeInterface::ATOM),
        'location' => ['latitude' => $observer->latitudeDegrees, 'longitude' => $observer->longitudeDegrees, 'elevation_meters' => $observer->elevationMeters, 'timezone' => $observer->timezone->getName()],
        'moon' => [
            'altitude_degrees' => $moonPosition->altitudeDegrees, 'azimuth_degrees' => $moonPosition->azimuthDegrees,
            'above_horizon' => $moonVisible, 'angular_diameter_degrees' => $moonDiameter,
            'visibility_state' => $moonVisibility['state'],
            'fraction_above_horizon' => $moonVisibility['fraction_above_horizon'],
            'visibility_reference' => 'apparent_limb_with_standard_refraction',
            'horizon_center_altitude_degrees' => $horizonCenterAltitude,
            'topocentric_semidiameter_degrees' => $moonTopocentricSemidiameter,
            'apparent_center_altitude_degrees' => $moonVisibility['apparent_center_altitude_degrees'],
            'upper_limb_altitude_degrees' => $moonVisibility['upper_limb_altitude_degrees'],
            'lower_limb_altitude_degrees' => $moonVisibility['lower_limb_altitude_degrees'],
            'phase_name' => $moonPosition->phaseName, 'illumination_fraction' => (float) $appearance['phase']['illumination_fraction'],
            'phase_display_name' => photographyMoonPhaseLabel((float) $appearance['phase']['illumination_fraction'], $moonPosition->cycleAngleDegrees < 180.0),
            'cycle_angle_degrees' => $moonPosition->cycleAngleDegrees,
            'bright_limb_angle_degrees' => (float) $appearance['orientation']['bright_limb_angle_degrees'],
            'lunar_north_screen_angle_degrees' => (float) $appearance['orientation']['lunar_north_screen_angle_degrees'],
            'surface_geometry' => $appearance['surface_geometry'],
            'relative_x_degrees' => 0.0, 'relative_y_degrees' => 0.0,
        ],
        'sun' => [
            'altitude_degrees' => $sunPosition->altitudeDegrees, 'azimuth_degrees' => $sunPosition->azimuthDegrees,
            'above_horizon' => $sunPosition->altitudeDegrees > 0.0, 'angular_diameter_degrees' => $sunDiameter,
            'separation_from_moon_degrees' => $sunRelative['separation_degrees'],
            'relative_x_degrees' => $sunRelative['x_degrees'], 'relative_y_degrees' => $sunRelative['y_degrees'],
        ],
        'eclipse' => $eclipseState ?? ['active' => false, 'type' => null, 'stage' => null, 'source' => 'astronomy_events'],
        'objects' => $objects,
        'horizon' => [
            'relative_y_degrees' => $moonPosition->altitudeDegrees - $effectiveHorizonAltitude,
            'effective_geometric_altitude_degrees' => $effectiveHorizonAltitude,
            'standard_refraction_degrees' => LunarDayCalculator::STANDARD_REFRACTION_DEGREES,
            'moon_clearance_degrees' => $moonPosition->altitudeDegrees - $effectiveHorizonAltitude,
        ],
    ];
}

function photographyMoonPhaseLabel(float $illuminationFraction, bool $waxing): string
{
    $illumination = max(0.0, min(1.0, $illuminationFraction));
    if ($illumination <= 0.005) return 'Luna nueva';
    if ($illumination >= 0.995) return 'Luna llena';
    if ($waxing) {
        if ($illumination < 0.05) return 'Creciente muy fina';
        if ($illumination < 0.45) return 'Creciente';
        if ($illumination <= 0.55) return 'Cuarto creciente';
        return 'Gibosa creciente';
    }
    if ($illumination < 0.05) return 'Menguante muy fina';
    if ($illumination < 0.45) return 'Menguante';
    if ($illumination <= 0.55) return 'Cuarto menguante';
    return 'Gibosa menguante';
}

function photographySceneClassification(array $state, bool $includeHorizon): array
{
    $selected = array_values(array_filter($state['objects'], static fn(array $object): bool => $object['selected']));
    $visibilityState = (string) ($state['moon']['visibility_state'] ?? (($state['moon']['above_horizon'] ?? false) ? 'fully_visible' : 'below_horizon'));
    $labels = [$visibilityState === 'below_horizon' ? 'Luna bajo el horizonte' : ($visibilityState === 'partially_visible' ? 'Luna parcialmente visible' : 'Luna visible')];
    $moonAltitude = (float) $state['moon']['altitude_degrees'];
    $sunAltitude = (float) $state['sun']['altitude_degrees'];
    if ($moonAltitude >= 45.0) $labels[] = 'Luna alta';
    elseif ($moonAltitude >= 15.0) $labels[] = 'Luna a media altura';
    elseif ($moonAltitude >= 5.0) $labels[] = 'Luna baja';
    elseif ($moonAltitude >= 0.0) $labels[] = 'Luna muy baja';
    if ($sunAltitude >= 0.0) $labels[] = 'Día';
    elseif ($sunAltitude >= -12.0) $labels[] = 'Crepúsculo';
    else $labels[] = 'Noche';
    if ((float) $state['moon']['illumination_fraction'] < 0.10) $labels[] = 'Luna fina';
    if ($includeHorizon) $labels[] = 'Con horizonte';
    foreach ($selected as $object) $labels[] = 'Luna y ' . $object['name'];
    if (array_filter($selected, static fn(array $object): bool => (float) $object['separation_from_moon_degrees'] <= 10.0) !== []) $labels[] = 'Conjunción';
    if (($state['eclipse']['active'] ?? false) === true) $labels[] = 'Eclipse ' . photographyEclipseClassificationLabel((string) ($state['eclipse']['classification'] ?? ''));
    $primary = ($state['eclipse']['active'] ?? false) === true ? 'eclipse' : ($selected !== [] ? 'moon_with_objects' : ($includeHorizon ? 'moon_with_horizon' : 'moon_alone'));
    $primaryLabel = ($state['eclipse']['active'] ?? false) === true ? 'Eclipse' : ($selected !== [] ? 'Luna + ' . implode(' + ', array_column($selected, 'name')) : ($includeHorizon ? 'Luna + horizonte' : 'Luna sola'));
    $conditions = [];
    if (!$state['moon']['above_horizon']) $conditions[] = 'La Luna está bajo el horizonte y no es visible desde esta ubicación.';
    foreach ($selected as $object) {
        if (!$object['above_horizon']) $conditions[] = $object['name'] . ' está bajo el horizonte.';
        elseif ((float) $object['altitude_degrees'] < 5.0) $conditions[] = $object['name'] . ' está muy bajo, a ' . number_format((float) $object['altitude_degrees'], 1, ',', '.') . '° del horizonte.';
        elseif ((float) $object['altitude_degrees'] < 12.0) $conditions[] = $object['name'] . ' está próximo al horizonte, a ' . number_format((float) $object['altitude_degrees'], 1, ',', '.') . '°.';
        $vertical = (float) $object['relative_y_degrees'];
        if (abs($vertical) >= 0.1) $conditions[] = $object['name'] . ' está ' . ($vertical > 0 ? 'debajo' : 'encima') . ' de la Luna.';
    }
    if ($state['sun']['altitude_degrees'] > 0) $conditions[] = 'El Sol está sobre el horizonte; considerá el brillo del cielo y la seguridad solar.';
    if (($state['eclipse']['active'] ?? false) === true && is_string($state['eclipse']['stage'] ?? null)) $conditions[] = 'Etapa del eclipse: ' . $state['eclipse']['stage'] . '.';
    return ['labels' => array_values(array_unique(array_filter($labels))), 'primary_scene' => $primary, 'primary_scene_label' => $primaryLabel, 'conditions' => $conditions, 'available_variants' => []];
}

/** @return array{show_horizon:bool,objects:list<array<string,mixed>>} */
function photographyElementCandidates(array $state, bool $includeHorizon): array
{
    $moonAltitude = is_numeric($state['moon']['altitude_degrees'] ?? null) ? (float) $state['moon']['altitude_degrees'] : INF;
    $objects = array_values(array_filter($state['objects'] ?? [], static function (mixed $object): bool {
        if (!is_array($object) || !in_array($object['kind'] ?? null, ['planet', 'star'], true)) return false;
        if (($object['selected'] ?? false) === true) return true;
        return is_numeric($object['separation_from_moon_degrees'] ?? null)
            && (float) $object['separation_from_moon_degrees'] <= 10.0;
    }));
    usort($objects, static fn(array $first, array $second): int => [
        ($first['kind'] ?? '') === 'planet' ? 0 : 1,
        (float) ($first['separation_from_moon_degrees'] ?? INF),
    ] <=> [
        ($second['kind'] ?? '') === 'planet' ? 0 : 1,
        (float) ($second['separation_from_moon_degrees'] ?? INF),
    ]);
    return ['show_horizon' => $includeHorizon || $moonAltitude <= 20.0, 'objects' => $objects];
}

function photographyRelativeCoordinates(float $originAltitude, float $originAzimuth, float $altitude, float $azimuth): array
{
    $ma = deg2rad($originAltitude); $mz = deg2rad($originAzimuth); $ta = deg2rad($altitude); $tz = deg2rad($azimuth);
    $origin = [cos($ma) * sin($mz), cos($ma) * cos($mz), sin($ma)];
    $target = [cos($ta) * sin($tz), cos($ta) * cos($tz), sin($ta)];
    $east = [cos($mz), -sin($mz), 0.0]; $up = [-sin($ma) * sin($mz), -sin($ma) * cos($mz), cos($ma)];
    $dot = static fn(array $a, array $b): float => $a[0] * $b[0] + $a[1] * $b[1] + $a[2] * $b[2];
    $separation = acos(max(-1.0, min(1.0, $dot($origin, $target))));
    $position = atan2($dot($target, $east), $dot($target, $up));
    return ['separation_degrees' => rad2deg($separation), 'x_degrees' => rad2deg($separation) * sin($position), 'y_degrees' => -rad2deg($separation) * cos($position)];
}

function photographyEquatorialToHorizontal(float $ra, float $dec, DateTimeImmutable $instant, AstronomyObserver $observer): array
{
    $jd = 2440587.5 + $instant->getTimestamp() / 86400.0; $t = ($jd - 2451545.0) / 36525.0;
    $sidereal = fmod(280.46061837 + 360.98564736629 * ($jd - 2451545.0) + .000387933 * $t * $t - $t ** 3 / 38710000.0, 360.0);
    $hour = deg2rad(fmod($sidereal + $observer->longitudeDegrees - $ra + 540.0, 360.0) - 180.0);
    $lat = deg2rad($observer->latitudeDegrees); $declination = deg2rad($dec);
    $altitude = asin(sin($lat) * sin($declination) + cos($lat) * cos($declination) * cos($hour));
    $azimuth = atan2(sin($hour), cos($hour) * sin($lat) - tan($declination) * cos($lat));
    return ['altitude_degrees' => rad2deg($altitude), 'azimuth_degrees' => fmod(rad2deg($azimuth) + 540.0, 360.0)];
}

function photographyEclipseStateFromEvents(array $events, DateTimeImmutable $instant): array
{
    $timestamp = $instant->getTimestamp();
    foreach ($events as $event) {
        if (!is_array($event) || ($event['type'] ?? null) !== 'eclipse') continue;
        $solar = ($event['subtype'] ?? '') === 'solar_eclipse';
        $details = is_array($event['details'] ?? null) ? $event['details'] : [];
        $global = is_array($details[$solar ? 'solar_eclipse_global' : 'eclipse_global'] ?? null) ? $details[$solar ? 'solar_eclipse_global' : 'eclipse_global'] : [];
        $local = is_array($details[$solar ? 'solar_eclipse_local' : 'eclipse_local'] ?? null) ? $details[$solar ? 'solar_eclipse_local' : 'eclipse_local'] : [];
        $visibility = strtolower((string) ($local['visibility_classification'] ?? ''));
        if ($visibility === 'not_visible') continue;
        $contacts = [];
        foreach (($local['contacts'] ?? []) as $contact) {
            if (!is_array($contact) || !is_string($contact['datetime'] ?? null)) continue;
            try { $date = new DateTimeImmutable($contact['datetime']); } catch (Throwable) { continue; }
            $contacts[] = ['code' => strtoupper((string) ($contact['code'] ?? '')), 'timestamp' => $date->getTimestamp()];
        }
        usort($contacts, static fn(array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);
        $first = $contacts[0]['timestamp'] ?? null; $last = $contacts[array_key_last($contacts)]['timestamp'] ?? null;
        if ($first === null || $last === null || $timestamp < $first || $timestamp > $last) continue;
        $stage = 'en curso';
        foreach ($contacts as $contact) if ($contact['timestamp'] <= $timestamp) $stage = photographyEclipseStageLabel($contact['code'], $solar);
        $localClassification = preg_replace('/^visible_/', '', $visibility);
        return ['active' => true, 'type' => $solar ? 'solar' : 'lunar', 'classification' => $localClassification !== '' ? $localClassification : strtolower((string) ($global['global_type'] ?? '')), 'global_classification' => strtolower((string) ($global['global_type'] ?? '')), 'stage' => $stage, 'visibility_classification' => $visibility, 'magnitude' => $local['max_magnitude'] ?? ($global['magnitudes']['umbral'] ?? null), 'source' => 'astronomy_events'];
    }
    return ['active' => false, 'type' => null, 'stage' => null, 'source' => 'astronomy_events'];
}

function photographyEclipseStageLabel(string $code, bool $solar): string
{
    return match ($code) {
        'P1' => $solar ? 'fase parcial inicial' : 'fase penumbral inicial', 'U1' => 'fase parcial inicial',
        'U2', 'C2' => 'totalidad', 'MAX' => 'máximo', 'U3', 'C3' => 'fase parcial final',
        'U4' => 'fase penumbral final', 'C1' => 'fase parcial inicial', default => 'en curso',
    };
}

function photographyEclipseClassificationLabel(string $classification): string
{
    return match (strtolower(trim($classification))) {
        'penumbral', 'penumbral_only' => 'penumbral', 'partial' => 'parcial', 'total' => 'total',
        'annular' => 'anular', 'hybrid' => 'híbrido', default => 'en curso',
    };
}
