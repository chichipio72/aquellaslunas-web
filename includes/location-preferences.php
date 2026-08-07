<?php

const ASTRONOMY_DEFAULT_LATITUDE = -34.53;
const ASTRONOMY_DEFAULT_LONGITUDE = -58.48;
const ASTRONOMY_DEFAULT_ELEVATION_METERS = 0.0;
const ASTRONOMY_DEFAULT_TIMEZONE = 'America/Argentina/Buenos_Aires';
const ASTRONOMY_LOCATION_COOKIE_VERSION = '2';
const ASTRONOMY_LOCATION_COOKIE_DAYS = 400;
const ASTRONOMY_LOCATION_COOKIE_LIFETIME = 60 * 60 * 24 * ASTRONOMY_LOCATION_COOKIE_DAYS;

function astronomyLocationCoordinate($value, float $minimum, float $maximum): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $coordinate = (float) $value;
    return is_finite($coordinate) && $coordinate >= $minimum && $coordinate <= $maximum
        ? round($coordinate, 4)
        : null;
}

function astronomyLocationTimezone($value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $timezone = trim($value);
    if (!in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC), true)) {
        return null;
    }
    return $timezone === 'America/Buenos_Aires'
        ? 'America/Argentina/Buenos_Aires'
        : $timezone;
}

function astronomyLocationElevation($value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $elevation = (float) $value;
    return is_finite($elevation) && $elevation >= -500.0 && $elevation <= 10000.0
        ? round($elevation, 1)
        : null;
}

function astronomyLocationMode($value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    // "custom" fue el valor histórico. Se conserva y se normaliza al leerlo.
    return match ($value) {
        'default' => 'default',
        'custom', 'manual', 'map' => 'manual',
        'geolocation' => 'geolocation',
        default => null,
    };
}

function astronomyLocationCoordinateLabel(float $latitude, float $longitude): string
{
    return number_format($latitude, 2, '.', '') . ', ' . number_format($longitude, 2, '.', '');
}

function astronomyLocationName($value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    return $name !== '' && $length <= 80 && !str_contains($name, ',') ? $name : null;
}

function astronomyStoredLocationLabel(float $latitude, float $longitude, string $mode): string
{
    if ($mode === 'default') {
        return 'Buenos Aires';
    }
    return astronomyLocationName($_COOKIE['astro_location_name'] ?? null)
        ?? astronomyLocationCoordinateLabel($latitude, $longitude);
}

function astronomyLocationCookieOptions(?int $expires = null): array
{
    $secure = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    return [
        'expires' => $expires ?? time() + ASTRONOMY_LOCATION_COOKIE_LIFETIME,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ];
}

function astronomyLocationHasCompleteCookies(): bool
{
    return astronomyLocationCoordinate($_COOKIE['astro_latitude'] ?? null, -90, 90) !== null
        && astronomyLocationCoordinate($_COOKIE['astro_longitude'] ?? null, -180, 180) !== null
        && astronomyLocationElevation($_COOKIE['astro_elevation'] ?? null) !== null
        && astronomyLocationTimezone($_COOKIE['astro_timezone'] ?? null) !== null
        && astronomyLocationMode($_COOKIE['astro_location_mode'] ?? null) !== null
        && astronomyLocationName($_COOKIE['astro_location_name'] ?? null) !== null
        && ($_COOKIE['astro_location_version'] ?? null) === ASTRONOMY_LOCATION_COOKIE_VERSION;
}

function astronomyLocationIsConfirmed(): bool
{
    if (!astronomyLocationHasCompleteCookies()) {
        return false;
    }
    $mode = astronomyLocationMode($_COOKIE['astro_location_mode'] ?? null);
    return $mode !== 'default' || ($_COOKIE['astro_location_confirmed'] ?? null) === '1';
}

function astronomyLocationIntroWasSeen(): bool
{
    return ($_COOKIE['astro_location_intro_seen'] ?? null) === '1';
}

function astronomyStoreLocationIntroSeen(): void
{
    setcookie('astro_location_intro_seen', '1', astronomyLocationCookieOptions());
}

function astronomyClearStoredLocation(bool $clearIntro = false): void
{
    $options = astronomyLocationCookieOptions(time() - 3600);
    foreach ([
        'astro_latitude',
        'astro_longitude',
        'astro_elevation',
        'astro_timezone',
        'astro_location_version',
        'astro_location_mode',
        'astro_location_name',
        'astro_location_confirmed',
    ] as $cookie) {
        setcookie($cookie, '', $options);
    }
    if ($clearIntro) {
        setcookie('astro_location_intro_seen', '', $options);
    }
}

function astronomyReverseGeocode(float $latitude, float $longitude): string
{
    $fallback = astronomyLocationCoordinateLabel($latitude, $longitude);
    $GLOBALS['astronomy_location_geocoder_status'] = 'not_requested';
    $endpoint = trim((string) (getenv('ASTRONOMY_REVERSE_GEOCODER_URL') ?: 'https://nominatim.openstreetmap.org/reverse'));
    if (filter_var($endpoint, FILTER_VALIDATE_URL) === false || parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
        error_log('Aquellas Lunas reverse geocoder disabled: invalid HTTPS endpoint.');
        $GLOBALS['astronomy_location_geocoder_status'] = 'invalid_endpoint';
        return $fallback;
    }

    $cachePath = sys_get_temp_dir() . '/aquellas-lunas-geocoder-cache.json';
    $cacheHandle = @fopen($cachePath, 'c+');
    if ($cacheHandle !== false) {
        @chmod($cachePath, 0666);
    }
    if ($cacheHandle === false || !flock($cacheHandle, LOCK_EX)) {
        if (is_resource($cacheHandle)) {
            fclose($cacheHandle);
        }
        error_log('Aquellas Lunas reverse geocoder cache is unavailable.');
        $GLOBALS['astronomy_location_geocoder_status'] = 'cache_unavailable';
        return $fallback;
    }

    $contents = stream_get_contents($cacheHandle);
    $cache = is_string($contents) ? json_decode($contents, true) : null;
    $cache = is_array($cache) ? $cache : ['last_request_at' => 0.0, 'items' => []];
    $key = number_format($latitude, 4, '.', '') . ',' . number_format($longitude, 4, '.', '');
    $cached = $cache['items'][$key] ?? null;
    if (is_array($cached) && (int) ($cached['expires_at'] ?? 0) > time()) {
        $name = astronomyLocationName($cached['name'] ?? null) ?? $fallback;
        $GLOBALS['astronomy_location_geocoder_status'] = $name === $fallback ? 'cached_fallback' : 'cached';
        flock($cacheHandle, LOCK_UN);
        fclose($cacheHandle);
        return $name;
    }

    $elapsed = microtime(true) - (float) ($cache['last_request_at'] ?? 0.0);
    if ($elapsed < 1.0) {
        usleep((int) ((1.0 - $elapsed) * 1_000_000));
    }
    $url = $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query([
        'format' => 'jsonv2',
        'addressdetails' => 1,
        'zoom' => 12,
        'lat' => $latitude,
        'lon' => $longitude,
        'accept-language' => 'es',
    ]);
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_USERAGENT => (string) (getenv('ASTRONOMY_REVERSE_GEOCODER_USER_AGENT')
            ?: 'AquellasLunas/1.0 (https://aquellaslunas.com.ar)'),
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    $cache['last_request_at'] = microtime(true);

    $decoded = is_string($response) ? json_decode($response, true) : null;
    $address = is_array($decoded) && is_array($decoded['address'] ?? null) ? $decoded['address'] : [];
    $name = null;
    foreach (['city', 'municipality', 'locality', 'town', 'village', 'county'] as $field) {
        $name = astronomyLocationName($address[$field] ?? null);
        if ($name !== null) {
            break;
        }
    }
    if ($status !== 200 || $name === null) {
        error_log('Aquellas Lunas reverse geocoder failed [http_status=' . $status
            . ' curl_error=' . ($error !== '' ? 'yes' : 'no') . ' locality=' . ($name !== null ? 'yes' : 'no') . '].');
        $name = $fallback;
        $GLOBALS['astronomy_location_geocoder_status'] = $status === 200
            ? 'invalid_response'
            : 'http_' . $status;
    } else {
        $GLOBALS['astronomy_location_geocoder_status'] = 'resolved';
    }
    $cache['items'][$key] = ['name' => $name, 'expires_at' => time() + 60 * 60 * 24 * 30];
    rewind($cacheHandle);
    ftruncate($cacheHandle, 0);
    fwrite($cacheHandle, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($cacheHandle);
    flock($cacheHandle, LOCK_UN);
    fclose($cacheHandle);
    return $name;
}

function astronomyStoreLocation(
    float $latitude,
    float $longitude,
    float $elevationMeters,
    string $timezone,
    string $mode,
    ?string $name = null,
    bool $confirmed = true
): void
{
    $mode = astronomyLocationMode($mode) ?? 'default';
    $options = astronomyLocationCookieOptions();
    $values = [
        'astro_latitude' => (string) $latitude,
        'astro_longitude' => (string) $longitude,
        'astro_elevation' => (string) $elevationMeters,
        'astro_timezone' => $timezone,
        'astro_location_mode' => $mode,
        'astro_location_name' => astronomyLocationName($name)
            ?? ($mode === 'default' ? 'Buenos Aires' : astronomyLocationCoordinateLabel($latitude, $longitude)),
        'astro_location_confirmed' => $confirmed ? '1' : '0',
        'astro_location_intro_seen' => '1',
        'astro_location_version' => ASTRONOMY_LOCATION_COOKIE_VERSION,
    ];
    foreach ($values as $cookie => $value) {
        setcookie($cookie, $value, $options);
        // La ubicación corregida debe ser la fuente de verdad en esta misma petición.
        $_COOKIE[$cookie] = $value;
    }
}

function astronomyStoreDefaultLocation(): void
{
    astronomyStoreLocation(
        ASTRONOMY_DEFAULT_LATITUDE,
        ASTRONOMY_DEFAULT_LONGITUDE,
        ASTRONOMY_DEFAULT_ELEVATION_METERS,
        ASTRONOMY_DEFAULT_TIMEZONE,
        'default',
        'Buenos Aires'
    );
}

function astronomyLocationStatusMessage(string $status, ?string $debugDetail = null): string
{
    $allowedDebugDetails = [
        'latitud ausente',
        'latitud no numérica',
        'latitud fuera de rango',
        'longitud ausente',
        'longitud no numérica',
        'longitud fuera de rango',
        'zona horaria ausente',
        'contexto no seguro',
        'permiso denegado',
        'error del servidor',
        'respuesta de geocodificación inválida',
        'geolocalización no disponible',
    ];
    if ($status === 'invalid' && in_array($debugDetail, $allowedDebugDetails, true)) {
        return 'No se usó la ubicación: ' . $debugDetail . '. Se mantuvo Buenos Aires.';
    }
    return match ($status) {
        'denied' => 'El navegador rechazó el permiso de ubicación. Se mantuvo Buenos Aires.',
        'unavailable' => 'El navegador no pudo determinar tu ubicación. Se mantuvo Buenos Aires.',
        'timeout' => 'La ubicación demoró demasiado en responder. Se mantuvo Buenos Aires.',
        'unsupported' => 'La geolocalización no está disponible. Verificá que el sitio use HTTPS. Se mantuvo Buenos Aires.',
        'invalid' => 'El navegador devolvió una ubicación inválida. Se mantuvo Buenos Aires.',
        'stored_invalid' => 'La ubicación guardada no era válida. Se restableció Buenos Aires.',
        'api_rejected' => 'La API rechazó los parámetros de la ubicación guardada. Se restableció Buenos Aires.',
        default => '',
    };
}

function astronomyApiRejectedLocationParameters(array $result): bool
{
    if (!in_array((int) ($result['http_code'] ?? 0), [400, 422], true)) {
        return false;
    }
    $body = strtolower(is_string($result['body'] ?? null) ? $result['body'] : '');
    return preg_match('/\b(latitude|longitude|timezone)\b/', $body) === 1;
}

function astronomyRecoverDefaultLocationFromApi(array $result): void
{
    error_log('Aquellas Lunas location parameters rejected by API with HTTP status '
        . (int) ($result['http_code'] ?? 0) . '; preserving the confirmed location.');
}
