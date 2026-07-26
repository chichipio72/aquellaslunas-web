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
