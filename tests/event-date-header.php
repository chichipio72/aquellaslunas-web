<?php

require_once __DIR__ . '/../includes/event-date-header.php';

function eventDateHeaderAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$newYork = new DateTimeZone('America/New_York');
$today = new DateTimeImmutable('2026-03-07 23:30:00', $newYork);

eventDateHeaderAssert(
    astronomyEventRelativeDayLabel(new DateTimeImmutable('2026-03-07 00:05:00', $newYork), $today) === 'HOY',
    'No reconoció dos instantes de la misma fecha civil local.'
);
eventDateHeaderAssert(
    astronomyEventRelativeDayLabel(new DateTimeImmutable('2026-03-08 23:45:00', $newYork), $today) === 'MAÑANA',
    'No reconoció mañana al atravesar el cambio de horario de verano.'
);
eventDateHeaderAssert(
    astronomyEventRelativeDayLabel(new DateTimeImmutable('2026-03-09 00:01:00', $newYork), $today) === null,
    'Etiquetó una fecha civil posterior a mañana.'
);
eventDateHeaderAssert(
    astronomyEventRelativeDayLabel(new DateTimeImmutable('2026-03-08 00:30:00', new DateTimeZone('UTC')), new DateTimeImmutable('2026-03-08 00:15:00', new DateTimeZone('UTC'))) === 'HOY',
    'No respetó la fecha civil de la zona horaria suministrada.'
);

$buenosAires = new DateTimeZone('America/Argentina/Buenos_Aires');
$observationalNow = new DateTimeImmutable('2026-08-27 12:00:00', $buenosAires);
foreach (['2026-08-27 22:30:00', '2026-08-28 01:00:00', '2026-08-28 04:45:00', '2026-08-28 06:00:00'] as $tonightTime) {
    eventDateHeaderAssert(
        astronomyEventObservationalPeriod(new DateTimeImmutable($tonightTime, $buenosAires), $observationalNow) === 'tonight',
        'La noche observacional no incluyó ' . $tonightTime . '.'
    );
}
eventDateHeaderAssert(
    astronomyEventObservationalPeriod(new DateTimeImmutable('2026-08-28 09:00:00', $buenosAires), $observationalNow) === 'tomorrow',
    'Un evento posterior al corte no quedó clasificado como mañana.'
);
eventDateHeaderAssert(
    astronomyEventObservationalPeriod(new DateTimeImmutable('2026-08-28 08:30:00', new DateTimeZone('UTC')), $observationalNow) === 'tonight',
    'La clasificación no convirtió el evento a la zona horaria activa.'
);
$eventInstant = new DateTimeImmutable('2026-08-28 01:00:00', $buenosAires);
foreach (['2026-08-27 18:00:00', '2026-08-27 23:30:00', '2026-08-28 00:30:00'] as $beforeInstant) {
    eventDateHeaderAssert(
        astronomyEventIsFuture($eventInstant, new DateTimeImmutable($beforeInstant, $buenosAires))
            && astronomyEventObservationalPeriod($eventInstant, new DateTimeImmutable($beforeInstant, $buenosAires)) === 'tonight',
        'Un eclipse futuro de madrugada no quedó etiquetado como esta noche desde ' . $beforeInstant . '.'
    );
}
foreach (['2026-08-28 01:00:00', '2026-08-28 01:30:00', '2026-08-28 09:00:00', '2026-08-28 18:00:00'] as $expiredReference) {
    eventDateHeaderAssert(
        !astronomyEventIsFuture($eventInstant, new DateTimeImmutable($expiredReference, $buenosAires))
            && astronomyEventObservationalPeriod($eventInstant, new DateTimeImmutable($expiredReference, $buenosAires)) === null,
        'Un evento ocurrido reapareció como futuro desde ' . $expiredReference . '.'
    );
}

ob_start();
renderAstronomyEventDateHeader(
    new DateTimeImmutable('2026-03-08 12:00:00', $newYork),
    $today,
    'dom 8 mar',
    ['class' => 'test-date']
);
$markup = ob_get_clean();
eventDateHeaderAssert(str_contains($markup, 'MAÑANA'), 'El componente no renderizó la etiqueta esperada.');
eventDateHeaderAssert(str_contains($markup, 'event-date-header test-date'), 'El componente no conservó la clase de contexto.');
eventDateHeaderAssert(str_contains($markup, '<time datetime='), 'El componente no renderizó el elemento time predeterminado.');
eventDateHeaderAssert(!str_contains($markup, '<>'), 'El componente renderizó una etiqueta HTML sin nombre.');

ob_start();
renderAstronomyEventDateHeader(
    new DateTimeImmutable('2026-08-28 04:45:00', $buenosAires),
    $observationalNow,
    'vie 28 ago · 04:45',
    ['observational_night' => true]
);
$observationalMarkup = ob_get_clean();
eventDateHeaderAssert(str_contains($observationalMarkup, 'ESTA NOCHE'), 'La portada no puede renderizar la etiqueta observacional.');

echo "Encabezado de fecha de eventos: OK\n";
