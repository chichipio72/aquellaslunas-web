<?php

declare(strict_types=1);

require_once __DIR__ . '/astronomy-trace.php';
require_once __DIR__ . '/home-notification-links.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\CachedCelesTrakTleProvider;
use AstronomyEngine\Satellite\ResolvedTle;
use AstronomyEngine\Satellite\SatelliteTransitEvent;
use AstronomyEngine\Satellite\SatelliteTransitService;
use AstronomyEngine\Satellite\SatelliteTransitServiceResult;

/** @return array{0:?DateTimeImmutable,1:?DateTimeImmutable} */
function homeSatelliteNightWindow(?array $tonightData, string $timezoneName): array
{
    $timezone = new DateTimeZone($timezoneName);
    $parse = static function ($value) use ($timezone): ?DateTimeImmutable {
        if (!is_string($value) || trim($value) === '') return null;
        try { return (new DateTimeImmutable($value))->setTimezone($timezone); }
        catch (Throwable) { return null; }
    };
    return [$parse($tonightData['night']['start'] ?? null), $parse($tonightData['night']['end'] ?? null)];
}

/**
 * @param list<SatelliteTransitEvent> $events
 * @return array{tonight_events:list<SatelliteTransitEvent>,upcoming_events:list<SatelliteTransitEvent>}
 */
function homeSatellitePartitionEvents(array $events, DateTimeImmutable $now, DateTimeImmutable $limit,
    ?DateTimeImmutable $nightStart, ?DateTimeImmutable $nightEnd): array
{
    $tonight = []; $upcoming = [];
    foreach ($events as $event) {
        if (!$event instanceof SatelliteTransitEvent || $event->maximum < $now || $event->maximum > $limit) continue;
        $belongsToTonight = $event->targetBody === 'moon' && $nightStart !== null && $nightEnd !== null
            && $event->maximum >= $nightStart && $event->maximum <= $nightEnd;
        if ($belongsToTonight) $tonight[] = $event;
        else $upcoming[] = $event;
    }
    $sort = static fn(SatelliteTransitEvent $first, SatelliteTransitEvent $second): int => $first->maximum <=> $second->maximum;
    usort($tonight, $sort); usort($upcoming, $sort);
    return ['tonight_events' => $tonight, 'upcoming_events' => $upcoming];
}

/** @param list<SatelliteTransitEvent> $events @return list<SatelliteTransitEvent> */
function homeSatelliteDisplayEvents(array $events): array
{
    $important = array_values(array_filter($events, static fn($event): bool => $event instanceof SatelliteTransitEvent
        && in_array($event->classification, ['transit', 'very_close'], true)));
    if ($important !== []) return $important;
    $nearPasses = array_values(array_filter($events, static fn($event): bool => $event instanceof SatelliteTransitEvent
        && $event->classification === 'near_pass'));
    usort($nearPasses, static fn(SatelliteTransitEvent $first, SatelliteTransitEvent $second): int =>
        ($first->minimumSeparationDegrees - $first->targetApparentRadiusDegrees)
            <=> ($second->minimumSeparationDegrees - $second->targetApparentRadiusDegrees));
    return $nearPasses === [] ? [] : [$nearPasses[0]];
}

/** @return array{title:string,time:string,duration:?string,edge_distance:?string,solar_warning:?string} */
function homeSatelliteEventPresentation(SatelliteTransitEvent $event, string $timezoneName,
    DateTimeImmutable $now): array
{
    $timezone = new DateTimeZone($timezoneName);
    $eventLocal = $event->maximum->setTimezone($timezone);
    $nowLocal = $now->setTimezone($timezone);
    $weekdays = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
    $eventTime = $eventLocal->format('Y-m-d') === $nowLocal->format('Y-m-d')
        ? $eventLocal->format('H:i')
        : $weekdays[(int) $eventLocal->format('N')] . ' ' . $eventLocal->format('j') . ' · ' . $eventLocal->format('H:i');
    $satellite = $event->satellite === 'iss' ? 'ISS' : ($event->satellite === 'tiangong' ? 'Tiangong' : $event->satelliteName);
    $body = $event->targetBody === 'sun' ? 'al Sol' : 'a la Luna';
    $classification = $event->classification === 'transit' ? 'tránsito' : 'acercamiento muy cercano';
    $duration = $event->durationSeconds !== null
        ? number_format($event->durationSeconds, $event->durationSeconds < 10.0 ? 1 : 0, ',', '.') . ' s'
        : null;
    return [
        'title' => $event->classification === 'near_pass'
            ? $satellite . ' pasará cerca ' . ($event->targetBody === 'sun' ? 'del Sol' : 'de la Luna')
            : $satellite . ' · ' . $classification . ' frente ' . $body,
        'time' => $eventTime,
        'duration' => $duration,
        'edge_distance' => $event->classification === 'near_pass'
            ? number_format(max(0.0, $event->minimumSeparationDegrees - $event->targetApparentRadiusDegrees), 1, ',', '.') . '°'
            : null,
        'solar_warning' => $event->targetBody === 'sun'
            ? 'No observes el Sol directamente ni con instrumentos sin un filtro solar certificado.'
            : null,
    ];
}

/** @param list<SatelliteTransitEvent> $events */
function renderHomeSatelliteEventItems(array $events, string $timezoneName, DateTimeImmutable $now,
    bool $featureFirst = false): void
{
    foreach (homeSatelliteDisplayEvents($events) as $index => $event) {
        $presentation = homeSatelliteEventPresentation($event, $timezoneName, $now);
        $class = 'home-v2-event home-v2-event--satellite'
            . ($featureFirst && $index === 0 ? ' home-v2-event--featured' : '');
        ?>
        <section class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" data-satellite-event data-satellite-target="<?= htmlspecialchars($event->targetBody, ENT_QUOTES, 'UTF-8') ?>">
            <?php renderHomeNotificationLink('satellite_transit'); ?>
            <time datetime="<?= htmlspecialchars($event->maximum->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($presentation['time'], ENT_QUOTES, 'UTF-8') ?></time>
            <h3><?= htmlspecialchars($presentation['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <?php if ($presentation['duration'] !== null): ?><p>Duración <?= htmlspecialchars($presentation['duration'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <?php if ($presentation['edge_distance'] !== null): ?><p>A unos <?= htmlspecialchars($presentation['edge_distance'], ENT_QUOTES, 'UTF-8') ?> del borde.</p><?php endif; ?>
            <?php if ($presentation['solar_warning'] !== null): ?><p class="home-v2-satellite-event__safety"><?= htmlspecialchars($presentation['solar_warning'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </section>
        <?php
    }
}

/** @return array<string,mixed> */
function homeSatelliteDiagnostic(array $context, float $totalMilliseconds, ?array $location = null): array
{
    $events = [];
    foreach (($context['events'] ?? []) as $event) {
        if (!$event instanceof SatelliteTransitEvent) continue;
        $events[] = [
            'satellite' => $event->satellite,
            'target' => $event->targetBody,
            'maximum' => $event->maximum->format('Y-m-d\TH:i:s.uP'),
            'classification' => $event->classification,
            'tle_epoch' => $event->tleEpochUtc->format('Y-m-d\TH:i:s.uP'),
        ];
    }
    $tleSources = [];
    $result = $context['combined_result'] ?? null;
    $searchStart = $result instanceof SatelliteTransitServiceResult ? $result->search->start : null;
    $searchEnd = $result instanceof SatelliteTransitServiceResult ? $result->search->end : null;
    foreach (($context['tle_metadata'] ?? []) as $satellite => $metadata) {
        if ($metadata instanceof ResolvedTle) {
            $epoch = $metadata->tle->epochUtc;
            $downloadedAt = $metadata->downloadedAtUtc;
            $cacheStatus = $metadata->cacheStatus;
            $satellite = $metadata->satellite;
        } elseif (is_array($metadata)) {
            try {
                $epoch = new DateTimeImmutable((string) ($metadata['epoch_utc'] ?? ''));
                $downloadedAt = new DateTimeImmutable((string) ($metadata['downloaded_at_utc'] ?? ''));
            } catch (Throwable) {
                continue;
            }
            $cacheStatus = (string) ($metadata['cache_status'] ?? 'unknown');
        } else {
            continue;
        }
        $ageAtStart = $searchStart !== null ? ($searchStart->getTimestamp() - $epoch->getTimestamp()) / 3600.0 : null;
        $ageAtEnd = $searchEnd !== null ? ($searchEnd->getTimestamp() - $epoch->getTimestamp()) / 3600.0 : null;
        $referenceAge = $ageAtEnd ?? $ageAtStart;
        $visualStatus = $referenceAge === null || $referenceAge < 24.0
            ? 'normal'
            : ($referenceAge <= 72.0 ? 'warning' : 'unreliable');
        $tleSources[(string) $satellite] = [
            'cache_status' => $cacheStatus,
            'downloaded_at_utc' => $downloadedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'),
            'epoch_utc' => $epoch->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'),
            'age_at_start_hours' => $ageAtStart,
            'age_at_end_hours' => $ageAtEnd,
            'visual_status' => $visualStatus,
            'visual_label' => match ($visualStatus) {
                'warning' => 'advertencia', 'unreliable' => 'no confiable', default => 'normal',
            },
        ];
    }
    return [
        'status' => match ($context['status'] ?? null) {
            'ok' => 'ejecutado correctamente', 'disabled' => 'deshabilitado', default => 'fallido',
        },
        'total_ms' => max(0.0, $totalMilliseconds),
        'tle_resolution_ms' => $context['metrics']['tle_resolution_ms'] ?? null,
        'calculation_ms' => $context['metrics']['total_ms'] ?? null,
        'request_id' => bin2hex(random_bytes(8)),
        'generated_at_utc' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP'),
        'result_source' => ($context['status'] ?? null) === 'ok' ? 'calculated' : 'calculation_failed',
        'error' => is_string($context['error'] ?? null) ? $context['error'] : null,
        'location' => is_array($location) ? [
            'latitude' => (float) ($location['latitude'] ?? 0.0),
            'longitude' => (float) ($location['longitude'] ?? 0.0),
            'elevation_meters' => (float) ($location['elevation_meters'] ?? 0.0),
            'timezone' => (string) ($location['timezone'] ?? ''),
            'mode' => (string) ($location['mode'] ?? ''),
        ] : null,
        'tle_sources' => $tleSources,
        'events' => $events,
    ];
}

/**
 * Executes the unified satellite service exactly once and builds the shared
 * home-page slice consumed by the Tonight and Upcoming sections.
 *
 * @param array{latitude:float,longitude:float,timezone:string} $location
 * @param null|callable(AstronomyObserver,DateTimeImmutable,int,array,array):SatelliteTransitServiceResult $runner
 * @return array<string,mixed>
 */
function homeSatelliteContextUntraced(array $location, DateTimeImmutable $now, ?array $tonightData,
    ?callable $runner = null, bool $enabled = true): array
{
    $empty = [
        'status' => 'unavailable', 'combined_result' => null, 'events' => [],
        'tonight_events' => [], 'upcoming_events' => [], 'tle_metadata' => [],
        'warnings' => [], 'metrics' => [], 'error' => null,
    ];
    if (!$enabled) {
        $empty['status'] = 'disabled';
        return $empty;
    }
    try {
        $observer = new AstronomyObserver(
            (float) $location['latitude'], (float) $location['longitude'], (string) $location['timezone'],
            (float) ($location['elevation_meters'] ?? 0.0)
        );
        if ($runner === null) {
            $cachePath = (string) (getenv('ASTRONOMY_TLE_CACHE_PATH')
                ?: dirname(__DIR__) . '/astronomy-engine/cache/satellite/tle-cache.json');
            $ttl = (int) (getenv('ASTRONOMY_TLE_CACHE_TTL_SECONDS')
                ?: CachedCelesTrakTleProvider::DEFAULT_TTL_SECONDS);
            $service = new SatelliteTransitService(new CachedCelesTrakTleProvider($cachePath, $ttl));
            $runner = static fn(AstronomyObserver $resolvedObserver, DateTimeImmutable $start, int $hours,
                array $satellites, array $targets): SatelliteTransitServiceResult =>
                $service->search($resolvedObserver, $start, $hours, $satellites, $targets);
        }
        $result = $runner($observer, $now, 48, ['iss', 'tiangong'], ['moon', 'sun']);
        if (!$result instanceof SatelliteTransitServiceResult) throw new RuntimeException('Invalid satellite home result.');
        $limit = $now->modify('+48 hours');
        [$nightStart, $nightEnd] = homeSatelliteNightWindow($tonightData, (string) $location['timezone']);
        $partition = homeSatellitePartitionEvents($result->search->events, $now, $limit, $nightStart, $nightEnd);
        return [
            'status' => 'ok', 'combined_result' => $result, 'events' => $result->search->events,
            'tonight_events' => $partition['tonight_events'], 'upcoming_events' => $partition['upcoming_events'],
            'tle_metadata' => $result->tleMetadata, 'warnings' => $result->warnings,
            'metrics' => $result->metrics, 'error' => null,
        ];
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas home satellite search error: ' . $exception->getMessage());
        $empty['error'] = $exception->getMessage();
        return $empty;
    }
}

/** Ejecuta y traza una búsqueda satelital completa de portada. */
function homeSatelliteContext(array $location, DateTimeImmutable $now, ?array $tonightData,
    ?callable $runner = null, bool $enabled = true): array
{
    return astronomyTraceExecute('satellite_transits', 'home', [
        'start' => $now->format('Y-m-d\TH:i:s.uP'), 'hours' => 48,
        'satellites' => ['iss', 'tiangong'], 'targets' => ['moon', 'sun'],
        'enabled' => $enabled,
        'night' => is_array($tonightData['night'] ?? null) ? $tonightData['night'] : null,
    ], $location, static fn(): array => homeSatelliteContextUntraced($location, $now, $tonightData, $runner, $enabled),
        static function (array $context): array {
            $result = $context['combined_result'] ?? null;
            return [
                'status' => (string) ($context['status'] ?? 'unavailable'),
                'result' => $result instanceof SatelliteTransitServiceResult ? $result->data() : null,
                'events' => array_map(static fn(SatelliteTransitEvent $event): array => $event->data(), $context['events'] ?? []),
                'tonight_events' => array_map(static fn(SatelliteTransitEvent $event): array => $event->data(), $context['tonight_events'] ?? []),
                'upcoming_events' => array_map(static fn(SatelliteTransitEvent $event): array => $event->data(), $context['upcoming_events'] ?? []),
                'warnings' => is_array($context['warnings'] ?? null) ? $context['warnings'] : [],
                'error' => is_string($context['error'] ?? null) ? $context['error'] : null,
            ];
        });
}
