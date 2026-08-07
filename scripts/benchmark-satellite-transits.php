<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\FixtureSatelliteTleProvider;
use AstronomyEngine\Satellite\SatelliteTransitService;

$service = new SatelliteTransitService(new FixtureSatelliteTleProvider(dirname(__DIR__) . '/tests/fixtures/satellite'));
$observer = new AstronomyObserver(41.87, 12.49, 'UTC');
$start = new DateTimeImmutable('2026-07-24T00:00:00Z');
$runs = [];
foreach ([24, 48] as $hours) {
    foreach ([['moon'], ['sun'], ['moon', 'sun']] as $targets) {
        if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
        $result = $service->search($observer, $start, $hours, ['iss', 'tiangong'], $targets);
        $runs[] = [
            'hours' => $hours, 'targets' => $targets,
            'events' => array_map(static fn($event): array => ['target_body' => $event->targetBody,
                'satellite' => $event->satellite, 'classification' => $event->classification,
                'maximum' => $event->maximum->format('Y-m-d\TH:i:s.uP')], $result->search->events),
            'metrics' => $result->metrics, 'peak_memory_bytes' => memory_get_peak_usage(true),
        ];
    }
}
echo json_encode(['scenario' => 'offline-satellite-transits', 'satellites' => ['iss','tiangong'],
    'observer' => $observer->data(), 'runs' => $runs, 'php_version' => PHP_VERSION],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
