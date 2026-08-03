<?php

require_once __DIR__ . '/includes/astronomy-data.php';
require_once __DIR__ . '/includes/moon-images.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Facade\MoonInstantFacade;

// Cambiar únicamente este valor (rango admitido por la API: 0.0 a 0.2).
const MOON_TERMINATOR_SOFTNESS = 0.05;
$moonImageOperationStarted = hrtime(true);

function sendMoonImageDiagnosticHeaders(string $source, int $startedAt): void
{
    if (!astronomyTimingsEnabled()) {
        return;
    }
    $elapsed = (hrtime(true) - $startedAt) / 1_000_000;
    header('X-Astronomy-Requested-Source: ' . (string) ($GLOBALS['moonImageRequestedSource'] ?? 'static'));
    header('X-Astronomy-Used-Source: ' . $source);
    header('X-Astronomy-Total-Ms: ' . number_format($elapsed, 3, '.', ''));
    header('Server-Timing: moon-image;dur=' . number_format($elapsed, 3, '.', ''));
}

function serveMoonImageFallback(): never
{
    $startedAt = (int) ($GLOBALS['moonImageOperationStarted'] ?? hrtime(true));
    $fallbackPath = __DIR__ . '/assets/images/moon-phases/moon_000_waxing_south.png';
    http_response_code(200);
    header('Content-Type: image/png');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    sendMoonImageDiagnosticHeaders('static-fallback', $startedAt);
    readfile($fallbackPath);
    exit;
}

$latitude = filter_var($_GET['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_GET['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$timezoneName = isset($_GET['timezone']) ? trim((string) $_GET['timezone']) : '';
$dateTimeValue = isset($_GET['datetime']) ? trim((string) $_GET['datetime']) : '';

if (
    $latitude === false || $latitude < -90 || $latitude > 90
    || $longitude === false || $longitude < -180 || $longitude > 180
    || !in_array($timezoneName, timezone_identifiers_list(), true)
    || !preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $dateTimeValue)
) {
    error_log('Aquellas Lunas Moon image: invalid request parameters.');
    serveMoonImageFallback();
}

try {
    $dateTime = new DateTimeImmutable($dateTimeValue);
    $timezone = new DateTimeZone($timezoneName);
    if ($dateTime->getOffset() !== $timezone->getOffset($dateTime)) {
        throw new InvalidArgumentException('Datetime offset does not match timezone.');
    }
} catch (Throwable $exception) {
    error_log('Aquellas Lunas Moon image configuration error: ' . $exception->getMessage());
    serveMoonImageFallback();
}

$parameters = [
    'orientation' => 'apparent',
    'latitude' => $latitude,
    'longitude' => $longitude,
    'timezone' => $timezoneName,
    'datetime' => $dateTime->format(DateTimeInterface::ATOM),
    'size' => 240,
    'terminator_softness' => MOON_TERMINATOR_SOFTNESS,
];
$apiParameters = $parameters;
$moonImageRequestedSource = astronomyDataSourceFor('moon_image');
$largeImage = null;

if ($moonImageRequestedSource === 'static') {
  try {
    $instant = (new MoonInstantFacade())->calculate(
        $dateTime,
        new AstronomyObserver((float) $latitude, (float) $longitude, $timezoneName)
    );
    $largeImage = moonPhaseLargeImage(
        $instant['phase']['illumination_percent'] ?? null,
        $instant['phase']['age_days'] ?? null,
        (float) $latitude
    );
    $phaseDirection = moonPhaseDirectionFromAge($instant['phase']['age_days'] ?? null);
    if ($phaseDirection !== null) {
        $apiParameters = [
            'orientation' => 'hemisphere',
            'latitude' => $latitude,
            'illumination_percent' => $instant['phase']['illumination_percent'],
            'phase_direction' => $phaseDirection,
            'size' => 240,
            'terminator_softness' => MOON_TERMINATOR_SOFTNESS,
        ];
    }
    if ($largeImage !== null && is_readable($largeImage['path'])) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        sendMoonImageDiagnosticHeaders('static', $moonImageOperationStarted);
        readfile($largeImage['path']);
        exit;
    }
  } catch (Throwable $exception) {
    error_log('Aquellas Lunas static Moon image selection error; using API fallback: ' . $exception->getMessage());
  }
}

try {
    $apiConfig = loadAstronomyApiConfig();
} catch (Throwable $exception) {
    error_log('Aquellas Lunas Moon image API fallback configuration error: ' . $exception->getMessage());
    if ($moonImageRequestedSource === 'api') {
        try {
            $instant = (new MoonInstantFacade())->calculate($dateTime, new AstronomyObserver((float) $latitude, (float) $longitude, $timezoneName));
            $largeImage = moonPhaseLargeImage($instant['phase']['illumination_percent'] ?? null, $instant['phase']['age_days'] ?? null, (float) $latitude);
            if ($largeImage !== null && is_readable($largeImage['path'])) {
                header('Content-Type: image/png');
                header('Cache-Control: public, max-age=86400');
                header('X-Content-Type-Options: nosniff');
                sendMoonImageDiagnosticHeaders('static', $moonImageOperationStarted);
                readfile($largeImage['path']);
                exit;
            }
        } catch (Throwable $staticException) {
            error_log('Aquellas Lunas Moon image static fallback error: ' . $staticException->getMessage());
        }
    }
    serveMoonImageFallback();
}

$requestResult = astronomyApiRequest($apiConfig['base_url'] . '/v1/moon/image?' . http_build_query($apiParameters), 'moon image API fallback', 12);
$response = $requestResult['body'];
$httpCode = $requestResult['http_code'];
$contentType = $requestResult['content_type'];
$curlError = $requestResult['curl_error'];

if ($response === false || $httpCode !== 200 || !is_string($contentType) || stripos($contentType, 'image/png') !== 0) {
    astronomyApiRecordValidation('moon image API fallback', null, false);
    error_log(
        'Aquellas Lunas Moon image error: HTTP ' . $httpCode
        . ($curlError !== '' ? ' - cURL: ' . $curlError : '')
    );
    if ($moonImageRequestedSource === 'api') {
        try {
            $instant = (new MoonInstantFacade())->calculate(
                $dateTime,
                new AstronomyObserver((float) $latitude, (float) $longitude, $timezoneName)
            );
            $largeImage = moonPhaseLargeImage(
                $instant['phase']['illumination_percent'] ?? null,
                $instant['phase']['age_days'] ?? null,
                (float) $latitude
            );
            if ($largeImage !== null && is_readable($largeImage['path'])) {
                header('Content-Type: image/png');
                header('Cache-Control: public, max-age=86400');
                header('X-Content-Type-Options: nosniff');
                sendMoonImageDiagnosticHeaders('static', $moonImageOperationStarted);
                readfile($largeImage['path']);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Aquellas Lunas Moon image static fallback error: ' . $exception->getMessage());
        }
    }
    serveMoonImageFallback();
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=900');
header('X-Content-Type-Options: nosniff');
astronomyApiRecordValidation('moon image API fallback', null, true);
sendMoonImageDiagnosticHeaders('api', $moonImageOperationStarted);
echo $response;
