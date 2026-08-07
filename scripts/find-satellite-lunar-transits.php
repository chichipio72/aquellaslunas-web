<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\CachedCelesTrakTleProvider;
use AstronomyEngine\Satellite\FixtureSatelliteTleProvider;
use AstronomyEngine\Satellite\SatelliteLunarTransitService;

function satelliteTransitUsage(): string
{
    return 'Uso: php scripts/find-satellite-lunar-transits.php --latitude=NUM --longitude=NUM '
        . '--timezone=ZONA --start=ISO8601 [--hours=24] [--satellites=iss,tiangong] [--json] '
        . '[--offline-fixtures]';
}

try {
    $options = getopt('', ['latitude:', 'longitude:', 'timezone:', 'start:', 'hours:', 'satellites:', 'json', 'offline-fixtures', 'help']);
    if (isset($options['help'])) { echo satelliteTransitUsage() . "\n"; exit(0); }
    foreach (['latitude', 'longitude', 'timezone', 'start'] as $required) {
        if (!isset($options[$required]) || !is_string($options[$required])) throw new InvalidArgumentException('Falta --' . $required . '.');
    }
    if (!is_numeric($options['latitude']) || !is_numeric($options['longitude'])) throw new InvalidArgumentException('Latitud y longitud deben ser numéricas.');
    $hoursRaw = isset($options['hours']) ? (string) $options['hours'] : '24';
    if (preg_match('/^\d+$/', $hoursRaw) !== 1) throw new InvalidArgumentException('--hours debe ser entero.');
    $selected = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($options['satellites'] ?? 'iss,tiangong'))))));
    if ($selected === [] || array_diff($selected, ['iss', 'tiangong']) !== []) throw new InvalidArgumentException('--satellites admite iss y/o tiangong.');
    $start = new DateTimeImmutable((string) $options['start']);
    if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', (string) $options['start']) !== 1) throw new InvalidArgumentException('--start debe incluir offset o Z.');
    $observer = new AstronomyObserver((float) $options['latitude'], (float) $options['longitude'], (string) $options['timezone']);
    $offline = isset($options['offline-fixtures']);
    if ($offline) {
        $provider = new FixtureSatelliteTleProvider(dirname(__DIR__) . '/tests/fixtures/satellite');
    } else {
        $ttl = (int) (getenv('ASTRONOMY_TLE_CACHE_TTL_SECONDS') ?: CachedCelesTrakTleProvider::DEFAULT_TTL_SECONDS);
        $cachePath = (string) (getenv('ASTRONOMY_TLE_CACHE_PATH')
            ?: dirname(__DIR__) . '/astronomy-engine/cache/satellite/tle-cache.json');
        $provider = new CachedCelesTrakTleProvider($cachePath, $ttl);
    }
    $result = (new SatelliteLunarTransitService($provider))->search($observer, $start, (int) $hoursRaw, $selected);
    $payload = $result->data();
    if (isset($options['json'])) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    } else {
        echo 'Ventana: ' . $payload['search_window']['start'] . ' → ' . $payload['search_window']['end'] . "\n";
        echo 'Eventos: ' . count($payload['events']) . "\n";
        foreach ($payload['events'] as $event) {
            printf("%s %s %s separación=%.6f° radio=%.6f°\n", $event['satellite'], $event['classification'],
                $event['maximum'], $event['minimum_separation_degrees'], $event['moon_apparent_radius_degrees']);
        }
        foreach ($payload['tle_metadata'] as $metadata) {
            printf("TLE %s: %s, edad %.2f h, confianza %s\n", $metadata['satellite'], $metadata['cache_status'],
                $metadata['epoch_age_hours'], $metadata['confidence']);
        }
        foreach ($payload['warnings'] as $warning) echo 'Advertencia: ' . $warning . "\n";
        echo 'Tiempo total: ' . $payload['metrics']['service_total_ms'] . " ms\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n" . satelliteTransitUsage() . "\n");
    exit(1);
}
