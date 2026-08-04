<?php

declare(strict_types=1);

use Explorador\ExtremaRequest;
use Explorador\LocalExtremaDetector;
use Explorador\VariableCatalog;

require_once dirname(__DIR__) . '/includes/VariableCatalog.php';
require_once dirname(__DIR__) . '/includes/PhaseEventDateProvider.php';
require_once dirname(__DIR__) . '/includes/SeriesRequest.php';
require_once dirname(__DIR__) . '/includes/ExtremaRequest.php';
require_once dirname(__DIR__) . '/includes/LocalExtremaDetector.php';

function extremaCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$catalog = new VariableCatalog();
$supported = array_filter($catalog->all(), static fn(array $item): bool => ($item['extrema_supported'] ?? false) === true);
extremaCheck(count($supported) === 13, 'Cantidad inesperada de variables compatibles.');

$detector = new LocalExtremaDetector();
$zone = new DateTimeZone('UTC');
$from = new DateTimeImmutable('2026-01-02', $zone);
$to = new DateTimeImmutable('2026-01-04', $zone);
$plateau = $detector->detect([
    ['date' => '2026-01-01', 'value' => 0], ['date' => '2026-01-02', 'value' => 1],
    ['date' => '2026-01-03', 'value' => 1], ['date' => '2026-01-04', 'value' => 0],
    ['date' => '2026-01-05', 'value' => 2],
], 'value', $from, $to);
extremaCheck(count($plateau['maximos']) === 1 && $plateau['maximos'][0]['fecha'] === '2026-01-02',
    'La meseta máxima no conservó la asimetría solicitada.');
extremaCheck(count($plateau['minimos']) === 1 && $plateau['minimos'][0]['fecha'] === '2026-01-04',
    'No se clasificó el mínimo del último día usando el vecino posterior.');

$broken = $detector->detect([
    ['date' => '2026-01-01', 'value' => 0], ['date' => '2026-01-02', 'value' => 1],
    ['date' => '2026-01-03', 'value' => null], ['date' => '2026-01-05', 'value' => 0],
], 'value', $from, $to);
extremaCheck($broken['maximos'] === [] && $broken['minimos'] === [], 'Un null o hueco no cortó la continuidad.');

$base = ['modo' => 'extremos', 'variable' => 'moon_distance_geocentric', 'tipo_extremo' => 'ambos',
    'fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-12-31', 'lat' => '-34.6037',
    'lon' => '-58.3816', 'timezone' => 'America/Argentina/Buenos_Aires'];
$request = ExtremaRequest::fromParameters($base, $catalog);
extremaCheck($request->warning !== null && $request->calculation->days === $request->requested->days + 2,
    'El rango corto o los vecinos auxiliares no se resolvieron correctamente.');
$difference = ExtremaRequest::fromParameters(array_replace($base, ['variable' => 'moonrise_daily_difference']), $catalog);
extremaCheck($difference->calculation->days === $difference->requested->days + 3,
    'Una diferencia diaria no recibió el historial auxiliar adicional.');

foreach ([
    array_replace($base, ['variable' => 'moon_azimuth']),
    $base + ['fases' => 'full_moon'],
    $base + ['campos' => 'moon_distance_geocentric,daylight_hours'],
] as $invalid) {
    $failed = false;
    try { ExtremaRequest::fromParameters($invalid, $catalog); }
    catch (InvalidArgumentException $exception) { $failed = true; }
    extremaCheck($failed, 'No se rechazó una solicitud incompatible de extremos.');
}

echo "Local extrema smoke tests: OK\n";
