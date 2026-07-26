<?php

require_once __DIR__ . '/api-client.php';

function astronomyTonightRequest(
    string $apiBaseUrl,
    array $location,
    string $date,
    string $detail,
    string $label,
    int $timeout = 12
): ?array {
    $query = http_build_query([
        'latitude' => $location['latitude'],
        'longitude' => $location['longitude'],
        'timezone' => $location['timezone'],
        'date' => $date,
        'detail' => $detail,
    ]);
    $result = astronomyApiRequest(rtrim($apiBaseUrl, '/') . '/v1/astronomy/tonight?' . $query, $label, $timeout);
    if (($location['mode'] ?? 'default') !== 'default' && astronomyApiRejectedLocationParameters($result)) {
        astronomyRecoverDefaultLocationFromApi($result);
    }
    if ($result['body'] === false || $result['http_code'] !== 200) {
        astronomyApiRecordValidation($label, null, false);
        return null;
    }
    $decoded = json_decode($result['body'], true);
    if (
        json_last_error() !== JSON_ERROR_NONE
        || !is_array($decoded)
        || !is_array($decoded['night'] ?? null)
        || !is_array($decoded['planets'] ?? null)
    ) {
        astronomyApiRecordValidation($label, false, false);
        error_log('Aquellas Lunas API ' . $label . ' invalid response: ' . json_last_error_msg());
        return null;
    }
    astronomyApiRecordValidation($label, true, true);
    return $decoded;
}

function astronomyTonightDateTime($value, string $timezone): ?DateTimeImmutable
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezone));
    } catch (Throwable $exception) {
        return null;
    }
}

function astronomyTonightTime($value, string $timezone): ?string
{
    return astronomyTonightDateTime($value, $timezone)?->format('H:i');
}

function astronomyTonightNightIsCurrent(array $data, DateTimeImmutable $now, string $timezone): bool
{
    $start = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $end = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    return $start !== null && $end !== null && $now >= $start && $now <= $end;
}

function astronomyTonightVisibleObjects(array $objects, bool $includeEarlier): array
{
    return array_values(array_filter($objects, static function ($object) use ($includeEarlier): bool {
        if (!is_array($object) || !is_string($object['name'] ?? null) || trim($object['name']) === '') {
            return false;
        }
        $status = $object['visibility_status'] ?? null;
        return in_array($status, $includeEarlier
            ? ['visible_now', 'visible_later', 'visible_earlier']
            : ['visible_now', 'visible_later'], true);
    }));
}

function astronomyTonightDirection(array $object): ?string
{
    $direction = is_string($object['direction'] ?? null) ? trim($object['direction']) : '';
    if ($direction === '') {
        return null;
    }
    return $direction === 'arriba' ? 'arriba' : 'hacia el ' . $direction;
}

function astronomyTonightPlanetSentence(array $object, string $timezone): string
{
    $name = trim((string) ($object['name'] ?? ''));
    $status = $object['visibility_status'] ?? '';
    $start = astronomyTonightTime($object['visibility_start'] ?? null, $timezone);
    $end = astronomyTonightTime($object['visibility_end'] ?? null, $timezone);
    if ($status === 'visible_now') {
        $text = $name . ' está visible ahora';
        $direction = astronomyTonightDirection($object);
        if ($direction !== null) {
            $text .= ' ' . $direction;
        }
        return $text . ($end !== null ? ', hasta las ' . $end . '.' : '.');
    }
    if ($status === 'visible_later') {
        return $name . ($start !== null ? ' aparecerá desde las ' . $start . '.' : ' aparecerá más tarde.');
    }
    if ($status === 'visible_earlier') {
        return $name . ($end !== null ? ' fue visible hasta las ' . $end . '.' : ' fue visible antes esta noche.');
    }
    return '';
}

function astronomyTonightSummaryPresentation(
    array $data,
    DateTimeImmutable $now,
    string $timezone
): array {
    $planets = astronomyTonightVisibleObjects(
        is_array($data['planets'] ?? null) ? $data['planets'] : [],
        astronomyTonightNightIsCurrent($data, $now, $timezone)
    );
    if ($planets === []) {
        return [
            'title' => 'El cielo de esta noche',
            'text' => 'Esta noche no habrá planetas visibles a simple vista desde tu ubicación.',
            'planets' => [],
        ];
    }
    $count = count($planets);
    return [
        'title' => $count === 1
            ? 'Esta noche se verá 1 planeta'
            : 'Esta noche se verán ' . $count . ' planetas',
        'text' => implode(' ', array_values(array_filter(array_map(
            static fn(array $planet): string => astronomyTonightPlanetSentence($planet, $timezone),
            $planets
        )))),
        'planets' => $planets,
    ];
}

function astronomyTonightWindowLabel(array $data, string $timezone): string
{
    $start = astronomyTonightTime($data['night']['start'] ?? null, $timezone);
    $end = astronomyTonightTime($data['night']['end'] ?? null, $timezone);
    $polarState = $data['night']['polar_state'] ?? null;
    if ($start !== null && $end !== null) {
        return 'Esta noche: ' . $start . '–' . $end;
    }
    if ($polarState === 'continuous_darkness') {
        return 'Oscuridad continua durante esta noche';
    }
    if ($polarState === 'no_civil_darkness') {
        return 'Esta noche no tendrá oscuridad civil';
    }
    return 'Ventana nocturna no disponible';
}

function astronomyTonightTemporalState(array $data, DateTimeImmutable $now, string $timezone): string
{
    $start = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $end = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    if ($start === null || $end === null) {
        return ($data['night']['polar_state'] ?? null) === 'no_civil_darkness'
            ? 'No habrá una ventana de oscuridad civil para esta fecha.'
            : 'La ventana nocturna no está disponible.';
    }
    if ($now < $start) {
        return 'La noche todavía no comenzó.';
    }
    if ($now <= $end) {
        return 'La noche está en curso.';
    }
    return 'La ventana de esta noche ya terminó.';
}

function astronomyTonightObjectSentence(array $object, string $timezone): string
{
    $status = $object['visibility_status'] ?? '';
    $start = astronomyTonightTime($object['visibility_start'] ?? null, $timezone);
    $end = astronomyTonightTime($object['visibility_end'] ?? null, $timezone);
    $aid = match ($object['observation_aid'] ?? null) {
        'naked_eye' => ' a simple vista',
        'binoculars' => ', preferentemente con binoculares,',
        'telescope' => ', con telescopio,',
        default => '',
    };
    if ($status === 'visible_now') {
        $text = 'Visible ahora' . $aid;
        $direction = astronomyTonightDirection($object);
        if ($direction !== null) {
            $text .= $direction === 'arriba'
                ? (str_ends_with($text, ',') ? ' arriba' : ', arriba')
                : ' ' . $direction;
        }
        $separator = str_ends_with($text, ',') ? ' ' : ', ';
        return $text . ($end !== null ? $separator . 'hasta las ' . $end . '.' : $separator . 'durante el resto de la noche.');
    }
    if ($status === 'visible_later') {
        if ($start === null) {
            return 'Será visible' . $aid . ' más tarde esta noche.';
        }
        return 'Será visible' . $aid . ' desde las ' . $start
            . ($end !== null ? ' hasta las ' . $end . '.' : ' hasta el amanecer.');
    }
    if ($status === 'visible_earlier') {
        if ($start !== null && $end !== null) {
            return 'Fue visible' . $aid . ' desde las ' . $start . ' hasta las ' . $end . '.';
        }
        return $end !== null
            ? 'Fue visible' . $aid . ' hasta las ' . $end . '.'
            : 'Fue visible' . $aid . ' antes esta noche.';
    }
    return '';
}

function astronomyTonightObservationAid($value): ?string
{
    return match ($value) {
        'naked_eye' => 'A simple vista',
        'binoculars' => 'Mejor con binoculares',
        'telescope' => 'Requiere telescopio',
        default => null,
    };
}

function astronomyTonightConstellationName(array $object): ?string
{
    $constellation = $object['constellation'] ?? null;
    if (!is_array($constellation) || !is_string($constellation['name'] ?? null)) {
        return null;
    }
    $name = trim($constellation['name']);
    return $name !== '' ? $name : null;
}

function astronomyTonightMoonProximity(array $object): ?string
{
    if (
        ($object['object_kind'] ?? null) === 'moon'
        || ($object['id'] ?? null) === 'moon'
        || ($object['near_moon'] ?? false) !== true
    ) {
        return null;
    }
    return match ($object['moon_proximity'] ?? null) {
        'very_close' => 'Se verá muy cerca de la Luna.',
        'close' => 'Se verá cerca de la Luna.',
        default => null,
    };
}

function astronomyTonightSections(array $data): array
{
    return array_filter([
        'Planetas' => astronomyTonightVisibleObjects(
            is_array($data['planets'] ?? null) ? $data['planets'] : [],
            true
        ),
        'Luna' => astronomyTonightVisibleObjects(
            is_array($data['moon'] ?? null) ? [$data['moon']] : [],
            true
        ),
        'Estrellas' => astronomyTonightVisibleObjects(
            is_array($data['stars'] ?? null) ? $data['stars'] : [],
            true
        ),
        'Otros objetos' => astronomyTonightVisibleObjects(
            is_array($data['deep_sky_objects'] ?? null) ? $data['deep_sky_objects'] : [],
            true
        ),
    ], static fn(array $objects): bool => $objects !== []);
}
