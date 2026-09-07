<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/current-datetime.php';
require_once __DIR__ . '/../../includes/location-preferences.php';
require_once __DIR__ . '/astronomy-data.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MoonDiskAppearanceCalculator;

sendDynamicNoCacheHeaders();
header('Content-Type: application/json; charset=UTF-8');

try {
    $timezone = ASTRONOMY_DEFAULT_TIMEZONE;
    // "Astronómico hoy" debe significar ahora real aunque la sesión general
    // conserve una simulación. El contexto simulado sigue disponible para
    // diagnóstico mediante clock=context.
    $clockMode = ($_GET['clock'] ?? '') === 'context' ? 'context' : 'system';
    $clock = moonThreeJsResolveClock($timezone, $clockMode);
    $latitude = ASTRONOMY_DEFAULT_LATITUDE;
    $longitude = ASTRONOMY_DEFAULT_LONGITUDE;
    $locationName = 'Buenos Aires / CABA';
    $customLocal = trim((string) ($_GET['local_datetime'] ?? ''));
    if ($customLocal !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $customLocal, new DateTimeZone($timezone));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $parsed->format('Y-m-d\TH:i') !== $customLocal
            || $parsed->format('Y-m-d') < '1900-01-01' || $parsed->format('Y-m-d') > '2050-12-31') {
            throw new InvalidArgumentException('Fecha y hora local inválidas.');
        }
        $latitude = filter_var($_GET['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_GET['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false) {
            throw new InvalidArgumentException('Coordenadas inválidas.');
        }
        $clock['instant'] = $parsed;
        $clock['source'] = 'custom_local_datetime';
        $locationName = trim((string) ($_GET['location_name'] ?? '')) ?: 'Ubicación personalizada';
    }
    $dayOffsetRaw = (string) ($_GET['day_offset'] ?? '0');
    if (preg_match('/^-?\d{1,2}$/', $dayOffsetRaw) !== 1) {
        throw new InvalidArgumentException('Desplazamiento de fecha inválido.');
    }
    $dayOffset = (int) $dayOffsetRaw;
    if ($dayOffset < -15 || $dayOffset > 15) {
        throw new InvalidArgumentException('Desplazamiento de fecha fuera de rango.');
    }
    if ($customLocal === '' && $dayOffset !== 0) {
        $clock['instant'] = $clock['instant']->modify(($dayOffset > 0 ? '+' : '') . $dayOffset . ' days');
    }
    $clock['day_offset'] = $dayOffset;
    $instant = $clock['instant'];
    $observer = new AstronomyObserver(
        (float) $latitude,
        (float) $longitude,
        $timezone,
        ASTRONOMY_DEFAULT_ELEVATION_METERS,
    );
    echo json_encode(
        moonThreeJsAstronomyPayload($instant, $observer, $clock, $locationName),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $error) {
    http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['error' => 'No se pudo calcular la apariencia lunar.']);
    error_log('Luna Three.js astronomy error: ' . $error->getMessage());
}
