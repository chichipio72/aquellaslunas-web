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

echo "Encabezado de fecha de eventos: OK\n";
