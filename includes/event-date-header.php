<?php

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
 * @param array{class?: string, date_tag?: string, id?: string, datetime?: string} $options
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
    $relativeLabel = astronomyEventRelativeDayLabel($eventDate, $today);
    $dateAttributes = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';
    if ($tag === 'time' && $datetime !== '') {
        $dateAttributes .= ' datetime="' . htmlspecialchars($datetime, ENT_QUOTES, 'UTF-8') . '"';
    }
    ?>
    <div class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>">
        <?php echo '<' . $tag . $dateAttributes . '>' . htmlspecialchars($dateLabel) . '</' . $tag . '>'; ?>
        <?php if ($relativeLabel !== null): ?><span class="event-date-badge event-date-badge--<?= $relativeLabel === 'HOY' ? 'today' : 'tomorrow' ?>"><?= $relativeLabel ?></span><?php endif; ?>
    </div>
    <?php
}
