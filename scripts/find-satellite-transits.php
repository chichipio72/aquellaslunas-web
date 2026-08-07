<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/api-client.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\CachedCelesTrakTleProvider;
use AstronomyEngine\Satellite\FixtureSatelliteTleProvider;
use AstronomyEngine\Satellite\MoonTransitTargetProvider;
use AstronomyEngine\Satellite\SatelliteAngularTransitDetector;
use AstronomyEngine\Satellite\SatelliteTransitService;
use AstronomyEngine\Satellite\SunTransitTargetProvider;

function satelliteSearchUsage(): string
{
    return 'Uso: php scripts/find-satellite-transits.php --latitude=NUM --longitude=NUM --timezone=ZONA '
        . '--start=ISO8601 [--elevation=METROS] [--hours=24] [--satellites=iss,tiangong] [--targets=moon,sun] '
        . '[--include-nearest] [--json] [--offline-fixtures]';
}

try {
    $options = getopt('', ['latitude:', 'longitude:', 'elevation:', 'timezone:', 'start:', 'hours:', 'satellites:', 'targets:',
        'include-nearest', 'diagnostic-nearest', 'json', 'offline-fixtures', 'help']);
    if (isset($options['help'])) { echo satelliteSearchUsage() . "\n"; exit(0); }
    foreach (['latitude', 'longitude', 'timezone', 'start'] as $required) {
        if (!isset($options[$required]) || !is_string($options[$required])) throw new InvalidArgumentException('Falta --' . $required . '.');
    }
    if (!is_numeric($options['latitude']) || !is_numeric($options['longitude'])) throw new InvalidArgumentException('Latitud y longitud deben ser numéricas.');
    $elevation = isset($options['elevation']) ? (string) $options['elevation'] : '0';
    if (!is_numeric($elevation)) throw new InvalidArgumentException('--elevation debe ser numérica.');
    $hoursRaw = isset($options['hours']) ? (string) $options['hours'] : '24';
    if (preg_match('/^\d+$/', $hoursRaw) !== 1) throw new InvalidArgumentException('--hours debe ser entero.');
    $list = static fn(string $value): array => array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    $satellites = $list((string) ($options['satellites'] ?? 'iss,tiangong'));
    $targets = $list((string) ($options['targets'] ?? 'moon,sun'));
    if ($satellites === [] || array_diff($satellites, ['iss', 'tiangong']) !== []) throw new InvalidArgumentException('--satellites admite iss y/o tiangong.');
    if ($targets === [] || array_diff($targets, ['moon', 'sun']) !== []) throw new InvalidArgumentException('--targets admite moon y/o sun.');
    $startText = (string) $options['start'];
    if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $startText) !== 1) throw new InvalidArgumentException('--start debe incluir offset o Z.');
    $start = new DateTimeImmutable($startText);
    $observer = new AstronomyObserver((float) $options['latitude'], (float) $options['longitude'],
        (string) $options['timezone'], (float) $elevation);
    if (isset($options['offline-fixtures'])) {
        $provider = new FixtureSatelliteTleProvider(dirname(__DIR__) . '/tests/fixtures/satellite');
    } else {
        $provider = new CachedCelesTrakTleProvider(
            (string) (getenv('ASTRONOMY_TLE_CACHE_PATH') ?: dirname(__DIR__) . '/astronomy-engine/cache/satellite/tle-cache.json'),
            (int) (getenv('ASTRONOMY_TLE_CACHE_TTL_SECONDS') ?: CachedCelesTrakTleProvider::DEFAULT_TTL_SECONDS),
        );
    }
    $payload = (new SatelliteTransitService($provider))->search($observer, $start, (int) $hoursRaw, $satellites, $targets)->data();
    $includeNearest = isset($options['include-nearest']) || isset($options['diagnostic-nearest']);
    if ($includeNearest) {
        $resolved = []; $tles = [];
        foreach ($satellites as $satellite) {
            $resolved[$satellite] = $provider->resolve($satellite);
            $tles[$satellite] = $resolved[$satellite]->tle;
        }
        $targetProviders = array_map(static fn(string $target) => $target === 'moon'
            ? new MoonTransitTargetProvider() : new SunTransitTargetProvider(), $targets);
        $nearest = (new SatelliteAngularTransitDetector())->nearestApproaches(
            $observer, $start, (int) $hoursRaw, $tles, $targetProviders
        );
        $payload['diagnostic_nearest'] = array_map(static function ($event) use ($resolved): array {
            $data = $event->data();
            $data['distance_to_edge_degrees'] = $event->minimumSeparationDegrees - $event->targetApparentRadiusDegrees;
            $data['tle_metadata'] = $resolved[$event->satellite]->data();
            return $data;
        }, $nearest);
    }
    if (isset($options['json'])) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    } else {
        printf("Ventana: %s → %s\nObjetivos: %s\nEventos: %d\n", $payload['search_window']['start'],
            $payload['search_window']['end'], implode(', ', $payload['targets']), count($payload['events']));
        foreach ($payload['events'] as $event) printf("%s %s %s %s separación=%.6f° radio=%.6f°\n",
            $event['target_body'], $event['satellite'], $event['classification'], $event['maximum'],
            $event['minimum_separation_degrees'], $event['target_apparent_radius_degrees']);
        foreach (($payload['diagnostic_nearest'] ?? []) as $nearest) {
            printf("Mínimo diagnóstico %s/%s: %s clasificación=%s separación=%.6f° borde=%.6f°\n",
                $nearest['satellite'], $nearest['target_body'], $nearest['maximum'], $nearest['classification'],
                $nearest['minimum_separation_degrees'], $nearest['distance_to_edge_degrees']);
            printf("  Satélite alt=%.6f° az=%.6f° distancia=%.3f km · %s alt=%.6f° az=%.6f°\n",
                $nearest['satellite_altitude_degrees'], $nearest['satellite_azimuth_degrees'],
                $nearest['satellite_distance_km'], ucfirst($nearest['target_body']),
                $nearest['target_altitude_degrees'], $nearest['target_azimuth_degrees']);
            printf("  TLE epoch=%s\n  %s\n  %s\n", $nearest['tle']['epoch_utc'],
                $nearest['tle']['line1'], $nearest['tle']['line2']);
            foreach ($nearest['warnings'] as $warning) echo '  Advertencia: ' . $warning . "\n";
        }
        foreach ($payload['warnings'] as $warning) echo 'Advertencia: ' . $warning . "\n";
        echo 'Tiempo total: ' . $payload['metrics']['service_total_ms'] . " ms\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n" . satelliteSearchUsage() . "\n"); exit(1);
}
