<?php

require_once __DIR__ . '/api-config.php';
require_once __DIR__ . '/editorial-configuration.php';

function homeNormalizeDailyIntervals($value, string $date, string $timezoneName): ?array
{
    if (!is_array($value)) {
        return null;
    }
    try {
        $timezone = new DateTimeZone($timezoneName);
        $dayStart = new DateTimeImmutable($date . ' 00:00:00', $timezone);
        $dayEnd = $dayStart->modify('+1 day');
    } catch (Exception $exception) {
        return null;
    }

    $normalized = [];
    foreach ($value as $interval) {
        if (!is_array($interval) || !is_string($interval['start'] ?? null) || !is_string($interval['end'] ?? null)) {
            return null;
        }
        try {
            $start = (new DateTimeImmutable($interval['start']))->setTimezone($timezone);
            $end = (new DateTimeImmutable($interval['end']))->setTimezone($timezone);
        } catch (Exception $exception) {
            return null;
        }
        $clippedStart = $start < $dayStart ? $dayStart : $start;
        $clippedEnd = $end > $dayEnd ? $dayEnd : $end;
        if ($clippedEnd <= $clippedStart) {
            continue;
        }
        $startMinutes = $clippedStart == $dayStart
            ? 0.0
            : ((int) $clippedStart->format('G') * 60) + (int) $clippedStart->format('i') + ((int) $clippedStart->format('s') / 60);
        $endMinutes = $clippedEnd == $dayEnd
            ? 1440.0
            : ((int) $clippedEnd->format('G') * 60) + (int) $clippedEnd->format('i') + ((int) $clippedEnd->format('s') / 60);
        $normalized[] = [
            'start' => max(0.0, min(1440.0, $startMinutes)),
            'end' => max(0.0, min(1440.0, $endMinutes)),
            'start_label' => $clippedStart == $dayStart ? '00:00' : $clippedStart->format('H:i'),
            'end_label' => $clippedEnd == $dayEnd ? '24:00' : $clippedEnd->format('H:i'),
        ];
    }
    return $normalized;
}

function homeVisibilityLabel(string $objectName, array $intervals): string
{
    if ($intervals === []) {
        return $objectName . ' no está sobre el horizonte durante este día.';
    }
    $parts = [];
    foreach ($intervals as $interval) {
        $start = $interval['start_label'];
        $end = $interval['end_label'];
        if ($start === '00:00' && $end === '24:00') {
            $parts[] = 'durante todo el día';
        } elseif ($start === '00:00') {
            $parts[] = 'desde antes de medianoche hasta las ' . $end;
        } elseif ($end === '24:00') {
            $parts[] = 'desde las ' . $start . ' y continúa después de medianoche';
        } else {
            $parts[] = 'de ' . $start . ' a ' . $end;
        }
    }
    return $objectName . ' está sobre el horizonte ' . implode('; y ', $parts) . '.';
}

function homeValidateMoonInstant($value): ?array
{
    if (!is_array($value) || !is_array($value['observer'] ?? null) || !is_array($value['datetime'] ?? null)) {
        return null;
    }
    $observer = $value['observer'];
    $altitude = $observer['altitude_degrees'] ?? null;
    $azimuth = $observer['azimuth_degrees'] ?? null;
    $aboveHorizon = $observer['above_horizon'] ?? null;
    $localDateTime = $value['datetime']['local'] ?? null;
    if (
        !is_numeric($altitude) || !is_numeric($azimuth) || !is_bool($aboveHorizon)
        || !is_finite((float) $altitude) || !is_finite((float) $azimuth)
        || (float) $altitude < -90 || (float) $altitude > 90
        || !is_string($localDateTime) || $localDateTime === ''
    ) {
        return null;
    }
    try {
        $instant = new DateTimeImmutable($localDateTime);
    } catch (Exception $exception) {
        return null;
    }
    $ageDays = is_numeric($value['phase']['age_days'] ?? null) && is_finite((float) $value['phase']['age_days'])
        ? (float) $value['phase']['age_days']
        : null;
    return [
        'instant' => $instant,
        'altitude_degrees' => (float) $altitude,
        'azimuth_degrees' => fmod(fmod((float) $azimuth, 360.0) + 360.0, 360.0),
        'above_horizon' => $aboveHorizon,
        'age_days' => $ageDays,
    ];
}

function homeNearestNewMoonDifferenceDays(array $phaseItems, DateTimeImmutable $instant, string $timezoneName): ?float
{
    try {
        $timezone = new DateTimeZone($timezoneName);
    } catch (Exception $exception) {
        return null;
    }
    $nearestSeconds = null;
    foreach ($phaseItems as $event) {
        if (!is_array($event) || ($event['type'] ?? null) !== 'moon_phase' || ($event['subtype'] ?? null) !== 'new_moon') {
            continue;
        }
        try {
            $newMoon = (new DateTimeImmutable((string) ($event['datetime'] ?? '')))->setTimezone($timezone);
        } catch (Exception $exception) {
            continue;
        }
        $difference = abs($newMoon->getTimestamp() - $instant->getTimestamp());
        if ($nearestSeconds === null || $difference < $nearestSeconds) {
            $nearestSeconds = $difference;
        }
    }
    return $nearestSeconds !== null ? $nearestSeconds / 86400 : null;
}

function homeNewMoonDifferenceFromAge(?float $ageDays): ?float
{
    // Mes sinódico medio; se usa sólo como respaldo cuando no hay eventos de Luna nueva válidos.
    $synodicMonthDays = 29.530588;
    if ($ageDays === null || $ageDays < 0 || $ageDays > $synodicMonthDays) {
        return null;
    }
    return min($ageDays, $synodicMonthDays - $ageDays);
}

function homeMoonDirection(float $azimuthDegrees): string
{
    $directions = ['norte', 'noreste', 'este', 'sudeste', 'sur', 'sudoeste', 'oeste', 'noroeste'];
    $normalized = fmod(fmod($azimuthDegrees, 360.0) + 360.0, 360.0);
    return $directions[((int) floor(($normalized + 22.5) / 45.0)) % 8];
}

function homeMoonVisibleSituation(float $altitudeDegrees, float $azimuthDegrees): string
{
    if ($altitudeDegrees >= astronomyEditorialNumber('home.altitude.high_max')) {
        return astronomyEditorialText('home.moon.overhead');
    }
    if ($altitudeDegrees >= astronomyEditorialNumber('home.altitude.medium_max')) {
        return astronomyEditorialText('home.moon.high');
    }
    $key = $altitudeDegrees < astronomyEditorialNumber('home.altitude.very_low_max') ? 'home.moon.very_low'
        : ($altitudeDegrees < astronomyEditorialNumber('home.altitude.low_max') ? 'home.moon.low' : 'home.moon.medium');
    return astronomyEditorialText($key, ['direccion' => homeMoonDirection($azimuthDegrees)]);
}

function homeFutureMoonrise($value, DateTimeImmutable $now, string $timezoneName): ?DateTimeImmutable
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    try {
        $rise = (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName));
    } catch (Exception $exception) {
        return null;
    }
    return $rise > $now ? $rise : null;
}

function homeMoonriseNoticePresentation(DateTimeImmutable $now, ?DateTimeImmutable $nextRise, int $maxMinutes): array
{
    if ($nextRise === null || $nextRise <= $now) {
        return ['text' => astronomyEditorialText('home.moon.below'), 'level' => null];
    }
    $secondsUntilRise = $nextRise->getTimestamp() - $now->getTimestamp();
    $minutesUntilRise = max(1, (int) ceil($secondsUntilRise / 60));
    if ($minutesUntilRise > $maxMinutes) {
        return ['text' => astronomyEditorialText('home.moon.below'), 'level' => null];
    }
    if ($minutesUntilRise >= astronomyEditorialNumber('home.moonrise.clock_minutes')) {
        return ['text' => astronomyEditorialText('home.moonrise.clock', ['hora' => $nextRise->format('H:i')]), 'level' => null];
    }
    if ($minutesUntilRise >= astronomyEditorialNumber('home.moonrise.soon_minutes')) {
        return [
            'text' => astronomyEditorialText('home.moonrise.minutes', ['minutos' => $minutesUntilRise . ' ' . ($minutesUntilRise === 1 ? 'minuto' : 'minutos')]),
            'level' => 'soon',
        ];
    }
    if ($minutesUntilRise >= astronomyEditorialNumber('home.moonrise.imminent_minutes')) {
        return ['text' => astronomyEditorialText('home.moonrise.imminent'), 'level' => 'imminent'];
    }
    return ['text' => astronomyEditorialText('home.moonrise.now'), 'level' => 'now'];
}

function homeMoonriseNotice(DateTimeImmutable $now, ?DateTimeImmutable $nextRise, int $maxMinutes): string
{
    return homeMoonriseNoticePresentation($now, $nextRise, $maxMinutes)['text'];
}

function homeMoonSituationPresentation(
    array $instantData,
    ?float $newMoonDifferenceDays,
    ?DateTimeImmutable $nextRise = null,
    int $moonriseNoticeMaxMinutes = DEFAULT_MOONRISE_NOTICE_MAX_MINUTES
): array
{
    if ($instantData['above_horizon'] === false) {
        return homeMoonriseNoticePresentation($instantData['instant'], $nextRise, $moonriseNoticeMaxMinutes);
    }
    if ($newMoonDifferenceDays !== null && $newMoonDifferenceDays <= astronomyEditorialNumber('home.new_moon.impossible_days')) {
        return ['text' => astronomyEditorialText('home.moon.new_impossible'), 'level' => null];
    }
    if ($newMoonDifferenceDays !== null && $newMoonDifferenceDays <= astronomyEditorialNumber('home.new_moon.thin_days')) {
        return ['text' => astronomyEditorialText('home.moon.new_thin'), 'level' => null];
    }
    return [
        'text' => homeMoonVisibleSituation($instantData['altitude_degrees'], $instantData['azimuth_degrees']),
        'level' => null,
    ];
}

function homeMoonSituation(
    array $instantData,
    ?float $newMoonDifferenceDays,
    ?DateTimeImmutable $nextRise = null,
    int $moonriseNoticeMaxMinutes = DEFAULT_MOONRISE_NOTICE_MAX_MINUTES
): string {
    return homeMoonSituationPresentation($instantData, $newMoonDifferenceDays, $nextRise, $moonriseNoticeMaxMinutes)['text'];
}
