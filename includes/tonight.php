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
    return array_values(array_filter($objects, static function ($object): bool {
        if (!is_array($object) || !is_string($object['name'] ?? null) || trim($object['name']) === '') {
            return false;
        }
        $status = $object['visibility_status'] ?? null;
        return in_array($status, ['visible_now', 'visible_later'], true);
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
            'title' => 'El cielo esta noche',
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

function astronomyTonightRelevantObject(array $object, array $data, DateTimeImmutable $now, string $timezone): ?array
{
    if (!is_string($object['name'] ?? null) || trim($object['name']) === '') {
        return null;
    }
    $nightStart = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $nightEnd = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    $start = astronomyTonightDateTime($object['visibility_start'] ?? null, $timezone);
    $end = astronomyTonightDateTime($object['visibility_end'] ?? null, $timezone);
    if ($nightStart === null || $nightEnd === null || $start === null || $end === null) {
        return null;
    }
    $start = $start < $nightStart ? $nightStart : $start;
    $end = $end > $nightEnd ? $nightEnd : $end;
    $currentNight = $now >= $nightStart && $now <= $nightEnd;
    if ($end <= $start || ($currentNight && $end <= $now)) {
        return null;
    }
    $effectiveStatus = $currentNight && $start <= $now ? 'visible_now' : 'visible_later';
    $nextUseful = $effectiveStatus === 'visible_now' ? $now : $start;
    $nightSeconds = max(1, $nightEnd->getTimestamp() - $nightStart->getTimestamp());
    $position = ($nextUseful->getTimestamp() - $nightStart->getTimestamp()) / $nightSeconds;
    $object['_effective_status'] = $effectiveStatus;
    $object['_visibility_start'] = $start;
    $object['_visibility_end'] = $end;
    $object['_next_useful'] = $nextUseful;
    $object['_moment'] = $position <= .2 ? 'dusk' : ($position >= .72 ? 'dawn' : 'night');
    return $object;
}

function astronomyTonightRelevantObjects(array $objects, array $data, DateTimeImmutable $now, string $timezone): array
{
    $relevant = [];
    foreach ($objects as $object) {
        $prepared = is_array($object) ? astronomyTonightRelevantObject($object, $data, $now, $timezone) : null;
        if ($prepared !== null) {
            $relevant[] = $prepared;
        }
    }
    usort($relevant, static fn(array $a, array $b): int => $a['_next_useful'] <=> $b['_next_useful']);
    return $relevant;
}

function astronomyTonightPreparedSections(array $data, DateTimeImmutable $now, string $timezone): array
{
    return array_filter([
        'planets' => astronomyTonightRelevantObjects(is_array($data['planets'] ?? null) ? $data['planets'] : [], $data, $now, $timezone),
        'moon' => astronomyTonightRelevantObjects(is_array($data['moon'] ?? null) ? [$data['moon']] : [], $data, $now, $timezone),
        'stars' => astronomyTonightRelevantObjects(is_array($data['stars'] ?? null) ? $data['stars'] : [], $data, $now, $timezone),
        'deep_sky' => astronomyTonightRelevantObjects(is_array($data['deep_sky_objects'] ?? null) ? $data['deep_sky_objects'] : [], $data, $now, $timezone),
    ], static fn(array $objects): bool => $objects !== []);
}

function astronomyTonightNaturalSentence(array $object, array $data, DateTimeImmutable $now, string $timezone): string
{
    $nightStart = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $nightEnd = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    $start = $object['_visibility_start'] ?? null;
    $end = $object['_visibility_end'] ?? null;
    if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable || $nightStart === null || $nightEnd === null) {
        return '';
    }
    if (($object['_effective_status'] ?? '') === 'visible_now') {
        $remainingMinutes = max(0, (int) floor(($end->getTimestamp() - $now->getTimestamp()) / 60));
        if ($end >= $nightEnd->modify('-10 minutes')) {
            return 'Está visible ahora y seguirá viéndose hasta el amanecer.';
        }
        return ($remainingMinutes >= 180 ? 'Seguirá visible' : 'Está visible ahora')
            . ' hasta las ' . $end->format('H:i') . '.';
    }
    $fromDusk = abs($start->getTimestamp() - $nightStart->getTimestamp()) <= 45 * 60;
    $untilDawn = $end >= $nightEnd->modify('-10 minutes');
    if ($fromDusk && $untilDawn) {
        return 'Estará visible desde el anochecer hasta el amanecer.';
    }
    if ($fromDusk) {
        return 'Estará visible al comenzar la noche, hasta las ' . $end->format('H:i') . '.';
    }
    if ($untilDawn) {
        return 'Aparecerá a las ' . $start->format('H:i') . ' y podrá verse hasta el amanecer.';
    }
    $durationMinutes = (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
    if ($durationMinutes >= 240) {
        return 'Podrá verse durante gran parte de la noche, desde las ' . $start->format('H:i') . '.';
    }
    return 'Aparecerá a las ' . $start->format('H:i') . ' y seguirá visible hasta las ' . $end->format('H:i') . '.';
}

function astronomyTonightMomentLabel(array $object): string
{
    return match ($object['_moment'] ?? null) {
        'dusk' => 'Al anochecer',
        'dawn' => 'Antes del amanecer',
        default => 'Durante la noche',
    };
}

function astronomyTonightRelevantWindowLabel(array $data, DateTimeImmutable $now, string $timezone): string
{
    $prefix = astronomyTonightNightIsCurrent($data, $now, $timezone) ? 'Esta noche' : 'La próxima noche';
    $start = astronomyTonightTime($data['night']['start'] ?? null, $timezone);
    $end = astronomyTonightTime($data['night']['end'] ?? null, $timezone);
    return $start !== null && $end !== null ? $prefix . ': ' . $start . '–' . $end : $prefix;
}

function astronomyTonightRelevantState(array $data, DateTimeImmutable $now, string $timezone): string
{
    $start = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $end = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    if ($start === null || $end === null) {
        return 'La ventana nocturna no está disponible.';
    }
    if ($now < $start) {
        return 'La noche comenzará a las ' . $start->format('H:i') . '.';
    }
    $remainingMinutes = max(0, (int) floor(($end->getTimestamp() - $now->getTimestamp()) / 60));
    return $remainingMinutes >= 180
        ? 'La noche ya comenzó. Quedan varias horas para observar.'
        : 'La noche ya comenzó y continuará hasta las ' . $end->format('H:i') . '.';
}

function astronomyTonightHighlights(array $sections): array
{
    $highlights = array_slice($sections['planets'] ?? [], 0, 2);
    $stars = $sections['stars'] ?? [];
    usort($stars, static fn(array $a, array $b): int => ((float) ($a['magnitude'] ?? 99)) <=> ((float) ($b['magnitude'] ?? 99)));
    if (count($highlights) < 3 && $stars !== []) {
        $highlights[] = $stars[0];
    }
    if (count($highlights) < 2 && ($sections['moon'][0] ?? null) !== null) {
        $highlights[] = $sections['moon'][0];
    }
    return array_slice($highlights, 0, 3);
}
