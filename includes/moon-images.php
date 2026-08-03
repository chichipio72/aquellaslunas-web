<?php

require_once __DIR__ . '/asset-url.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MoonBrightLimbCalculator;

const SYNODIC_MONTH_DAYS = 29.530588853;
const MOON_PHASE_ASSET_DIRECTORY = __DIR__ . '/../assets/images/moon-phases';
const MOON_PHASE_ASSET_URL = 'assets/images/moon-phases';
const MOON_PHASE_LARGE_ASSET_DIRECTORY = __DIR__ . '/../assets/images/moon-phases-large';

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
    $selection = moonPhaseAssetSelection($illuminationPercent, $ageDays, $latitude, MOON_PHASE_ASSET_DIRECTORY);
    if ($selection === null) {
        return null;
    }
    $selection['url'] = versionedAssetUrl(MOON_PHASE_ASSET_URL . '/' . $selection['filename']);
    unset($selection['path'], $selection['filename']);
    return $selection;
}

function moonPhaseLargeImage($illuminationPercent, $ageDays, float $latitude): ?array
{
    return moonPhaseAssetSelection($illuminationPercent, $ageDays, $latitude, MOON_PHASE_LARGE_ASSET_DIRECTORY);
}

function recordMoonImageDiagnostic(string $label, $illuminationPercent, $ageDays, float $latitude): void
{
    if (!function_exists('astronomyRecordDiagnostic')) {
        return;
    }
    $started = hrtime(true);
    $selection = moonPhaseLargeImage($illuminationPercent, $ageDays, $latitude);
    $elapsed = (hrtime(true) - $started) / 1_000_000;
    $requestedSource = function_exists('astronomyDataSourceFor') ? astronomyDataSourceFor('moon_image') : 'static';
    $usedSource = $requestedSource === 'api'
        ? 'api'
        : ($selection !== null && is_readable($selection['path']) ? 'static' : 'api');
    astronomyRecordDiagnostic([
        'label' => $label,
        'requested_source' => $requestedSource,
        'used_source' => $usedSource,
        'source_ms' => $elapsed,
        'total_ms' => $elapsed,
        'fallback_from' => $requestedSource === 'static' && $usedSource === 'api' ? 'static' : null,
        'fallback_to' => $requestedSource === 'static' && $usedSource === 'api' ? 'api' : null,
        'result_count' => $selection !== null ? 1 : 0,
    ]);
}

function moonApparentRotation(
    DateTimeImmutable $instant,
    float $latitude,
    float $longitude,
    string $timezone
): ?array {
    try {
        $geometry = (new MoonBrightLimbCalculator())->calculate(
            $instant,
            new AstronomyObserver($latitude, $longitude, $timezone)
        );
        $rendererDegrees = (float) $geometry['rotation_degrees'];
        return [
            'renderer_degrees' => $rendererDegrees,
            // GD rota positivo en sentido antihorario; CSS lo hace en sentido horario.
            'css_degrees' => -$rendererDegrees,
        ];
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas apparent Moon orientation error: ' . $exception->getMessage());
        return null;
    }
}

function moonPhaseAssetSelection($illuminationPercent, $ageDays, float $latitude, string $directory): ?array
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
    $path = rtrim($directory, '/') . '/' . $filename;
    if (!is_file($path)) {
        error_log('Aquellas Lunas: missing Moon phase asset ' . $filename . '.');
        return null;
    }

    return [
        'path' => $path,
        'filename' => $filename,
        'percent' => $percent,
        'direction' => $direction,
    ];
}
