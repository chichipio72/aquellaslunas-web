<?php

require_once __DIR__ . '/location-preferences.php';
require_once __DIR__ . '/timezone-resolver.php';

/**
 * Fuente única de verdad para la ubicación activa.
 *
 * @return array{name:string,latitude:float,longitude:float,elevation_meters:float,timezone:string,mode:string}
 */
function astronomyLocationContext(?callable $timezoneResolver = null): array
{
    $hadStoredValues = astronomyLocationRequestWasInvalid();
    $fallback = [
        'name' => 'Buenos Aires',
        'latitude' => ASTRONOMY_DEFAULT_LATITUDE,
        'longitude' => ASTRONOMY_DEFAULT_LONGITUDE,
        'elevation_meters' => ASTRONOMY_DEFAULT_ELEVATION_METERS,
        'timezone' => ASTRONOMY_DEFAULT_TIMEZONE,
        'mode' => 'default',
        'confirmed' => false,
        'initial' => true,
        'stored_invalid' => false,
    ];
    $latitude = astronomyLocationCoordinate($_COOKIE['astro_latitude'] ?? null, -90, 90);
    $longitude = astronomyLocationCoordinate($_COOKIE['astro_longitude'] ?? null, -180, 180);
    $storedTimezone = astronomyLocationTimezone($_COOKIE['astro_timezone'] ?? null);
    $elevation = array_key_exists('astro_elevation', $_COOKIE)
        ? astronomyLocationElevation($_COOKIE['astro_elevation'])
        : ASTRONOMY_DEFAULT_ELEVATION_METERS;
    $mode = astronomyLocationMode($_COOKIE['astro_location_mode'] ?? null);

    if ($latitude === null || $longitude === null || $elevation === null || $mode === null) {
        if ($hadStoredValues) {
            astronomyClearStoredLocation(true);
            $fallback['stored_invalid'] = true;
        }
        return $fallback;
    }
    try {
        $timezone = ($timezoneResolver ?? 'astronomyResolveTimezone')($latitude, $longitude);
        $timezone = astronomyLocationTimezone($timezone);
        if ($timezone === null) {
            throw new RuntimeException('El resolvedor devolvió una zona horaria inválida.');
        }
    } catch (Throwable $error) {
        error_log('Aquellas Lunas timezone resolution failed while loading location: ' . $error->getMessage());
        $fallback['stored_invalid'] = $hadStoredValues;
        return $fallback;
    }
    $name = astronomyStoredLocationLabel($latitude, $longitude, $mode);
    $confirmed = $mode !== 'default' || ($_COOKIE['astro_location_confirmed'] ?? null) === '1';
    $needsMigration = $storedTimezone !== $timezone
        || !array_key_exists('astro_elevation', $_COOKIE)
        || ($_COOKIE['astro_location_version'] ?? null) !== ASTRONOMY_LOCATION_COOKIE_VERSION;
    $coordinateLabel = astronomyLocationCoordinateLabel($latitude, $longitude);
    if ($mode !== 'default' && ($name === $coordinateLabel || astronomyLocationName($_COOKIE['astro_location_name'] ?? null) === null)) {
        $resolvedName = astronomyReverseGeocode($latitude, $longitude);
        if ($resolvedName !== $coordinateLabel) {
            $name = $resolvedName;
            $needsMigration = true;
        }
    }
    if ($needsMigration) {
        astronomyStoreLocation($latitude, $longitude, $elevation, $timezone, $mode, $name, $confirmed);
    }
    return [
        'name' => $name,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'elevation_meters' => $elevation,
        'timezone' => $timezone,
        'mode' => $mode,
        'confirmed' => astronomyLocationIsConfirmed(),
        'initial' => !astronomyLocationIsConfirmed(),
        'stored_invalid' => false,
    ];
}

function astronomyLocationRequestWasInvalid(): bool
{
    return isset($_COOKIE['astro_latitude'], $_COOKIE['astro_longitude'], $_COOKIE['astro_timezone'])
        || isset($_COOKIE['astro_location_mode']);
}

/** Devuelve una ruta interna segura, limitada a la instalación actual. */
function astronomyLocationReturnPath($value, ?string $scriptName = null): ?string
{
    if (!is_string($value) || $value === '' || $value !== trim($value)
        || preg_match('/[\x00-\x1F\x7F\\\\]/', $value) === 1
        || preg_match('/[\x00-\x1F\x7F\\\\]/', rawurldecode($value)) === 1) {
        return null;
    }
    $parts = parse_url($value);
    if ($parts === false) {
        return null;
    }
    foreach (['scheme', 'host', 'user', 'pass', 'port', 'fragment'] as $forbiddenPart) {
        if (array_key_exists($forbiddenPart, $parts)) return null;
    }
    $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';
    if (!str_starts_with($path, '/') || str_starts_with($path, '//') || rawurldecode($path) !== $path) {
        return null;
    }
    foreach (explode('/', $path) as $segment) {
        if ($segment === '.' || $segment === '..') return null;
    }
    $basePath = rtrim(dirname($scriptName ?? (string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\');
    $prefix = ($basePath === '' || $basePath === '.') ? '/' : $basePath . '/';
    if (!str_starts_with($path, $prefix)) return null;
    $query = is_string($parts['query'] ?? null) ? $parts['query'] : '';
    return $path . ($query !== '' ? '?' . $query : '');
}

/** Devuelve una etiqueta pública para un retorno interno ya validado. */
function astronomyLocationReturnLabel(string $returnPath): string
{
    $path = (string) (parse_url($returnPath, PHP_URL_PATH) ?? '');
    $key = basename(rtrim($path, '/'));
    $labels = [
        'explorador' => 'Volver al Explorador astronómico',
        'planificador.php' => 'Volver al Planificador',
        'cielo-de-hoy.php' => 'Volver a El cielo hoy',
        'cielo-de-esta-noche.php' => 'Volver a El cielo esta noche',
        'sol-y-luna.php' => 'Volver al Calendario solar y lunar',
        'luna-fecha-favorita.php' => 'Volver a La Luna de tu fecha favorita',
        'luna-interactiva.php' => 'Volver a Luna interactiva',
        'fotografia.php' => 'Volver a Fotografía',
        'eventos.php' => 'Volver a Eventos lunares',
        'eclipses.php' => 'Volver a Eclipses',
        'notificaciones.php' => 'Volver a Configurar notificaciones',
        'index.php' => 'Volver al inicio',
    ];
    return $labels[$key] ?? 'Volver a la página anterior';
}

/**
 * Valida y guarda una ubicación enviada exclusivamente desde ubicacion.php.
 *
 * @return array{ok:bool,message:string}
 */
function astronomySaveLocationRequest(array $input, ?callable $timezoneResolver = null): array
{
    $latitude = astronomyLocationCoordinate($input['latitude'] ?? null, -90, 90);
    $longitude = astronomyLocationCoordinate($input['longitude'] ?? null, -180, 180);
    $elevation = astronomyLocationElevation($input['elevation_meters'] ?? null);
    $mode = astronomyLocationMode($input['location_mode'] ?? null);
    $name = astronomyLocationName($input['location_name'] ?? null);
    if ($latitude === null || $longitude === null || $elevation === null || $mode === null || $name === null) {
        return ['ok' => false, 'message' => 'Revisá la localidad, las coordenadas y la elevación.'];
    }
    try {
        $timezone = ($timezoneResolver ?? 'astronomyResolveTimezone')($latitude, $longitude);
        $timezone = astronomyLocationTimezone($timezone);
        if ($timezone === null) {
            throw new RuntimeException('El resolvedor devolvió una zona horaria inválida.');
        }
    } catch (Throwable $error) {
        error_log('Aquellas Lunas timezone resolution failed while saving location: ' . $error->getMessage());
        return ['ok' => false, 'message' => 'No se pudo resolver la zona horaria. La ubicación anterior no fue modificada.'];
    }
    astronomyStoreLocation($latitude, $longitude, $elevation, $timezone, $mode, $name);
    return ['ok' => true, 'message' => 'Ubicación guardada.'];
}
