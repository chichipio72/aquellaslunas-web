<?php

declare(strict_types=1);

use Explorador\PhaseEventDateProvider;
use Explorador\PhaseSelectionException;
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

function phaseCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$zone = new DateTimeZone('America/Argentina/Buenos_Aires');
$from = new DateTimeImmutable('2026-01-01 00:00:00', $zone);
$to = new DateTimeImmutable('2026-01-02 00:00:00', $zone);
$local = PhaseEventDateProvider::localDates([
    ['event_type' => 'full_moon', 'event_time' => '2026-01-02 01:30:00.000000'],
], $from, $to, $zone, ['full_moon']);
phaseCheck(($local[0]['date'] ?? '') === '2026-01-01', 'No se convirtió el instante UTC a la fecha local anterior.');
phaseCheck(($local[0]['instant'] ?? '') === '2026-01-01T22:30:00-03:00', 'El instante local de fase es incorrecto.');

$catalog = new VariableCatalog();
$parameters = ['fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-01-02',
    'lat' => '-34.6037', 'lon' => '-58.3816', 'timezone' => 'America/Argentina/Buenos_Aires',
    'campos' => 'moonrise_daily_difference,moonset_daily_difference,sunrise_daily_difference,sunset_daily_difference'];
$dailyRequest = SeriesRequest::fromParameters($parameters, $catalog);
$plan = (new SeriesPlanner($catalog))->plan($dailyRequest->fields);
$daily = (new SeriesBuilder($catalog))->build($dailyRequest, $plan);
$filteredRequest = SeriesRequest::fromParameters($parameters + ['fases' => 'full_moon'], $catalog);
$filtered = (new SeriesBuilder($catalog))->build($filteredRequest, $plan, null, [[
    'date' => '2020-01-02', 'phase' => 'full_moon', 'phase_label' => 'Luna llena',
    'instant' => '2020-01-02T12:00:00-03:00',
]]);
phaseCheck(count($filtered['rows']) === 1, 'El filtro devolvió fechas auxiliares.');
foreach (['moonrise_daily_difference', 'moonset_daily_difference',
    'sunrise_daily_difference', 'sunset_daily_difference'] as $field) {
    phaseCheck($filtered['rows'][0][$field] === $daily['rows'][1][$field],
        'La diferencia diaria no utilizó el día civil anterior real para ' . $field . '.');
}

$failed = false;
try { SeriesRequest::fromParameters($parameters + ['fases' => 'fase_inventada'], $catalog); }
catch (InvalidArgumentException $exception) { $failed = true; }
phaseCheck($failed, 'No se rechazó una fase desconocida.');

$failed = false;
try {
    (new PhaseEventDateProvider(static function (): PDO { throw new RuntimeException('secret'); }))
        ->dates($from, $to, $zone, ['full_moon']);
} catch (PhaseSelectionException $exception) {
    $failed = $exception->getMessage() === 'No se pudieron consultar las fases lunares en MariaDB.';
}
phaseCheck($failed, 'La indisponibilidad de MariaDB no produjo el error público esperado.');

echo "Phase filter smoke tests: OK\n";
