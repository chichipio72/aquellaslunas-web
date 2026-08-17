<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/home-satellite-context.php';
require_once __DIR__ . '/../includes/site-configuration.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\SatelliteTransitEvent;
use AstronomyEngine\Satellite\SatelliteTransitSearchResult;
use AstronomyEngine\Satellite\SatelliteTransitServiceResult;
use AstronomyEngine\Satellite\ResolvedTle;
use AstronomyEngine\Satellite\Tle;

function homeSatelliteAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function homeSatelliteEvent(string $body, string $instant, string $classification = 'close', ?float $duration = null,
    float $separation = 0.5, float $radius = 0.25): SatelliteTransitEvent
{
    $date = new DateTimeImmutable($instant);
    return new SatelliteTransitEvent(
        $body, 'tiangong', 'CSS (TIANHE)', $classification, $date, null, null, $duration,
        $separation, $radius, $radius - $separation, 20.0, 180.0, 21.0, 181.0, 800.0,
        'line 1 fixture', 'line 2 fixture', new DateTimeImmutable('2026-07-24T00:00:00Z'), [],
    );
}

$now = new DateTimeImmutable('2026-07-24T12:00:00Z');
$observer = new AstronomyObserver(41.87, 12.49, 'UTC');
$events = [
    homeSatelliteEvent('moon', '2026-07-24T22:00:00Z'),
    homeSatelliteEvent('moon', '2026-07-24T15:00:00Z'),
    homeSatelliteEvent('sun', '2026-07-24T16:00:00Z'),
    homeSatelliteEvent('moon', '2026-07-26T13:00:00Z'),
    homeSatelliteEvent('sun', '2026-07-24T11:00:00Z'),
];
$search = new SatelliteTransitSearchResult($now, $now->modify('+48 hours'), $observer,
    ['moon','sun'], $events, ['total_ms' => 1.0], 0.0);
$diagnosticTle = static function (string $satellite, string $epoch, string $status): ResolvedTle {
    $tle = new Tle(strtoupper($satellite), 'line 1', 'line 2', $satellite === 'iss' ? 25544 : 48274,
        new DateTimeImmutable($epoch), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0);
    return new ResolvedTle($satellite, $tle, new DateTimeImmutable('2026-07-24T11:45:00Z'),
        $status, 0.0, 'high', []);
};
$serviceResult = new SatelliteTransitServiceResult($search, [
    'iss' => $diagnosticTle('iss', '2026-07-24T00:00:00Z', 'cache_hit'),
    'tiangong' => $diagnosticTle('tiangong', '2026-07-23T04:00:00Z', 'fallback'),
], [], ['service_total_ms' => 1.0]);
$calls = 0;
$runner = static function (AstronomyObserver $resolvedObserver, DateTimeImmutable $start, int $hours,
    array $satellites, array $targets) use (&$calls, $serviceResult): SatelliteTransitServiceResult {
    $calls++;
    homeSatelliteAssert($resolvedObserver->latitudeDegrees === 41.87 && $start->format('c') === '2026-07-24T12:00:00+00:00',
        'Home search did not use the resolved observer and page clock.');
    homeSatelliteAssert($hours === 48 && $satellites === ['iss','tiangong'] && $targets === ['moon','sun'],
        'Home search did not request the combined 48-hour catalog.');
    return $serviceResult;
};
$tonight = ['night' => ['start' => '2026-07-24T18:00:00Z', 'end' => '2026-07-25T05:00:00Z']];
$context = homeSatelliteContext(['latitude' => 41.87, 'longitude' => 12.49, 'timezone' => 'UTC'], $now, $tonight, $runner);

homeSatelliteAssert($calls === 1, 'SatelliteTransitService runner was executed more than once.');
homeSatelliteAssert($context['status'] === 'ok' && $context['combined_result'] === $serviceResult,
    'Combined result is not retained in the shared context.');
homeSatelliteAssert(count($context['tonight_events']) === 1 && $context['tonight_events'][0]->targetBody === 'moon'
    && $context['tonight_events'][0]->maximum->format('H:i') === '22:00', 'Local-night lunar assignment failed.');
homeSatelliteAssert(count($context['upcoming_events']) === 2,
    'Upcoming assignment did not retain only the remaining in-window events.');
homeSatelliteAssert(array_filter($context['upcoming_events'], static fn($event): bool =>
    $event->targetBody === 'moon' && $event->maximum->format('H:i') === '22:00') === [],
    'Tonight lunar event was duplicated in Upcoming.');

$disabledCalls = 0;
$disabled = homeSatelliteContext(['latitude' => 41.87, 'longitude' => 12.49, 'timezone' => 'UTC'], $now, $tonight,
    static function () use (&$disabledCalls, $serviceResult): SatelliteTransitServiceResult {
        $disabledCalls++; return $serviceResult;
    }, false);
homeSatelliteAssert($disabledCalls === 0 && $disabled['status'] === 'disabled' && $disabled['events'] === [],
    'Disabled satellite calculation resolved or executed the service.');
homeSatelliteAssert((astronomySiteConfigCatalog()['home.satellite_transits.enabled']['default'] ?? null) === true,
    'Administrative satellite option is missing or has the wrong default.');

$renderMoon = homeSatelliteEvent('moon', '2026-07-24T22:00:00Z', 'very_close');
$renderSun = homeSatelliteEvent('sun', '2026-07-24T16:00:00Z', 'transit', 1.49);
ob_start(); renderHomeSatelliteEventItems([$renderMoon], 'UTC', $now, true); $tonightHtml = (string) ob_get_clean();
ob_start(); renderHomeSatelliteEventItems([$renderSun], 'UTC', $now); $upcomingHtml = (string) ob_get_clean();
ob_start(); renderHomeSatelliteEventItems([], 'UTC', $now); $emptyHtml = (string) ob_get_clean();
homeSatelliteAssert(str_contains($tonightHtml, 'Tiangong · acercamiento muy cercano frente a la Luna')
    && str_contains($tonightHtml, '>22:00<'), 'Tonight satellite event is not rendered with its local time.');
homeSatelliteAssert(str_contains($upcomingHtml, 'Tiangong · tránsito frente al Sol')
    && str_contains($upcomingHtml, 'Duración 1,5 s') && str_contains($upcomingHtml, 'filtro solar certificado'),
    'Upcoming solar event omitted its summary, duration, or safety warning.');
homeSatelliteAssert($emptyHtml === '', 'Empty satellite events left visual markup or spacing.');
$nearFarther = homeSatelliteEvent('moon', '2026-07-24T20:00:00Z', 'near_pass', null, 3.1, 0.25);
$nearClosest = homeSatelliteEvent('moon', '2026-07-24T21:00:00Z', 'near_pass', null, 2.4, 0.25);
$closeDiagnostic = homeSatelliteEvent('moon', '2026-07-24T19:00:00Z', 'close', null, 0.8, 0.25);
$selectedNear = homeSatelliteDisplayEvents([$nearFarther, $closeDiagnostic, $nearClosest]);
homeSatelliteAssert(count($selectedNear) === 1 && $selectedNear[0] === $nearClosest,
    'La portada no eligió solamente el near_pass de menor distancia al borde.');
homeSatelliteAssert(homeSatelliteDisplayEvents([$closeDiagnostic]) === [], 'La categoría close se volvió pública.');
homeSatelliteAssert(homeSatelliteDisplayEvents([$nearClosest, $renderMoon]) === [$renderMoon],
    'Un near_pass desplazó a un evento satelital más importante.');
ob_start(); renderHomeSatelliteEventItems([$nearClosest], 'UTC', $now); $nearHtml = (string) ob_get_clean();
homeSatelliteAssert(str_contains($nearHtml, 'Tiangong pasará cerca de la Luna')
    && str_contains($nearHtml, 'A unos 2,2° del borde.'),
    'El near_pass no mostró el texto o la distancia al borde esperados.');
$nearSun = homeSatelliteEvent('sun', '2026-07-24T17:00:00Z', 'near_pass', null, 3.2, 0.26);
ob_start(); renderHomeSatelliteEventItems([$nearSun], 'UTC', $now); $nearSunHtml = (string) ob_get_clean();
homeSatelliteAssert(str_contains($nearSunHtml, 'Tiangong pasará cerca del Sol')
    && str_contains($nearSunHtml, 'filtro solar certificado'),
    'El near_pass solar perdió su texto o la advertencia de seguridad.');
$augustNow = new DateTimeImmutable('2026-08-07T17:00:00-03:00');
$augustEvent = homeSatelliteEvent('moon', '2026-08-08T04:21:05-03:00', 'near_pass', null, 2.74, 0.272);
$augustPresentation = homeSatelliteEventPresentation(
    $augustEvent, 'America/Argentina/Buenos_Aires', $augustNow
);
homeSatelliteAssert($augustPresentation['time'] === 'Sáb 8 · 04:21',
    'El evento satelital del día local siguiente no mostró día abreviado, fecha y hora.');
$successDiagnostic = homeSatelliteDiagnostic($context, 10.0, [
    'latitude' => 41.87, 'longitude' => 12.49, 'timezone' => 'UTC', 'mode' => 'manual',
]);
$disabledDiagnostic = homeSatelliteDiagnostic($disabled, 0.1);
$failedDiagnostic = homeSatelliteDiagnostic(['status' => 'unavailable', 'metrics' => []], 2.0);
homeSatelliteAssert($successDiagnostic['status'] === 'ejecutado correctamente'
    && $disabledDiagnostic['status'] === 'deshabilitado' && $failedDiagnostic['status'] === 'fallido',
    'Satellite diagnostic does not distinguish success, disabled, and failed states.');
homeSatelliteAssert($successDiagnostic['result_source'] === 'calculated'
    && $successDiagnostic['location']['latitude'] === 41.87
    && is_string($successDiagnostic['request_id']) && strlen($successDiagnostic['request_id']) === 16
    && count($successDiagnostic['events']) === count($context['events']),
    'El diagnóstico no conserva identidad, ubicación o eventos de la solicitud satelital.');
homeSatelliteAssert($failedDiagnostic['result_source'] === 'calculation_failed',
    'El diagnóstico presenta un cálculo fallido como reutilizado o calculado.');
homeSatelliteAssert($successDiagnostic['tle_sources']['iss']['cache_status'] === 'cache_hit'
    && $successDiagnostic['tle_sources']['iss']['visual_status'] === 'warning'
    && $successDiagnostic['tle_sources']['iss']['age_at_start_hours'] === 12.0
    && $successDiagnostic['tle_sources']['iss']['age_at_end_hours'] === 60.0,
    'El diagnóstico TLE no conserva estado, epoch o edades de la ISS.');
homeSatelliteAssert($successDiagnostic['tle_sources']['tiangong']['visual_status'] === 'unreliable'
    && $successDiagnostic['tle_sources']['tiangong']['visual_label'] === 'no confiable',
    'El diagnóstico TLE no aplica el umbral no confiable al final de la ventana.');

$index = file_get_contents(__DIR__ . '/../index.php');
homeSatelliteAssert(is_string($index) && substr_count($index, 'homeSatelliteContext(') === 1,
    'Home page does not contain exactly one satellite context execution.');
homeSatelliteAssert(str_contains($index, "'tonight' => ['satellite_events'")
    && str_contains($index, "'upcoming' => ['satellite_events'"), 'Home sections do not receive their shared satellite slices.');

$failureCalls = 0;
$unavailable = homeSatelliteContext(['latitude' => 41.87, 'longitude' => 12.49, 'timezone' => 'UTC'], $now, $tonight,
    static function () use (&$failureCalls): SatelliteTransitServiceResult {
        $failureCalls++; throw new RuntimeException('offline without valid cache');
    });
homeSatelliteAssert($failureCalls === 1 && $unavailable['status'] === 'unavailable'
    && $unavailable['tonight_events'] === [] && $unavailable['upcoming_events'] === [],
    'Satellite failure is not isolated from the rest of the home context.');

if (in_array('--preview', $argv ?? [], true)) {
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Vista previa satelital</title><link rel="stylesheet" href="http://localhost:18080/assets/css/styles.css">'
        . '<link rel="stylesheet" href="http://localhost:18080/assets/css/home-v2.css"></head><body>'
        . '<main class="home-v2"><div class="container public-page-container home-v2__container">'
        . '<article class="home-v2-card home-v2-tonight atmosphere-card--night"><div class="home-v2-card__heading"><h2>El cielo esta noche</h2></div>'
        . '<div class="home-v2-upcoming home-v2-satellite-events home-v2-satellite-events--tonight">' . $tonightHtml . '</div></article>'
        . '<article class="home-v2-card home-v2-upcoming-card atmosphere-card--night"><div class="home-v2-card__heading"><h2>Lo próximo</h2></div>'
        . '<div class="home-v2-upcoming">' . $upcomingHtml . '</div></article></div></main></body></html>';
    exit(0);
}

echo json_encode([
    'status' => 'OK', 'service_executions' => $calls,
    'combined_events' => count($context['events']),
    'tonight_events' => count($context['tonight_events']),
    'upcoming_events' => count($context['upcoming_events']),
    'failure_isolated' => $unavailable['status'] === 'unavailable',
    'disabled_service_executions' => $disabledCalls,
    'solar_warning_rendered' => str_contains($upcomingHtml, 'filtro solar certificado'),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
