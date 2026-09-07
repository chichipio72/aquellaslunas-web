<?php

declare(strict_types=1);

function homeSolarEventDateTime(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone($timezone);
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array<int,array<string,mixed>> $dailySunData
 * @return array<int,array{kind:string,date:DateTimeImmutable,label:string,time:string}>
 */
function homeNextSolarEvents(array $dailySunData, DateTimeImmutable $now, string $timezoneName): array
{
    try {
        $timezone = new DateTimeZone($timezoneName);
    } catch (Throwable) {
        return [];
    }
    $localNow = $now->setTimezone($timezone);
    $candidates = [];
    foreach ($dailySunData as $sunData) {
        if (!is_array($sunData)) {
            continue;
        }
        foreach (['rise', 'set'] as $kind) {
            $date = homeSolarEventDateTime($sunData[$kind] ?? null, $timezone);
            if ($date !== null && $date > $localNow) {
                $candidates[] = ['kind' => $kind, 'date' => $date];
            }
        }
    }
    usort($candidates, static fn(array $first, array $second): int => $first['date'] <=> $second['date']);

    $result = [];
    foreach (array_slice($candidates, 0, 2) as $candidate) {
        $date = $candidate['date'];
        $kind = $candidate['kind'];
        $dayDifference = (int) $localNow->setTime(0, 0)->diff($date->setTime(0, 0))->format('%r%a');
        $name = $kind === 'rise' ? 'Salida' : 'Puesta';
        $label = match ($dayDifference) {
            0 => $name . ' del Sol',
            1 => $name . ' mañana',
            default => $name . ' ' . $date->format('d/m'),
        };
        $result[] = ['kind' => $kind, 'date' => $date, 'label' => $label, 'time' => $date->format('H:i')];
    }
    return $result;
}
