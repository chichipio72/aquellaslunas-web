<?php

require_once __DIR__ . '/api-client.php';

function astronomyFeaturedDates(string $anchor, float $latitude, float $longitude, string $timezoneName): array
{
    $timezone = new DateTimeZone($timezoneName);
    $anchorDate = new DateTimeImmutable($anchor . ' 00:00:00', $timezone);
    $recentStart = $anchorDate->modify('-6 months');
    $futureEnd = $anchorDate->modify('+1 year');
    $ranges = [
        [$recentStart, (int) $recentStart->diff($anchorDate)->days + 1],
        [$anchorDate->modify('+1 day'), (int) $anchorDate->modify('+1 day')->diff($futureEnd)->days + 1],
    ];
    $apiConfig = loadAstronomyApiConfig();
    $allowedSubtypes = [
        'new_moon' => 'Luna nueva',
        'first_quarter' => 'Cuarto creciente',
        'full_moon' => 'Luna llena',
        'last_quarter' => 'Cuarto menguante',
    ];
    $events = [];
    foreach ($ranges as [$start, $days]) {
        if ($days < 1 || $days > 366) {
            throw new RuntimeException('Featured dates range is invalid.');
        }
        $query = http_build_query([
            'start_date' => $start->format('Y-m-d'),
            'days' => $days,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezoneName,
            'types' => 'moon_phase',
        ]);
        $result = astronomyApiRequest(
            $apiConfig['base_url'] . '/v1/astronomy/events?' . $query,
            'planner featured phases',
            20
        );
        if ($result['body'] === false || $result['http_code'] !== 200) {
            astronomyApiRecordValidation('planner featured phases', null, false);
            throw new RuntimeException('Featured dates API request failed.');
        }
        $decoded = json_decode($result['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded['items'] ?? null)) {
            astronomyApiRecordValidation('planner featured phases', false, false);
            throw new RuntimeException('Featured dates API response is invalid.');
        }
        astronomyApiRecordValidation('planner featured phases', true, true);
        foreach ($decoded['items'] as $item) {
            $subtype = is_array($item) && is_string($item['subtype'] ?? null) ? $item['subtype'] : '';
            $datetime = is_array($item) && is_string($item['datetime'] ?? null) ? $item['datetime'] : '';
            if (($allowedSubtypes[$subtype] ?? null) === null || $datetime === '') {
                continue;
            }
            try {
                $localDate = (new DateTimeImmutable($datetime))->setTimezone($timezone)->format('Y-m-d');
            } catch (Exception $exception) {
                continue;
            }
            $events[$subtype . '-' . $localDate] = [
                'subtype' => $subtype,
                'label' => $allowedSubtypes[$subtype],
                'date' => $localDate,
            ];
        }
    }
    usort($events, static fn(array $first, array $second): int => $first['date'] <=> $second['date']);
    $recent = array_values(array_filter($events, static fn(array $event): bool => $event['date'] < $anchor));
    $upcoming = array_values(array_filter($events, static fn(array $event): bool => $event['date'] >= $anchor));
    usort($recent, static fn(array $first, array $second): int => $second['date'] <=> $first['date']);
    return [
        'anchor_date' => $anchor,
        'range' => ['past_months' => 6, 'future_months' => 12],
        'upcoming' => $upcoming,
        'recent' => $recent,
    ];
}
