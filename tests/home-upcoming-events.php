<?php

require_once __DIR__ . '/../includes/presentation.php';
require_once __DIR__ . '/../includes/event-presentation.php';
require_once __DIR__ . '/../includes/home-upcoming-events.php';

function upcomingAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function upcomingEvent(string $datetime, string $type = 'conjunction', ?string $id = null): array
{
    $event = ['type' => $type, 'datetime' => $datetime, 'details' => []];
    if ($id !== null) {
        $event['id'] = $id;
    }
    return $event;
}

$now = new DateTimeImmutable('2026-07-28T23:30:00-03:00');
$calls = [];
$first = homeUpcomingProgressiveSearch($now, 'America/Argentina/Buenos_Aires', static function ($start, $days, $label) use (&$calls): array {
    $calls[] = [$start, $days, $label];
    return ['items' => array_map(
        static fn(int $day): array => upcomingEvent(
            (new DateTimeImmutable('2026-07-29T12:00:00-03:00'))->modify('+' . $day . ' days')->format(DateTimeInterface::ATOM),
            'conjunction',
            'first-' . $day
        ),
        range(0, 5)
    )];
});
upcomingAssert(count($calls) === 1, 'No se detuvo después del primer tramo completo.');
upcomingAssert($calls[0] === ['2026-07-28', 7, '1-7'], 'El primer tramo no coincide con 7 días desde la fecha local.');
upcomingAssert(count($first['events']) === (int) astronomyEditorialNumber('home.upcoming.max_items'), 'No se conservó el máximo editorial de eventos.');

$calls = [];
$second = homeUpcomingProgressiveSearch($now, 'America/Argentina/Buenos_Aires', static function ($start, $days, $label) use (&$calls): array {
    $calls[] = [$start, $days, $label];
    if ($label === '1-7') {
        return ['items' => [
            upcomingEvent('2026-07-29T01:00:00-03:00', 'moon_phase', 'shared'),
            upcomingEvent('2026-07-29T01:00:00-03:00', 'moon_phase', 'shared'),
            upcomingEvent('2026-07-27T01:00:00-03:00', 'conjunction', 'past'),
        ]];
    }
    return ['items' => array_map(
        static fn(int $day): array => upcomingEvent(sprintf('2026-08-%02dT12:00:00-03:00', 4 + $day), 'apsis', 'second-' . $day),
        range(0, 4)
    )];
});
upcomingAssert(count($calls) === 2, 'No se consultó exactamente hasta el segundo tramo.');
upcomingAssert($calls[1] === ['2026-08-04', 7, '8-14'], 'El segundo tramo repitió o salteó fechas.');
upcomingAssert(count($second['events']) === 6, 'El filtrado o la deduplicación alteró el objetivo.');

$calls = [];
$third = homeUpcomingProgressiveSearch($now, 'America/Argentina/Buenos_Aires', static function ($start, $days, $label) use (&$calls): array {
    $calls[] = [$start, $days, $label];
    return ['items' => $label === '15-30'
        ? [upcomingEvent('2026-08-12T17:36:00-03:00', 'moon_phase')]
        : []];
});
upcomingAssert(count($calls) === 3, 'No se recorrieron los tres tramos en el escenario escaso.');
upcomingAssert($calls[2] === ['2026-08-11', 16, '15-30'], 'El tercer tramo no cubre exclusivamente los días 15 a 30.');
upcomingAssert(count($third['events']) === 1, 'Se perdió el evento del último tramo.');

$deduplicated = homeUpcomingValidEvents([
    upcomingEvent('2026-08-01T10:00:00Z', 'conjunction'),
    upcomingEvent('2026-08-01T10:00:00Z', 'conjunction'),
    upcomingEvent('2026-08-01T10:00:00Z', 'apsis'),
], $now, 'America/Argentina/Buenos_Aires');
upcomingAssert(count($deduplicated) === 2, 'No se deduplicó por tipo y fecha/hora.');

echo "Búsqueda progresiva de Lo próximo: OK\n";
