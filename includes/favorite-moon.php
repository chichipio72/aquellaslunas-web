<?php

declare(strict_types=1);

const FAVORITE_MOON_MIN_DATE = '1900-01-01';
const FAVORITE_MOON_MAX_DATE = '2050-12-31';

/** @return array{instant:DateTimeImmutable,date:string,time:string,corrected:bool} */
function favoriteMoonSelection(mixed $date, mixed $time, DateTimeImmutable $fallback): array
{
    $dateValue = is_string($date) ? trim($date) : '';
    $timeValue = is_string($time) ? trim($time) : '';
    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) === 1
        && $dateValue >= FAVORITE_MOON_MIN_DATE && $dateValue <= FAVORITE_MOON_MAX_DATE;
    $validTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeValue) === 1;
    $candidate = null;
    if ($validDate && $validTime) {
        $candidate = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dateValue . ' ' . $timeValue, $fallback->getTimezone());
        if ($candidate === false || $candidate->format('Y-m-d H:i') !== $dateValue . ' ' . $timeValue) $candidate = null;
    }
    $instant = $candidate ?? $fallback->setTime((int) $fallback->format('H'), (int) $fallback->format('i'));
    return [
        'instant' => $instant,
        'date' => $instant->format('Y-m-d'),
        'time' => $instant->format('H:i'),
        'corrected' => ($dateValue !== '' || $timeValue !== '') && $candidate === null,
    ];
}

function favoriteMoonPhaseLabel(string $phaseName): string
{
    $normalized = trim($phaseName);
    if ($normalized === '') return 'Fase no disponible';
    return ucfirst($normalized);
}

function favoriteMoonReturnPath(string $scriptPath, string $date, string $time): string
{
    return $scriptPath . '?' . http_build_query(['fecha' => $date, 'hora' => $time]);
}

