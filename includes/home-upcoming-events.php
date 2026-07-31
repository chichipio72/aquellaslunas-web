<?php

const HOME_UPCOMING_EVENT_LIMIT = 6;
const HOME_UPCOMING_EVENT_TYPES = 'moon_phase,apsis,conjunction,earthshine,full_moon_observation';
const HOME_UPCOMING_SEGMENTS = [
    ['offset_days' => 0, 'days' => 7, 'label' => '1-7'],
    ['offset_days' => 7, 'days' => 7, 'label' => '8-14'],
    ['offset_days' => 14, 'days' => 16, 'label' => '15-30'],
];

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
        if ($date === null || $date < $now || $key === null || !astronomyEarthshineEventIsDisplayable($event)) {
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

/**
 * @param callable(string,int,string): ?array $fetchSegment
 * @return array{events:array,queries:array}
 */
function homeUpcomingProgressiveSearch(
    DateTimeImmutable $now,
    string $timezoneName,
    callable $fetchSegment,
    int $limit = HOME_UPCOMING_EVENT_LIMIT
): array {
    $initialDate = new DateTimeImmutable($now->format('Y-m-d'), new DateTimeZone($timezoneName));
    $collected = [];
    $queries = [];
    $valid = [];
    foreach (HOME_UPCOMING_SEGMENTS as $segment) {
        $startDate = $initialDate->modify('+' . $segment['offset_days'] . ' days')->format('Y-m-d');
        $days = (int) $segment['days'];
        $queries[] = ['start_date' => $startDate, 'days' => $days, 'label' => $segment['label']];
        $response = $fetchSegment($startDate, $days, (string) $segment['label']);
        if (is_array($response['items'] ?? null)) {
            array_push($collected, ...$response['items']);
        }
        $valid = homeUpcomingValidEvents($collected, $now, $timezoneName);
        if (count($valid) >= $limit) {
            break;
        }
    }
    return ['events' => array_slice($valid, 0, $limit), 'queries' => $queries];
}
