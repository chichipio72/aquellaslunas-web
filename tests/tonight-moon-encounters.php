<?php

require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/home-tonight-scene.php';

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
$venusScene = array_values(array_filter($currentObservedCase['moon_scenes'] ?? [], static fn(array $scene): bool => ($scene['anchor_id'] ?? '') === 'venus'))[0] ?? null;
tonightEncounterAssert(is_array($venusScene) && is_array($venusScene['moon'] ?? null), 'El motor no produjo la escena Luna–Venus.');
$venusSceneObject = array_values(array_filter($venusScene['objects'] ?? [], static fn(array $item): bool => ($item['id'] ?? '') === 'venus'))[0] ?? null;
tonightEncounterAssert(is_array($venusSceneObject), 'La escena no contiene su planeta ancla.');
$projectedSeparation = hypot((float) $venusSceneObject['relative_x_degrees'], (float) $venusSceneObject['relative_y_degrees']);
tonightEncounterAssert(abs($projectedSeparation - (float) $venusSceneObject['separation_degrees']) < 0.001,
    'La proyección no conserva la separación angular a escala.');
tonightEncounterAssert(
    (float) $venusSceneObject['altitude_degrees'] < (float) $venusScene['moon']['altitude_degrees']
    && (float) $venusSceneObject['relative_y_degrees'] > 0,
    'La dirección vertical relativa Luna–Venus no coincide con sus alturas reales.'
);
tonightEncounterAssert(isset($venusScene['moon']['illumination_percent'], $venusScene['moon']['angular_diameter_degrees']),
    'La escena no incluye fase o diámetro aparente lunar.');

$futureOnlyCase = $calculator->calculate(
    new DateTimeImmutable('2026-08-16', $observer->timezone),
    $observer,
    'summary',
    new DateTimeImmutable('2026-08-16T19:21:00-03:00')
);
$futureVenusScene = array_values(array_filter($futureOnlyCase['moon_scenes'] ?? [], static fn(array $scene): bool => ($scene['anchor_id'] ?? '') === 'venus'))[0] ?? null;
tonightEncounterAssert(
    is_array($futureVenusScene) && new DateTimeImmutable($futureVenusScene['datetime']) >= new DateTimeImmutable('2026-08-16T19:21:00-03:00'),
    'La escena eligió un horario anterior al reloj vigente.'
);

$sceneModel = homeTonightMoonSceneModel($currentObservedCase, [], new DateTimeImmutable('2026-08-16T19:00:00-03:00'), $observer->timezone->getName(), $observer->latitudeDegrees, $observer->longitudeDegrees);
tonightEncounterAssert(is_array($sceneModel), 'La portada no preparó la escena real Luna–Venus.');
ob_start(); renderHomeTonightMoonScene($sceneModel); $sceneHtml = (string) ob_get_clean();
tonightEncounterAssert(str_contains($sceneHtml, 'home-tonight-scene__moon') && str_contains($sceneHtml, '>Luna</text>') && str_contains($sceneHtml, '>Venus</text>'),
    'El SVG real no contiene la Luna y Venus etiquetados.');
tonightEncounterAssert(str_contains($sceneHtml, 'moon_') && str_contains($sceneHtml, 'waxing_south.png'),
    'La escena no reutilizó la imagen lunar de fase correspondiente.');
$linkedSceneModel = homeTonightMoonSceneModel($currentObservedCase, [], new DateTimeImmutable('2026-08-16T19:00:00-03:00'), $observer->timezone->getName(), $observer->latitudeDegrees, $observer->longitudeDegrees, [
    'latitude' => $observer->latitudeDegrees, 'longitude' => $observer->longitudeDegrees, 'elevation_meters' => 0.0, 'timezone' => $observer->timezone->getName(),
]);
ob_start(); renderHomeTonightMoonScene($linkedSceneModel); $linkedSceneHtml = (string) ob_get_clean();
tonightEncounterAssert(str_contains($linkedSceneHtml, 'Explorar la escena') && str_contains($linkedSceneHtml, 'observation_time='),
    'El esquema compartido no ofrece su escena exacta en Fotografía.');

$emptySceneData = $currentObservedCase; $emptySceneData['moon_encounters'] = [];
tonightEncounterAssert(homeTonightMoonSceneModel($emptySceneData, [], new DateTimeImmutable('2026-08-16T19:00:00-03:00'), $observer->timezone->getName(), $observer->latitudeDegrees, $observer->longitudeDegrees) === null,
    'Una noche sin cercanías mostró una Luna aislada.');

$formalEvents = [[
    'type' => 'conjunction', 'subtype' => 'venus', 'datetime' => '2026-08-16T19:10:00-03:00',
    'details' => ['both_above_horizon' => true],
]];
$formalModel = homeTonightMoonSceneModel($currentObservedCase, $formalEvents, new DateTimeImmutable('2026-08-16T19:00:00-03:00'), $observer->timezone->getName(), $observer->latitudeDegrees, $observer->longitudeDegrees);
tonightEncounterAssert(is_array($formalModel), 'Una conjunción formal visible fue excluida del esquema al evitar su duplicación textual.');

$symbolModel = $sceneModel;
$symbolModel['objects'] = [
    ['id' => 'mars', 'name' => 'Marte', 'object_kind' => 'planet', 'relative_x_degrees' => 1.0, 'relative_y_degrees' => 0.0],
    ['id' => 'saturn', 'name' => 'Saturno', 'object_kind' => 'planet', 'relative_x_degrees' => -2.0, 'relative_y_degrees' => 1.0],
    ['id' => 'spica', 'name' => 'Spica', 'object_kind' => 'star', 'relative_x_degrees' => 2.0, 'relative_y_degrees' => -1.0],
];
ob_start(); renderHomeTonightMoonScene($symbolModel); $symbolHtml = (string) ob_get_clean();
tonightEncounterAssert(str_contains($symbolHtml, 'home-tonight-scene__object--mars'), 'Marte no usa su símbolo rojizo identificable.');
tonightEncounterAssert(str_contains($symbolHtml, 'home-tonight-scene__saturn-ring'), 'Saturno no tiene anillo.');
tonightEncounterAssert(str_contains($symbolHtml, 'home-tonight-scene__object--star') && str_contains($symbolHtml, '>Spica</text>'), 'La estrella no usa rayos o etiqueta visible.');
tonightEncounterAssert(str_contains($symbolHtml, 'home-tonight-scene__star-core') && str_contains($symbolHtml, 'home-tonight-scene__star-halo'), 'La estrella no usa núcleo y halo fotográficos.');

$syntheticScene = [
    'night' => ['start' => '2026-08-16T18:30:00-03:00', 'end' => '2026-08-17T07:00:00-03:00'],
    'moon_encounters' => [['id' => 'venus', 'name' => 'Venus', 'object_kind' => 'planet', 'minimum_separation_degrees' => 2.0, 'visibility_end' => '2026-08-17T01:00:00-03:00']],
    'moon_scenes' => [[
        'anchor_id' => 'venus', 'datetime' => '2026-08-16T21:00:00-03:00',
        'moon' => ['altitude_degrees' => 2.0, 'azimuth_degrees' => 270.0, 'illumination_percent' => 18.0, 'age_days' => 4.0, 'angular_diameter_degrees' => 0.5],
        'objects' => [
            ['id' => 'venus', 'name' => 'Venus', 'object_kind' => 'planet', 'separation_degrees' => 2.0, 'relative_x_degrees' => 2.0, 'relative_y_degrees' => 0.0],
            ['id' => 'spica', 'name' => 'Spica', 'object_kind' => 'star', 'separation_degrees' => 5.0, 'relative_x_degrees' => -3.0, 'relative_y_degrees' => -4.0],
            ['id' => 'saturn', 'name' => 'Saturno', 'object_kind' => 'planet', 'separation_degrees' => 6.0, 'relative_x_degrees' => 5.0, 'relative_y_degrees' => 3.3166],
            ['id' => 'mars', 'name' => 'Marte', 'object_kind' => 'planet', 'separation_degrees' => 11.0, 'relative_x_degrees' => 11.0, 'relative_y_degrees' => 0.0],
        ],
    ]],
];
$lowModel = homeTonightMoonSceneModel($syntheticScene, [], new DateTimeImmutable('2026-08-16T20:00:00-03:00'), $observer->timezone->getName(), $observer->latitudeDegrees, $observer->longitudeDegrees);
tonightEncounterAssert(is_array($lowModel) && $lowModel['horizon_visible'] === true, 'La Luna baja no mostró el horizonte.');
tonightEncounterAssert(array_column($lowModel['objects'], 'id') === ['venus', 'spica', 'saturn'], 'No se conservaron varios planetas y una estrella dentro de 10°, o se aceptó uno exterior.');
$highScene = $syntheticScene; $highScene['moon_scenes'][0]['moon']['altitude_degrees'] = 45.0;
$highModel = homeTonightMoonSceneModel($highScene, [], new DateTimeImmutable('2026-08-16T20:00:00-03:00'), $observer->timezone->getName(), $observer->latitudeDegrees, $observer->longitudeDegrees);
tonightEncounterAssert(is_array($highModel) && $highModel['horizon_visible'] === false, 'La Luna alta mostró un horizonte irrelevante.');
$closeScene = $highScene; $closeScene['moon_scenes'][0]['objects'] = [$highScene['moon_scenes'][0]['objects'][0]];
$closeScene['moon_scenes'][0]['objects'][0]['separation_degrees'] = 0.8;
$closeScene['moon_scenes'][0]['objects'][0]['relative_x_degrees'] = 0.8;
$closeModel = homeTonightMoonSceneModel($closeScene, [], new DateTimeImmutable('2026-08-16T20:00:00-03:00'), $observer->timezone->getName(), $observer->latitudeDegrees, $observer->longitudeDegrees);
tonightEncounterAssert(is_array($closeModel) && $closeModel['bounds'][2] < $highModel['bounds'][2], 'El encuadre no se adapta a una agrupación angular más compacta.');
$outsideOnly = $syntheticScene; $outsideOnly['moon_scenes'][0]['objects'] = [$syntheticScene['moon_scenes'][0]['objects'][3]];
tonightEncounterAssert(homeTonightMoonSceneModel($outsideOnly, [], new DateTimeImmutable('2026-08-16T20:00:00-03:00'), $observer->timezone->getName(), $observer->latitudeDegrees, $observer->longitudeDegrees) === null,
    'Un único objeto a más de 10° produjo un esquema.');

$homeSummary = $calculator->calculate(
    new DateTimeImmutable('2026-08-16', $observer->timezone),
    $observer,
    'summary',
    new DateTimeImmutable('2026-08-16T19:00:00-03:00')
);
tonightEncounterAssert(isset($homeSummary['moon_encounters']), 'El resumen usado por la portada no incluyó encuentros lunares.');
tonightEncounterAssert(in_array('venus', array_column($homeSummary['moon_encounters'], 'id'), true), 'La portada no recibió el encuentro Luna–Venus.');
tonightEncounterAssert(in_array('venus', array_column($homeSummary['moon_scenes'] ?? [], 'anchor_id'), true), 'El resumen usado por la portada no incluyó la escena Luna–Venus.');

echo "Encuentros observacionales con la Luna: catálogo, umbral y caso real OK\n";
