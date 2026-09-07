<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/includes/api-client.php';

use AstronomyEngine\Satellite\CachedCelesTrakTleProvider;

$cachePath = (string) (getenv('ASTRONOMY_TLE_CACHE_PATH')
    ?: dirname(__DIR__) . '/astronomy-engine/cache/satellite/tle-cache.json');
$ttl = (int) (getenv('ASTRONOMY_TLE_CACHE_TTL_SECONDS')
    ?: CachedCelesTrakTleProvider::DEFAULT_TTL_SECONDS);
$provider = new CachedCelesTrakTleProvider($cachePath, $ttl);
$failed = false;

foreach (['iss' => 'ISS', 'tiangong' => 'Tiangong'] as $satellite => $label) {
    try {
        $resolved = $provider->refresh($satellite);
        printf(
            "%s | resultado=%s | epoch=%s | antigüedad=%.1f h\n",
            $label,
            $resolved->cacheStatus,
            $resolved->tle->epochUtc->format('Y-m-d H:i:s T'),
            $resolved->epochAgeHours,
        );
    } catch (Throwable $exception) {
        $failed = true;
        printf(
            "%s | resultado=error | epoch=— | antigüedad=— | error=%s\n",
            $label,
            str_replace(["\r", "\n"], ' ', $exception->getMessage()),
        );
    }
}

exit($failed ? 1 : 0);
