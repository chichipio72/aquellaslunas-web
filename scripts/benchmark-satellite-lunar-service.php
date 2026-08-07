<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\CachedCelesTrakTleProvider;
use AstronomyEngine\Satellite\SatelliteLunarTransitService;

$cachePath = (string) (getenv('ASTRONOMY_TLE_CACHE_PATH')
    ?: dirname(__DIR__) . '/astronomy-engine/cache/satellite/tle-cache.json');
$ttl = (int) (getenv('ASTRONOMY_TLE_CACHE_TTL_SECONDS') ?: CachedCelesTrakTleProvider::DEFAULT_TTL_SECONDS);
$service = new SatelliteLunarTransitService(new CachedCelesTrakTleProvider($cachePath, $ttl));
$observer = new AstronomyObserver(-34.6037, -58.3816, 'America/Argentina/Buenos_Aires');
$start = new DateTimeImmutable('now', $observer->timezone);

// First call may populate or refresh the cache; the measured call must be hot.
$service->search($observer, $start, 1, ['iss', 'tiangong']);
if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
$result = $service->search($observer, $start, 48, ['iss', 'tiangong']);
$payload = $result->data();
echo json_encode([
    'scenario' => 'hot-cache-48h-iss-tiangong',
    'events' => $payload['events'],
    'tle_metadata' => $payload['tle_metadata'],
    'warnings' => $payload['warnings'],
    'metrics' => $payload['metrics'],
    'peak_memory_bytes' => memory_get_peak_usage(true),
    'php_version' => PHP_VERSION,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
