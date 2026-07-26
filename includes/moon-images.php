<?php

require_once __DIR__ . '/asset-url.php';

const SYNODIC_MONTH_DAYS = 29.530588853;
const MOON_PHASE_ASSET_DIRECTORY = __DIR__ . '/../assets/images/moon-phases';
const MOON_PHASE_ASSET_URL = 'assets/images/moon-phases';

function moonPhaseDirectionFromAge($ageDays): ?string
{
    if (!is_numeric($ageDays)) {
        return null;
    }

    $age = (float) $ageDays;
    if (!is_finite($age) || $age < 0 || $age >= SYNODIC_MONTH_DAYS) {
        return null;
    }

    return $age < SYNODIC_MONTH_DAYS / 2 ? 'waxing' : 'waning';
}

function moonPhaseThumbnail($illuminationPercent, $ageDays, float $latitude): ?array
{
    if (!is_numeric($illuminationPercent)) {
        return null;
    }

    $illumination = (float) $illuminationPercent;
    $direction = moonPhaseDirectionFromAge($ageDays);
    if (!is_finite($illumination) || $illumination < 0 || $illumination > 100 || $direction === null) {
        return null;
    }

    $percent = (int) round($illumination);
    $hemisphere = $latitude < 0 ? 'south' : 'north';
    $filename = sprintf('moon_%03d_%s_%s.png', $percent, $direction, $hemisphere);
    $path = MOON_PHASE_ASSET_DIRECTORY . '/' . $filename;
    if (!is_file($path)) {
        error_log('Aquellas Lunas: missing Moon thumbnail ' . $filename . '.');
        return null;
    }

    return [
        'url' => versionedAssetUrl(MOON_PHASE_ASSET_URL . '/' . $filename),
        'percent' => $percent,
        'direction' => $direction,
    ];
}
