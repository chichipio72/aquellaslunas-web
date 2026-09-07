<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\CachedCelesTrakTleProvider;
use AstronomyEngine\Satellite\FixtureSatelliteTleProvider;
use AstronomyEngine\Satellite\SatelliteLunarTransitService;
use AstronomyEngine\Satellite\TleDownloader;

function tleServiceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

final class TestTleDownloader implements TleDownloader
{
    /** @var list<string|Throwable> */
    public array $responses;
    public int $calls = 0;
    /** @param list<string|Throwable> $responses */
    public function __construct(array $responses) { $this->responses = $responses; }
    public function download(int $catalogNumber): string
    {
        $response = $this->responses[$this->calls] ?? new RuntimeException('No queued response.');
        $this->calls++;
        if ($response instanceof Throwable) throw $response;
        return $response;
    }
}

function tleFixture(string $id): string
{
    $contents = file_get_contents(__DIR__ . '/fixtures/satellite/' . $id . '.tle');
    if (!is_string($contents)) throw new RuntimeException('Missing fixture ' . $id . '.');
    return $contents;
}

$temporary = sys_get_temp_dir() . '/aquellas-lunas-tle-test-' . bin2hex(random_bytes(6));
if (!mkdir($temporary, 0775, true)) throw new RuntimeException('Could not create temporary test directory.');
$cachePath = $temporary . '/tle-cache.json';
$now = new DateTimeImmutable('2026-07-25T00:00:00Z');
$clock = static function () use (&$now): DateTimeImmutable { return $now; };

try {
    $downloader = new TestTleDownloader([tleFixture('iss'), tleFixture('iss'), new RuntimeException('offline')]);
    $provider = new CachedCelesTrakTleProvider($cachePath, 60, $downloader, $clock);

    $first = $provider->resolve('iss');
    tleServiceAssert($first->cacheStatus === 'refreshed' && $downloader->calls === 1, 'Initial cache population failed.');
    $hot = $provider->resolve('iss');
    tleServiceAssert($hot->cacheStatus === 'cache_hit' && $downloader->calls === 1, 'Hot cache caused a download.');

    $now = $now->modify('+61 seconds');
    $updated = $provider->resolve('iss');
    tleServiceAssert($updated->cacheStatus === 'refreshed' && $downloader->calls === 2, 'Expired cache was not refreshed.');

    $now = $now->modify('+61 seconds');
    $fallback = $provider->resolve('iss');
    tleServiceAssert($fallback->cacheStatus === 'fallback' && $downloader->calls === 3 && $fallback->warnings !== [], 'Download failure did not use valid fallback.');

    $localDownloader = new TestTleDownloader([new RuntimeException('web must not download stale cache')]);
    $localFirst = new CachedCelesTrakTleProvider($cachePath, 60, $localDownloader, $clock, false);
    $staleLocal = $localFirst->resolve('iss');
    tleServiceAssert($staleLocal->cacheStatus === 'cache_hit' && $localDownloader->calls === 0,
        'Local-first resolution downloaded merely because a valid cache entry was stale.');

    $cacheBeforeFailedRefresh = file_get_contents($cachePath);
    try {
        $localFirst->refresh('iss');
        throw new RuntimeException('Failed forced refresh was reported as successful.');
    } catch (RuntimeException $exception) {
        tleServiceAssert(str_contains($exception->getMessage(), 'Could not refresh a valid TLE'),
            'Forced refresh returned an unclear download error.');
    }
    tleServiceAssert(file_get_contents($cachePath) === $cacheBeforeFailedRefresh,
        'Failed forced refresh modified the last valid cache.');

    $invalidRefresh = tleFixture('iss');
    $invalidRefresh[68] = $invalidRefresh[68] === '0' ? '1' : '0';
    $invalidRefreshProvider = new CachedCelesTrakTleProvider(
        $cachePath, 60, new TestTleDownloader([$invalidRefresh]), $clock, false
    );
    try {
        $invalidRefreshProvider->refresh('iss');
        throw new RuntimeException('Invalid forced refresh was reported as successful.');
    } catch (RuntimeException $exception) {
        tleServiceAssert(str_contains($exception->getMessage(), 'Could not refresh a valid TLE'),
            'Invalid forced refresh returned an unclear validation error.');
    }
    tleServiceAssert(file_get_contents($cachePath) === $cacheBeforeFailedRefresh,
        'Invalid forced refresh replaced the last valid cache.');

    $forcedDownloader = new TestTleDownloader([tleFixture('iss')]);
    $forcedProvider = new CachedCelesTrakTleProvider($cachePath, 60, $forcedDownloader, $clock, false);
    $forced = $forcedProvider->refresh('iss');
    tleServiceAssert($forced->cacheStatus === 'refreshed' && $forcedDownloader->calls === 1,
        'Forced refresh did not download and persist a valid TLE.');

    $emptyCacheDownloader = new TestTleDownloader([tleFixture('tiangong')]);
    $emptyCacheProvider = new CachedCelesTrakTleProvider(
        $temporary . '/empty-local-first.json', 60, $emptyCacheDownloader, $clock, false
    );
    $lastResort = $emptyCacheProvider->resolve('tiangong');
    tleServiceAssert($lastResort->cacheStatus === 'refreshed' && $emptyCacheDownloader->calls === 1,
        'Local-first resolution did not download as a last resort when no valid cache existed.');

    $invalid = tleFixture('tiangong');
    $invalid[68] = $invalid[68] === '0' ? '1' : '0';
    $invalidProvider = new CachedCelesTrakTleProvider($temporary . '/invalid.json', 60, new TestTleDownloader([$invalid]), $clock);
    try {
        $invalidProvider->resolve('tiangong');
        throw new RuntimeException('Invalid TLE was accepted.');
    } catch (RuntimeException $exception) {
        tleServiceAssert(str_contains($exception->getMessage(), 'no valid cache'), 'Invalid TLE returned an unclear error.');
    }

    $observer = new AstronomyObserver(-34.53, -58.48, 'America/Argentina/Buenos_Aires');
    $offlineService = new SatelliteLunarTransitService(new FixtureSatelliteTleProvider(__DIR__ . '/fixtures/satellite'));
    $search = $offlineService->search($observer, new DateTimeImmutable('2026-07-26T00:00:00-03:00'), 48, ['iss', 'tiangong']);
    $alertEvents = array_values(array_filter($search->search->events,
        static fn($event): bool => in_array($event->classification, ['transit', 'very_close'], true)));
    tleServiceAssert(count($alertEvents) === 1, 'Offline 48-hour search lost the historical alert event.');
    tleServiceAssert($alertEvents[0]->satellite === 'tiangong', 'Offline search returned the wrong satellite.');
    tleServiceAssert($search->metrics['tle_fixtures'] === 2 && $search->metrics['service_total_ms'] > 0, 'Service counters are incomplete.');
    tleServiceAssert(count($search->tleMetadata) === 2 && $search->warnings !== [], 'Service metadata or warnings are missing.');

    echo json_encode([
        'status' => 'OK',
        'cache' => ['hot' => $hot->cacheStatus, 'expired' => $updated->cacheStatus, 'fallback' => $fallback->cacheStatus,
            'local_first' => $staleLocal->cacheStatus, 'forced' => $forced->cacheStatus,
            'download_calls' => $downloader->calls],
        'offline_48h' => ['events' => count($alertEvents), 'event' => $alertEvents[0]->data(),
            'metrics' => $search->metrics],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} finally {
    foreach (glob($temporary . '/*') ?: [] as $path) unlink($path);
    rmdir($temporary);
}
