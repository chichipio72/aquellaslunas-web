<?php

declare(strict_types=1);

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

function nodeCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$catalog = new VariableCatalog();
$fields = ['moon_ecliptic_longitude', 'moon_node_angle',
    'moon_mean_ascending_node_longitude', 'sun_node_angle'];
$request = SeriesRequest::fromParameters([
    'fecha_desde' => '2026-01-01', 'fecha_hasta' => '2026-12-31',
    'lat' => '-34.6037', 'lon' => '-58.3816',
    'timezone' => 'America/Argentina/Buenos_Aires', 'campos' => implode(',', $fields),
], $catalog);
$plan = (new SeriesPlanner($catalog))->plan($request->fields);
nodeCheck(count($plan['providers']) === 2, 'No se compartieron los proveedores instantáneos.');
$result = (new SeriesBuilder($catalog))->build($request, $plan);
nodeCheck(count($result['rows']) === 365, 'El rango anual es incorrecto.');

foreach ($fields as $field) {
    $values = array_column($result['rows'], $field);
    nodeCheck(min($values) >= 0.0 && max($values) < 360.0, $field . ' fuera de 0°–360°.');
    $definition = $catalog->get($field);
    nodeCheck(($definition['extrema_supported'] ?? null) === false, $field . ' admite extremos.');
    nodeCheck(($definition['phase_supported'] ?? false) && ($definition['relation_supported'] ?? false),
        $field . ' no declara compatibilidad de fase/relación.');
}

$nearAscending = array_reduce($result['rows'], static fn(?array $best, array $row): array =>
    $best === null || min($row['moon_node_angle'], 360 - $row['moon_node_angle'])
        < min($best['moon_node_angle'], 360 - $best['moon_node_angle']) ? $row : $best);
$nearDescending = array_reduce($result['rows'], static fn(?array $best, array $row): array =>
    $best === null || abs($row['moon_node_angle'] - 180) < abs($best['moon_node_angle'] - 180) ? $row : $best);
nodeCheck(min($nearAscending['moon_node_angle'], 360 - $nearAscending['moon_node_angle']) < 7.0,
    'No se encontró paso diario cercano al nodo ascendente.');
nodeCheck(abs($nearDescending['moon_node_angle'] - 180) < 7.0,
    'No se encontró paso diario cercano al nodo descendente.');

$nodeValues = array_column($result['rows'], 'moon_mean_ascending_node_longitude');
nodeCheck($nodeValues[count($nodeValues) - 1] < $nodeValues[0], 'El nodo medio no retrograda durante el año.');

$solarNearZero = $solarNearHalf = 0;
foreach ($result['rows'] as $row) {
    if (min($row['sun_node_angle'], 360 - $row['sun_node_angle']) < 1.0) $solarNearZero++;
    if (abs($row['sun_node_angle'] - 180) < 1.0) $solarNearHalf++;
}
nodeCheck($solarNearZero >= 1 && $solarNearHalf >= 1, 'El Sol no atraviesa ambas zonas nodales en el año.');

$longitudeWrapDates = $nodeAngleWrapDates = [];
for ($index = 1; $index < count($result['rows']); $index++) {
    if ($result['rows'][$index]['moon_ecliptic_longitude'] < $result['rows'][$index - 1]['moon_ecliptic_longitude'] - 180) {
        $longitudeWrapDates[] = $result['rows'][$index]['date'];
    }
    if ($result['rows'][$index]['moon_node_angle'] < $result['rows'][$index - 1]['moon_node_angle'] - 180) {
        $nodeAngleWrapDates[] = $result['rows'][$index]['date'];
    }
}
nodeCheck(count($longitudeWrapDates) >= 12 && count($nodeAngleWrapDates) >= 12
    && $longitudeWrapDates !== $nodeAngleWrapDates,
    'Los ciclos sidéreo y dracónico no muestran el desfase esperado.');

$phaseItems = [
    ['date' => '2026-01-18', 'phase' => 'new_moon', 'phase_label' => 'Luna nueva', 'instant' => '2026-01-18T16:51:00-03:00'],
    ['date' => '2026-01-26', 'phase' => 'first_quarter', 'phase_label' => 'Cuarto creciente', 'instant' => '2026-01-26T01:47:00-03:00'],
    ['date' => '2026-02-01', 'phase' => 'full_moon', 'phase_label' => 'Luna llena', 'instant' => '2026-02-01T19:09:00-03:00'],
    ['date' => '2026-02-09', 'phase' => 'last_quarter', 'phase_label' => 'Cuarto menguante', 'instant' => '2026-02-09T09:43:00-03:00'],
];
$phaseResult = (new SeriesBuilder($catalog))->build($request, $plan, null, $phaseItems);
nodeCheck(array_column($phaseResult['rows'], 'main_phase') === array_column($phaseItems, 'phase_label'),
    'El filtro no conserva las cuatro fases principales.');

echo "Lunar node cycle smoke tests: OK\n";
