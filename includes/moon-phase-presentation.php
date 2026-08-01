<?php

require_once __DIR__ . '/event-type-configuration.php';
require_once __DIR__ . '/editorial-configuration.php';

function astronomyMajorMoonPhaseLabels(): array
{
    $labels = [];
    foreach (['new_moon', 'first_quarter', 'full_moon', 'last_quarter'] as $subtype) {
        $labels[$subtype] = astronomyEventFriendlyName(['type' => 'moon_phase', 'subtype' => $subtype]);
    }
    return $labels;
}

function astronomyIntermediateMoonPhaseLabels(): array
{
    return [
        'new_moon' => astronomyEditorialText('event.phase.waxing_crescent'),
        'first_quarter' => astronomyEditorialText('event.phase.waxing_gibbous'),
        'full_moon' => astronomyEditorialText('event.phase.waning_gibbous'),
        'last_quarter' => astronomyEditorialText('event.phase.waning_crescent'),
    ];
}

function astronomyMoonPhaseEventDateTime($value, string $timezoneName): ?DateTimeImmutable
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName));
    } catch (Throwable $exception) {
        return null;
    }
}

/**
 * Resuelve el nombre editorial para un día civil local a partir de eventos exactos.
 *
 * Una fase principal conserva su nombre únicamente durante la fecha local del
 * evento. Los demás días toman la fase intermedia posterior al último evento
 * principal conocido. Devuelve null si la secuencia no alcanza para clasificar.
 */
function astronomyMoonPhaseLabelForLocalDate(
    string $localDate,
    string $timezoneName,
    array $phaseEvents
): ?string {
    try {
        $timezone = new DateTimeZone($timezoneName);
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $localDate, $timezone);
    } catch (Throwable $exception) {
        return null;
    }
    if ($day === false || $day->format('Y-m-d') !== $localDate) {
        return null;
    }

    $normalized = [];
    $majorLabels = astronomyMajorMoonPhaseLabels();
    foreach ($phaseEvents as $event) {
        if (!is_array($event) || ($event['type'] ?? null) !== 'moon_phase') {
            continue;
        }
        $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : '';
        if (!isset($majorLabels[$subtype])) {
            continue;
        }
        $instant = astronomyMoonPhaseEventDateTime($event['datetime'] ?? null, $timezoneName);
        if ($instant !== null) {
            $normalized[] = ['subtype' => $subtype, 'instant' => $instant];
        }
    }
    usort($normalized, static fn(array $first, array $second): int => $first['instant'] <=> $second['instant']);

    foreach ($normalized as $phase) {
        if ($phase['instant']->format('Y-m-d') === $localDate) {
            return $majorLabels[$phase['subtype']];
        }
    }

    $dayEnd = $day->modify('+1 day');
    $previousSubtype = null;
    foreach ($normalized as $phase) {
        if ($phase['instant'] >= $dayEnd) {
            break;
        }
        $previousSubtype = $phase['subtype'];
    }

    $intermediateLabels = astronomyIntermediateMoonPhaseLabels();
    return $previousSubtype !== null
        ? $intermediateLabels[$previousSubtype]
        : null;
}
