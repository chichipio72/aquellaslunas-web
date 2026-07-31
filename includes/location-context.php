<?php

require_once __DIR__ . '/location-preferences.php';

/**
 * Fuente única de verdad para la ubicación activa.
 *
 * @return array{name:string,latitude:float,longitude:float,timezone:string,mode:string}
 */
function astronomyLocationContext(): array
{
    $hadStoredValues = astronomyLocationRequestWasInvalid();
    $fallback = [
        'name' => 'Buenos Aires',
        'latitude' => ASTRONOMY_DEFAULT_LATITUDE,
        'longitude' => ASTRONOMY_DEFAULT_LONGITUDE,
        'timezone' => ASTRONOMY_DEFAULT_TIMEZONE,
        'mode' => 'default',
        'confirmed' => false,
        'initial' => true,
        'stored_invalid' => false,
    ];
    $latitude = astronomyLocationCoordinate($_COOKIE['astro_latitude'] ?? null, -90, 90);
    $longitude = astronomyLocationCoordinate($_COOKIE['astro_longitude'] ?? null, -180, 180);
    $timezone = astronomyLocationTimezone($_COOKIE['astro_timezone'] ?? null);
    $mode = astronomyLocationMode($_COOKIE['astro_location_mode'] ?? null);

    if ($latitude === null || $longitude === null || $timezone === null || $mode === null) {
        if ($hadStoredValues) {
            astronomyClearStoredLocation(true);
            $fallback['stored_invalid'] = true;
        }
        return $fallback;
    }
    $name = astronomyStoredLocationLabel($latitude, $longitude, $mode);
    $coordinateLabel = astronomyLocationCoordinateLabel($latitude, $longitude);
    if ($mode !== 'default' && ($name === $coordinateLabel || astronomyLocationName($_COOKIE['astro_location_name'] ?? null) === null)) {
        $resolvedName = astronomyReverseGeocode($latitude, $longitude);
        if ($resolvedName !== $coordinateLabel) {
            $name = $resolvedName;
            astronomyStoreLocation($latitude, $longitude, $timezone, $mode, $name);
        }
    }
    return [
        'name' => $name,
        'latitude' => $latitude,
        'longitude' => $longitude,
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

/**
 * Valida y guarda una ubicación enviada exclusivamente desde ubicacion.php.
 *
 * @return array{ok:bool,message:string}
 */
function astronomySaveLocationRequest(array $input): array
{
    $latitude = astronomyLocationCoordinate($input['latitude'] ?? null, -90, 90);
    $longitude = astronomyLocationCoordinate($input['longitude'] ?? null, -180, 180);
    $timezone = astronomyLocationTimezone($input['timezone'] ?? null);
    $mode = astronomyLocationMode($input['location_mode'] ?? null);
    $name = astronomyLocationName($input['location_name'] ?? null);
    if ($latitude === null || $longitude === null || $timezone === null || $mode === null || $name === null) {
        return ['ok' => false, 'message' => 'Revisá la localidad, las coordenadas y la zona horaria.'];
    }
    astronomyStoreLocation($latitude, $longitude, $timezone, $mode, $name);
    return ['ok' => true, 'message' => 'Ubicación guardada.'];
}
