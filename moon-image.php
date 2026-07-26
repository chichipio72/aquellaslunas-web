<?php

require_once __DIR__ . '/includes/api-client.php';

// Cambiar únicamente este valor (rango admitido por la API: 0.0 a 0.2).
const MOON_TERMINATOR_SOFTNESS = 0.05;

function serveMoonImageFallback(): never
{
    $fallbackPath = __DIR__ . '/assets/images/moon-phases/moon_000_waxing_south.png';
    http_response_code(200);
    header('Content-Type: image/png');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
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
    $apiConfig = loadAstronomyApiConfig();
} catch (Throwable $exception) {
    error_log('Aquellas Lunas Moon image configuration error: ' . $exception->getMessage());
    serveMoonImageFallback();
}

$query = http_build_query([
    'orientation' => 'apparent',
    'latitude' => $latitude,
    'longitude' => $longitude,
    'timezone' => $timezoneName,
    'datetime' => $dateTime->format(DateTimeInterface::ATOM),
    'size' => 240,
    'terminator_softness' => MOON_TERMINATOR_SOFTNESS,
]);

$requestResult = astronomyApiRequest($apiConfig['base_url'] . '/v1/moon/image?' . $query, 'moon image', 12);
$response = $requestResult['body'];
$httpCode = $requestResult['http_code'];
$contentType = $requestResult['content_type'];
$curlError = $requestResult['curl_error'];

if ($response === false || $httpCode !== 200 || !is_string($contentType) || stripos($contentType, 'image/png') !== 0) {
    error_log(
        'Aquellas Lunas Moon image error: HTTP ' . $httpCode
        . ($curlError !== '' ? ' - cURL: ' . $curlError : '')
    );
    serveMoonImageFallback();
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=900');
header('X-Content-Type-Options: nosniff');
echo $response;
