<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\SatelliteLunarTransitDetector;
use AstronomyEngine\Satellite\TleParser;

$parser = new TleParser(); $satellites = [];
foreach (['iss', 'tiangong'] as $id) {
    $lines = file(dirname(__DIR__) . '/tests/fixtures/satellite/' . $id . '.tle', FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || count($lines) !== 3) throw new RuntimeException('Missing TLE fixture ' . $id);
    $satellites[$id] = $parser->parse($lines[0], $lines[1], $lines[2]);
}
$observer = new AstronomyObserver(-34.53, -58.48, 'America/Argentina/Buenos_Aires');
$start = new DateTimeImmutable('2026-07-26 00:00:00', $observer->timezone);
// Warmup with the same computational path but a short window.
(new SatelliteLunarTransitDetector())->search($observer, $start, 1, ['iss' => $satellites['iss']]);
if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
$memoryInitial = memory_get_usage(true);
$runs = [];
foreach ([[24, ['iss']], [24, ['iss','tiangong']], [48, ['iss']], [48, ['iss','tiangong']]] as [$hours, $ids]) {
    $selected = []; foreach ($ids as $id) $selected[$id] = $satellites[$id];
    $result = (new SatelliteLunarTransitDetector())->search($observer, $start, $hours, $selected);
    $metrics = $result->metrics;
    $component = $metrics['propagation_ms'] + $metrics['topocentric_ms'] + $metrics['lunar_ms'];
    $total = max(0.001, $metrics['total_ms']);
    $runs[] = [
        'hours' => $hours, 'satellites' => $ids, 'event_count' => count($result->events),
        'events' => array_map(static fn($event): array => ['satellite' => $event->satellite,
            'classification' => $event->classification, 'maximum' => $event->maximum->format('Y-m-d\TH:i:s.uP')], $result->events),
        'metrics' => $metrics,
        'exclusive_component_distribution_percent' => [
            'propagation' => round($metrics['propagation_ms'] / $total * 100, 2),
            'topocentric' => round($metrics['topocentric_ms'] / $total * 100, 2),
            'lunar' => round($metrics['lunar_ms'] / $total * 100, 2),
            'search_and_object_overhead' => round(max(0.0, $total - $component) / $total * 100, 2),
        ],
    ];
}
$payload = [
    'scenario' => 'satellite-lunar-transits', 'dut1_seconds' => 0.0, 'warmup_hours' => 1,
    'runs' => $runs,
    'memory_bytes' => ['initial' => $memoryInitial, 'final' => memory_get_usage(true), 'peak' => memory_get_peak_usage(true)],
    'environment' => ['php_version' => PHP_VERSION, 'sapi' => PHP_SAPI,
        'opcache_cli_enabled' => filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL),
        'jit' => ini_get('opcache.jit') ?: null, 'jit_buffer_size' => (string) ini_get('opcache.jit_buffer_size')],
];
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
