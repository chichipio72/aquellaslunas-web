<?php

require_once __DIR__ . '/../includes/web-database.php';
require_once __DIR__ . '/../includes/astronomy-laboratory-extrema.php';

function astronomyExtremaAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$variables = astronomyLaboratoryExtremaVariables();
$expectedFields = [
    'distancia_luna_km',
    'amplitud_salida_luna',
    'amplitud_puesta_luna',
    'tiempo_luna_sobre_horizonte',
    'diferencia_salida_luna_min',
    'duracion_dia',
    'amplitud_salida_sol',
    'amplitud_puesta_sol',
];
astronomyExtremaAssert(array_keys($variables) === $expectedFields, 'El catálogo de extremos no coincide.');
foreach (['hora_salida_luna', 'hora_puesta_sol', 'iluminacion_porc', 'fase_lunar', 'angulo_nodo_sol'] as $invalidField) {
    astronomyExtremaAssert(!isset($variables[$invalidField]), 'Se habilitó una variable circular o incompatible.');
}
$definitions = astronomyLaboratoryFieldDefinitions();
foreach ($variables as $field => $metadata) {
    astronomyExtremaAssert($metadata['sql'] === $definitions[$field]['sql'], 'Se duplicó o alteró una expresión derivada.');
}
astronomyExtremaAssert(
    $variables['distancia_luna_km']['maximum_label'] === 'Apogeos'
        && $variables['distancia_luna_km']['minimum_label'] === 'Perigeos',
    'La distancia lunar no usa nombres astronómicos.'
);

$moon = $variables['distancia_luna_km'];
astronomyExtremaAssert(
    astronomyLaboratoryExtremaRangeAssessment(new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2026-12-31'), $moon)['meets_minimum'] === false,
    'Se aceptó un rango lunar inferior a dos años.'
);
$moonWarning = astronomyLaboratoryExtremaRangeAssessment(
    new DateTimeImmutable('2022-01-01'),
    new DateTimeImmutable('2025-12-31'),
    $moon
);
astronomyExtremaAssert($moonWarning['meets_minimum'] && !$moonWarning['meets_recommendation'], 'No se detectó la recomendación lunar.');
$sun = $variables['duracion_dia'];
astronomyExtremaAssert(
    astronomyLaboratoryExtremaRangeAssessment(new DateTimeImmutable('2021-01-01'), new DateTimeImmutable('2025-12-31'), $sun)['meets_minimum'] === false,
    'Se aceptó un rango solar inferior a cinco años.'
);

$connection = getWebDatabaseConnection();
$from = new DateTimeImmutable('2020-01-01');
$to = new DateTimeImmutable('2025-12-31');
$both = queryAstronomyLaboratoryExtrema($connection, 'distancia_luna_km', $from, $to, 'ambos');
$maximums = queryAstronomyLaboratoryExtrema($connection, 'distancia_luna_km', $from, $to, 'maximo');
$minimums = queryAstronomyLaboratoryExtrema($connection, 'distancia_luna_km', $from, $to, 'minimo');
astronomyExtremaAssert($both['maximos'] !== [] && $both['minimos'] !== [], 'Ambos no produjo dos series.');
astronomyExtremaAssert($maximums['minimos'] === [] && $maximums['maximos'] === $both['maximos'], 'Máximos devolvió mínimos o perdió datos.');
astronomyExtremaAssert($minimums['maximos'] === [] && $minimums['minimos'] === $both['minimos'], 'Mínimos devolvió máximos o perdió datos.');
foreach (array_merge($both['maximos'], $both['minimos']) as $point) {
    astronomyExtremaAssert($point['valor'] !== null, 'La consulta devolvió valores nulos.');
}
$moonHorizon = queryAstronomyLaboratoryExtrema(
    $connection,
    'tiempo_luna_sobre_horizonte',
    new DateTimeImmutable('2020-01-01'),
    new DateTimeImmutable('2025-12-31'),
    'ambos'
);
foreach (array_merge($moonHorizon['maximos'], $moonHorizon['minimos']) as $point) {
    astronomyExtremaAssert($point['valor'] > 0.0, 'Un NULL o ciclo incompleto se convirtió en cero.');
}
try {
    queryAstronomyLaboratoryExtrema($connection, 'iluminacion_porc', $from, $to, 'ambos');
    throw new RuntimeException('La consulta aceptó una variable fuera de la lista blanca.');
} catch (InvalidArgumentException) {
}

fwrite(STDOUT, "astronomy laboratory extrema tests: ok\n");
