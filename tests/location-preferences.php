<?php

require_once __DIR__ . '/../includes/location-preferences.php';

function locationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

locationAssert(astronomyLocationCoordinate('-34.6037', -90, 90) === -34.6037, 'No aceptó una latitud válida.');
locationAssert(astronomyLocationCoordinate('NaN', -90, 90) === null, 'Aceptó NaN.');
locationAssert(astronomyLocationCoordinate(91, -90, 90) === null, 'Aceptó una latitud fuera de rango.');
locationAssert(astronomyLocationTimezone('America/Argentina/Buenos_Aires') !== null, 'Rechazó una zona IANA válida.');
locationAssert(astronomyLocationTimezone('America/Buenos_Aires') === 'America/Argentina/Buenos_Aires', 'No canonicalizó el alias IANA de Buenos Aires.');
locationAssert(astronomyLocationTimezone('Invalid/Zone') === null, 'Aceptó una zona inexistente.');
locationAssert(astronomyLocationMode('custom') === 'manual', 'No migró el modo custom histórico.');
locationAssert(astronomyLocationMode('geolocation') === 'geolocation', 'Rechazó el modo geolocation.');
locationAssert(astronomyLocationMode('other') === null, 'Aceptó un modo desconocido.');
locationAssert(astronomyLocationName(' Córdoba ') === 'Córdoba', 'No normalizó una localidad válida.');
locationAssert(astronomyLocationName('Córdoba, Argentina') === null, 'Aceptó una dirección compuesta.');
locationAssert(astronomyLocationCoordinateLabel(-34.525, -58.475) === '-34.53, -58.48', 'El fallback no redondeó las coordenadas.');
locationAssert(ASTRONOMY_LOCATION_COOKIE_DAYS === 400, 'La persistencia de ubicación no quedó fijada en 400 días.');

$_COOKIE = [];
locationAssert(!astronomyLocationIsConfirmed(), 'Una visita sin cookies apareció confirmada.');
locationAssert(!astronomyLocationIntroWasSeen(), 'Una visita nueva apareció con el aviso ya visto.');
$_COOKIE = [
    'astro_latitude' => '-34.499',
    'astro_longitude' => '-58.5751',
    'astro_timezone' => 'America/Argentina/Buenos_Aires',
    'astro_location_mode' => 'manual',
    'astro_location_name' => 'Boulogne Sur Mer',
];
locationAssert(astronomyLocationIsConfirmed(), 'Una selección manual histórica completa dejó de considerarse confirmada.');
$_COOKIE['astro_location_mode'] = 'default';
locationAssert(!astronomyLocationIsConfirmed(), 'El fallback predeterminado se confundió con Buenos Aires confirmado.');
$_COOKIE['astro_location_confirmed'] = '1';
locationAssert(astronomyLocationIsConfirmed(), 'Buenos Aires elegido explícitamente no quedó confirmado.');
$_COOKIE['astro_location_intro_seen'] = '1';
locationAssert(astronomyLocationIntroWasSeen(), 'No se reconoció la cookie independiente del aviso.');
$_COOKIE = [];

locationAssert(astronomyApiRejectedLocationParameters([
    'http_code' => 422,
    'body' => '{"detail":[{"loc":["query","timezone"],"msg":"invalid"}]}',
]), 'No detectó un rechazo de zona horaria.');
locationAssert(!astronomyApiRejectedLocationParameters([
    'http_code' => 503,
    'body' => '{"detail":"timezone service unavailable"}',
]), 'Confundió una caída de API con parámetros inválidos.');
locationAssert(!astronomyApiRejectedLocationParameters([
    'http_code' => 422,
    'body' => '{"detail":[{"loc":["query","days"],"msg":"invalid"}]}',
]), 'Confundió otro parámetro inválido con la ubicación.');

echo "Validación de ubicación: OK\n";
