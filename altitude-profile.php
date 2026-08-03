<?php

require_once __DIR__ . '/includes/astronomy-data.php';

header('Content-Type: application/json; charset=utf-8');
sendDynamicNoCacheHeaders();

function altitudeProfileError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    altitudeProfileError(405, 'Método no permitido.');
}

function altitudeProfileNumber($value, float $minimum, float $maximum): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= $minimum && $number <= $maximum ? $number : null;
}

$target = isset($_GET['target']) ? trim((string) $_GET['target']) : '';
$date = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
$timezone = isset($_GET['timezone']) ? trim((string) $_GET['timezone']) : '';
$latitude = altitudeProfileNumber($_GET['latitude'] ?? null, -90.0, 90.0);
$longitude = altitudeProfileNumber($_GET['longitude'] ?? null, -180.0, 180.0);

if (!in_array($target, ['sun', 'moon'], true)) {
    altitudeProfileError(400, 'Objetivo inválido.');
}
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
    altitudeProfileError(400, 'Fecha inválida.');
}
if ($latitude === null || $longitude === null) {
    altitudeProfileError(400, 'Ubicación inválida.');
}
try {
    new DateTimeZone($timezone);
} catch (Exception $exception) {
    altitudeProfileError(400, 'Zona horaria inválida.');
}

try {
    $intervalMinutes = loadAltitudeProfileIntervalMinutes();
} catch (RuntimeException $exception) {
    error_log('Aquellas Lunas altitude profile configuration error: ' . $exception->getMessage());
    altitudeProfileError(503, 'No se pudo cargar el recorrido.');
}

try {
    $decoded = astronomyDataAltitudeProfile(
        compact('latitude', 'longitude', 'timezone'),
        $date,
        $target,
        $intervalMinutes,
        'altitude profile ' . $target,
        12
    );
} catch (Throwable $exception) {
    error_log('Aquellas Lunas altitude profile error: ' . $exception->getMessage());
    altitudeProfileError(503, 'No se pudo cargar el recorrido.');
}

$expectedRoles = $target === 'sun'
    ? ['winter_solstice', 'requested_date', 'summer_solstice']
    : ['previous_date', 'requested_date', 'next_date'];
$series = [];
foreach (($decoded['series'] ?? []) as $item) {
    if (!is_array($item) || !in_array($item['role'] ?? null, $expectedRoles, true) || !is_array($item['points'] ?? null)) {
        continue;
    }
    $points = [];
    foreach ($item['points'] as $point) {
        $localTime = is_array($point) && is_string($point['local_time'] ?? null) ? $point['local_time'] : '';
        $altitude = is_array($point) ? altitudeProfileNumber($point['altitude_degrees'] ?? null, -90.0, 90.0) : null;
        if ($localTime === '' || $altitude === null) {
            continue;
        }
        $points[] = ['local_time' => $localTime, 'altitude_degrees' => $altitude];
    }
    if (count($points) >= 2) {
        $series[] = [
            'role' => $item['role'],
            'local_date' => (string) ($item['local_date'] ?? ''),
            'points' => $points,
        ];
    }
}
$returnedRoles = array_column($series, 'role');
sort($returnedRoles);
$sortedExpectedRoles = $expectedRoles;
sort($sortedExpectedRoles);
$returnedInterval = filter_var($decoded['interval_minutes'] ?? null, FILTER_VALIDATE_INT, [
    'options' => [
        'min_range' => MIN_ALTITUDE_PROFILE_INTERVAL_MINUTES,
        'max_range' => MAX_ALTITUDE_PROFILE_INTERVAL_MINUTES,
    ],
]);
if ($returnedRoles !== $sortedExpectedRoles || $returnedInterval === false) {
    astronomyApiRecordValidation('altitude profile ' . $target, true, false);
    altitudeProfileError(503, 'No se pudo cargar el recorrido.');
}

astronomyApiRecordValidation('altitude profile ' . $target, true, true);
echo json_encode([
    'target' => $target,
    'requested_date' => $date,
    'timezone' => $timezone,
    'interval_minutes' => (int) $returnedInterval,
    'series' => $series,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
