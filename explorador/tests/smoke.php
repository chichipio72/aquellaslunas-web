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

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function requestFor(string $fields): SeriesRequest
{
    return SeriesRequest::fromParameters(['fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-01-03',
        'lat' => '-34.6037', 'lon' => '-58.3816', 'timezone' => 'America/Argentina/Buenos_Aires',
        'campos' => $fields], new VariableCatalog());
}

$catalog = new VariableCatalog();
check(count($catalog->all()) === count(array_unique(array_keys($catalog->all()))), 'Las claves del catálogo no son únicas.');
$planner = new SeriesPlanner($catalog);
$plan = $planner->plan(['moonrise_daily_difference']);
check(in_array('moon_events', $plan['providers'], true), 'No se resolvió la dependencia lunar.');
check(in_array('moonrise_time', $plan['required'], true), 'No se incluyó el campo requerido.');

$cases = [
    'moon_illumination' => 'moon_instant',
    'sun_altitude' => 'sun_instant',
    'moonrise_time' => 'moon_events',
    'sunrise_time' => 'sun_events',
    'sunrise_daily_difference' => 'sun_events',
];
foreach ($cases as $field => $provider) {
    $request = requestFor($field);
    $plan = $planner->plan($request->fields);
    check($plan['providers'] === [$provider], 'Proveedor incorrecto para ' . $field . '.');
    $result = (new SeriesBuilder($catalog))->build($request, $plan);
    check(count($result['rows']) === 3, 'Cantidad de filas incorrecta para ' . $field . '.');
    check(array_keys($result['rows'][0]) === ['date', $field], 'La fila contiene campos internos para ' . $field . '.');
}

foreach ([
    ['lat' => '91', 'lon' => '0', 'timezone' => 'UTC'],
    ['lat' => '0', 'lon' => '181', 'timezone' => 'UTC'],
    ['lat' => '0', 'lon' => '0', 'timezone' => 'Invalid/Zone'],
] as $invalid) {
    $failed = false;
    try {
        SeriesRequest::fromParameters(['fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-01-03',
            'campos' => 'moon_illumination'] + $invalid, $catalog);
    } catch (InvalidArgumentException $exception) { $failed = true; }
    check($failed, 'No se rechazó una ubicación inválida.');
}
$failed = false;
try { requestFor('unknown_field'); } catch (InvalidArgumentException $exception) { $failed = true; }
check($failed, 'No se rechazó el campo desconocido.');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'css'], true)
        || str_contains($file->getPathname(), '/benchmarks/results/')) continue;
    $contents = file_get_contents($file->getPathname());
    check(is_string($contents) && preg_match('/\bmb_[A-Za-z0-9_]+\s*\(/', $contents) !== 1,
        'Se encontró una llamada mb_* en ' . $file->getPathname());
}

echo "Smoke tests: OK\n";
