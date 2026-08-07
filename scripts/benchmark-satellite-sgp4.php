<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\SatelliteTopocentricCalculator;
use AstronomyEngine\Satellite\Sgp4\Sgp4Propagator;
use AstronomyEngine\Satellite\TleParser;

function satelliteBenchmark(callable $operation): float
{
    $started = hrtime(true);
    $operation();
    return (hrtime(true) - $started) / 1_000_000.0;
}

$tlePath = dirname(__DIR__) . '/tests/fixtures/satellite/iss.tle';
$lines = file($tlePath, FILE_IGNORE_NEW_LINES);
if (!is_array($lines) || count($lines) !== 3) throw new RuntimeException('ISS TLE fixture is unavailable.');
$parser = new TleParser();
$tle = $parser->parse($lines[0], $lines[1], $lines[2]);
$propagator = new Sgp4Propagator($tle);
$instant = new DateTimeImmutable('2026-07-25 12:00:00.000000', new DateTimeZone('UTC'));
$observer = new AstronomyObserver(-34.6037, -58.3816, 'UTC', 0.0);
$transform = new SatelliteTopocentricCalculator();

// Short warmup outside measured regions.
for ($index = 0; $index < 100; $index++) $propagator->propagate($instant->modify("+{$index} seconds"));

$memoryInitial = memory_get_usage(true);
$metrics = [];
$metrics['parse_and_initialize_100_iterations_ms'] = satelliteBenchmark(function () use ($lines): void {
    for ($index = 0; $index < 100; $index++) {
        $tle = (new TleParser())->parse($lines[0], $lines[1], $lines[2]);
        new Sgp4Propagator($tle);
    }
});
foreach ([1, 100, 1000] as $count) {
    $metrics["propagate_{$count}_ms"] = satelliteBenchmark(function () use ($propagator, $instant, $count): void {
        for ($index = 0; $index < $count; $index++) $propagator->propagate($instant->modify("+{$index} seconds"));
    });
}
$states = [];
for ($index = 0; $index < 1000; $index++) $states[] = $propagator->propagate($instant->modify("+{$index} seconds"));
$metrics['topocentric_1000_ms'] = satelliteBenchmark(function () use ($states, $transform, $observer): void {
    foreach ($states as $state) $transform->calculate($state, $observer);
});

$opcache = filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL);
$jitBuffer = (string) ini_get('opcache.jit_buffer_size');
$result = [
    'scenario' => 'satellite-sgp4-basic',
    'tle' => 'ISS (ZARYA)',
    'warmup_propagations' => 100,
    'metrics_ms' => $metrics,
    'derived' => [
        'parse_and_initialize_average_us' => $metrics['parse_and_initialize_100_iterations_ms'] * 10.0,
        'propagation_average_us_1000' => $metrics['propagate_1000_ms'] * 1000.0 / 1000.0,
        'topocentric_average_us_1000' => $metrics['topocentric_1000_ms'] * 1000.0 / 1000.0,
    ],
    'memory_bytes' => ['initial' => $memoryInitial, 'final' => memory_get_usage(true), 'peak' => memory_get_peak_usage(true)],
    'environment' => [
        'php_version' => PHP_VERSION, 'sapi' => PHP_SAPI, 'loaded_php_ini' => php_ini_loaded_file() ?: null,
        'opcache_cli_enabled' => $opcache,
        'jit' => ini_get('opcache.jit') ?: null,
        'jit_buffer_size' => $jitBuffer,
    ],
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
