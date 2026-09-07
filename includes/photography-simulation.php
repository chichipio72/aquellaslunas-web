<?php
declare(strict_types=1);

require_once __DIR__ . '/site-configuration.php';
require_once __DIR__ . '/asset-url.php';

function photographySimulationConfig(?callable $connectionFactory = null): array
{
    $values = astronomySiteConfigLoadAll($connectionFactory);
    $prefix = 'photography.simulated.';
    $config = [];
    foreach (astronomySiteConfigCatalog() as $key => $definition) {
        if (str_starts_with($key, $prefix)) {
            $config[substr($key, strlen($prefix))] = $values[$key] ?? $definition['default'];
        }
    }
    return $config;
}

function photographySimulationDefinitions(): array
{
    return array_filter(
        astronomySiteConfigCatalog(),
        static fn(array $definition, string $key): bool => str_starts_with($key, 'photography.simulated.'),
        ARRAY_FILTER_USE_BOTH
    );
}

function photographySimulationSmoothstep(float $edge0, float $edge1, float $value): float
{
    $weight = max(0.0, min(1.0, ($value - $edge0) / max(0.000001, $edge1 - $edge0)));
    return $weight * $weight * (3.0 - 2.0 * $weight);
}

function photographySimulationSmootherstep(float $edge0, float $edge1, float $value): float
{
    $weight = max(0.0, min(1.0, ($value - $edge0) / max(0.000001, $edge1 - $edge0)));
    return $weight * $weight * $weight * ($weight * ($weight * 6.0 - 15.0) + 10.0);
}

function photographySimulationMixNumber(float $first, float $second, float $weight): float
{
    return $first + ($second - $first) * max(0.0, min(1.0, $weight));
}

function photographySimulationMixColor(string $first, string $second, float $weight): string
{
    if (preg_match('/^#[0-9a-f]{6}$/i', $first) !== 1 || preg_match('/^#[0-9a-f]{6}$/i', $second) !== 1) return '#eaf3ff';
    $weight = max(0.0, min(1.0, $weight));
    $channels = [];
    for ($offset = 1; $offset <= 5; $offset += 2) {
        $channels[] = (int) round(photographySimulationMixNumber((float) hexdec(substr($first, $offset, 2)), (float) hexdec(substr($second, $offset, 2)), $weight));
    }
    return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
}

function photographySimulationMoonProfile(array $configuration, string $sky, string $height): array
{
    $prefix = 'moon_' . $sky . '_' . $height . '_';
    return [
        'lit_color' => (string) ($configuration[$prefix . 'lit_color'] ?? '#eaf3ff'),
        'lit_brightness' => (float) ($configuration[$prefix . 'lit_brightness'] ?? 2.8),
        'dark_brightness' => (float) ($configuration[$prefix . 'dark_brightness'] ?? 1.0),
        'dark_sky_mix' => (float) ($configuration[$prefix . 'dark_sky_mix'] ?? 1.0),
        'contrast' => (float) ($configuration[$prefix . 'contrast'] ?? 1.12),
        'texture_visibility' => (float) ($configuration[$prefix . 'texture_visibility'] ?? 1.0),
        'lit_sky_mix' => (float) ($configuration[$prefix . 'lit_sky_mix'] ?? 0.0),
        'terminator_detail' => (float) ($configuration[$prefix . 'terminator_detail'] ?? 0.0),
        'limb_softness' => (float) ($configuration[$prefix . 'limb_softness'] ?? 0.0),
    ];
}

function photographySimulationMixMoonProfiles(array $first, array $second, float $weight): array
{
    return [
        'lit_color' => photographySimulationMixColor($first['lit_color'], $second['lit_color'], $weight),
        'lit_brightness' => photographySimulationMixNumber($first['lit_brightness'], $second['lit_brightness'], $weight),
        'dark_brightness' => photographySimulationMixNumber($first['dark_brightness'], $second['dark_brightness'], $weight),
        'dark_sky_mix' => photographySimulationMixNumber($first['dark_sky_mix'], $second['dark_sky_mix'], $weight),
        'contrast' => photographySimulationMixNumber($first['contrast'], $second['contrast'], $weight),
        'texture_visibility' => photographySimulationMixNumber($first['texture_visibility'], $second['texture_visibility'], $weight),
        'lit_sky_mix' => photographySimulationMixNumber($first['lit_sky_mix'], $second['lit_sky_mix'], $weight),
        'terminator_detail' => photographySimulationMixNumber($first['terminator_detail'], $second['terminator_detail'], $weight),
        'limb_softness' => photographySimulationMixNumber($first['limb_softness'], $second['limb_softness'], $weight),
    ];
}

function photographySimulationMoonAppearance(array $state, array $configuration): array
{
    $solarAltitude = (float) ($state['sun']['altitude_degrees'] ?? -18.0);
    $lunarAltitude = (float) ($state['moon']['altitude_degrees'] ?? 0.0);
    $nightAltitude = (float) ($configuration['night_transition_altitude'] ?? -18.0);
    $dayAltitude = (float) ($configuration['day_transition_altitude'] ?? 8.0);
    $twilightCenter = max($nightAltitude + 0.001, min($dayAltitude - 0.001, (float) ($configuration['twilight_center_altitude'] ?? -3.0)));
    $heightStart = (float) ($configuration['moon_low_to_high_start_altitude'] ?? 0.0);
    $heightEnd = max($heightStart + 0.001, (float) ($configuration['moon_low_to_high_end_altitude'] ?? 60.0));
    $heightWeight = photographySimulationSmoothstep($heightStart, $heightEnd, $lunarAltitude);
    $solarSeparation = max(0.0, min(180.0, (float) ($state['sun']['separation_from_moon_degrees'] ?? 0.0)));
    $directionWeight = photographySimulationSmootherstep(0.0, 180.0, $solarSeparation);

    $profiles = [];
    foreach (['night', 'day'] as $sky) $profiles[$sky] = photographySimulationMixMoonProfiles(
        photographySimulationMoonProfile($configuration, $sky, 'low'),
        photographySimulationMoonProfile($configuration, $sky, 'high'),
        $heightWeight
    );
    $twilightLow = photographySimulationMixMoonProfiles(
        photographySimulationMoonProfile($configuration, 'twilight', 'low'),
        photographySimulationMoonProfile($configuration, 'twilight', 'low_antisolar'),
        $directionWeight
    );
    $profiles['twilight'] = photographySimulationMixMoonProfiles(
        $twilightLow, photographySimulationMoonProfile($configuration, 'twilight', 'high'), $heightWeight
    );
    if ($solarAltitude <= $twilightCenter) {
        $skyWeight = photographySimulationSmoothstep($nightAltitude, $twilightCenter, $solarAltitude);
        $appearance = photographySimulationMixMoonProfiles($profiles['night'], $profiles['twilight'], $skyWeight);
        $skyBand = 'night_twilight';
    } else {
        $skyWeight = photographySimulationSmoothstep($twilightCenter, $dayAltitude, $solarAltitude);
        $appearance = photographySimulationMixMoonProfiles($profiles['twilight'], $profiles['day'], $skyWeight);
        $skyBand = 'twilight_day';
    }
    $atmosphereStrength = pow(1.0 - photographySimulationSmoothstep(
        (float) ($configuration['atmosphere_horizon_altitude'] ?? 0.5),
        max((float) ($configuration['atmosphere_horizon_altitude'] ?? 0.5) + 0.5, (float) ($configuration['atmosphere_clear_altitude'] ?? 30.0)),
        $lunarAltitude
    ), 1.35);
    if ($solarAltitude <= $twilightCenter) {
        $contextWarmth = photographySimulationMixNumber(
            (float) ($configuration['atmosphere_warmth_night'] ?? 0.68),
            (float) ($configuration['atmosphere_warmth_twilight'] ?? 0.42),
            photographySimulationSmoothstep($nightAltitude, $twilightCenter, $solarAltitude)
        );
    } else {
        $contextWarmth = photographySimulationMixNumber(
            (float) ($configuration['atmosphere_warmth_twilight'] ?? 0.42),
            (float) ($configuration['atmosphere_warmth_day'] ?? 0.12),
            photographySimulationSmoothstep($twilightCenter, $dayAltitude, $solarAltitude)
        );
    }
    $extinction = $atmosphereStrength * (float) ($configuration['atmosphere_extinction_strength'] ?? 0.38);
    $contrastLoss = $atmosphereStrength * (float) ($configuration['atmosphere_contrast_loss'] ?? 0.48);
    $detailLoss = $atmosphereStrength * (float) ($configuration['atmosphere_detail_loss'] ?? 0.42);
    $haze = $atmosphereStrength * (float) ($configuration['atmosphere_haze_integration'] ?? 0.3);
    $baseLitBrightness = $appearance['lit_brightness'];
    $appearance['lit_brightness'] *= 1.0 - $extinction;
    $appearance['contrast'] *= 1.0 - $contrastLoss;
    $appearance['texture_visibility'] *= 1.0 - $detailLoss;
    $appearance['lit_sky_mix'] = photographySimulationMixNumber($appearance['lit_sky_mix'], 0.8, $haze);
    $appearance['dark_sky_mix'] = photographySimulationMixNumber($appearance['dark_sky_mix'], 1.0, $haze);
    $appearance['limb_softness'] = min(0.15, $appearance['limb_softness'] + $haze * 0.04);
    $appearance['lit_color'] = photographySimulationMixColor(
        $appearance['lit_color'],
        (string) ($configuration['atmosphere_warm_color'] ?? '#e5a06d'),
        $atmosphereStrength * $contextWarmth
    );
    $appearance['interpolation'] = [
        'sky_band' => $skyBand,
        'sky_weight' => $skyWeight,
        'height_weight' => $heightWeight,
        'height_transition_start_degrees' => $heightStart,
        'height_transition_end_degrees' => $heightEnd,
        'twilight_center_altitude_degrees' => $twilightCenter,
        'atmosphere_strength' => $atmosphereStrength,
        'atmosphere_context_warmth' => $contextWarmth,
        'base_lit_brightness' => $baseLitBrightness,
        'atmosphere_extinction' => $extinction,
        'atmosphere_brightness_multiplier' => 1.0 - $extinction,
        'brightness_after_extinction' => $appearance['lit_brightness'],
        'atmosphere_haze_integration' => $haze,
        'solar_direction_separation_degrees' => $solarSeparation,
        'solar_direction_weight' => $directionWeight,
    ];
    return $appearance;
}

function photographySimulationMoonPayload(array $state, array $configuration): array
{
    $appearance = photographySimulationMoonAppearance($state, $configuration);
    $rendererAsset = versionedAssetUrl('assets/js/photography-moon-three.js');
    $rendererModuleUrl = str_starts_with($rendererAsset, 'assets/js/')
        ? './' . substr($rendererAsset, strlen('assets/js/'))
        : './photography-moon-three.js';
    return [
        'geometry' => [
            'illumination_fraction' => max(0.0, min(1.0, (float) ($state['moon']['illumination_fraction'] ?? 0.0))),
            'orientation' => [
                'lunar_north_screen_angle_degrees' => (float) ($state['moon']['lunar_north_screen_angle_degrees'] ?? 0.0),
            ],
            'surface_geometry' => $state['moon']['surface_geometry'] ?? [],
        ],
        'appearance' => $appearance,
        'textures' => [
            'albedo' => versionedAssetUrl('assets/images/moon-three/lroc_color_2k.jpg'),
            'normal' => versionedAssetUrl('assets/images/moon-three/ldem_4_normal.png'),
        ],
        'renderer_url' => $rendererModuleUrl,
    ];
}
