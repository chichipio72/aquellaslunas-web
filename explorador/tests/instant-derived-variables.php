<?php

declare(strict_types=1);

use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MeeusSolarPositionCalculator;
use Explorador\SeriesBuilder;
use Explorador\SeriesPlanner;
use Explorador\SeriesRequest;
use Explorador\VariableCatalog;

require_once dirname(__DIR__) . '/includes/astronomy-engine-autoload.php';
require_once dirname(__DIR__) . '/includes/VariableCatalog.php';
require_once dirname(__DIR__) . '/includes/PhaseEventDateProvider.php';
require_once dirname(__DIR__) . '/includes/SeriesRequest.php';
require_once dirname(__DIR__) . '/includes/SeriesPlanner.php';
require_once dirname(__DIR__) . '/includes/SeriesBuilder.php';

function derivedCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function derivedRequest(string $from, string $to, string $fields, string $phases = ''): SeriesRequest
{
    return SeriesRequest::fromParameters([
        'fecha_desde' => $from, 'fecha_hasta' => $to,
        'lat' => '-34.6037', 'lon' => '-58.3816',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'campos' => $fields, 'fases' => $phases,
    ], new VariableCatalog());
}

$catalog = new VariableCatalog();
$fields = 'sun_equation_of_time_minutes,sun_moon_angular_distance,moon_apparent_diameter_arcmin';
$request = derivedRequest('2026-01-01', '2026-01-03', $fields);
$plan = (new SeriesPlanner($catalog))->plan($request->fields);
derivedCheck(count($plan['providers']) === 2
    && in_array('sun_instant', $plan['providers'], true)
    && in_array('moon_instant', $plan['providers'], true), 'No se compartieron los dos proveedores instantáneos.');
$threeDays = (new SeriesBuilder($catalog))->build($request, $plan);
derivedCheck(count($threeDays['rows']) === 3, 'El rango de tres días es incorrecto.');

$yearRequest = derivedRequest('2025-01-01', '2025-12-31', $fields);
$yearPlan = (new SeriesPlanner($catalog))->plan($yearRequest->fields);
$year = (new SeriesBuilder($catalog))->build($yearRequest, $yearPlan);
derivedCheck(count($year['rows']) === 365, 'El rango anual es incorrecto.');
$equationValues = array_column($year['rows'], 'sun_equation_of_time_minutes');
derivedCheck(min($equationValues) < -10.0 && max($equationValues) > 10.0,
    'La ecuación del tiempo no conserva ambos signos.');
derivedCheck(min($equationValues) > -20.0 && max($equationValues) < 20.0,
    'La ecuación del tiempo está fuera del rango esperado.');

$moon = new MeeusLunarCalculator();
$zone = new DateTimeZone('UTC');
$phaseCases = [
    ['2026-01-18 19:00:00', 0.0, 15.0, 'Luna nueva'],
    ['2026-01-26 04:00:00', 90.0, 20.0, 'Cuarto'],
    ['2026-01-03 10:00:00', 180.0, 15.0, 'Luna llena'],
];
foreach ($phaseCases as [$instant, $expected, $tolerance, $label]) {
    $position = $moon->calculate(new DateTimeImmutable($instant, $zone), 0.0, 0.0);
    derivedCheck($position->solarElongationDegrees >= 0.0 && $position->solarElongationDegrees <= 180.0,
        $label . ': separación fuera de rango.');
    derivedCheck(abs($position->solarElongationDegrees - $expected) < $tolerance,
        $label . ': separación inesperada.');
}

$diameters = [];
$distances = [];
foreach (['2026-01-01', '2026-01-15', '2026-02-01'] as $date) {
    $position = $moon->calculate(new DateTimeImmutable($date . ' 00:00:00', $zone), 0.0, 0.0);
    $diameters[] = $position->geocentricApparentDiameterArcminutes();
    $distances[] = $position->distanceKilometers;
}
derivedCheck(min($diameters) > 28.0 && max($diameters) < 35.0,
    'El diámetro lunar está fuera del rango razonable.');
$nearest = array_search(min($distances), $distances, true);
$farthest = array_search(max($distances), $distances, true);
derivedCheck($diameters[$nearest] > $diameters[$farthest],
    'El diámetro lunar no varía inversamente con la distancia.');

$filteredRequest = derivedRequest('2026-01-01', '2026-01-31', $fields, 'full_moon');
$filteredPlan = (new SeriesPlanner($catalog))->plan($filteredRequest->fields);
$filtered = (new SeriesBuilder($catalog))->build($filteredRequest, $filteredPlan, null, [[
    'date' => '2026-01-03', 'phase' => 'full_moon', 'phase_label' => 'Luna llena',
    'instant' => '2026-01-03T07:02:54-03:00',
]]);
derivedCheck(count($filtered['rows']) === 1 && $filtered['rows'][0]['main_phase'] === 'Luna llena',
    'El filtro por fase no conserva las variables nuevas.');

foreach (['sun_equation_of_time_minutes', 'sun_moon_angular_distance', 'moon_apparent_diameter_arcmin'] as $field) {
    $definition = $catalog->get($field);
    derivedCheck($definition['type'] === 'number' && !isset($definition['extrema_supported']),
        'Contrato o soporte de extremos incorrecto para ' . $field . '.');
}

$solar = (new MeeusSolarPositionCalculator())->calculate(
    new DateTimeImmutable('2026-02-11 00:00:00', $zone), 0.0, 0.0
);
derivedCheck($solar->equationOfTimeMinutes < -10.0,
    'El signo de la ecuación del tiempo no coincide con aparente menos medio.');

echo "Instant derived variables smoke tests: OK\n";
