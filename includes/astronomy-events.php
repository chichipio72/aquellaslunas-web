<?php

declare(strict_types=1);

require_once __DIR__ . '/api-client.php';
require_once __DIR__ . '/web-database.php';
require_once __DIR__ . '/astronomy-trace.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use AstronomyEngine\Facade\AstronomyEventsFacade;
use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\ConjunctionCatalog;
use AstronomyEngine\LunarEventCalculator;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MoonApparentSize;

const ASTRONOMY_EVENT_DATABASE_START = '1900-01-01T00:00:00+00:00';
const ASTRONOMY_EVENT_DATABASE_END = '2051-01-01T00:00:00+00:00';

function astronomyEventSourceCatalog(): array
{
    return [
        'moon_phase' => [
            'label' => 'Fases lunares',
            'sources' => ['api', 'database', 'php', 'auto', 'compare'],
            'environment' => 'ASTRONOMY_EVENT_SOURCE_MOON_PHASE',
        ],
        'lunar_apsis' => [
            'label' => 'Ápsides lunares',
            'sources' => ['api', 'database', 'php', 'auto', 'compare'],
            'environment' => 'ASTRONOMY_EVENT_SOURCE_LUNAR_APSIS',
        ],
        'lunar_orbit' => [
            'label' => 'Nodos lunares',
            'sources' => ['api', 'database', 'php', 'auto', 'compare'],
            'environment' => 'ASTRONOMY_EVENT_SOURCE_LUNAR_ORBIT',
        ],
        'lunar_libration' => [
            'label' => 'Libraciones',
            'sources' => ['api', 'database', 'php', 'auto', 'compare'],
            'environment' => 'ASTRONOMY_EVENT_SOURCE_LUNAR_LIBRATION',
        ],
        'lunar_conjunction' => [
            'label' => 'Conjunciones lunares',
            'sources' => ['api', 'database', 'php', 'auto', 'compare'],
            'environment' => 'ASTRONOMY_EVENT_SOURCE_LUNAR_CONJUNCTION',
        ],
        'eclipse' => [
            'label' => 'Eclipses',
            'sources' => ['api', 'database', 'php', 'auto', 'compare'],
            'environment' => 'ASTRONOMY_EVENT_SOURCE_ECLIPSE',
        ],
    ];
}

/** @return array<string,string> */
function &astronomyEventSourceRequestCache(): array
{
    static $cache = [];
    return $cache;
}

function astronomyEventSourceResetRequestCache(): void
{
    $cache = &astronomyEventSourceRequestCache();
    $cache = [];
}

function astronomyEventSourceFallback(array $definition): string
{
    $environmentName = $definition['environment'] ?? null;
    if (is_string($environmentName)) {
        $environmentSource = getenv($environmentName);
        $environmentSource = is_string($environmentSource) ? strtolower(trim($environmentSource)) : '';
        if ($environmentSource !== '') {
            if (!in_array($environmentSource, $definition['sources'], true)) {
                throw new RuntimeException('La fuente astronómica configurada en el entorno no es válida.');
            }
            return $environmentSource;
        }
    }
    return 'api';
}

/** @return array<string,string> */
function astronomyEventSourcesForGroups(array $eventGroups): array
{
    $catalog = astronomyEventSourceCatalog();
    $eventGroups = array_values(array_unique(array_map('strval', $eventGroups)));
    foreach ($eventGroups as $eventGroup) {
        if (!is_array($catalog[$eventGroup] ?? null)) {
            throw new InvalidArgumentException('El grupo de eventos astronómicos no está soportado.');
        }
    }
    $cache = &astronomyEventSourceRequestCache();
    $missing = array_values(array_filter($eventGroups, static fn(string $group): bool => !array_key_exists($group, $cache)));
    if ($missing !== []) {
        $parameters = [];
        $placeholders = [];
        foreach ($missing as $index => $eventGroup) {
            $placeholder = ':key_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = 'astronomy.event_source.' . $eventGroup;
        }
        $persisted = [];
        try {
            $statement = getWebDatabaseConnection()->prepare(
                'SELECT clave,valor FROM admin_configuracion_sitio WHERE clave IN (' . implode(',', $placeholders) . ')'
            );
            $statement->execute($parameters);
            $persisted = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $exception) {
            // La configuración persistida es opcional; cada grupo conserva entorno/default.
        }
        if (($GLOBALS['home_upcoming_profile_enabled'] ?? false) === true) {
            homeUpcomingProfileCount('consulta por lote de resolución de fuentes');
        }
        foreach ($missing as $eventGroup) {
            $definition = $catalog[$eventGroup];
            $configurationKey = 'astronomy.event_source.' . $eventGroup;
            $persistedSource = $persisted[$configurationKey] ?? null;
            $cache[$eventGroup] = is_string($persistedSource) && in_array($persistedSource, $definition['sources'], true)
                ? $persistedSource
                : astronomyEventSourceFallback($definition);
        }
    }
    $resolved = [];
    foreach ($eventGroups as $eventGroup) {
        $resolved[$eventGroup] = $cache[$eventGroup];
    }
    return $resolved;
}

function astronomyEventSourceFor(string $eventGroup, ?string $productionConfigPath = null): string
{
    $definition = astronomyEventSourceCatalog()[$eventGroup] ?? null;
    if (!is_array($definition)) {
        throw new InvalidArgumentException('El grupo de eventos astronómicos no está soportado.');
    }
    $cache = &astronomyEventSourceRequestCache();
    if (array_key_exists($eventGroup, $cache)) {
        if (($GLOBALS['home_upcoming_profile_enabled'] ?? false) === true) {
            homeUpcomingProfileCount('resolución de fuente reutilizada: ' . $eventGroup);
        }
        return $cache[$eventGroup];
    }
    astronomyEventSourcesForGroups([$eventGroup]);
    return $cache[$eventGroup];
}

/** @return array<string,string> */
function astronomyEventSourceSettings(): array
{
    return astronomyEventSourcesForGroups(array_keys(astronomyEventSourceCatalog()));
}

function astronomyEventSourceUpdate(PDO $connection, array $updates): void
{
    $catalog = astronomyEventSourceCatalog();
    if (array_keys($updates) !== array_keys($catalog)) {
        throw new InvalidArgumentException('La configuración de fuentes está incompleta.');
    }
    foreach ($updates as $eventGroup => $source) {
        if (!is_string($source) || !in_array($source, $catalog[$eventGroup]['sources'], true)) {
            throw new InvalidArgumentException('La fuente seleccionada no está permitida para el fenómeno.');
        }
    }

    $statement = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave,valor,descripcion) '
        . 'VALUES (:clave,:valor,:descripcion) '
        . 'ON DUPLICATE KEY UPDATE valor=VALUES(valor),descripcion=VALUES(descripcion)'
    );
    $connection->beginTransaction();
    try {
        foreach ($updates as $eventGroup => $source) {
            $statement->execute([
                'clave' => 'astronomy.event_source.' . $eventGroup,
                'valor' => $source,
                'descripcion' => 'Fuente de datos para ' . $catalog[$eventGroup]['label'] . '.',
            ]);
        }
        $connection->commit();
        astronomyEventSourceResetRequestCache();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

/**
 * Punto de entrada único para eventos astronómicos de la web.
 *
 * @param array<string,mixed> $request Contrato compatible con /v1/astronomy/events.
 * @return array{items:list<array<string,mixed>>}
 */
function astronomyEventsUntraced(array $request, string $context = 'events', int $timeout = 35): array
{
    $upcomingProfile = str_starts_with($context, 'home v2 upcoming ');
    $rangeStarted = hrtime(true);
    [$startUtc, $endUtc] = astronomyEventsUtcRange($request);
    $requestedTypes = astronomyEventsRequestedTypes($request['types'] ?? '');
    if ($upcomingProfile) homeUpcomingProfileAdd('normalización de rango y tipos', (hrtime(true) - $rangeStarted) / 1_000_000);
    $apiTypes = [];
    $apiGroups = [];
    $apiTypesWithoutFallback = [];
    $localGroups = [];
    $phpFacadeTypes = [];
    $requestedEventGroups = [];
    foreach ($requestedTypes as $publicType) {
        $eventGroup = astronomyEventGroupFromPublicType($publicType);
        if ($eventGroup !== null) {
            $requestedEventGroups[] = $eventGroup;
        }
    }
    astronomyEventSourcesForGroups($requestedEventGroups);
    foreach ($requestedTypes as $publicType) {
        if (in_array($publicType, ['earthshine', 'full_moon_observation'], true)) {
            $phpFacadeTypes[] = $publicType;
            continue;
        }
        $eventGroup = astronomyEventGroupFromPublicType($publicType);
        $sourceStarted = hrtime(true);
        $source = $eventGroup !== null ? astronomyEventSourceFor($eventGroup) : 'api';
        if ($upcomingProfile) {
            homeUpcomingProfileAdd('resolución de fuentes por grupo · ' . $context, (hrtime(true) - $sourceStarted) / 1_000_000);
            homeUpcomingProfileCount('fuente resuelta: ' . ($eventGroup ?? $publicType));
        }
        if ($eventGroup === null || $source === 'api') {
            $apiTypes[] = $publicType;
            if ($eventGroup === null) {
                $apiTypesWithoutFallback[] = $publicType;
            } else {
                $apiGroups[$eventGroup] = true;
            }
            continue;
        }
        $localGroups[$eventGroup] = $source;
    }

    $items = [];
    if ($phpFacadeTypes !== []) {
        try {
            $derivedLabel = implode(',', $phpFacadeTypes);
            $derivedStarted = hrtime(true);
            $items = astronomyEventsTimedSource(
                $context,
                $derivedLabel,
                'php',
                'php',
                static fn(): array => astronomyEventsFromPhpFacade($request, $phpFacadeTypes)
            );
            if ($upcomingProfile) {
                homeUpcomingProfileAdd('eventos derivados · ' . $context, (hrtime(true) - $derivedStarted) / 1_000_000);
                homeUpcomingProfileCount('eventos derivados obtenidos', count($items));
            }
        } catch (Throwable $phpException) {
            error_log('Aquellas Lunas PHP derived events error; using API fallback: ' . $phpException->getMessage());
            $apiTypes = array_merge($apiTypes, $phpFacadeTypes);
            $apiTypesWithoutFallback = array_merge($apiTypesWithoutFallback, $phpFacadeTypes);
        }
    }
    if ($apiTypes !== []) {
        $apiRequest = $request;
        $apiRequest['types'] = implode(',', $apiTypes);
        try {
            $apiStarted = hrtime(true);
            $items = astronomyEventsFromApi($apiRequest, $context . ' API groups', $timeout);
            if ($upcomingProfile) {
                homeUpcomingProfileAdd('llamada grupos API · ' . $context, (hrtime(true) - $apiStarted) / 1_000_000);
                homeUpcomingProfileCount('eventos API obtenidos', count($items));
            }
        } catch (Throwable $apiException) {
            if ($apiGroups === []) {
                throw $apiException;
            }
            if ($apiTypesWithoutFallback !== []) {
                astronomyEventsRecordDiagnostic($context, [
                    'group' => 'api_only',
                    'requested' => 'api',
                    'used' => 'unavailable',
                    'reason' => 'api_unavailable_no_fallback',
                    'types' => $apiTypesWithoutFallback,
                ]);
            }
            foreach (array_keys($apiGroups) as $eventGroup) {
                $items = array_merge($items, astronomyEventsForApiFallback(
                    $eventGroup,
                    $startUtc,
                    $endUtc,
                    (float) ($request['latitude'] ?? 0.0),
                    (float) ($request['longitude'] ?? 0.0),
                    (string) ($request['timezone'] ?? 'UTC'),
                    $context,
                    $apiException
                ));
            }
        }
    }
    foreach ($localGroups as $eventGroup => $source) {
        $localStarted = hrtime(true);
        $items = array_merge($items, astronomyEventsForGroup(
            $eventGroup,
            $source,
            $startUtc,
            $endUtc,
            (float) ($request['latitude'] ?? 0.0),
            (float) ($request['longitude'] ?? 0.0),
            (string) ($request['timezone'] ?? 'UTC'),
            $context
        ));
        if ($upcomingProfile) homeUpcomingProfileAdd('llamada grupo local: ' . $eventGroup . ' · ' . $context, (hrtime(true) - $localStarted) / 1_000_000);
    }

    if (in_array('eclipse', $requestedTypes, true)) {
        $normalizationStarted = hrtime(true);
        $items = astronomyNormalizeEclipseItems(
            $items,
            $startUtc,
            $endUtc,
            (float) ($request['latitude'] ?? 0.0),
            (float) ($request['longitude'] ?? 0.0),
            (string) ($request['timezone'] ?? 'UTC')
        );
        if ($upcomingProfile) homeUpcomingProfileAdd('normalización y enriquecimiento local de eclipses', (hrtime(true) - $normalizationStarted) / 1_000_000);
    }

    if (in_array('conjunction', $requestedTypes, true)) {
        $normalizationStarted = hrtime(true);
        $items = astronomyNormalizeConjunctionItems(
            $items,
            $startUtc,
            $endUtc,
            (float) ($request['latitude'] ?? 0.0),
            (float) ($request['longitude'] ?? 0.0),
            (string) ($request['timezone'] ?? 'UTC')
        );
        if ($upcomingProfile) homeUpcomingProfileAdd('enriquecimiento local de conjunciones', (hrtime(true) - $normalizationStarted) / 1_000_000);
    }

    $sortStarted = hrtime(true);
    usort($items, 'astronomyEventsChronologicalComparison');
    if ($upcomingProfile) {
        homeUpcomingProfileAdd('ordenamiento por operación', (hrtime(true) - $sortStarted) / 1_000_000);
        homeUpcomingProfileCount('eventos devueltos por operaciones', count($items));
    }
    return ['items' => $items];
}

/** Punto de entrada trazado para una consulta completa de eventos. */
function astronomyEvents(array $request, string $context = 'events', int $timeout = 35): array
{
    $location = [
        'latitude' => (float) ($request['latitude'] ?? 0.0),
        'longitude' => (float) ($request['longitude'] ?? 0.0),
        'elevation_meters' => (float) ($request['elevation_meters'] ?? $request['elevation'] ?? 0.0),
        'timezone' => (string) ($request['timezone'] ?? 'UTC'),
    ];
    return astronomyTraceExecute('events', $context, [
        'parameters' => $request, 'timeout_seconds' => $timeout,
    ], $location, static fn(): array => astronomyEventsUntraced($request, $context, $timeout));
}

/** @param list<string> $types @return list<array<string,mixed>> */
function astronomyEventsFromPhpFacade(array $request, array $types): array
{
    $timezone = (string) ($request['timezone'] ?? 'UTC');
    $startDate = (string) ($request['start_date'] ?? '');
    $observer = new AstronomyObserver(
        (float) ($request['latitude'] ?? 0.0),
        (float) ($request['longitude'] ?? 0.0),
        $timezone,
        (float) ($request['elevation'] ?? $request['elevation_meters'] ?? 0.0)
    );
    $result = (new AstronomyEventsFacade())->between(
        new DateTimeImmutable($startDate, new DateTimeZone($timezone)),
        $observer,
        (int) ($request['days'] ?? 30),
        $types,
        ['max_difference_minutes' => (int) ($request['max_difference_minutes'] ?? 90)]
    );
    if (!is_array($result['items'] ?? null)) {
        throw new UnexpectedValueException('La fachada PHP de eventos devolvió un contrato inválido.');
    }
    return array_values(array_filter($result['items'], 'is_array'));
}

/** @return list<array<string,mixed>> */
function astronomyEventsForApiFallback(
    string $eventGroup,
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    string $timezone,
    string $context,
    Throwable $apiException
): array {
    $supportsPhp = in_array('php', astronomyEventSourceCatalog()[$eventGroup]['sources'] ?? [], true);
    if (astronomyEventDatabaseCovers($startUtc, $endUtc)) {
        try {
            $items = astronomyEventsTimedSource($context, $eventGroup, 'api', 'database', static fn(): array => astronomyEventsFromDatabase($eventGroup, $startUtc, $endUtc), 'api');
            astronomyEventsRecordDiagnostic($context, [
                'group' => $eventGroup,
                'requested' => 'api',
                'used' => 'database',
                'reason' => 'api_unavailable',
            ]);
            return $items;
        } catch (Throwable $databaseException) {
            if (!$supportsPhp) {
                throw $apiException;
            }
            $items = astronomyEventsTimedSource($context, $eventGroup, 'api', 'php', static fn(): array => astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude, $timezone), 'database');
            astronomyEventsRecordDiagnostic($context, [
                'group' => $eventGroup,
                'requested' => 'api',
                'used' => 'php',
                'reason' => 'api_unavailable_database_unavailable',
            ]);
            return $items;
        }
    }

    if (!$supportsPhp) {
        throw $apiException;
    }
    $items = astronomyEventsTimedSource($context, $eventGroup, 'api', 'php', static fn(): array => astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude, $timezone), 'api');
    astronomyEventsRecordDiagnostic($context, [
        'group' => $eventGroup,
        'requested' => 'api',
        'used' => 'php',
        'reason' => 'api_unavailable_database_out_of_coverage',
    ]);
    return $items;
}

function astronomyEventGroupFromPublicType(string $publicType): ?string
{
    return [
        'moon_phase' => 'moon_phase',
        'apsis' => 'lunar_apsis',
        'lunar_nodes' => 'lunar_orbit',
        'libration' => 'lunar_libration',
        'conjunction' => 'lunar_conjunction',
        'eclipse' => 'eclipse',
    ][$publicType] ?? null;
}

function astronomyEventPublicTypeForGroup(string $eventGroup): string
{
    return [
        'moon_phase' => 'moon_phase',
        'lunar_apsis' => 'apsis',
        'lunar_orbit' => 'lunar_nodes',
        'lunar_libration' => 'libration',
        'lunar_conjunction' => 'conjunction',
        'eclipse' => 'eclipse',
    ][$eventGroup] ?? throw new InvalidArgumentException('El grupo de eventos no tiene tipo público.');
}

/** @return list<array<string,mixed>> */
function astronomyEventsFromApi(array $request, string $context, int $timeout): array
{
    $apiConfig = loadAstronomyApiConfig();
    $result = astronomyApiRequest(
        $apiConfig['base_url'] . '/v1/astronomy/events?' . http_build_query($request),
        $context,
        $timeout
    );
    if ($result['body'] === false || $result['http_code'] !== 200) {
        astronomyApiRecordValidation($context, null, false);
        throw new RuntimeException('No se pudieron obtener los eventos astronómicos desde la API.');
    }
    $decoded = json_decode($result['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded['items'] ?? null)) {
        astronomyApiRecordValidation($context, false, false);
        throw new RuntimeException('La respuesta de eventos astronómicos de la API no es válida.');
    }
    astronomyApiRecordValidation($context, true, true);
    $items = array_values(array_filter($decoded['items'], 'is_array'));
    astronomyAnnotateLastDiagnostic($context, ['result_count' => count($items)]);
    return $items;
}

/** @return list<array<string,mixed>> */
function astronomyEventsTimedSource(
    string $context,
    string $eventGroup,
    string $requestedSource,
    string $usedSource,
    callable $operation,
    ?string $fallbackFrom = null
): array {
    $started = hrtime(true);
    try {
        $items = $operation();
        $elapsed = (hrtime(true) - $started) / 1_000_000;
        astronomyRecordDiagnostic([
            'label' => $context . ' · ' . $eventGroup,
            'requested_source' => $requestedSource,
            'used_source' => $usedSource,
            'source_ms' => $elapsed,
            'total_ms' => $elapsed,
            'fallback_from' => $fallbackFrom,
            'fallback_to' => $fallbackFrom !== null ? $usedSource : null,
            'result_count' => count($items),
        ]);
        return $items;
    } catch (Throwable $exception) {
        $elapsed = (hrtime(true) - $started) / 1_000_000;
        astronomyRecordDiagnostic([
            'label' => $context . ' · ' . $eventGroup,
            'requested_source' => $requestedSource,
            'used_source' => $usedSource,
            'source_ms' => $elapsed,
            'total_ms' => $elapsed,
            'fallback_from' => $fallbackFrom,
            'fallback_to' => $fallbackFrom !== null ? $usedSource : null,
            'technical_error' => get_debug_type($exception) . ': ' . $exception->getMessage(),
            'outcome' => 'error',
        ]);
        throw $exception;
    }
}

/** @return list<array<string,mixed>> */
function astronomyEventsForGroup(
    string $eventGroup,
    string $source,
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    string $timezone,
    string $context
): array {
    if ($source === 'database') {
        return astronomyEventsTimedSource($context, $eventGroup, 'database', 'database', static fn(): array => astronomyEventsFromDatabase($eventGroup, $startUtc, $endUtc));
    }
    if ($source === 'php') {
        return astronomyEventsTimedSource($context, $eventGroup, 'php', 'php', static fn(): array => astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude, $timezone));
    }
    if ($source === 'auto') {
        if (astronomyEventDatabaseCovers($startUtc, $endUtc)) {
            try {
                return astronomyEventsTimedSource($context, $eventGroup, 'auto', 'database', static fn(): array => astronomyEventsFromDatabase($eventGroup, $startUtc, $endUtc));
            } catch (Throwable $exception) {
                astronomyEventsRecordDiagnostic($context, [
                    'group' => $eventGroup,
                    'mode' => 'auto',
                    'fallback' => 'php',
                    'reason' => 'database_unavailable',
                ]);
            }
        }
        return astronomyEventsTimedSource($context, $eventGroup, 'auto', 'php', static fn(): array => astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude, $timezone), astronomyEventDatabaseCovers($startUtc, $endUtc) ? 'database' : null);
    }
    if ($source === 'compare') {
        $phpItems = astronomyEventsTimedSource($context . ' compare', $eventGroup, 'compare', 'php', static fn(): array => astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude, $timezone));
        try {
            $databaseItems = astronomyEventsTimedSource($context, $eventGroup, 'compare', 'database', static fn(): array => astronomyEventsFromDatabase($eventGroup, $startUtc, $endUtc));
            astronomyEventsRecordDiagnostic($context, [
                'group' => $eventGroup,
                'mode' => 'compare',
                'primary' => 'database',
                'comparison' => astronomyCompareEvents($eventGroup, $databaseItems, $phpItems),
            ]);
            return $databaseItems;
        } catch (Throwable $exception) {
            if ($eventGroup === 'eclipse') {
                throw new RuntimeException('No se pudo obtener el resultado principal de eclipses desde MariaDB.', 0, $exception);
            }
            astronomyEventsRecordDiagnostic($context, [
                'group' => $eventGroup,
                'mode' => 'compare',
                'primary' => 'php',
                'database_error' => true,
                'comparison' => [],
            ]);
            return $phpItems;
        }
    }

    throw new RuntimeException('La fuente del grupo astronómico no está soportada.');
}

/** @return list<array<string,mixed>> */
function astronomyEventsFromDatabase(
    string $eventGroup,
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc
): array
{
    $statement = getWebDatabaseConnection()->prepare(
        'SELECT event_type,event_time,title,data FROM astronomical_events '
        . 'WHERE event_group=:event_group '
        . 'AND event_time>=:start_time AND event_time<:end_time ORDER BY event_time'
    );
    $statement->execute([
        'event_group' => $eventGroup,
        'start_time' => $startUtc->format('Y-m-d H:i:s.u'),
        'end_time' => $endUtc->format('Y-m-d H:i:s.u'),
    ]);

    $items = [];
    foreach ($statement as $row) {
        $details = json_decode((string) $row['data'], true);
        if (!is_array($details)) {
            $details = [];
        }
        $dateTime = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s.u',
            (string) $row['event_time'],
            new DateTimeZone('UTC')
        );
        if ($dateTime === false) {
            throw new RuntimeException('MariaDB contiene una fecha astronómica inválida.');
        }
        $eventType = (string) $row['event_type'];
        if (!astronomyEventDatabaseTypeIsAllowed($eventGroup, $eventType)) {
            continue;
        }
        $items[] = astronomyNormalizeEvent(
            $eventGroup,
            astronomyEventPublicSubtype($eventGroup, $eventType),
            $dateTime,
            (string) $row['title'],
            $details,
            'database'
        );
    }
    return $items;
}

/** @return list<array<string,mixed>> */
function astronomyEventsFromPhp(
    string $eventGroup,
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    string $timezone = 'UTC'
): array
{
    if ($eventGroup === 'eclipse') {
        return astronomyEclipseEventsFromPhp($startUtc, $endUtc, $latitude, $longitude, $timezone);
    }
    $items = [];
    foreach (astronomyLunarEventsFromPhp($startUtc, $endUtc, $latitude, $longitude, [$eventGroup]) as $event) {
        if ($event->group !== $eventGroup) {
            continue;
        }
        $items[] = astronomyNormalizeEvent(
            $eventGroup,
            astronomyEventPublicSubtype($eventGroup, $event->type),
            $event->dateTime,
            astronomyEventTitle($eventGroup, $event->type),
            $event->data,
            'php'
        );
    }
    return $items;
}

/** @return list<array<string,mixed>> */
function astronomyNormalizeConjunctionItems(
    array $items,
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    string $timezone
): array {
    $needsPortableLocal = false;
    foreach ($items as $item) {
        if (!is_array($item) || ($item['type'] ?? null) !== 'conjunction') continue;
        $details = is_array($item['details'] ?? null) ? $item['details'] : [];
        if (!is_string($details['visibility_classification'] ?? null)) {
            $needsPortableLocal = true;
            break;
        }
    }
    if ($needsPortableLocal) {
        try {
            $portableItems = astronomyEventsFromPhp('lunar_conjunction', $startUtc, $endUtc, $latitude, $longitude, $timezone);
            $items = astronomyEnrichConjunctionItems($items, $portableItems);
        } catch (Throwable $exception) {
            error_log('Aquellas Lunas conjunction local enrichment error: ' . $exception->getMessage());
        }
    }
    return astronomyEnrichConjunctionVisualGeometry($items, $latitude, $longitude);
}

/** @return list<array<string,mixed>> */
function astronomyEnrichConjunctionVisualGeometry(array $items,float $latitude,float $longitude):array
{
    $calculator=new LunarEventCalculator(new MeeusLunarCalculator());
    foreach($items as $index=>$item){if(!is_array($item)||($item['type']??null)!=='conjunction')continue;$details=is_array($item['details']??null)?$item['details']:[];$classification=(string)($details['visibility_classification']??'');$instantValue=$classification==='visible_nearby'?($details['best_visible_time']??null):($item['datetime']??null);$target=is_string($details['planet']??null)?strtolower(trim($details['planet'])):strtolower(trim((string)($item['subtype']??'')));if(!is_string($instantValue)||$instantValue===''||$target==='')continue;try{$geometry=$calculator->conjunctionVisualGeometry(new DateTimeImmutable($instantValue),$target,$latitude,$longitude);}catch(Throwable $exception){error_log('Aquellas Lunas conjunction visual geometry error: '.$exception->getMessage());continue;}if($geometry===null)continue;$details['visual_geometry_time']=(new DateTimeImmutable($instantValue))->format(DateTimeInterface::ATOM);foreach($geometry as $key=>$value)$details['visual_'.$key]=$value;$item['details']=$details;$items[$index]=$item;}
    return $items;
}

/** @return list<array<string,mixed>> */
function astronomyEnrichConjunctionItems(array $items, array $portableItems): array
{
    $localKeys = [
        'object_kind', 'both_above_horizon', 'moon_altitude_degrees',
        'target_altitude_degrees', 'sun_altitude_degrees', 'solar_elongation_degrees',
        'visibility_classification', 'visible_window_start', 'visible_window_end',
        'best_visible_time', 'not_observable_reason',
    ];
    foreach ($items as $index => $item) {
        if (!is_array($item) || ($item['type'] ?? null) !== 'conjunction') continue;
        $details = is_array($item['details'] ?? null) ? $item['details'] : [];
        if (is_string($details['visibility_classification'] ?? null)) continue;
        $portable = astronomyClosestConjunctionItem($item, $portableItems);
        if ($portable === null) continue;
        $portableDetails = is_array($portable['details'] ?? null) ? $portable['details'] : [];
        foreach ($localKeys as $key) {
            if (array_key_exists($key, $portableDetails)) $details[$key] = $portableDetails[$key];
        }
        $details['local_calculation_source'] = 'php';
        $items[$index]['details'] = $details;
    }
    return $items;
}

function astronomyClosestConjunctionItem(array $item, array $candidates): ?array
{
    $timestamp = astronomyEventTimestamp($item['datetime'] ?? null);
    $subtype = (string) ($item['subtype'] ?? '');
    $closest = null;
    $difference = PHP_FLOAT_MAX;
    foreach ($candidates as $candidate) {
        if (!is_array($candidate) || ($candidate['type'] ?? null) !== 'conjunction' || ($candidate['subtype'] ?? null) !== $subtype) continue;
        $candidateTimestamp = astronomyEventTimestamp($candidate['datetime'] ?? null);
        if ($timestamp === null || $candidateTimestamp === null) continue;
        $candidateDifference = abs($timestamp - $candidateTimestamp);
        if ($candidateDifference < $difference) {
            $difference = $candidateDifference;
            $closest = $candidate;
        }
    }
    // Las fuentes pueden diferir algunos minutos en el mínimo, pero no deben
    // cruzar observabilidad entre dos encuentros mensuales del mismo astro.
    return $difference <= 21600.0 ? $closest : null;
}

/** @return list<array<string,mixed>> */
function astronomyEclipseEventsFromPhp(
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    string $timezone
): array {
    $zone = new DateTimeZone($timezone);
    $startLocal = $startUtc->setTimezone($zone);
    $endLocal = $endUtc->setTimezone($zone);
    $days = (int) $startLocal->diff($endLocal)->days;
    $result = (new AstronomyEventsFacade())->between(
        $startLocal,
        new AstronomyObserver($latitude, $longitude, $timezone),
        $days,
        ['eclipse']
    );
    $items = [];
    foreach (($result['items'] ?? []) as $item) {
        if (!is_array($item) || ($item['type'] ?? null) !== 'eclipse') {
            continue;
        }
        $dateTime = new DateTimeImmutable((string) ($item['datetime'] ?? ''));
        $endDateTime = is_string($item['end_datetime'] ?? null)
            ? new DateTimeImmutable($item['end_datetime'])
            : null;
        $details = is_array($item['details'] ?? null) ? $item['details'] : [];
        $details['_source'] = 'php';
        $items[] = [
            'type' => 'eclipse',
            'subtype' => (string) ($item['subtype'] ?? ''),
            'datetime' => $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'),
            'end_datetime' => $endDateTime?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'),
            'title' => (string) ($item['title'] ?? ''),
            'details' => $details,
        ];
    }
    return $items;
}

/** @return list<array<string,mixed>> */
function astronomyNormalizeEclipseItems(
    array $items,
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    string $timezone
): array {
    $needsPortableLocal = false;
    foreach ($items as $item) {
        if (!is_array($item) || ($item['type'] ?? null) !== 'eclipse') {
            continue;
        }
        $details = is_array($item['details'] ?? null) ? $item['details'] : [];
        $subtype = (string) ($item['subtype'] ?? '');
        $localKey = $subtype === 'solar_eclipse' ? 'solar_eclipse_local' : 'eclipse_local';
        if (!is_array($details[$localKey] ?? null) && !is_array($details['local'] ?? null)) {
            $needsPortableLocal = true;
            break;
        }
    }
    $portableItems = $needsPortableLocal
        ? astronomyEclipseEventsFromPhp($startUtc, $endUtc, $latitude, $longitude, $timezone)
        : [];

    foreach ($items as $index => $item) {
        if (!is_array($item) || ($item['type'] ?? null) !== 'eclipse') {
            continue;
        }
        $portable = is_array($item['details']['local'] ?? null)
            ? $item
            : astronomyClosestEclipseItem($item, $portableItems);
        $items[$index] = astronomyNormalizeEclipseItemContract($item, $portable);
        $items[$index] = astronomyResolveLocalEclipseVisibilityMap($items[$index]);
    }
    return $items;
}

/** @return array<string,mixed> */
function astronomyResolveLocalEclipseVisibilityMap(array $item): array
{
    $subtype = (string) ($item['subtype'] ?? '');
    $globalKey = $subtype === 'solar_eclipse' ? 'solar_eclipse_global' : 'eclipse_global';
    $details = is_array($item['details'] ?? null) ? $item['details'] : [];
    $global = is_array($details[$globalKey] ?? null) ? $details[$globalKey] : [];
    $map = is_array($global['visibility_map'] ?? null) ? $global['visibility_map'] : [];
    $existingFilename = is_string($map['local_filename'] ?? null) ? trim($map['local_filename']) : '';
    if (($map['available'] ?? null) === true && $existingFilename !== '') {
        return $item;
    }

    $timestamp = astronomyEventTimestamp($item['datetime'] ?? null);
    if ($timestamp === null || !in_array($subtype, ['lunar_eclipse', 'solar_eclipse'], true)) {
        return $item;
    }
    $date = (new DateTimeImmutable('@' . (string) (int) $timestamp))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    $filename = ($subtype === 'solar_eclipse' ? 'solar-eclipse-' : 'lunar-eclipse-') . $date . '.png';
    if (!is_file(dirname(__DIR__) . '/assets/images/eclipses/' . $filename)) {
        return $item;
    }

    $global['visibility_map'] = [
        'source' => 'NASA/GSFC Five Millennium Eclipse Catalog',
        'status' => 'available',
        'available' => true,
        'local_filename' => $filename,
        'attribution' => 'Eclipse map/figure/table/predictions courtesy of Fred Espenak, NASA/Goddard Space Flight Center, from eclipse.gsfc.nasa.gov.',
    ];
    $details[$globalKey] = $global;
    $item['details'] = $details;
    return $item;
}

function astronomyClosestEclipseItem(array $item, array $candidates): ?array
{
    $timestamp = astronomyEventTimestamp($item['datetime'] ?? null);
    $closest = null;
    $difference = PHP_FLOAT_MAX;
    foreach ($candidates as $candidate) {
        if (!is_array($candidate) || ($candidate['subtype'] ?? null) !== ($item['subtype'] ?? null)) {
            continue;
        }
        $candidateTimestamp = astronomyEventTimestamp($candidate['datetime'] ?? null);
        if ($timestamp === null || $candidateTimestamp === null) {
            continue;
        }
        $candidateDifference = abs($timestamp - $candidateTimestamp);
        if ($candidateDifference < $difference) {
            $difference = $candidateDifference;
            $closest = $candidate;
        }
    }
    return $difference <= 86400.0 ? $closest : null;
}

function astronomyNormalizeEclipseItemContract(array $item, ?array $portable): array
{
    $subtype = (string) ($item['subtype'] ?? '');
    $details = is_array($item['details'] ?? null) ? $item['details'] : [];
    $portableDetails = is_array($portable['details'] ?? null) ? $portable['details'] : [];
    $globalKey = $subtype === 'solar_eclipse' ? 'solar_eclipse_global' : 'eclipse_global';
    $localKey = $subtype === 'solar_eclipse' ? 'solar_eclipse_local' : 'eclipse_local';
    if (is_array($details[$globalKey] ?? null) && is_array($details[$localKey] ?? null)) {
        return $item;
    }

    $global = is_array($details[$globalKey] ?? null) ? $details[$globalKey] : [];
    if ($global === []) {
        if (is_array($details['global'] ?? null)) {
            $global = $details['global'];
        } else {
            $global = $details;
            unset($global['_source']);
        }
    }
    $classification = (string) ($details['classification'] ?? $global['global_type'] ?? $portableDetails['classification'] ?? '');
    if (!is_string($global['global_type'] ?? null) || trim((string) $global['global_type']) === '') {
        $global['global_type'] = $classification;
    }

    $local = is_array($details[$localKey] ?? null) ? $details[$localKey] : [];
    if ($local === []) {
        $portableLocal = is_array($details['local'] ?? null)
            ? $details['local']
            : (is_array($portableDetails['local'] ?? null) ? $portableDetails['local'] : []);
        $local = astronomyEclipsePortableLocalContract($subtype, $portableLocal, $classification);
    }

    $source = (string) ($details['_source'] ?? $portableDetails['_source'] ?? 'unknown');
    $item['details'] = [
        $globalKey => $global,
        $localKey => $local,
        '_source' => $source,
    ];
    $globalType = strtolower(trim((string) ($global['global_type'] ?? '')));
    $translated = ['partial' => 'parcial', 'penumbral' => 'penumbral', 'total' => 'total', 'annular' => 'anular', 'hybrid' => 'híbrido'][$globalType] ?? $globalType;
    if ($translated !== '') {
        $item['title'] = ($subtype === 'solar_eclipse' ? 'Eclipse solar ' : 'Eclipse lunar ') . $translated;
    }
    return $item;
}

/** @return array<string,mixed> */
function astronomyEclipsePortableLocalContract(string $subtype, array $local, string $globalType): array
{
    $visibleContacts = array_values(array_filter($local['visibleContacts'] ?? [], 'is_string'));
    if ($subtype === 'solar_eclipse') {
        $visibility = ($local['visible'] ?? false) === true
            ? (string) ($local['localClassification'] ?? 'partial')
            : 'not_visible';
        $body = 'sun';
    } else {
        $visibleSet = array_fill_keys($visibleContacts, true);
        $globalType = strtolower(trim($globalType));
        $visibility = match (true) {
            $visibleContacts === [] => 'not_visible',
            isset($visibleSet['U2']) || isset($visibleSet['U3']) || ($globalType === 'total' && isset($visibleSet['MAX'])) => 'visible_total',
            isset($visibleSet['U1']) || isset($visibleSet['U4']) || (in_array($globalType, ['partial', 'total'], true) && isset($visibleSet['MAX'])) => 'visible_partial',
            default => 'visible_penumbral_only',
        };
        $body = 'moon';
    }
    $interval = is_array($local['visibleInterval'] ?? null) ? $local['visibleInterval'] : [];
    $contacts = [];
    foreach ((is_array($local['contacts'] ?? null) ? $local['contacts'] : []) as $contact) {
        if (!is_array($contact)) {
            continue;
        }
        $contacts[] = [
            'code' => (string) ($contact['code'] ?? ''),
            'datetime' => $contact['local'] ?? $contact['utc'] ?? null,
            $body => [
                'altitude_degrees' => $contact['altitude_degrees'] ?? null,
                'azimuth_degrees' => $contact['azimuth_degrees'] ?? null,
                'above_horizon' => ($contact['visible'] ?? false) === true,
            ],
        ];
    }
    $result = [
        'timezone' => (string) ($local['timezone'] ?? ''),
        'visibility_classification' => $visibility,
        'visible_contact_codes' => $visibleContacts,
        'first_visible_instant' => $interval['start'] ?? null,
        'last_visible_instant' => $interval['end'] ?? null,
        'contacts' => $contacts,
    ];
    if ($subtype === 'solar_eclipse') {
        $result += [
            'max_magnitude' => $local['localMagnitude'] ?? 0.0,
            'max_obscuration' => $local['obscuration'] ?? 0.0,
            'sunrise_during_eclipse' => $local['sunrise'] ?? null,
            'sunset_during_eclipse' => $local['sunset'] ?? null,
        ];
    } else {
        $result += [
            'moonrise_during_eclipse' => $local['moonrise'] ?? null,
            'moonset_during_eclipse' => $local['moonset'] ?? null,
        ];
    }
    return $result;
}

/** @return array<string,mixed> */
function astronomyNormalizeEvent(
    string $eventGroup,
    string $subtype,
    DateTimeImmutable $dateTime,
    string $title,
    array $details,
    string $source
): array {
    if (in_array($eventGroup, ['moon_phase', 'lunar_apsis'], true) && is_numeric($details['distance_km'] ?? null)) {
        $details['apparent_size_percent'] = MoonApparentSize::percentOfMean((float) $details['distance_km']);
    }
    if ($eventGroup === 'lunar_conjunction') {
        $objectId = astronomyEventPublicSubtype($eventGroup, $subtype);
        $details['object_id'] = $objectId;
        $details['object_name'] = ConjunctionCatalog::names()[$objectId] ?? $objectId;
    }
    $details['_source'] = $source;
    return [
        'type' => astronomyEventPublicTypeForGroup($eventGroup),
        'subtype' => $subtype,
        'datetime' => $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'),
        'title' => $title !== '' ? $title : astronomyEventTitle($eventGroup, $subtype),
        'details' => $details,
    ];
}

function astronomyEventTitle(string $eventGroup, string $subtype): string
{
    $titles = [
        'moon_phase' => ['new_moon' => 'Luna nueva', 'first_quarter' => 'Cuarto creciente', 'full_moon' => 'Luna llena', 'last_quarter' => 'Cuarto menguante'],
        'lunar_apsis' => ['perigee' => 'Perigeo lunar', 'apogee' => 'Apogeo lunar'],
        'lunar_orbit' => ['ascending_node' => 'Nodo lunar ascendente', 'descending_node' => 'Nodo lunar descendente'],
        'lunar_libration' => ['libration_east' => 'Libración hacia el este', 'libration_west' => 'Libración hacia el oeste', 'libration_north' => 'Libración hacia el norte', 'libration_south' => 'Libración hacia el sur'],
    ];
    return $titles[$eventGroup][$subtype] ?? ($eventGroup === 'lunar_conjunction' ? 'Conjunción lunar' : $subtype);
}

function astronomyEventDatabaseCovers(DateTimeImmutable $startUtc, DateTimeImmutable $endUtc): bool
{
    $coverageStart = new DateTimeImmutable(ASTRONOMY_EVENT_DATABASE_START);
    $coverageEnd = new DateTimeImmutable(ASTRONOMY_EVENT_DATABASE_END);
    return $startUtc >= $coverageStart && $endUtc <= $coverageEnd;
}

/** @return list<array<string,mixed>> */
function astronomyCompareEvents(string $eventGroup, array $databaseItems, array $phpItems): array
{
    $comparison = [];
    $usedPhpIndexes = [];
    foreach ($databaseItems as $databaseItem) {
        $bestIndex = null;
        $bestDifference = PHP_INT_MAX;
        $databaseTimestamp = astronomyEventTimestamp($databaseItem['datetime'] ?? null);
        foreach ($phpItems as $index => $phpItem) {
            if (isset($usedPhpIndexes[$index]) || ($phpItem['subtype'] ?? null) !== ($databaseItem['subtype'] ?? null)) {
                continue;
            }
            $phpTimestamp = astronomyEventTimestamp($phpItem['datetime'] ?? null);
            if ($databaseTimestamp === null || $phpTimestamp === null) {
                continue;
            }
            $difference = abs($databaseTimestamp - $phpTimestamp);
            if ($difference < $bestDifference) {
                $bestDifference = $difference;
                $bestIndex = $index;
            }
        }
        $phpItem = $bestIndex !== null ? $phpItems[$bestIndex] : null;
        if ($bestIndex !== null) {
            $usedPhpIndexes[$bestIndex] = true;
        }
        $comparison[] = astronomyEventComparisonRow($eventGroup, $databaseItem, $phpItem);
    }
    foreach ($phpItems as $index => $phpItem) {
        if (!isset($usedPhpIndexes[$index])) {
            $comparison[] = astronomyEventComparisonRow($eventGroup, null, $phpItem);
        }
    }
    return $comparison;
}

/** @return array<string,mixed> */
function astronomyEventComparisonRow(string $eventGroup, ?array $databaseItem, ?array $phpItem): array
{
    $databaseDetails = is_array($databaseItem['details'] ?? null) ? $databaseItem['details'] : [];
    $phpDetails = is_array($phpItem['details'] ?? null) ? $phpItem['details'] : [];
    $databaseTimestamp = astronomyEventTimestamp($databaseItem['datetime'] ?? null);
    $phpTimestamp = astronomyEventTimestamp($phpItem['datetime'] ?? null);
    $row = [
        'subtype' => $databaseItem['subtype'] ?? $phpItem['subtype'] ?? null,
        'datetime_database' => $databaseItem['datetime'] ?? null,
        'datetime_php' => $phpItem['datetime'] ?? null,
        'diferencia_segundos' => $databaseTimestamp !== null && $phpTimestamp !== null
            ? abs($databaseTimestamp - $phpTimestamp)
            : null,
        'distance_km_database' => $databaseDetails['distance_km'] ?? $databaseDetails['moon_distance_km'] ?? null,
        'distance_km_php' => $phpDetails['distance_km'] ?? $phpDetails['moon_distance_km'] ?? null,
        'illumination_percent_database' => $databaseDetails['illumination_percent'] ?? null,
        'illumination_percent_php' => $phpDetails['illumination_percent'] ?? null,
        'solo_database' => $databaseItem !== null && $phpItem === null,
        'solo_php' => $databaseItem === null && $phpItem !== null,
    ];
    if ($eventGroup === 'lunar_libration') {
        $row += [
            'value_degrees_database' => $databaseDetails['value_degrees'] ?? null,
            'value_degrees_php' => $phpDetails['value_degrees'] ?? null,
            'other_axis_degrees_database' => $databaseDetails['other_axis_degrees'] ?? null,
            'other_axis_degrees_php' => $phpDetails['other_axis_degrees'] ?? null,
        ];
    }
    if ($eventGroup === 'lunar_conjunction') {
        $row += [
            'separation_degrees_database' => $databaseDetails['separation_degrees'] ?? null,
            'separation_degrees_php' => $phpDetails['separation_degrees'] ?? null,
        ];
    }
    return $row;
}

function astronomyEventDatabaseTypeIsAllowed(string $eventGroup, string $eventType): bool
{
    return match ($eventGroup) {
        'moon_phase' => in_array($eventType, ['new_moon', 'first_quarter', 'full_moon', 'last_quarter'], true),
        'lunar_apsis' => in_array($eventType, ['perigee', 'apogee'], true),
        'lunar_orbit' => in_array($eventType, ['ascending_node', 'descending_node'], true),
        'lunar_libration' => in_array($eventType, ['libration_east', 'libration_west', 'libration_north', 'libration_south'], true),
        'lunar_conjunction' => preg_match('/^moon_[a-z0-9_]+_conjunction$/', $eventType) === 1,
        'eclipse' => in_array($eventType, ['lunar_eclipse', 'solar_eclipse'], true),
        default => false,
    };
}

function astronomyEventPublicSubtype(string $eventGroup, string $eventType): string
{
    if ($eventGroup === 'lunar_conjunction' && str_starts_with($eventType, 'moon_') && str_ends_with($eventType, '_conjunction')) {
        return substr($eventType, 5, -12);
    }
    return $eventType;
}

/** @return list<AstronomyEngine\LunarEvent> */
function astronomyLunarEventsFromPhp(
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    array $eventGroups
): array {
    static $cache = [];
    $eventGroups = array_values(array_unique($eventGroups));
    sort($eventGroups);
    $key = implode('|', [$startUtc->format('U.u'), $endUtc->format('U.u'), $latitude, $longitude, implode(',', $eventGroups)]);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('El autoload de Composer no está disponible.');
    }
    require_once $autoload;
    $calculator = new AstronomyEngine\LunarEventCalculator(new AstronomyEngine\MeeusLunarCalculator());
    return $cache[$key] = $calculator->calculate($startUtc, $endUtc, $latitude, $longitude, $eventGroups);
}

function astronomyEventsRecordDiagnostic(string $context, array $diagnostic): void
{
    $existing = $GLOBALS['astronomy_event_diagnostics'][$context] ?? [];
    $groups = is_array($existing['groups'] ?? null) ? $existing['groups'] : [];
    $eventGroup = is_string($diagnostic['group'] ?? null) ? $diagnostic['group'] : 'unknown';
    $groups[$eventGroup] = $diagnostic;
    $GLOBALS['astronomy_event_diagnostics'][$context] = $diagnostic + ['groups' => $groups];
}

/** @return array<string,mixed> */
function astronomyEventDiagnostics(): array
{
    return canUseSiteDebugTools()
        ? (is_array($GLOBALS['astronomy_event_diagnostics'] ?? null) ? $GLOBALS['astronomy_event_diagnostics'] : [])
        : [];
}

/** @return array{DateTimeImmutable,DateTimeImmutable} */
function astronomyEventsUtcRange(array $request): array
{
    $timezoneName = is_string($request['timezone'] ?? null) ? $request['timezone'] : 'UTC';
    try {
        $timezone = new DateTimeZone($timezoneName);
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('La zona horaria de eventos no es válida.', 0, $exception);
    }
    $startDate = is_string($request['start_date'] ?? null) ? $request['start_date'] : '';
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate, $timezone);
    $days = filter_var($request['days'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 366],
    ]);
    if ($start === false || $start->format('Y-m-d') !== $startDate || $days === false) {
        throw new InvalidArgumentException('El rango solicitado para eventos no es válido.');
    }
    return [
        $start->setTimezone(new DateTimeZone('UTC')),
        $start->modify('+' . $days . ' days')->setTimezone(new DateTimeZone('UTC')),
    ];
}

/** @return list<string> */
function astronomyEventsRequestedTypes(mixed $types): array
{
    $values = is_array($types) ? $types : explode(',', (string) $types);
    return array_values(array_unique(array_filter(array_map(
        static fn(mixed $type): string => trim((string) $type),
        $values
    ))));
}

function astronomyEventsChronologicalComparison(array $first, array $second): int
{
    return (strtotime((string) ($first['datetime'] ?? '')) ?: PHP_INT_MAX)
        <=> (strtotime((string) ($second['datetime'] ?? '')) ?: PHP_INT_MAX);
}

function astronomyEventTimestamp(mixed $value): ?float
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    try {
        return (float) (new DateTimeImmutable($value))->format('U.u');
    } catch (Throwable $exception) {
        return null;
    }
}
