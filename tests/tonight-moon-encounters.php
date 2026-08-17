<?php

require_once __DIR__ . '/../includes/api-client.php';

use AstronomyEngine\ConjunctionCatalog;
use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\TonightCalculator;

function tonightEncounterAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$targets = ConjunctionCatalog::targets();
tonightEncounterAssert(count($targets) === 18, 'El cálculo nocturno no usa el catálogo completo de conjunciones.');
tonightEncounterAssert(count(ConjunctionCatalog::names()) === count($targets), 'Falta el nombre público de un objetivo de conjunción.');
tonightEncounterAssert(TonightCalculator::MOON_ENCOUNTER_MAX_SEPARATION_DEGREES === 10.0, 'El umbral observacional dejó de ser 10°.');

$observer = new AstronomyObserver(-34.6037, -58.3816, 'America/Argentina/Buenos_Aires', 25.0);
$calculator = new TonightCalculator();
$futureFormalCase = $calculator->calculate(
    new DateTimeImmutable('2026-08-15', $observer->timezone),
    $observer,
    'full',
    new DateTimeImmutable('2026-08-15T12:00:00-03:00')
);
$venus = array_values(array_filter($futureFormalCase['moon_encounters'], static fn(array $item): bool => $item['id'] === 'venus'));
tonightEncounterAssert(count($venus) === 1, 'El caso real Luna–Venus previo a la conjunción formal no produjo encuentro.');
tonightEncounterAssert($venus[0]['minimum_separation_degrees'] <= 10.0, 'El caso real Luna–Venus superó el umbral.');
tonightEncounterAssert($venus[0]['minimum_separation_at'] === '2026-08-15T20:50:00-03:00', 'Cambió inesperadamente el mínimo observable Luna–Venus.');

$currentObservedCase = $calculator->calculate(
    new DateTimeImmutable('2026-08-16', $observer->timezone),
    $observer,
    'full',
    new DateTimeImmutable('2026-08-16T19:00:00-03:00')
);
$currentVenus = array_values(array_filter($currentObservedCase['moon_encounters'], static fn(array $item): bool => $item['id'] === 'venus'));
tonightEncounterAssert(count($currentVenus) === 1, 'El caso observado Luna–Venus del 16/08 no produjo encuentro con el umbral de 10°.');
tonightEncounterAssert($currentVenus[0]['minimum_separation_degrees'] === 7.1, 'Cambió inesperadamente la separación observada Luna–Venus del 16/08.');

$homeSummary = $calculator->calculate(
    new DateTimeImmutable('2026-08-16', $observer->timezone),
    $observer,
    'summary',
    new DateTimeImmutable('2026-08-16T19:00:00-03:00')
);
tonightEncounterAssert(isset($homeSummary['moon_encounters']), 'El resumen usado por la portada no incluyó encuentros lunares.');
tonightEncounterAssert(in_array('venus', array_column($homeSummary['moon_encounters'], 'id'), true), 'La portada no recibió el encuentro Luna–Venus.');

echo "Encuentros observacionales con la Luna: catálogo, umbral y caso real OK\n";
