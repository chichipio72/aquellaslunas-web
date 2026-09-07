<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/home-satellite-context.php';
require_once __DIR__ . '/../includes/home-satellite-local-test.php';

function homeSatelliteLocalAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$previousEnvironment = getenv('APP_ENV');
putenv('APP_ENV=local');
$fallbackLocation = [
    'name' => 'Buenos Aires', 'latitude' => -34.6, 'longitude' => -58.45,
    'timezone' => 'America/Argentina/Buenos_Aires', 'mode' => 'default',
];
$fallbackNow = new DateTimeImmutable('2026-08-03T12:00:00-03:00');
$mode = homeSatelliteLocalTestMode('historical');
$setup = homeSatelliteLocalTestSetup($mode, $fallbackLocation, $fallbackNow);
homeSatelliteLocalAssert($setup['mode'] === 'historical'
    && $setup['location']['latitude'] === -34.53 && $setup['location']['longitude'] === -58.48
    && $setup['now']->format('Y-m-d H:i P') === '2026-07-25 18:00 -03:00',
    'El perfil histórico no activó ubicación y reloj esperados.');

$runner = $setup['runner'];
homeSatelliteLocalAssert(is_callable($runner), 'El perfil histórico no activó un proveedor ejecutable.');
$context = homeSatelliteContext($setup['location'], $setup['now'], [
    'night' => ['start' => '2026-07-25T18:35:00-03:00', 'end' => '2026-07-26T07:25:00-03:00'],
], $runner, true);
$historical = array_values(array_filter($context['tonight_events'], static fn($event): bool =>
    $event->targetBody === 'moon' && $event->satellite === 'tiangong' && $event->classification === 'transit'));
$expectedMaximum = new DateTimeImmutable('2026-07-26T05:25:00-03:00');
homeSatelliteLocalAssert($context['status'] === 'ok' && count($historical) === 1
    && abs($historical[0]->maximum->getTimestamp() - $expectedMaximum->getTimestamp()) <= 20,
    'El fixture histórico no reprodujo el tránsito lunar de Tiangong.');
homeSatelliteLocalAssert(($context['metrics']['tle_fixtures'] ?? 0) === 2,
    'La búsqueda histórica no resolvió ambos TLE desde fixtures locales.');

foreach (['synthetic-lunar', 'synthetic-solar', 'synthetic-near-lunar', 'synthetic-near-solar',
    'synthetic-none', 'synthetic-error'] as $syntheticMode) {
    $synthetic = homeSatelliteLocalTestSetup(homeSatelliteLocalTestMode($syntheticMode), $fallbackLocation, $fallbackNow);
    $result = homeSatelliteContext($synthetic['location'], $synthetic['now'], [
        'night' => ['start' => '2026-07-25T18:35:00-03:00', 'end' => '2026-07-26T07:25:00-03:00'],
    ], $synthetic['runner'], true);
    if ($syntheticMode === 'synthetic-error') {
        homeSatelliteLocalAssert($result['status'] === 'unavailable', 'El modo de error sintético no quedó aislado.');
    } elseif ($syntheticMode === 'synthetic-none') {
        homeSatelliteLocalAssert($result['status'] === 'ok' && $result['events'] === [], 'El modo sin eventos no quedó vacío.');
    } else {
        homeSatelliteLocalAssert($result['status'] === 'ok' && count($result['events']) === 1,
            'El modo sintético no generó su evento visual.');
    }
}

$sectionCases = [
    'synthetic-tonight-transit' => ['tonight', 'moon', 'transit'],
    'synthetic-tonight-near-pass' => ['tonight', 'moon', 'near_pass'],
    'synthetic-upcoming-lunar-transit' => ['upcoming', 'moon', 'transit'],
    'synthetic-upcoming-lunar-near-pass' => ['upcoming', 'moon', 'near_pass'],
    'synthetic-upcoming-solar-transit' => ['upcoming', 'sun', 'transit'],
    'synthetic-upcoming-solar-near-pass' => ['upcoming', 'sun', 'near_pass'],
];
foreach ($sectionCases as $syntheticMode => [$section, $body, $classification]) {
    $synthetic = homeSatelliteLocalTestSetup(homeSatelliteLocalTestMode($syntheticMode), $fallbackLocation, $fallbackNow);
    $result = homeSatelliteContext($synthetic['location'], $synthetic['now'], [
        'night' => ['start' => '2026-07-25T18:35:00-03:00', 'end' => '2026-07-26T07:25:00-03:00'],
    ], $synthetic['runner'], true);
    $selected = $result[$section . '_events'];
    $other = $result[($section === 'tonight' ? 'upcoming' : 'tonight') . '_events'];
    homeSatelliteLocalAssert(count($selected) === 1 && $other === []
        && $selected[0]->targetBody === $body && $selected[0]->classification === $classification,
        'El modo ' . $syntheticMode . ' no quedó exclusivamente en la sección esperada.');
    homeSatelliteLocalAssert($result['tle_metadata'] === []
        && $selected[0]->tleLine1 === 'SYNTHETIC LOCAL TEST — NO TLE',
        'Un modo sintético resolvió o simuló metadatos de proveedor TLE.');
    if ($classification === 'transit') {
        homeSatelliteLocalAssert($selected[0]->durationSeconds === 2.0,
            'El tránsito sintético no incluyó duración coherente.');
    } else {
        homeSatelliteLocalAssert($selected[0]->durationSeconds === null
            && $selected[0]->minimumSeparationDegrees - $selected[0]->targetApparentRadiusDegrees <= 3.0,
            'El near_pass sintético no incluyó distancia al borde coherente.');
    }
    if ($body === 'sun') {
        homeSatelliteLocalAssert($result['tonight_events'] === [] && $selected[0]->warnings !== [],
            'Un evento solar apareció en Esta noche o perdió su advertencia.');
    }
}

$prioritySetup = homeSatelliteLocalTestSetup(homeSatelliteLocalTestMode('synthetic-priority'), $fallbackLocation, $fallbackNow);
$priority = homeSatelliteContext($prioritySetup['location'], $prioritySetup['now'], [
    'night' => ['start' => '2026-07-25T18:35:00-03:00', 'end' => '2026-07-26T07:25:00-03:00'],
], $prioritySetup['runner'], true);
$priorityTonight = homeSatelliteDisplayEvents($priority['tonight_events']);
$priorityUpcoming = homeSatelliteDisplayEvents($priority['upcoming_events']);
homeSatelliteLocalAssert(count($priorityTonight) === 1 && $priorityTonight[0]->classification === 'near_pass'
    && $priorityTonight[0]->satellite === 'tiangong',
    'El modo de prioridad no limitó Esta noche al near_pass más cercano.');
homeSatelliteLocalAssert(count($priorityUpcoming) === 1 && $priorityUpcoming[0]->classification === 'very_close',
    'El modo de prioridad no hizo que very_close desplazara a near_pass en Lo próximo.');
$allPriorityIds = array_map(static fn($event): string => $event->targetBody . '|' . $event->satellite . '|'
    . $event->maximum->format('c'), array_merge($priority['tonight_events'], $priority['upcoming_events']));
homeSatelliteLocalAssert(count($allPriorityIds) === count(array_unique($allPriorityIds)),
    'El modo de prioridad duplicó eventos entre secciones.');
homeSatelliteLocalAssert(count(homeSatelliteLocalTestReference()) >= count($sectionCases) + 3,
    'La referencia local no enumera los modos públicos de prueba.');

$viewerEvent = $priorityUpcoming[0];
$viewerUrl = homeSatelliteViewerUrl($viewerEvent);
homeSatelliteLocalAssert(str_contains($viewerUrl, 'iss-y-tiangong.php?')
    && str_contains($viewerUrl, 'station=' . $viewerEvent->satellite)
    && str_contains($viewerUrl, 'time='),
    'El evento satelital no enlazó el visor con estación e instante.');
ob_start();
renderHomeSatelliteEventItems([$viewerEvent], 'America/Argentina/Buenos_Aires', $fallbackNow);
$viewerHtml = (string) ob_get_clean();
homeSatelliteLocalAssert(substr_count($viewerHtml, 'Ver ISS y Tiangong') === 1,
    'La acción contextual del visor falta o está duplicada.');
$lunarPresentation = homeSatelliteEventPresentation($priorityTonight[0], 'America/Argentina/Buenos_Aires', $fallbackNow);
homeSatelliteLocalAssert(str_starts_with((string) $lunarPresentation['target_altitude'], 'Luna a ')
    && str_contains((string) $lunarPresentation['target_altitude'], ' horizonte.'),
    'El evento lunar no informa la altura topocéntrica de la Luna.');

putenv('APP_ENV=production');
homeSatelliteLocalAssert(homeSatelliteLocalTestMode('historical') === null
    && homeSatelliteLocalTestMode('synthetic-solar') === null
    && homeSatelliteLocalTestMode('synthetic-tonight-transit') === null
    && homeSatelliteLocalTestReference() === [],
    'Un modo satelital de prueba pudo activarse en producción.');
$productionSetup = homeSatelliteLocalTestSetup('historical', $fallbackLocation, $fallbackNow);
homeSatelliteLocalAssert($productionSetup['mode'] === null && $productionSetup['runner'] === null
    && $productionSetup['location'] === $fallbackLocation && $productionSetup['now'] === $fallbackNow,
    'El perfil local alteró la portada en producción.');

if ($previousEnvironment === false) putenv('APP_ENV');
else putenv('APP_ENV=' . $previousEnvironment);

echo json_encode([
    'status' => 'OK',
    'historical_event' => $historical[0]->maximum->format(DateTimeInterface::ATOM),
    'historical_fixture_tles' => $context['metrics']['tle_fixtures'] ?? 0,
    'production_blocked' => true,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
