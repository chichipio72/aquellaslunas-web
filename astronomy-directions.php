<?php

require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-preferences.php';

header('Content-Type: application/json; charset=utf-8');
sendDynamicNoCacheHeaders();

function directionsError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    directionsError(405, 'Método no permitido.');
}

$date = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
$time = isset($_GET['time']) ? trim((string) $_GET['time']) : '';
$latitude = astronomyLocationCoordinate($_GET['latitude'] ?? null, -90, 90);
$longitude = astronomyLocationCoordinate($_GET['longitude'] ?? null, -180, 180);
$timezone = astronomyLocationTimezone($_GET['timezone'] ?? null);
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
$validDate = $parsedDate !== false
    && $parsedDate->format('Y-m-d') === $date
    && $date >= '1900-01-01'
    && $date <= '2050-12-31';
$validTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time) === 1;

if (!$validDate || !$validTime) {
    directionsError(400, 'Fecha u hora inválida.');
}
if ($latitude === null || $longitude === null || $timezone === null) {
    directionsError(400, 'Ubicación inválida.');
}
$normalizedTime = strlen($time) === 5 ? $time . ':00' : $time;

try {
    $apiConfig = loadAstronomyApiConfig();
} catch (RuntimeException $exception) {
    error_log('Aquellas Lunas directions configuration error: ' . $exception->getMessage());
    directionsError(503, 'No se pudieron cargar las direcciones.');
}
$parameters = compact('date', 'latitude', 'longitude', 'timezone') + ['time' => $normalizedTime];
$result = astronomyApiRequest(
    $apiConfig['base_url'] . '/v1/astronomy/directions?' . http_build_query($parameters),
    'directions',
    15
);
if ($result['body'] === false || $result['http_code'] !== 200) {
    astronomyApiRecordValidation('directions', null, false);
    directionsError(503, 'No se pudieron cargar las direcciones.');
}
$decoded = json_decode($result['body'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
    astronomyApiRecordValidation('directions', false, false);
    directionsError(503, 'No se pudieron cargar las direcciones.');
}

function directionsAzimuth($value): ?float
{
    return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0 && (float) $value < 360
        ? (float) $value : null;
}

function directionsEvent($value): ?array
{
    if ($value === null) {
        return null;
    }
    $eventTime = is_array($value) && is_string($value['time'] ?? null) ? $value['time'] : '';
    $azimuth = is_array($value) ? directionsAzimuth($value['azimuth_degrees'] ?? null) : null;
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $eventTime) !== 1 || $azimuth === null) {
        directionsError(503, 'La respuesta de direcciones está incompleta.');
    }
    return ['time' => $eventTime, 'azimuth_degrees' => $azimuth];
}

function directionsBody($value): array
{
    if (!is_array($value) || !is_array($value['instant'] ?? null)) {
        directionsError(503, 'La respuesta de direcciones está incompleta.');
    }
    $instant = $value['instant'];
    $azimuth = directionsAzimuth($instant['azimuth_degrees'] ?? null);
    $altitude = is_numeric($instant['altitude_degrees'] ?? null) ? (float) $instant['altitude_degrees'] : null;
    $aboveHorizon = $instant['above_horizon'] ?? null;
    if ($azimuth === null || $altitude === null || !is_finite($altitude)
        || $altitude < -90 || $altitude > 90 || !is_bool($aboveHorizon)) {
        directionsError(503, 'La respuesta de direcciones está incompleta.');
    }
    return [
        'rise' => directionsEvent($value['rise'] ?? null),
        'set' => directionsEvent($value['set'] ?? null),
        'instant' => [
            'azimuth_degrees' => $azimuth,
            'altitude_degrees' => $altitude,
            'above_horizon' => $aboveHorizon,
        ],
    ];
}

$observer = is_array($decoded['observer'] ?? null) ? $decoded['observer'] : [];
if (($decoded['date'] ?? null) !== $date || ($decoded['time'] ?? null) !== $normalizedTime
    || ($decoded['timezone'] ?? null) !== $timezone
    || astronomyLocationCoordinate($observer['latitude'] ?? null, -90, 90) === null
    || astronomyLocationCoordinate($observer['longitude'] ?? null, -180, 180) === null) {
    astronomyApiRecordValidation('directions', true, false);
    directionsError(503, 'La respuesta de direcciones está incompleta.');
}
$payload = [
    'date' => $date,
    'time' => $normalizedTime,
    'timezone' => $timezone,
    'observer' => ['latitude' => (float) $observer['latitude'], 'longitude' => (float) $observer['longitude']],
    'sun' => directionsBody($decoded['sun'] ?? null),
    'moon' => directionsBody($decoded['moon'] ?? null),
];
astronomyApiRecordValidation('directions', true, true);
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
