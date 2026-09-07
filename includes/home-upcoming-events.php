<?php

require_once __DIR__ . '/editorial-configuration.php';
require_once __DIR__ . '/event-date-header.php';

function homeUpcomingProfileReset(): void
{
    $GLOBALS['home_upcoming_profile'] = ['timings' => [], 'counts' => [], 'segments' => []];
}

function homeUpcomingProfileAdd(string $key, float $milliseconds): void
{
    if (($GLOBALS['home_upcoming_profile_enabled'] ?? false) !== true) return;
    $GLOBALS['home_upcoming_profile']['timings'][$key] =
        (float) ($GLOBALS['home_upcoming_profile']['timings'][$key] ?? 0.0) + max(0.0, $milliseconds);
}

function homeUpcomingProfileCount(string $key, int $amount = 1): void
{
    if (($GLOBALS['home_upcoming_profile_enabled'] ?? false) !== true) return;
    $GLOBALS['home_upcoming_profile']['counts'][$key] =
        (int) ($GLOBALS['home_upcoming_profile']['counts'][$key] ?? 0) + $amount;
}

function homeUpcomingProfileSegment(string $label, array $data): void
{
    if (($GLOBALS['home_upcoming_profile_enabled'] ?? false) === true) {
        $GLOBALS['home_upcoming_profile']['segments'][$label] = $data;
    }
}

function homeUpcomingEventDateTime($value, string $timezoneName): ?DateTimeImmutable
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName));
    } catch (Throwable) {
        return null;
    }
}

function homeUpcomingEventKey(array $event): ?string
{
    foreach (['id', 'event_id', 'uid'] as $key) {
        $identifier = is_scalar($event[$key] ?? null) ? trim((string) $event[$key]) : '';
        if ($identifier !== '') {
            return 'id:' . $identifier;
        }
    }
    $type = trim((string) ($event['type'] ?? ''));
    $datetime = trim((string) ($event['datetime'] ?? ''));
    return $type !== '' && $datetime !== '' ? 'type-time:' . $type . '|' . $datetime : null;
}

function homeUpcomingValidEvents(array $events, DateTimeImmutable $now, string $timezoneName): array
{
    $unique = [];
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $date = homeUpcomingEventDateTime($event['datetime'] ?? null, $timezoneName);
        $key = homeUpcomingEventKey($event);
        if (!astronomyEventIsFuture($date, $now) || $key === null || !astronomyEarthshineEventIsDisplayable($event)) {
            continue;
        }
        $unique[$key] ??= $event;
    }
    $result = array_values($unique);
    usort($result, static function (array $first, array $second) use ($timezoneName): int {
        $firstDate = homeUpcomingEventDateTime($first['datetime'] ?? null, $timezoneName);
        $secondDate = homeUpcomingEventDateTime($second['datetime'] ?? null, $timezoneName);
        return ($firstDate?->getTimestamp() ?? PHP_INT_MAX) <=> ($secondDate?->getTimestamp() ?? PHP_INT_MAX);
    });
    return $result;
}

function homeUpcomingEventIsLowPriority(array $event): bool
{
    return ($event['type'] ?? null) === 'lunar_nodes';
}

function homeUpcomingPreferredEvents(array $events, int $limit, string $timezoneName): array
{
    $regular = [];
    $lowPriority = [];
    foreach ($events as $event) {
        if (homeUpcomingEventIsLowPriority($event)) {
            $lowPriority[] = $event;
        } else {
            $regular[] = $event;
        }
    }
    $selected = array_slice([...$regular, ...$lowPriority], 0, $limit);
    usort($selected, static function (array $first, array $second) use ($timezoneName): int {
        $firstDate = homeUpcomingEventDateTime($first['datetime'] ?? null, $timezoneName);
        $secondDate = homeUpcomingEventDateTime($second['datetime'] ?? null, $timezoneName);
        return ($firstDate?->getTimestamp() ?? PHP_INT_MAX) <=> ($secondDate?->getTimestamp() ?? PHP_INT_MAX);
    });
    return $selected;
}

/**
 * @param callable(string,int,string): ?array $fetchSegment
 * @return array{events:array,queries:array}
 */
function homeUpcomingProgressiveSearch(
    DateTimeImmutable $now,
    string $timezoneName,
    callable $fetchSegment,
    ?int $limit = null
): array {
    if ($limit === null) {
        $editorialStarted = hrtime(true);
        $limit = (int) astronomyEditorialNumber('home.upcoming.max_items');
        homeUpcomingProfileAdd('preparación editorial de búsqueda · max_items', (hrtime(true) - $editorialStarted) / 1_000_000);
        homeUpcomingProfileCount('lecturas editoriales de búsqueda');
    }
    $initialDate = new DateTimeImmutable($now->format('Y-m-d'), new DateTimeZone($timezoneName));
    $collected = [];
    $queries = [];
    $valid = [];
    $editorialStarted = hrtime(true);
    $maxDays = (int) astronomyEditorialNumber('home.upcoming.max_days');
    homeUpcomingProfileAdd('preparación editorial de búsqueda · max_days', (hrtime(true) - $editorialStarted) / 1_000_000);
    homeUpcomingProfileCount('lecturas editoriales de búsqueda');
    $segments = [
        ['offset_days' => 0, 'days' => 7, 'label' => '1-7'],
        ['offset_days' => 7, 'days' => 7, 'label' => '8-14'],
        ['offset_days' => 14, 'days' => $maxDays - 14, 'label' => '15-' . $maxDays],
    ];
    foreach ($segments as $segment) {
        $rangeStarted = hrtime(true);
        $startDate = $initialDate->modify('+' . $segment['offset_days'] . ' days')->format('Y-m-d');
        $days = (int) $segment['days'];
        homeUpcomingProfileAdd('resolución de rangos', (hrtime(true) - $rangeStarted) / 1_000_000);
        $queries[] = ['start_date' => $startDate, 'days' => $days, 'label' => $segment['label']];
        $fetchStarted = hrtime(true);
        $response = $fetchSegment($startDate, $days, (string) $segment['label']);
        $fetchMilliseconds = (hrtime(true) - $fetchStarted) / 1_000_000;
        homeUpcomingProfileAdd('resolución segmento ' . $segment['label'], $fetchMilliseconds);
        $receivedCount = is_array($response['items'] ?? null) ? count($response['items']) : 0;
        $combineStarted = hrtime(true);
        if (is_array($response['items'] ?? null)) {
            array_push($collected, ...$response['items']);
        }
        homeUpcomingProfileAdd('combinación de segmentos', (hrtime(true) - $combineStarted) / 1_000_000);
        $selectionStarted = hrtime(true);
        $valid = homeUpcomingValidEvents($collected, $now, $timezoneName);
        $selectionMilliseconds = (hrtime(true) - $selectionStarted) / 1_000_000;
        homeUpcomingProfileAdd('deduplicación, ordenamiento y selección', $selectionMilliseconds);
        homeUpcomingProfileSegment((string) $segment['label'], [
            'start_date' => $startDate,
            'days' => $days,
            'fetch_ms' => $fetchMilliseconds,
            'received' => $receivedCount,
            'collected' => count($collected),
            'valid_after_merge' => count($valid),
            'discarded_after_merge' => count($collected) - count($valid),
        ]);
        $regularCount = count(array_filter($valid, static fn(array $event): bool => !homeUpcomingEventIsLowPriority($event)));
        if ($regularCount >= $limit) {
            break;
        }
    }
    $sliceStarted = hrtime(true);
    $selected = homeUpcomingPreferredEvents($valid, $limit, $timezoneName);
    homeUpcomingProfileAdd('selección final', (hrtime(true) - $sliceStarted) / 1_000_000);
    homeUpcomingProfileCount('eventos seleccionados', count($selected));
    return ['events' => $selected, 'queries' => $queries];
}
