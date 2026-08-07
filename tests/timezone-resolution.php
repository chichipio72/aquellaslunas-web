<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/location-context.php';
require_once __DIR__ . '/../includes/astronomy-trace.php';

function timezoneResolutionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

timezoneResolutionAssert(astronomyResolveTimezone(37.8846, -4.7760) === 'Europe/Madrid',
    'Córdoba, España no resolvió Europe/Madrid.');
timezoneResolutionAssert(astronomyResolveTimezone(-34.6037, -58.3816) === 'America/Argentina/Buenos_Aires',
    'Buenos Aires no resolvió su zona IANA.');

$_COOKIE = [];
$spain = astronomySaveLocationRequest([
    'location_name' => 'Córdoba', 'latitude' => '37.8846', 'longitude' => '-4.7760',
    'elevation_meters' => '123', 'timezone' => 'America/Argentina/Buenos_Aires', 'location_mode' => 'manual',
]);
timezoneResolutionAssert($spain['ok'], 'No guardó Córdoba.');
timezoneResolutionAssert($_COOKIE['astro_timezone'] === 'Europe/Madrid', 'Confió en la zona enviada por el cliente.');
timezoneResolutionAssert($_COOKIE['astro_elevation'] === '123', 'No persistió la elevación con la ubicación.');
$reloaded = astronomyLocationContext();
timezoneResolutionAssert($reloaded['timezone'] === 'Europe/Madrid' && $reloaded['elevation_meters'] === 123.0,
    'La recarga perdió el bloque geográfico persistido.');

foreach (['manual', 'map', 'geolocation'] as $mode) {
    $result = astronomySaveLocationRequest([
        'location_name' => 'Buenos Aires', 'latitude' => '-34.6037', 'longitude' => '-58.3816',
        'elevation_meters' => '25', 'timezone' => 'Europe/Madrid', 'location_mode' => $mode,
    ]);
    timezoneResolutionAssert($result['ok'] && $_COOKIE['astro_timezone'] === 'America/Argentina/Buenos_Aires',
        'La ruta ' . $mode . ' no resolvió la zona en el servidor.');
}

$beforeFailure = $_COOKIE;
$failed = astronomySaveLocationRequest([
    'location_name' => 'Córdoba', 'latitude' => '37.8846', 'longitude' => '-4.7760',
    'elevation_meters' => '100', 'location_mode' => 'manual',
], static function (): string {
    throw new RuntimeException('Fallo sintético.');
});
timezoneResolutionAssert(!$failed['ok'] && $_COOKIE === $beforeFailure,
    'Un fallo del resolvedor modificó parcialmente la ubicación anterior.');

$_COOKIE = [
    'astro_latitude' => '37.8846', 'astro_longitude' => '-4.7760',
    'astro_timezone' => 'America/Argentina/Buenos_Aires', 'astro_location_mode' => 'manual',
    'astro_location_name' => 'Córdoba', 'astro_location_confirmed' => '1',
];
$migrated = astronomyLocationContext();
timezoneResolutionAssert($migrated['timezone'] === 'Europe/Madrid', 'No corrigió una cookie histórica incoherente.');
timezoneResolutionAssert($_COOKIE['astro_timezone'] === 'Europe/Madrid'
    && $_COOKIE['astro_elevation'] === '0'
    && $_COOKIE['astro_location_version'] === ASTRONOMY_LOCATION_COOKIE_VERSION,
    'La migración no reescribió el bloque completo.');
$traceLocation = astronomyTraceLocation($migrated);
timezoneResolutionAssert($traceLocation['timezone'] === 'Europe/Madrid', 'La trazabilidad no recibió la zona resuelta.');

echo "Timezone resolution tests: OK\n";

