<?php

declare(strict_types=1);

use AstronomyEngine\Facade\AstronomyEventsFacade;
use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\CachedCelesTrakTleProvider;
use AstronomyEngine\Satellite\CelesTrakTleDownloader;
use AstronomyEngine\Satellite\SatelliteTransitService;
use AstronomyEngine\Satellite\SatelliteTransitEvent;

/** @return array<string,mixed> */
function astronomyPushDefaultParameters(string $notificationType): array
{
    return match ($notificationType) {
        'lunar_conjunction' => ['maximum_separation_deg' => 3.0],
        'satellite_transit' => [
            'satellites' => ['iss', 'tiangong'],
            'targets' => ['sun', 'moon'],
            'classifications' => ['transit', 'very_close'],
        ],
        'eclipse' => ['kinds' => ['solar', 'lunar']],
        default => [],
    };
}

/** @return array<string,mixed> */
function astronomyPushEventParameters(array $preference): array
{
    $stored = astronomyPushValidateParametersJson($preference['parameters_json'] ?? null);
    return $stored === null
        ? astronomyPushDefaultParameters((string) ($preference['notification_type'] ?? 'moonrise'))
        : array_replace(astronomyPushDefaultParameters((string) ($preference['notification_type'] ?? 'moonrise')), $stored);
}

function astronomyPushProviderObserver(array $device): AstronomyObserver
{
    return new AstronomyObserver(
        (float) $device['latitude'],
        (float) $device['longitude'],
        (string) $device['timezone'],
        (float) ($device['elevation_meters'] ?? 0.0),
    );
}

function astronomyPushPlanetName(string $id): ?string
{
    return match ($id) {
        'mercury' => 'Mercurio', 'venus' => 'Venus', 'mars' => 'Marte',
        'jupiter' => 'Júpiter', 'saturn' => 'Saturno', 'uranus' => 'Urano',
        'neptune' => 'Neptuno', default => null,
    };
}

function astronomyPushEclipseClassification(string $classification): string
{
    return match ($classification) {
        'total' => 'total', 'partial' => 'parcial', 'penumbral' => 'penumbral',
        'annular' => 'anular', default => $classification,
    };
}

/** @return list<array<string,mixed>> */
function astronomyPushEclipseEvents(array $device, DateTimeImmutable $nowUtc): array
{
    $observer = astronomyPushProviderObserver($device);
    $start = astronomyPushUtc($nowUtc)->setTimezone($observer->timezone);
    $result = (new AstronomyEventsFacade())->between($start, $observer, 366, ['eclipse']);
    $events = [];
    foreach ($result['items'] as $item) {
        $local = $item['details']['local'] ?? null;
        if (!is_array($local) || ($local['visible'] ?? false) !== true) continue;
        $subtype = (string) ($item['subtype'] ?? '');
        $kind = $subtype === 'solar_eclipse' ? 'solar' : ($subtype === 'lunar_eclipse' ? 'lunar' : '');
        if ($kind === '') continue;
        $parameters = astronomyPushEventParameters($device);
        if (!in_array($kind, $parameters['kinds'] ?? [], true)) continue;
        $eventUtc = astronomyPushUtc(new DateTimeImmutable((string) $item['datetime']));
        $classification = (string) ($local['localClassification'] ?? $item['details']['classification'] ?? '');
        $classificationEs = astronomyPushEclipseClassification($classification);
        $events[] = [
            'notification_type' => 'eclipse',
            'event_key' => $kind . ':' . $classification . ':' . $eventUtc->format('Ymd\THis\Z'),
            'event_time_utc' => $eventUtc,
            'event_time_local' => $eventUtc->setTimezone($observer->timezone),
            'eclipse_kind' => $kind === 'solar' ? 'solar' : 'lunar',
            'eclipse_type' => 'eclipse ' . ($kind === 'solar' ? 'solar ' : 'lunar ') . $classificationEs,
            'metadata' => ['visibility' => $local['visibilityClass'] ?? ($local['visible'] ? 'visible' : 'not_visible')],
        ];
    }
    return $events;
}

/** @return list<array<string,mixed>> */
function astronomyPushLunarConjunctionEvents(array $device, DateTimeImmutable $nowUtc): array
{
    $observer = astronomyPushProviderObserver($device);
    $parameters = astronomyPushEventParameters($device);
    $maximum = (float) ($parameters['maximum_separation_deg'] ?? 3.0);
    if (!is_finite($maximum) || $maximum <= 0.0 || $maximum > 5.0) {
        throw new RuntimeException('La separación máxima de conjunciones no es válida.');
    }
    $result = (new AstronomyEventsFacade())->between(
        astronomyPushUtc($nowUtc)->setTimezone($observer->timezone), $observer, 3, ['conjunction']
    );
    $events = [];
    foreach ($result['items'] as $item) {
        $details = $item['details'] ?? null;
        if (!is_array($details) || ($details['object_kind'] ?? null) !== 'planet') continue;
        $separation = (float) ($details['separation_degrees'] ?? INF);
        if (!is_finite($separation) || $separation > $maximum) continue;
        $planet = (string) ($details['planet'] ?? $item['subtype'] ?? '');
        $publicName = astronomyPushPlanetName($planet);
        if ($publicName === null) continue;
        $eventUtc = astronomyPushUtc(new DateTimeImmutable((string) $item['datetime']));
        $events[] = [
            'notification_type' => 'lunar_conjunction',
            'event_key' => 'moon:' . $planet . ':' . $eventUtc->format('Ymd\THis\Z'),
            'event_time_utc' => $eventUtc,
            'event_time_local' => $eventUtc->setTimezone($observer->timezone),
            'object_name' => $publicName,
            'separation' => number_format($separation, 1, ',', '') . '°',
            'separation_degrees' => $separation,
            'metadata' => ['visibility_classification' => $details['visibility_classification'] ?? null],
        ];
    }
    return $events;
}

/** @return list<array<string,mixed>> */
function astronomyPushSatelliteTransitEvents(array $device, DateTimeImmutable $nowUtc,
    ?SatelliteTransitService $service = null): array
{
    $parameters = astronomyPushEventParameters($device);
    $satellites = array_values(array_intersect(['iss', 'tiangong'], (array) ($parameters['satellites'] ?? [])));
    $targets = array_values(array_intersect(['sun', 'moon'], (array) ($parameters['targets'] ?? [])));
    $classifications = array_values(array_intersect(['transit', 'very_close'],
        (array) ($parameters['classifications'] ?? [])));
    if ($satellites === [] || $targets === [] || $classifications === []) {
        throw new RuntimeException('Los parámetros de tránsitos satelitales no son válidos.');
    }
    if ($service === null) {
        $cachePath = (string) (getenv('ASTRONOMY_TLE_CACHE_PATH')
            ?: dirname(__DIR__) . '/astronomy-engine/cache/satellite/tle-cache.json');
        $ttl = (int) (getenv('ASTRONOMY_TLE_CACHE_TTL_SECONDS')
            ?: CachedCelesTrakTleProvider::DEFAULT_TTL_SECONDS);
        $service = new SatelliteTransitService(new CachedCelesTrakTleProvider(
            $cachePath, $ttl, new CelesTrakTleDownloader(3), null, false
        ));
    }
    $observer = astronomyPushProviderObserver($device);
    $result = $service->search($observer, astronomyPushUtc($nowUtc), 48, $satellites, $targets);
    $events = [];
    foreach ($result->search->events as $event) {
        $payload = astronomyPushSatelliteEventPayload($event, $classifications, $observer->timezone);
        if ($payload !== null) $events[] = $payload;
    }
    return $events;
}

/** @param list<string> $classifications @return array<string,mixed>|null */
function astronomyPushSatelliteEventPayload(SatelliteTransitEvent $event, array $classifications,
    DateTimeZone $timezone): ?array
{
    if (!in_array($event->classification, $classifications, true)
        || !in_array($event->classification, ['transit', 'very_close'], true)
        || !in_array($event->satellite, ['iss', 'tiangong'], true)
        || !in_array($event->targetBody, ['sun', 'moon'], true)) return null;
    return [
        'notification_type' => 'satellite_transit',
        'event_key' => $event->satellite . ':' . $event->targetBody . ':' . $event->classification . ':'
            . astronomyPushUtc($event->maximum)->format('Ymd\THis\Z'),
        'event_time_utc' => astronomyPushUtc($event->maximum),
        'event_time_local' => $event->maximum->setTimezone($timezone),
        'satellite_name' => $event->satellite === 'iss' ? 'ISS' : 'Tiangong',
        'target_name' => $event->targetBody === 'sun' ? 'Sol' : 'Luna',
        'classification' => $event->classification === 'transit' ? 'tránsito' : 'paso extremadamente cercano',
        'separation' => number_format($event->minimumSeparationDegrees, 2, ',', '') . '°',
        'metadata' => ['warnings' => $event->warnings, 'tle_epoch_utc' => $event->tleEpochUtc->format(DATE_ATOM)],
    ];
}

/** @return list<array<string,mixed>> */
function astronomyPushProviderEvents(array $device, DateTimeImmutable $nowUtc): array
{
    return match ((string) ($device['notification_type'] ?? 'moonrise')) {
        'moonrise' => astronomyPushMoonriseEvents($device, $nowUtc),
        'eclipse' => astronomyPushEclipseEvents($device, $nowUtc),
        'lunar_conjunction' => astronomyPushLunarConjunctionEvents($device, $nowUtc),
        'satellite_transit' => astronomyPushSatelliteTransitEvents($device, $nowUtc),
        default => throw new RuntimeException('No existe un proveedor para el tipo solicitado.'),
    };
}
