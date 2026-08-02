<?php

declare(strict_types=1);

require_once __DIR__ . '/api-client.php';
require_once __DIR__ . '/web-database.php';

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
        'eclipse' => ['label' => 'Eclipses', 'sources' => ['api']],
    ];
}

function astronomyEventSourceFor(string $eventGroup, ?string $productionConfigPath = null): string
{
    $definition = astronomyEventSourceCatalog()[$eventGroup] ?? null;
    if (!is_array($definition)) {
        throw new InvalidArgumentException('El grupo de eventos astronómicos no está soportado.');
    }
    $supportedSources = $definition['sources'];
    $configurationKey = 'astronomy.event_source.' . $eventGroup;

    try {
        $statement = getWebDatabaseConnection()->prepare(
            'SELECT valor FROM admin_configuracion_sitio WHERE clave=:clave LIMIT 1'
        );
        $statement->execute(['clave' => $configurationKey]);
        $persistedSource = $statement->fetchColumn();
        if (is_string($persistedSource) && in_array($persistedSource, $supportedSources, true)) {
            return $persistedSource;
        }
    } catch (Throwable $exception) {
        // La configuración persistida es opcional; se continúa con entorno/default.
    }

    $environmentName = $definition['environment'] ?? null;
    if (is_string($environmentName)) {
        $environmentSource = getenv($environmentName);
        $environmentSource = is_string($environmentSource) ? strtolower(trim($environmentSource)) : '';
        if ($environmentSource !== '') {
            if (!in_array($environmentSource, $supportedSources, true)) {
                throw new RuntimeException('La fuente astronómica configurada en el entorno no es válida.');
            }
            return $environmentSource;
        }
    }

    return 'api';
}

/** @return array<string,string> */
function astronomyEventSourceSettings(): array
{
    $settings = [];
    foreach (array_keys(astronomyEventSourceCatalog()) as $eventGroup) {
        $settings[$eventGroup] = astronomyEventSourceFor($eventGroup);
    }
    return $settings;
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
function astronomyEvents(array $request, string $context = 'events', int $timeout = 35): array
{
    [$startUtc, $endUtc] = astronomyEventsUtcRange($request);
    $requestedTypes = astronomyEventsRequestedTypes($request['types'] ?? '');
    $apiTypes = [];
    $apiGroups = [];
    $apiTypesWithoutFallback = [];
    $localGroups = [];
    foreach ($requestedTypes as $publicType) {
        $eventGroup = astronomyEventGroupFromPublicType($publicType);
        $source = $eventGroup !== null ? astronomyEventSourceFor($eventGroup) : 'api';
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
    if ($apiTypes !== []) {
        $apiRequest = $request;
        $apiRequest['types'] = implode(',', $apiTypes);
        try {
            $items = astronomyEventsFromApi($apiRequest, $context . ' API groups', $timeout);
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
                    $context,
                    $apiException
                ));
            }
        }
    }
    foreach ($localGroups as $eventGroup => $source) {
        $items = array_merge($items, astronomyEventsForGroup(
            $eventGroup,
            $source,
            $startUtc,
            $endUtc,
            (float) ($request['latitude'] ?? 0.0),
            (float) ($request['longitude'] ?? 0.0),
            $context
        ));
    }

    usort($items, 'astronomyEventsChronologicalComparison');
    return ['items' => $items];
}

/** @return list<array<string,mixed>> */
function astronomyEventsForApiFallback(
    string $eventGroup,
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    string $context,
    Throwable $apiException
): array {
    $supportsPhp = in_array('php', astronomyEventSourceCatalog()[$eventGroup]['sources'] ?? [], true);
    if (astronomyEventDatabaseCovers($startUtc, $endUtc)) {
        try {
            $items = astronomyEventsFromDatabase($eventGroup, $startUtc, $endUtc);
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
            $items = astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude);
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
    $items = astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude);
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
    return array_values(array_filter($decoded['items'], 'is_array'));
}

/** @return list<array<string,mixed>> */
function astronomyEventsForGroup(
    string $eventGroup,
    string $source,
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    float $latitude,
    float $longitude,
    string $context
): array {
    if ($source === 'database') {
        return astronomyEventsFromDatabase($eventGroup, $startUtc, $endUtc);
    }
    if ($source === 'php') {
        return astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude);
    }
    if ($source === 'auto') {
        if (astronomyEventDatabaseCovers($startUtc, $endUtc)) {
            try {
                return astronomyEventsFromDatabase($eventGroup, $startUtc, $endUtc);
            } catch (Throwable $exception) {
                astronomyEventsRecordDiagnostic($context, [
                    'group' => $eventGroup,
                    'mode' => 'auto',
                    'fallback' => 'php',
                    'reason' => 'database_unavailable',
                ]);
            }
        }
        return astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude);
    }
    if ($source === 'compare') {
        $phpItems = astronomyEventsFromPhp($eventGroup, $startUtc, $endUtc, $latitude, $longitude);
        try {
            $databaseItems = astronomyEventsFromDatabase($eventGroup, $startUtc, $endUtc);
            astronomyEventsRecordDiagnostic($context, [
                'group' => $eventGroup,
                'mode' => 'compare',
                'primary' => 'database',
                'comparison' => astronomyCompareEvents($eventGroup, $databaseItems, $phpItems),
            ]);
            return $databaseItems;
        } catch (Throwable $exception) {
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
    float $longitude
): array
{
    $items = [];
    foreach (astronomyLunarEventsFromPhp($startUtc, $endUtc, $latitude, $longitude) as $event) {
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

/** @return array<string,mixed> */
function astronomyNormalizeEvent(
    string $eventGroup,
    string $subtype,
    DateTimeImmutable $dateTime,
    string $title,
    array $details,
    string $source
): array {
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
    float $longitude
): array {
    static $cache = [];
    $key = implode('|', [$startUtc->format('U.u'), $endUtc->format('U.u'), $latitude, $longitude]);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('El autoload de Composer no está disponible.');
    }
    require_once $autoload;
    $calculator = new AstronomyEngine\LunarEventCalculator(new AstronomyEngine\MeeusLunarCalculator());
    return $cache[$key] = $calculator->calculate($startUtc, $endUtc, $latitude, $longitude);
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
