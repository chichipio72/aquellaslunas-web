<?php

require_once __DIR__ . '/editorial-configuration.php';
require_once __DIR__ . '/event-type-configuration.php';

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

function astronomyTonightConjunctionObjects(array $event): ?array
{
    $title = is_string($event['title'] ?? null) ? trim($event['title']) : '';
    if (!preg_match('/^Conjunción\s+(.+?)\s*[–-]\s*(.+)$/u', $title, $matches)) {
        return null;
    }
    $first = trim($matches[1]);
    $second = trim($matches[2]);
    if ($first === '' || $second === '') {
        return null;
    }
    if ($first === 'Luna') {
        return ['La Luna', $second];
    }
    if ($second === 'Luna') {
        return ['La Luna', $first];
    }
    return null;
}

function astronomyTonightNaturalList(array $names): string
{
    $names = array_values(array_filter(array_map(
        static fn($name): string => is_string($name) ? trim($name) : '',
        $names
    ), static fn(string $name): bool => $name !== ''));
    if (count($names) < 2) {
        return $names[0] ?? '';
    }
    $last = array_pop($names);
    return implode(', ', $names) . ' y ' . $last;
}

function astronomyTonightComparisonKey(string $name, ?bool $mbstringAvailable = null): string
{
    $name = preg_replace('/^La\s+/u', '', trim($name)) ?? trim($name);
    $useMbstring = $mbstringAvailable ?? function_exists('mb_strtolower');
    return $useMbstring && function_exists('mb_strtolower')
        ? mb_strtolower($name, 'UTF-8')
        : strtolower($name);
}

function astronomyTonightCardText(
    ?array $data,
    array $events,
    DateTimeImmutable $now,
    string $timezone
): ?string {
    if ($data === null) {
        return null;
    }
    $nightStart = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $nightEnd = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    $planets = astronomyTonightVisibleObjects(
        is_array($data['planets'] ?? null) ? $data['planets'] : [],
        astronomyTonightNightIsCurrent($data, $now, $timezone)
    );

    $encounterSentence = '';
    $encounterObjects = [];
    if ($nightStart !== null && $nightEnd !== null) {
        foreach ($events as $event) {
            if (!is_array($event) || ($event['type'] ?? '') !== 'conjunction') {
                continue;
            }
            $date = astronomyTonightDateTime($event['datetime'] ?? null, $timezone);
            $details = is_array($event['details'] ?? null) ? $event['details'] : [];
            $objects = astronomyTonightConjunctionObjects($event);
            if (
                $date === null
                || $date < $nightStart
                || $date > $nightEnd
                || ($details['both_above_horizon'] ?? false) !== true
                || $objects === null
            ) {
                continue;
            }
            $encounterObjects = $objects;
            $encounterSentence = astronomyEditorialText('tonight.card.encounter', ['objetos' => astronomyTonightNaturalList($objects), 'hora' => $date->format('H:i')]);
            break;
        }
    }

    $encounterLookup = array_map(
        static fn(string $name): string => astronomyTonightComparisonKey($name),
        $encounterObjects
    );
    $planetNames = [];
    $planetMaximum = (int) astronomyEditorialNumber('tonight.card.planets_max');
    foreach ($planets as $planet) {
        if (count($planetNames) >= $planetMaximum) break;
        $name = trim((string) ($planet['name'] ?? ''));
        if ($name === '' || in_array(astronomyTonightComparisonKey($name), $encounterLookup, true)) {
            continue;
        }
        $planetNames[] = $name;
    }
    $visibleSentence = '';
    if ($planetNames !== []) {
        $visibleSentence = astronomyEditorialText(count($planetNames) === 1 ? 'tonight.card.visible_one' : 'tonight.card.visible_many', [
            'inicio' => astronomyEditorialText($encounterSentence !== '' ? 'tonight.card.prefix.also' : 'tonight.card.prefix.tonight'),
            'objetos' => astronomyTonightNaturalList($planetNames),
        ]);
    }
    if ($encounterSentence !== '' || $visibleSentence !== '') {
        return trim($encounterSentence . ' ' . $visibleSentence);
    }

    $stars = astronomyTonightVisibleObjects(
        is_array($data['stars'] ?? null) ? $data['stars'] : [],
        astronomyTonightNightIsCurrent($data, $now, $timezone)
    );
    if ($stars !== [] && astronomyEditorialNumber('tonight.card.stars_max') > 0) {
        $names = array_slice(array_column($stars, 'name'), 0, (int) astronomyEditorialNumber('tonight.card.stars_max'));
        $starMessage = count($names) === 1 ? 'tonight.card.star_one' : (count($names) === 2 ? 'tonight.card.star_two' : 'tonight.card.star_many');
        return astronomyEditorialText($starMessage, ['objetos' => astronomyTonightNaturalList($names)]);
    }
    return astronomyEditorialText('tonight.card.no_planets');
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
    return $direction === 'arriba' ? astronomyEditorialText('tonight.direction.overhead') : astronomyEditorialText('tonight.direction.toward', ['direccion' => $direction]);
}

function astronomyTonightPlanetSentence(array $object, string $timezone): string
{
    $name = trim((string) ($object['name'] ?? ''));
    $status = $object['visibility_status'] ?? '';
    $start = astronomyTonightTime($object['visibility_start'] ?? null, $timezone);
    $end = astronomyTonightTime($object['visibility_end'] ?? null, $timezone);
    if ($status === 'visible_now') {
        $direction = astronomyTonightDirection($object);
        return astronomyEditorialText($end !== null ? 'tonight.planet.now_until' : 'tonight.planet.now', [
            'nombre' => $name, 'direccion' => $direction !== null ? ' ' . $direction : '', 'fin' => $end ?? '',
        ]);
    }
    if ($status === 'visible_later') {
        return astronomyEditorialText($start !== null ? 'tonight.planet.later_at' : 'tonight.planet.later', ['nombre' => $name, 'inicio' => $start ?? '']);
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
            'text' => astronomyEditorialText('tonight.card.no_planets'),
            'planets' => [],
        ];
    }
    $count = count($planets);
    return [
        'title' => $count === 1
            ? astronomyEditorialText('tonight.summary.one_planet')
            : astronomyEditorialText('tonight.summary.many_planets', ['cantidad' => (string) $count]),
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
        return astronomyEditorialText('tonight.window.current', ['inicio' => $start, 'fin' => $end]);
    }
    if ($polarState === 'continuous_darkness') {
        return astronomyEditorialText('tonight.window.continuous_darkness');
    }
    if ($polarState === 'no_civil_darkness') {
        return astronomyEditorialText('tonight.window.no_civil_darkness');
    }
    return astronomyEditorialText('tonight.window.unavailable');
}

function astronomyTonightTemporalState(array $data, DateTimeImmutable $now, string $timezone): string
{
    $start = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $end = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    if ($start === null || $end === null) {
        return ($data['night']['polar_state'] ?? null) === 'no_civil_darkness'
            ? astronomyEditorialText('tonight.temporal.no_civil_darkness')
            : astronomyEditorialText('tonight.temporal.unavailable');
    }
    if ($now < $start) {
        return astronomyEditorialText('tonight.temporal.not_started');
    }
    if ($now <= $end) {
        return astronomyEditorialText('tonight.temporal.in_progress');
    }
    return astronomyEditorialText('tonight.temporal.finished');
}

function astronomyTonightObjectSentence(array $object, string $timezone): string
{
    $status = $object['visibility_status'] ?? '';
    $start = astronomyTonightTime($object['visibility_start'] ?? null, $timezone);
    $end = astronomyTonightTime($object['visibility_end'] ?? null, $timezone);
    $aid = match ($object['observation_aid'] ?? null) {
        'naked_eye' => astronomyEditorialText('tonight.aid.fragment.naked_eye'),
        'binoculars' => astronomyEditorialText('tonight.aid.fragment.binoculars'),
        'telescope' => astronomyEditorialText('tonight.aid.fragment.telescope'),
        default => '',
    };
    if ($status === 'visible_now') {
        $direction = astronomyTonightDirection($object);
        $directionText = '';
        if ($direction !== null) {
            $directionText = ($object['direction'] ?? null) === 'arriba'
                ? (str_ends_with($aid, ',') ? ' ' . $direction : ', ' . $direction)
                : ' ' . $direction;
        }
        $separator = $directionText === '' && str_ends_with($aid, ',') ? ' ' : ', ';
        return astronomyEditorialText($end !== null ? 'tonight.object.now_until' : 'tonight.object.now_rest', [
            'ayuda' => $aid, 'direccion' => $directionText,
            'separador' => $separator, 'fin' => $end ?? '',
        ]);
    }
    if ($status === 'visible_later') {
        if ($start === null) {
            return astronomyEditorialText('tonight.object.later', ['ayuda' => $aid]);
        }
        return astronomyEditorialText($end !== null ? 'tonight.object.window' : 'tonight.object.until_dawn', ['ayuda' => $aid, 'inicio' => $start, 'fin' => $end ?? '']);
    }
    return '';
}

function astronomyTonightObservationAid($value): ?string
{
    return match ($value) {
        'naked_eye' => astronomyEditorialText('tonight.aid.naked_eye'),
        'binoculars' => astronomyEditorialText('tonight.aid.binoculars'),
        'telescope' => astronomyEditorialText('tonight.aid.telescope'),
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
        'very_close' => astronomyEditorialText('tonight.proximity.very_close'),
        'close' => astronomyEditorialText('tonight.proximity.close'),
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
    $object['_moment'] = $position <= astronomyEditorialNumber('tonight.dusk_max_ratio') ? 'dusk' : ($position >= astronomyEditorialNumber('tonight.dawn_min_ratio') ? 'dawn' : 'night');
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

function astronomyTonightHasRelevantMoonEvent(array $data, array $events, string $timezone): bool
{
    $nightStart = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $nightEnd = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    if ($nightStart === null || $nightEnd === null) {
        return false;
    }
    $nightDates = [$nightStart->format('Y-m-d') => true, $nightEnd->format('Y-m-d') => true];
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $type = is_string($event['type'] ?? null) ? $event['type'] : '';
        $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : '';
        $date = astronomyTonightDateTime($event['datetime'] ?? null, $timezone);
        if ($date === null) {
            continue;
        }
        if ($type === 'moon_phase' && $subtype === 'full_moon' && astronomyEventRelevantTonight($event) && isset($nightDates[$date->format('Y-m-d')])) {
            return true;
        }
        if (astronomyEventRelevantTonight($event) && $date >= $nightStart && $date <= $nightEnd) {
            return true;
        }
    }
    return false;
}

function astronomyTonightApplyMoonEditorialPriority(array $sections, bool $moonIsRelevant): array
{
    if (!$moonIsRelevant) {
        unset($sections['moon']);
    }
    return $sections;
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
        if ($end >= $nightEnd->modify('-' . (int) astronomyEditorialNumber('tonight.dawn_tolerance_minutes') . ' minutes')) {
            return astronomyEditorialText('tonight.visible.until_dawn');
        }
        return astronomyEditorialText($remainingMinutes >= astronomyEditorialNumber('tonight.long_remaining_minutes') ? 'tonight.visible.long_until_time' : 'tonight.visible.until_time', ['fin' => $end->format('H:i')]);
    }
    $fromDusk = abs($start->getTimestamp() - $nightStart->getTimestamp()) <= astronomyEditorialNumber('tonight.dusk_tolerance_minutes') * 60;
    $untilDawn = $end >= $nightEnd->modify('-' . (int) astronomyEditorialNumber('tonight.dawn_tolerance_minutes') . ' minutes');
    if ($fromDusk && $untilDawn) {
        return astronomyEditorialText('tonight.future.all_night');
    }
    if ($fromDusk) {
        return astronomyEditorialText('tonight.future.from_dusk', ['fin' => $end->format('H:i')]);
    }
    if ($untilDawn) {
        return astronomyEditorialText('tonight.future.until_dawn', ['inicio' => $start->format('H:i')]);
    }
    $durationMinutes = (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
    if ($durationMinutes >= astronomyEditorialNumber('tonight.long_window_minutes')) {
        return astronomyEditorialText('tonight.future.long', ['inicio' => $start->format('H:i')]);
    }
    return astronomyEditorialText('tonight.future.until_time', ['inicio' => $start->format('H:i'), 'fin' => $end->format('H:i')]);
}

function astronomyTonightMomentLabel(array $object): string
{
    return match ($object['_moment'] ?? null) {
        'dusk' => astronomyEditorialText('tonight.moment.dusk'),
        'dawn' => astronomyEditorialText('tonight.moment.dawn'),
        default => astronomyEditorialText('tonight.moment.night'),
    };
}

function astronomyTonightRelevantWindowLabel(array $data, DateTimeImmutable $now, string $timezone): string
{
    $prefix = astronomyEditorialText(astronomyTonightNightIsCurrent($data, $now, $timezone) ? 'tonight.relevant.current' : 'tonight.relevant.next');
    $start = astronomyTonightTime($data['night']['start'] ?? null, $timezone);
    $end = astronomyTonightTime($data['night']['end'] ?? null, $timezone);
    return $start !== null && $end !== null ? astronomyEditorialText('tonight.relevant.range', ['periodo' => $prefix, 'inicio' => $start, 'fin' => $end]) : $prefix;
}

function astronomyTonightRelevantState(array $data, DateTimeImmutable $now, string $timezone): string
{
    $start = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $end = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    if ($start === null || $end === null) {
        return astronomyEditorialText('tonight.temporal.unavailable');
    }
    if ($now < $start) {
        return astronomyEditorialText('tonight.state.starts', ['inicio' => $start->format('H:i')]);
    }
    $remainingMinutes = max(0, (int) floor(($end->getTimestamp() - $now->getTimestamp()) / 60));
    return $remainingMinutes >= astronomyEditorialNumber('tonight.long_remaining_minutes')
        ? astronomyEditorialText('tonight.state.hours')
        : astronomyEditorialText('tonight.state.until', ['fin' => $end->format('H:i')]);
}

function astronomyTonightHighlights(array $sections): array
{
    $stars = $sections['stars'] ?? [];
    usort($stars, static fn(array $a, array $b): int => ((float) ($a['magnitude'] ?? 99)) <=> ((float) ($b['magnitude'] ?? 99)));
    $categories = [
        'planets' => ['items' => $sections['planets'] ?? [], 'max' => (int) astronomyEditorialNumber('tonight.highlights.planets_max'), 'order' => astronomyEditorialNumber('tonight.highlights.planets_order')],
        'stars' => ['items' => $stars, 'max' => (int) astronomyEditorialNumber('tonight.highlights.stars_max'), 'order' => astronomyEditorialNumber('tonight.highlights.stars_order')],
        'moon' => ['items' => $sections['moon'] ?? [], 'max' => (int) astronomyEditorialNumber('tonight.highlights.moon_max'), 'order' => astronomyEditorialNumber('tonight.highlights.moon_order'), 'fill_below' => (int) astronomyEditorialNumber('tonight.highlights.moon_fill_below')],
    ];
    uasort($categories, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);
    $highlights = [];
    $generalMaximum = (int) astronomyEditorialNumber('tonight.highlights.max');
    foreach ($categories as $category) {
        if (isset($category['fill_below']) && count($highlights) >= $category['fill_below']) continue;
        foreach (array_slice($category['items'], 0, $category['max']) as $item) {
            if (count($highlights) >= $generalMaximum) break 2;
            $highlights[] = $item;
        }
    }
    return $highlights;
}
