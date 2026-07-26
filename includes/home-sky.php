<?php

require_once __DIR__ . '/api-config.php';

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
    if ($altitudeDegrees >= 80.0) {
        return 'Está visible, prácticamente sobre tu cabeza.';
    }
    if ($altitudeDegrees >= 60.0) {
        return 'Está visible, muy alta. Mirá casi hacia arriba.';
    }
    if ($altitudeDegrees < 15.0) {
        $height = 'muy baja';
    } elseif ($altitudeDegrees < 35.0) {
        $height = 'baja';
    } elseif ($altitudeDegrees < 60.0) {
        $height = 'a media altura';
    }
    return 'Está visible, ' . $height . ' hacia ' . homeMoonDirection($azimuthDegrees) . '.';
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
        return ['text' => 'No está sobre el horizonte.', 'level' => null];
    }
    $secondsUntilRise = $nextRise->getTimestamp() - $now->getTimestamp();
    $minutesUntilRise = max(1, (int) ceil($secondsUntilRise / 60));
    if ($minutesUntilRise > $maxMinutes) {
        return ['text' => 'No está sobre el horizonte.', 'level' => null];
    }
    if ($minutesUntilRise >= 60) {
        return ['text' => 'La Luna saldrá a las ' . $nextRise->format('H:i') . '.', 'level' => null];
    }
    if ($minutesUntilRise >= 15) {
        return [
            'text' => 'La Luna saldrá en ' . $minutesUntilRise . ' ' . ($minutesUntilRise === 1 ? 'minuto' : 'minutos') . '.',
            'level' => 'soon',
        ];
    }
    if ($minutesUntilRise >= 5) {
        return ['text' => 'La Luna está por salir.', 'level' => 'imminent'];
    }
    return ['text' => 'Preparate: la Luna está por salir.', 'level' => 'now'];
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
    if ($newMoonDifferenceDays !== null && $newMoonDifferenceDays <= 1.0) {
        return ['text' => 'Está sobre el horizonte, pero es prácticamente imposible verla.', 'level' => null];
    }
    if ($newMoonDifferenceDays !== null && $newMoonDifferenceDays <= 3.0) {
        return ['text' => 'Está muy finita y cuesta encontrarla a simple vista.', 'level' => null];
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
