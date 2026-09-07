<?php

declare(strict_types=1);

require_once __DIR__ . '/api-client.php';
require_once __DIR__ . '/location-context.php';
require_once __DIR__ . '/current-datetime.php';
require_once __DIR__ . '/moon-three-render.php';
require_once __DIR__ . '/interactive-moon.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\LunarEclipse;
use AstronomyEngine\LunarEclipseCalculator;
use AstronomyEngine\MoonDiskAppearanceCalculator;
use AstronomyEngine\PrincipalPhaseCalculator;

function lunarEmbedBoolean(mixed $value, bool $default): bool
{
    if ($value === null) return $default;
    return in_array((string) $value, ['1', 'true', 'on'], true);
}

function lunarEmbedFloat(mixed $value, float $default, float $minimum, float $maximum): float
{
    if (!is_numeric($value)) return $default;
    return max($minimum, min($maximum, (float) $value));
}

function lunarEmbedColor(mixed $value, string $default): string
{
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : $default;
}

/** @return array{illumination:string,autoplay:bool,speed:float,zoom:float,controls:bool} */
function lunarLibrationEmbedOptions(array $query): array
{
    return [
        'illumination' => ($query['mode'] ?? '') === 'full' ? 'full' : 'realistic',
        'autoplay' => lunarEmbedBoolean($query['autoplay'] ?? null, true),
        'speed' => lunarEmbedFloat($query['speed'] ?? null, 1.0, 0.25, 8.0),
        'zoom' => lunarEmbedFloat($query['zoom'] ?? null, 1.0, 0.65, 1.35),
        'controls' => lunarEmbedBoolean($query['controls'] ?? null, true),
    ];
}

/** @return array<string,mixed> */
function lunarInteractiveEmbedOptions(array $query): array
{
    $base = interactiveMoonOptions($query);
    return $base + [
        'rotation' => ($query['rotation'] ?? '') === 'locked' ? 'locked' : 'free',
        'controls' => lunarEmbedBoolean($query['controls'] ?? null, true),
        'yaw' => lunarEmbedFloat($query['yaw'] ?? null, 0.0, -180.0, 180.0),
        'pitch' => lunarEmbedFloat($query['pitch'] ?? null, 0.0, -75.0, 75.0),
        'zoom' => lunarEmbedFloat($query['zoom'] ?? null, 1.0, 0.8, 1.7),
    ];
}

/** @return array<string,float|bool> */
function earthMoonEmbedOptions(array $query): array
{
    return [
        'autoplay' => lunarEmbedBoolean($query['autoplay'] ?? null, false),
        'speed' => lunarEmbedFloat($query['speed'] ?? null, 1.0, 0.25, 8.0),
        'controls' => lunarEmbedBoolean($query['controls'] ?? null, true),
        'moon_sun' => lunarEmbedFloat($query['moon_sun'] ?? null, 3.2, 0.0, 8.0),
        'moon_ambient' => lunarEmbedFloat($query['moon_ambient'] ?? null, 0.01, 0.0, 1.0),
        'moon_exposure' => lunarEmbedFloat($query['moon_exposure'] ?? null, 1.15, 0.5, 2.0),
        'moon_roughness' => lunarEmbedFloat($query['moon_roughness'] ?? null, 1.0, 0.0, 1.0),
        'moon_normal_x' => lunarEmbedFloat($query['moon_normal_x'] ?? null, 1.0, -4.0, 4.0),
        'moon_normal_y' => lunarEmbedFloat($query['moon_normal_y'] ?? null, 1.0, -4.0, 4.0),
        'earth_sun' => lunarEmbedFloat($query['earth_sun'] ?? null, 2.7, 0.0, 8.0),
        'earth_ambient' => lunarEmbedFloat($query['earth_ambient'] ?? null, 0.035, 0.0, 1.0),
        'earth_exposure' => lunarEmbedFloat($query['earth_exposure'] ?? null, 1.05, 0.5, 2.0),
        'earth_roughness' => lunarEmbedFloat($query['earth_roughness'] ?? null, 1.0, 0.0, 1.0),
    ];
}

/** @return array<string,float|bool|string> */
function lunarEclipseEmbedOptions(array $query): array
{
    return [
        'autoplay' => lunarEmbedBoolean($query['autoplay'] ?? null, false),
        'speed' => lunarEmbedFloat($query['speed'] ?? null, 1.0, 0.25, 8.0),
        'controls' => lunarEmbedBoolean($query['controls'] ?? null, true),
        'geometry' => lunarEmbedBoolean($query['geometry'] ?? null, true),
        'sun_intensity' => lunarEmbedFloat($query['sun_intensity'] ?? null, 3.2, 0.0, 8.0),
        'ambient_intensity' => lunarEmbedFloat($query['ambient_intensity'] ?? null, 0.01, 0.0, 1.0),
        'exposure' => lunarEmbedFloat($query['exposure'] ?? null, 1.15, 0.5, 2.0),
        'penumbra_darkness' => lunarEmbedFloat($query['penumbra_darkness'] ?? null, 0.18, 0.0, 1.0),
        'umbra_darkness' => lunarEmbedFloat($query['umbra_darkness'] ?? null, 0.955, 0.0, 1.0),
        'copper_intensity' => lunarEmbedFloat($query['copper_intensity'] ?? null, 0.26, 0.0, 1.0),
        'copper_color' => lunarEmbedColor($query['copper_color'] ?? null, '#c95f32'),
    ];
}

/** @return array<string,mixed> */
function lunarEclipseEmbedPayload(AstronomyObserver $observer, array $options): array
{
    $rangeStart = new DateTimeImmutable('2026-03-01 00:00:00', new DateTimeZone('UTC'));
    $rangeEnd = new DateTimeImmutable('2026-03-06 00:00:00', new DateTimeZone('UTC'));
    $events = (new LunarEclipseCalculator())->events($rangeStart, $rangeEnd);
    $eclipse = $events[0] ?? null;
    if (!$eclipse instanceof LunarEclipse || $eclipse->classification !== 'total') {
        throw new RuntimeException('No se pudo calcular el eclipse lunar total de referencia.');
    }

    $configuration = moonThreeRenderConfigurationLoad();
    $payload = moonThreeRenderPayload($eclipse->maximum, $observer, $configuration);
    unset($payload['wallpaper']);

    $moonRadius = (float) $eclipse->maximumGeometry['moon_radius_radians'];
    $contacts = [];
    foreach ($eclipse->contacts->all() as $code => $instant) {
        $contacts[$code] = $instant?->format(DateTimeInterface::ATOM);
    }
    $timelineStart = $eclipse->contacts->P1->modify('-30 minutes');
    $timelineEnd = $eclipse->contacts->P4->modify('+30 minutes');
    $payload['eclipse'] = [
        'title' => 'Eclipse lunar total del 3 de marzo de 2026',
        'classification' => $eclipse->classification,
        'calculation_model' => $eclipse->calculationModel,
        'maximum' => $eclipse->maximum->format(DateTimeInterface::ATOM),
        'timeline_start' => $timelineStart->format(DateTimeInterface::ATOM),
        'timeline_end' => $timelineEnd->format(DateTimeInterface::ATOM),
        'contacts' => $contacts,
        'magnitudes' => $eclipse->magnitudes,
        'shadow' => [
            'closest_approach_moon_radii' => (float) $eclipse->maximumGeometry['closest_approach_radians'] / $moonRadius,
            'umbra_radius_moon_radii' => (float) $eclipse->maximumGeometry['umbra_radius_radians'] / $moonRadius,
            'penumbra_radius_moon_radii' => (float) $eclipse->maximumGeometry['penumbra_radius_radians'] / $moonRadius,
        ],
        'animation' => $options + ['duration_seconds' => 32.0],
    ];
    return $payload;
}

function lunarEmbedObserver(array $location): AstronomyObserver
{
    return new AstronomyObserver(
        (float) $location['latitude'],
        (float) $location['longitude'],
        (string) $location['timezone'],
        (float) ($location['elevation_meters'] ?? 0.0),
    );
}

/** @return array{start:DateTimeImmutable,end:DateTimeImmutable} */
function lunarLibrationCycleBounds(DateTimeImmutable $instant): array
{
    $calculator = new PrincipalPhaseCalculator();
    $events = $calculator->between($instant->modify('-45 days'), $instant->modify('+45 days'));
    $previous = null;
    $next = null;
    foreach ($events as $event) {
        if (($event['type'] ?? null) !== 'new_moon' || !$event['dateTime'] instanceof DateTimeImmutable) continue;
        if ($event['dateTime'] <= $instant) $previous = $event['dateTime'];
        if ($event['dateTime'] > $instant && $next === null) $next = $event['dateTime'];
    }
    if (!$previous instanceof DateTimeImmutable || !$next instanceof DateTimeImmutable) {
        throw new RuntimeException('No se pudieron resolver las lunas nuevas que delimitan la animación.');
    }
    return ['start' => $previous, 'end' => $next];
}

/** @return array<string,mixed> */
function lunarLibrationEmbedPayload(DateTimeImmutable $instant, AstronomyObserver $observer, array $options, ?array $configuration = null): array
{
    $configuration ??= favoriteMoonThreeRenderConfigurationLoad();
    $payload = moonThreeRenderPayload($instant, $observer, $configuration, 'favorite.moon_three.');
    $calculator = new MoonDiskAppearanceCalculator();
    $cycle = lunarLibrationCycleBounds($instant);
    $cycleStart = $cycle['start'];
    $cycleEnd = $cycle['end'];
    $durationSeconds = $cycleEnd->getTimestamp() - $cycleStart->getTimestamp();
    $samples = [];
    $sampleCount = 121;
    $cycleStartGeometry = $calculator->calculate($cycleStart, $observer);
    $fixedCelestialNorthScreenAngle = (float) $cycleStartGeometry['orientation']['celestial_north_screen_angle_degrees'];
    $signedAngle = static function (float $angle): float {
        $normalized = fmod($angle + 180.0, 360.0);
        if ($normalized < 0.0) $normalized += 360.0;
        return $normalized - 180.0;
    };
    for ($index = 0; $index < $sampleCount; $index++) {
        $sampleInstant = $cycleStart->modify('+' . (string) (int) round($durationSeconds * $index / ($sampleCount - 1)) . ' seconds');
        $geometry = $calculator->calculate($sampleInstant, $observer);
        $samples[] = [
            'progress' => $index / ($sampleCount - 1),
            'datetime' => $sampleInstant->format(DateTimeInterface::ATOM),
            'longitude' => (float) $geometry['surface_geometry']['subobserver']['longitude_degrees'],
            'latitude' => (float) $geometry['surface_geometry']['subobserver']['latitude_degrees'],
            // Conserva la orientación local inicial sin comprimir en segundos la
            // rotación diaria del campo; sólo evoluciona el eje lunar calculado.
            'disk_angle' => $signedAngle(
                $fixedCelestialNorthScreenAngle - (float) $geometry['orientation']['axis_position_angle_degrees'],
            ),
            'sun' => $geometry['surface_geometry']['subsolar']['body_fixed_unit_vector'],
            'illumination' => (float) $geometry['phase']['illumination_fraction'],
            'phase' => (string) $geometry['phase']['name'],
        ];
    }
    unset($payload['geometry']);
    $payload['samples'] = $samples;
    $payload['animation'] = $options + [
        'cycle_seconds' => 30.0,
        'loop_blend_fraction' => 0.02,
        'period' => 'luna nueva a luna nueva',
        'cycle_start' => $cycleStart->format(DateTimeInterface::ATOM),
        'cycle_end' => $cycleEnd->format(DateTimeInterface::ATOM),
        'sampling' => '121 muestras entre lunas nuevas calculadas; referencia topocéntrica local inicial fija, eje lunar variable, tiempo siempre hacia adelante y blend visual corto de cierre',
    ];
    return $payload;
}

/** @return array<string,mixed> */
function earthMoonEmbedPayload(DateTimeImmutable $instant, AstronomyObserver $observer, array $options): array
{
    $configuration = moonThreeRenderConfigurationLoad();
    $payload = moonThreeRenderPayload($instant, $observer, $configuration);
    $appearanceCalculator = new MoonDiskAppearanceCalculator();
    $lunarCalculator = new MeeusLunarCalculator();
    $cycle = lunarLibrationCycleBounds($instant);
    $durationSeconds = $cycle['end']->getTimestamp() - $cycle['start']->getTimestamp();
    $initialProgress = ($instant->getTimestamp() - $cycle['start']->getTimestamp()) / $durationSeconds;
    $samples = [];
    $sampleCount = 121;
    $selectedGeometry = $appearanceCalculator->calculate($instant, $observer);
    $fixedCelestialNorthScreenAngle = (float) $selectedGeometry['orientation']['celestial_north_screen_angle_degrees'];
    $signedAngle = static function (float $angle): float {
        $normalized = fmod($angle + 180.0, 360.0);
        if ($normalized < 0.0) $normalized += 360.0;
        return $normalized - 180.0;
    };
    for ($index = 0; $index < $sampleCount; $index++) {
        $sampleInstant = $cycle['start']->modify('+' . (string) (int) round($durationSeconds * $index / ($sampleCount - 1)) . ' seconds');
        $geometry = $appearanceCalculator->calculate($sampleInstant, $observer);
        $moonPosition = $lunarCalculator->calculate($sampleInstant->setTimezone(new DateTimeZone('UTC')), 0.0, 0.0, 0.0);
        $julianDay = 2440587.5 + (float) $sampleInstant->format('U.u') / 86400.0;
        $centuries = ($julianDay - 2451545.0) / 36525.0;
        $greenwichSidereal = 280.46061837 + 360.98564736629 * ($julianDay - 2451545.0)
            + 0.000387933 * $centuries * $centuries - $centuries ** 3 / 38710000.0;
        $moonIllumination = max(0.0, min(1.0, (float) $geometry['phase']['illumination_fraction']));
        $moonCycleAngle = (float) $geometry['phase']['cycle_angle_degrees'];
        $samples[] = [
            'progress' => $index / ($sampleCount - 1),
            'datetime' => $sampleInstant->format(DateTimeInterface::ATOM),
            'moon' => [
                'longitude' => (float) $geometry['surface_geometry']['subobserver']['longitude_degrees'],
                'latitude' => (float) $geometry['surface_geometry']['subobserver']['latitude_degrees'],
                // La referencia de pantalla corresponde al observador en el
                // instante elegido. No comprimimos la rotación diaria del campo
                // dentro de la animación; sólo cambia el eje lunar por libración.
                'disk_angle' => $signedAngle(
                    $fixedCelestialNorthScreenAngle - (float) $geometry['orientation']['axis_position_angle_degrees'],
                ),
                'sun' => $geometry['surface_geometry']['subsolar']['body_fixed_unit_vector'],
                'illumination' => $moonIllumination,
                'cycle_angle' => $moonCycleAngle,
                'phase' => (string) $geometry['phase']['name'],
            ],
            'earth' => [
                // Punto terrestre situado bajo la Luna: longitud este = AR lunar - GST.
                'longitude' => $signedAngle((float) $moonPosition->rightAscensionDegrees - $greenwichSidereal),
                'latitude' => (float) $moonPosition->declinationDegrees,
                // El eje terrestre conserva una inclinación fija en pantalla.
                'disk_angle' => $fixedCelestialNorthScreenAngle,
                'illumination' => 1.0 - $moonIllumination,
                'cycle_angle' => fmod($moonCycleAngle + 180.0, 360.0),
            ],
        ];
    }
    unset($payload['geometry'], $payload['wallpaper']);
    $payload['textures']['earth_albedo'] = versionedAssetUrl('assets/images/moon-three/nasa-blue-marble-2048.png');
    $payload['samples'] = $samples;
    $payload['comparison'] = $options + [
        'initial_progress' => max(0.0, min(1.0, $initialProgress)),
        'cycle_seconds' => 30.0,
        'cycle_start' => $cycle['start']->format(DateTimeInterface::ATOM),
        'cycle_end' => $cycle['end']->format(DateTimeInterface::ATOM),
        'earth_moon_apparent_diameter_ratio' => 3.67,
        'observer' => [
            'latitude' => $observer->latitudeDegrees,
            'longitude' => $observer->longitudeDegrees,
            'timezone' => $observer->timezone->getName(),
        ],
        'selected_datetime' => $instant->format(DateTimeInterface::ATOM),
        'fixed_celestial_north_screen_angle_degrees' => $fixedCelestialNorthScreenAngle,
    ];
    return $payload;
}
