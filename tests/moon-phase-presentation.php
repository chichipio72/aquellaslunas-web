<?php

require_once __DIR__ . '/../includes/moon-phase-presentation.php';

function moonPhasePresentationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$timezone = 'America/Argentina/Buenos_Aires';
$events = [
    ['type' => 'moon_phase', 'subtype' => 'first_quarter', 'datetime' => '2026-07-21T14:05:00-03:00'],
    ['type' => 'moon_phase', 'subtype' => 'full_moon', 'datetime' => '2026-07-29T23:58:00-03:00'],
    ['type' => 'moon_phase', 'subtype' => 'last_quarter', 'datetime' => '2026-08-06T00:02:00-03:00'],
    ['type' => 'moon_phase', 'subtype' => 'new_moon', 'datetime' => '2026-08-12T18:00:00-03:00'],
];

$cases = [
    ['2026-07-28', 'Luna gibosa creciente'],
    ['2026-07-29', 'Luna llena'],
    ['2026-07-30', 'Luna gibosa menguante'],
    ['2026-08-05', 'Luna gibosa menguante'],
    ['2026-08-06', 'Cuarto menguante'],
    ['2026-08-07', 'Luna menguante'],
    ['2026-08-12', 'Luna nueva'],
    ['2026-08-13', 'Luna creciente'],
];
foreach ($cases as [$date, $expected]) {
    moonPhasePresentationAssert(
        astronomyMoonPhaseLabelForLocalDate($date, $timezone, $events) === $expected,
        'Fase incorrecta para ' . $date . '.'
    );
}

$utcBoundaryEvents = [
    ['type' => 'moon_phase', 'subtype' => 'full_moon', 'datetime' => '2026-07-30T02:30:00Z'],
];
moonPhasePresentationAssert(
    astronomyMoonPhaseLabelForLocalDate('2026-07-29', $timezone, $utcBoundaryEvents) === 'Luna llena',
    'La fase cercana a medianoche no respetó el día civil local.'
);
moonPhasePresentationAssert(
    astronomyMoonPhaseLabelForLocalDate('2026-07-30', $timezone, $utcBoundaryEvents) === 'Luna gibosa menguante',
    'La fase posterior a medianoche local quedó extendida un día.'
);

echo "Presentación editorial de fases lunares: OK\n";
