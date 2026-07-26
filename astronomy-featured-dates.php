<?php

require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-preferences.php';
require_once __DIR__ . '/includes/featured-dates.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=3600');

function featuredDatesError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    featuredDatesError(405, 'Método no permitido.');
}

$anchor = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
$latitude = astronomyLocationCoordinate($_GET['latitude'] ?? null, -90, 90);
$longitude = astronomyLocationCoordinate($_GET['longitude'] ?? null, -180, 180);
$timezoneName = astronomyLocationTimezone($_GET['timezone'] ?? null);
$parsedAnchor = DateTimeImmutable::createFromFormat('!Y-m-d', $anchor);
if ($parsedAnchor === false || $parsedAnchor->format('Y-m-d') !== $anchor
    || $anchor < '1900-07-01' || $anchor > '2049-12-31') {
    featuredDatesError(400, 'Fecha inválida.');
}
if ($latitude === null || $longitude === null || $timezoneName === null) {
    featuredDatesError(400, 'Ubicación inválida.');
}
try {
    $payload = astronomyFeaturedDates($anchor, $latitude, $longitude, $timezoneName);
} catch (RuntimeException $exception) {
    error_log('Aquellas Lunas featured dates configuration error: ' . $exception->getMessage());
    featuredDatesError(503, 'No se pudieron cargar las fechas destacadas.');
}
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
