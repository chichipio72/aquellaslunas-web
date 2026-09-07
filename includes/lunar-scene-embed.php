<?php

declare(strict_types=1);

require_once __DIR__ . '/favorite-moon.php';
require_once __DIR__ . '/moon-three-render.php';
require_once __DIR__ . '/photography-simulation.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MeeusSolarPositionCalculator;

/** @return array<string,mixed> */
function lunarSceneEmbedOptions(array $query, DateTimeImmutable $current): array
{
    $selection = favoriteMoonSelection($query['fecha'] ?? null, $query['hora'] ?? null, $current);
    $enum = static fn(string $key, array $allowed, string $default): string => in_array((string) ($query[$key] ?? ''), $allowed, true) ? (string) $query[$key] : $default;
    return $selection + [
        'lit_brightness' => lunarEmbedFloat($query['lit_brightness'] ?? null, 3.2, 0.0, 8.0),
        'lit_tint' => lunarEmbedColor($query['lit_tint'] ?? null, '#fff7e8'),
        'dark_brightness' => lunarEmbedFloat($query['dark_brightness'] ?? null, 0.04, 0.0, 1.0),
        'texture_saturation' => lunarEmbedFloat($query['texture_saturation'] ?? null, 0.85, 0.0, 2.0),
        'texture_contrast' => lunarEmbedFloat($query['texture_contrast'] ?? null, 1.15, 0.5, 2.0),
        'texture_resolution' => $enum('texture_resolution', ['low', 'high'], 'high'),
        'earthshine_intensity' => lunarEmbedFloat($query['earthshine_intensity'] ?? null, 0.12, 0.0, 1.0),
        'shadow_sky_mix' => lunarEmbedFloat($query['shadow_sky_mix'] ?? null, 0.12, 0.0, 1.0),
        'dark_side_opacity' => lunarEmbedFloat($query['dark_side_opacity'] ?? null, 0.82, 0.0, 1.0),
        'dark_limb_softness' => lunarEmbedFloat($query['dark_limb_softness'] ?? null, 0.08, 0.0, 0.3),
        'halo_intensity' => lunarEmbedFloat($query['halo_intensity'] ?? null, 0.18, 0.0, 1.0),
        'edge_softness' => lunarEmbedFloat($query['edge_softness'] ?? null, 0.02, 0.0, 0.15),
        'terminator_softness' => lunarEmbedFloat($query['terminator_softness'] ?? null, 0.13, 0.01, 0.35),
        'location_mode' => 'active',
        'sky_mode' => $enum('sky_mode', ['natural', 'custom'], 'natural'),
        'sky_center_color' => lunarEmbedColor($query['sky_center_color'] ?? null, '#789fc4'),
        'sky_center_brightness' => lunarEmbedFloat($query['sky_center_brightness'] ?? null, 1.0, 0.0, 2.0),
        'sky_edge_color' => lunarEmbedColor($query['sky_edge_color'] ?? null, '#263f62'),
        'sky_edge_brightness' => lunarEmbedFloat($query['sky_edge_brightness'] ?? null, 1.0, 0.0, 2.0),
        'sky_gradient_radius' => lunarEmbedFloat($query['sky_gradient_radius'] ?? null, 64.0, 25.0, 100.0),
    ];
}

function lunarSceneAdjustColor(string $color, float $brightness): string
{
    $channels = [];
    for ($offset = 1; $offset <= 5; $offset += 2) {
        $channels[] = max(0, min(255, (int) round(hexdec(substr($color, $offset, 2)) * $brightness)));
    }
    return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
}

function lunarSceneNaturalSkyColor(float $solarAltitude): string
{
    if ($solarAltitude <= -18.0) return '#01040c';
    if ($solarAltitude < -4.0) return photographySimulationMixColor('#01040c', '#58455f', photographySimulationSmoothstep(-18.0, -4.0, $solarAltitude));
    if ($solarAltitude < 8.0) return photographySimulationMixColor('#58455f', '#73a9d4', photographySimulationSmoothstep(-4.0, 8.0, $solarAltitude));
    return photographySimulationMixColor('#73a9d4', '#78b9e5', photographySimulationSmoothstep(8.0, 35.0, $solarAltitude));
}

/** @return array{moon:array<string,mixed>,scene:array<string,mixed>} */
function lunarSceneEmbedPayload(DateTimeImmutable $instant, array $location, array $options): array
{
    $observer = new AstronomyObserver(
        (float) $location['latitude'],
        (float) $location['longitude'],
        (string) $location['timezone'],
        (float) ($location['elevation_meters'] ?? 0.0),
    );
    $prefix = 'scene.moon_three.';
    $configuration = [
        $prefix . 'sun_intensity' => $options['lit_brightness'],
        $prefix . 'ambient_intensity' => $options['dark_brightness'],
        $prefix . 'relief_mode' => 'normal',
        $prefix . 'dem_resolution' => $options['texture_resolution'] === 'high' ? 'high' : 'low',
        $prefix . 'bump_scale' => 0.035,
        $prefix . 'normal_scale_x' => 1.0,
        $prefix . 'normal_scale_y' => 1.0,
        $prefix . 'texture_contrast' => $options['texture_contrast'],
        $prefix . 'texture_brightness' => 0.95,
        $prefix . 'texture_gamma' => 0.95,
        $prefix . 'texture_saturation' => $options['texture_saturation'],
        $prefix . 'exposure' => 1.15,
        $prefix . 'size_percent' => 100.0,
    ];
    $moon = moonThreeRenderPayload($instant, $observer, $configuration, $prefix);
    $sun = (new MeeusSolarPositionCalculator())->calculate(
        $instant,
        $observer->latitudeDegrees,
        $observer->longitudeDegrees,
        $observer->elevationMeters,
    );
    $naturalSky = lunarSceneNaturalSkyColor($sun->altitudeDegrees);
    $centerBase = $options['sky_mode'] === 'custom'
        ? $options['sky_center_color']
        : photographySimulationMixColor($naturalSky, '#e6f1fb', 0.16);
    $edgeBase = $options['sky_mode'] === 'custom'
        ? $options['sky_edge_color']
        : photographySimulationMixColor($naturalSky, '#000000', 0.28);
    $centerColor = lunarSceneAdjustColor($centerBase, (float) $options['sky_center_brightness']);
    $edgeColor = lunarSceneAdjustColor($edgeBase, (float) $options['sky_edge_brightness']);
    $moon['appearance']['earthshine_intensity'] = $options['earthshine_intensity'];
    $moon['appearance']['shadow_sky_mix'] = $options['shadow_sky_mix'];
    $moon['appearance']['dark_side_opacity'] = $options['dark_side_opacity'];
    $moon['appearance']['dark_limb_softness'] = $options['dark_limb_softness'];
    $moon['appearance']['scene_sky_color'] = $centerColor;
    $moon['appearance']['edge_softness'] = $options['edge_softness'];
    $moon['appearance']['terminator_softness'] = $options['terminator_softness'];
    $moon['appearance']['lit_tint'] = $options['lit_tint'];
    return [
        'moon' => $moon,
        'scene' => [
            'instant' => $instant->format(DateTimeInterface::ATOM),
            'location_name' => (string) ($location['name'] ?? 'Ubicación configurada'),
            'timezone' => $observer->timezone->getName(),
            'sun_altitude_degrees' => $sun->altitudeDegrees,
            'sky_mode' => $options['sky_mode'],
            'sky_center_color' => $centerColor,
            'sky_edge_color' => $edgeColor,
            'sky_gradient_radius_percent' => $options['sky_gradient_radius'],
            'halo_intensity' => $options['halo_intensity'],
        ],
    ];
}
