<?php

const ASTRONOMY_OBSERVATIONAL_NIGHT_CUTOFF_HOUR = 6;

/**
 * Determina elegibilidad temporal con instantes completos en la zona activa.
 */
function astronomyEventIsFuture(
    ?DateTimeInterface $eventDate,
    DateTimeInterface $referenceDate
): bool {
    if ($eventDate === null) {
        return false;
    }
    $reference = DateTimeImmutable::createFromInterface($referenceDate);
    $event = DateTimeImmutable::createFromInterface($eventDate)->setTimezone($reference->getTimezone());
    return $event > $reference;
}

/**
 * Clasifica un instante según la noche observacional de la ubicación activa.
 * La madrugada inmediata, hasta las 06:00 inclusive, pertenece a "esta noche".
 */
function astronomyEventObservationalPeriod(
    ?DateTimeInterface $eventDate,
    DateTimeInterface $referenceDate
): ?string {
    if ($eventDate === null) {
        return null;
    }
    $reference = DateTimeImmutable::createFromInterface($referenceDate);
    $event = DateTimeImmutable::createFromInterface($eventDate)->setTimezone($reference->getTimezone());
    if (!astronomyEventIsFuture($event, $reference)) {
        return null;
    }
    if ($event->format('Y-m-d') === $reference->format('Y-m-d')) {
        return 'tonight';
    }
    if ($event->format('Y-m-d') !== $reference->modify('+1 day')->format('Y-m-d')) {
        return null;
    }
    $cutoff = $event->setTime(ASTRONOMY_OBSERVATIONAL_NIGHT_CUTOFF_HOUR, 0, 0);
    return $event <= $cutoff ? 'tonight' : 'tomorrow';
}

function astronomyEventObservationalLabel(
    ?DateTimeInterface $eventDate,
    DateTimeInterface $referenceDate
): ?string {
    return match (astronomyEventObservationalPeriod($eventDate, $referenceDate)) {
        'tonight' => 'ESTA NOCHE',
        'tomorrow' => 'MAÑANA',
        default => null,
    };
}

/**
 * Compara claves de fecha civil ya convertidas a la zona horaria activa.
 */
function astronomyEventRelativeDayLabel(?DateTimeInterface $eventDate, DateTimeInterface $today): ?string
{
    if ($eventDate === null) {
        return null;
    }
    $eventDay = $eventDate->format('Y-m-d');
    if ($eventDay === $today->format('Y-m-d')) {
        return 'HOY';
    }
    return $eventDay === DateTimeImmutable::createFromInterface($today)->modify('+1 day')->format('Y-m-d')
        ? 'MAÑANA'
        : null;
}

/**
 * Renderiza la fila compartida fecha/etiqueta sin crear una línea adicional.
 *
 * @param array{class?: string, date_tag?: string, id?: string, datetime?: string, observational_night?: bool} $options
 */
function renderAstronomyEventDateHeader(
    ?DateTimeInterface $eventDate,
    DateTimeInterface $today,
    string $dateLabel,
    array $options = []
): void {
    $requestedTag = $options['date_tag'] ?? 'time';
    $tag = in_array($requestedTag, ['time', 'h2', 'p'], true)
        ? $requestedTag
        : 'time';
    $class = trim('event-date-header ' . (string) ($options['class'] ?? ''));
    $id = trim((string) ($options['id'] ?? ''));
    $datetime = trim((string) ($options['datetime'] ?? ($eventDate?->format(DateTimeInterface::ATOM) ?? '')));
    $relativeLabel = ($options['observational_night'] ?? false) === true
        ? astronomyEventObservationalLabel($eventDate, $today)
        : astronomyEventRelativeDayLabel($eventDate, $today);
    $dateAttributes = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';
    if ($tag === 'time' && $datetime !== '') {
        $dateAttributes .= ' datetime="' . htmlspecialchars($datetime, ENT_QUOTES, 'UTF-8') . '"';
    }
    ?>
    <div class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>">
        <?php echo '<' . $tag . $dateAttributes . '>' . htmlspecialchars($dateLabel) . '</' . $tag . '>'; ?>
        <?php if ($relativeLabel !== null): ?><span class="event-date-badge event-date-badge--<?= in_array($relativeLabel, ['HOY', 'ESTA NOCHE'], true) ? 'today' : 'tomorrow' ?>"><?= $relativeLabel ?></span><?php endif; ?>
    </div>
    <?php
}
