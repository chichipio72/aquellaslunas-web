<?php

const ASTRONOMY_MAJOR_MOON_PHASE_LABELS = [
    'new_moon' => 'Luna nueva',
    'first_quarter' => 'Cuarto creciente',
    'full_moon' => 'Luna llena',
    'last_quarter' => 'Cuarto menguante',
];

const ASTRONOMY_INTERMEDIATE_MOON_PHASE_LABELS = [
    'new_moon' => 'Luna creciente',
    'first_quarter' => 'Luna gibosa creciente',
    'full_moon' => 'Luna gibosa menguante',
    'last_quarter' => 'Luna menguante',
];

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
    foreach ($phaseEvents as $event) {
        if (!is_array($event) || ($event['type'] ?? null) !== 'moon_phase') {
            continue;
        }
        $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : '';
        if (!isset(ASTRONOMY_MAJOR_MOON_PHASE_LABELS[$subtype])) {
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
            return ASTRONOMY_MAJOR_MOON_PHASE_LABELS[$phase['subtype']];
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

    return $previousSubtype !== null
        ? ASTRONOMY_INTERMEDIATE_MOON_PHASE_LABELS[$previousSubtype]
        : null;
}
