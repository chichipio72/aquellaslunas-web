<?php

declare(strict_types=1);

require_once __DIR__ . '/api-client.php';
require_once __DIR__ . '/web-database.php';
require_once __DIR__ . '/astronomy-trace.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use AstronomyEngine\Facade\AltitudeProfileFacade;
use AstronomyEngine\Facade\AstronomyDirectionsFacade;
use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Facade\AstronomyRangeFacade;
use AstronomyEngine\Facade\DailyAstronomyFacade;
use AstronomyEngine\Facade\MoonInstantFacade;
use AstronomyEngine\TonightCalculator;

function astronomyDataSourceCatalog(): array
{
    return [
        'daily' => ['label' => 'daily', 'sources' => ['api', 'php'], 'default' => 'php'],
        'range' => ['label' => 'range', 'sources' => ['api', 'php'], 'default' => 'php'],
        'directions' => ['label' => 'directions', 'sources' => ['api', 'php'], 'default' => 'php'],
        'moon_instant' => ['label' => 'moon/instant', 'sources' => ['api', 'php'], 'default' => 'php'],
        'altitude_profile' => ['label' => 'altitude-profile', 'sources' => ['api', 'php'], 'default' => 'php'],
        'tonight' => ['label' => 'tonight', 'sources' => ['api', 'php'], 'default' => 'php'],
        'moon_image' => ['label' => 'moon/image', 'sources' => ['static', 'api'], 'default' => 'static'],
    ];
}

function astronomyDataSourceFor(string $functionality): string
{
    $definition = astronomyDataSourceCatalog()[$functionality] ?? null;
    if (!is_array($definition)) {
        throw new InvalidArgumentException('La funcionalidad astronómica general no está soportada.');
    }
    try {
        $statement = getWebDatabaseConnection()->prepare(
            'SELECT valor FROM admin_configuracion_sitio WHERE clave=:clave LIMIT 1'
        );
        $statement->execute(['clave' => 'astronomy.data_source.' . $functionality]);
        $source = $statement->fetchColumn();
        if (is_string($source) && in_array($source, $definition['sources'], true)) {
            return $source;
        }
    } catch (Throwable $exception) {
        // La selección persistida es opcional; se conserva el default compatible.
    }
    return $definition['default'];
}

function astronomyDataSourceSettings(): array
{
    $settings = [];
    foreach (array_keys(astronomyDataSourceCatalog()) as $functionality) {
        $settings[$functionality] = astronomyDataSourceFor($functionality);
    }
    return $settings;
}

function astronomyDataSourceUpdate(PDO $connection, array $updates): void
{
    $catalog = astronomyDataSourceCatalog();
    if (array_keys($updates) !== array_keys($catalog)) {
        throw new InvalidArgumentException('La configuración de cálculos generales está incompleta.');
    }
    foreach ($updates as $functionality => $source) {
        if (!is_string($source) || !in_array($source, $catalog[$functionality]['sources'], true)) {
            throw new InvalidArgumentException('La fuente general seleccionada no está permitida.');
        }
    }
    $statement = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave,valor,descripcion) '
        . 'VALUES (:clave,:valor,:descripcion) '
        . 'ON DUPLICATE KEY UPDATE valor=VALUES(valor),descripcion=VALUES(descripcion)'
    );
    $connection->beginTransaction();
    try {
        foreach ($updates as $functionality => $source) {
            $statement->execute([
                'clave' => 'astronomy.data_source.' . $functionality,
                'valor' => $source,
                'descripcion' => 'Fuente de datos para ' . $catalog[$functionality]['label'] . '.',
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

function astronomyDataObserver(array $location): AstronomyObserver
{
    return new AstronomyObserver(
        (float) $location['latitude'],
        (float) $location['longitude'],
        (string) $location['timezone'],
        (float) ($location['elevation'] ?? $location['elevation_meters'] ?? 0.0)
    );
}

/** @return array{latitude:float,longitude:float,timezone:string} */
function astronomyDataLocationParameters(array $location): array
{
    return [
        'latitude' => (float) $location['latitude'],
        'longitude' => (float) $location['longitude'],
        'timezone' => (string) $location['timezone'],
    ];
}

/** @return array<string,mixed> */
function astronomyDataApiFallback(string $path, array $parameters, string $label, int $timeout): array
{
    $apiConfig = loadAstronomyApiConfig();
    $result = astronomyApiRequest(
        rtrim($apiConfig['base_url'], '/') . $path . '?' . http_build_query($parameters),
        $label,
        $timeout
    );
    if ($result['body'] === false || $result['http_code'] !== 200) {
        astronomyApiRecordValidation($label, null, false);
        throw new RuntimeException('La API astronómica no respondió correctamente.');
    }
    $decoded = json_decode($result['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        astronomyApiRecordValidation($label, false, false);
        throw new RuntimeException('La API astronómica devolvió una respuesta inválida.');
    }
    astronomyApiRecordValidation($label, true, true);
    return $decoded;
}

/** @return array<string,mixed> */
function astronomyDataResolveUntraced(string $functionality, callable $phpCalculation, string $path, array $parameters, string $label, int $timeout, ?callable $validator = null): array
{
    $requestedSource = astronomyDataSourceFor($functionality);
    $operationStarted = hrtime(true);
    if ($requestedSource === 'api') {
        try {
            $result = astronomyDataApiFallback($path, $parameters, $label, $timeout);
            if ($validator !== null) {
                $validator($result);
            }
            astronomyAnnotateLastDiagnostic($label, [
                'requested_source' => 'api',
                'used_source' => 'api',
                'total_ms' => (hrtime(true) - $operationStarted) / 1_000_000,
                'result_count' => astronomyDataResultCount($result),
            ]);
            return $result;
        } catch (Throwable $apiException) {
            error_log('Aquellas Lunas API astronomy ' . $label . ' error; using PHP fallback: ' . $apiException->getMessage());
            $phpStarted = hrtime(true);
            $result = $phpCalculation();
            if (!is_array($result)) {
                throw new UnexpectedValueException('El motor PHP devolvió un contrato inválido.', 0, $apiException);
            }
            if ($validator !== null) {
                $validator($result);
            }
            astronomyRecordDiagnostic([
                'label' => $label . ' PHP fallback',
                'requested_source' => 'api',
                'used_source' => 'php',
                'source_ms' => (hrtime(true) - $phpStarted) / 1_000_000,
                'total_ms' => (hrtime(true) - $operationStarted) / 1_000_000,
                'fallback_from' => 'api',
                'fallback_to' => 'php',
                'technical_error' => get_debug_type($apiException) . ': ' . $apiException->getMessage(),
                'result_count' => astronomyDataResultCount($result),
            ]);
            return $result;
        }
    }
    $phpStarted = $operationStarted;
    try {
        $result = $phpCalculation();
        if (!is_array($result)) {
            throw new UnexpectedValueException('El motor PHP devolvió un contrato inválido.');
        }
        if ($validator !== null) {
            $validator($result);
        }
        $elapsed = (hrtime(true) - $phpStarted) / 1_000_000;
        astronomyRecordDiagnostic([
            'label' => $label,
            'requested_source' => 'php',
            'used_source' => 'php',
            'source_ms' => $elapsed,
            'total_ms' => (hrtime(true) - $operationStarted) / 1_000_000,
            'result_count' => astronomyDataResultCount($result),
        ]);
        return $result;
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas PHP astronomy ' . $label . ' error; using API fallback: ' . $exception->getMessage());
        $fallbackLabel = $label . ' API fallback';
        $result = astronomyDataApiFallback($path, $parameters, $fallbackLabel, $timeout);
        if ($validator !== null) {
            $validator($result);
        }
        astronomyAnnotateLastDiagnostic($fallbackLabel, [
            'requested_source' => 'php',
            'used_source' => 'api',
            'total_ms' => (hrtime(true) - $operationStarted) / 1_000_000,
            'fallback_from' => 'php',
            'fallback_to' => 'api',
            'technical_error' => get_debug_type($exception) . ': ' . $exception->getMessage(),
            'result_count' => astronomyDataResultCount($result),
        ]);
        return $result;
    }
}

/** @return array<string,mixed> */
function astronomyDataResolve(string $functionality, callable $phpCalculation, string $path, array $parameters,
    string $label, int $timeout, ?callable $validator = null): array
{
    $location = [
        'latitude' => (float) ($parameters['latitude'] ?? 0.0),
        'longitude' => (float) ($parameters['longitude'] ?? 0.0),
        'elevation_meters' => (float) ($parameters['elevation_meters'] ?? $parameters['elevation'] ?? 0.0),
        'timezone' => (string) ($parameters['timezone'] ?? 'UTC'),
    ];
    return astronomyTraceExecute($functionality, $label, [
        'endpoint' => $path, 'parameters' => $parameters, 'timeout_seconds' => $timeout,
    ], $location, static fn(): array => astronomyDataResolveUntraced(
        $functionality, $phpCalculation, $path, $parameters, $label, $timeout, $validator
    ));
}

function astronomyDataResultCount(array $result): ?int
{
    if (is_array($result['items'] ?? null)) {
        return count($result['items']);
    }
    if (is_array($result['days'] ?? null)) {
        return count($result['days']);
    }
    return 1;
}

/** @return array<string,mixed> */
function astronomyDataDaily(array $location, string $date, bool $includeLightPeriods, string $label, int $timeout = 18): array
{
    $parameters = ['date' => $date, 'include_light_periods' => $includeLightPeriods ? 'true' : 'false'] + astronomyDataLocationParameters($location);
    return astronomyDataResolve(
        'daily',
        static fn(): array => (new DailyAstronomyFacade())->calculate($date, astronomyDataObserver($location), $includeLightPeriods),
        '/v1/astronomy/daily', $parameters, $label, $timeout
    );
}

/** @return array<string,mixed> */
function astronomyDataRange(array $location, string $startDate, int $days, string $label, int $timeout = 18): array
{
    $parameters = ['start_date' => $startDate, 'days' => $days] + astronomyDataLocationParameters($location);
    return astronomyDataResolve(
        'range',
        static fn(): array => (new AstronomyRangeFacade())->calculate($startDate, $days, astronomyDataObserver($location)),
        '/v1/astronomy/range', $parameters, $label, $timeout
    );
}

/** @return array<string,mixed> */
function astronomyDataDirections(array $location, string $date, string $time, string $label, int $timeout = 15): array
{
    $parameters = ['date' => $date, 'time' => $time] + astronomyDataLocationParameters($location);
    return astronomyDataResolve(
        'directions',
        static fn(): array => (new AstronomyDirectionsFacade())->calculate($date, $time, astronomyDataObserver($location)),
        '/v1/astronomy/directions', $parameters, $label, $timeout
    );
}

/** @return array<string,mixed> */
function astronomyDataMoonInstant(array $location, DateTimeImmutable $instant, string $label, int $timeout = 12): array
{
    $parameters = ['datetime' => $instant->format(DateTimeInterface::ATOM)] + astronomyDataLocationParameters($location);
    return astronomyDataResolve(
        'moon_instant',
        static fn(): array => (new MoonInstantFacade())->calculate($instant, astronomyDataObserver($location)),
        '/v1/moon/instant', $parameters, $label, $timeout
    );
}

/** @return array<string,mixed> */
function astronomyDataAltitudeProfile(array $location, string $date, string $target, int $intervalMinutes, string $label, int $timeout = 12): array
{
    $parameters = ['date' => $date, 'target' => $target, 'interval_minutes' => $intervalMinutes] + astronomyDataLocationParameters($location);
    return astronomyDataResolve(
        'altitude_profile',
        static fn(): array => (new AltitudeProfileFacade())->calculate(
            new DateTimeImmutable($date, new DateTimeZone((string) $location['timezone'])),
            astronomyDataObserver($location),
            ['target' => $target, 'interval_minutes' => $intervalMinutes]
        ),
        '/v1/astronomy/altitude-profile', $parameters, $label, $timeout
    );
}

/** @return array<string,mixed> */
function astronomyDataTonight(array $location, string $date, string $detail, string $label, int $timeout = 12): array
{
    $parameters = ['date' => $date, 'detail' => $detail] + astronomyDataLocationParameters($location);
    $calculator = new TonightCalculator();
    $result = astronomyDataResolve(
        'tonight',
        static fn(): array => $calculator->calculate(
            new DateTimeImmutable($date, new DateTimeZone((string) $location['timezone'])),
            astronomyDataObserver($location),
            $detail
        ),
        '/v1/astronomy/tonight', $parameters, $label, $timeout,
        static function (array $result) use ($detail): void {
            if (!is_array($result['night'] ?? null) || !is_array($result['planets'] ?? null)) {
                throw new UnexpectedValueException('La fuente de Tonight devolvió un contrato inválido.');
            }
            if ($detail === 'full' && (!is_array($result['stars'] ?? null) || !is_array($result['deep_sky_objects'] ?? null))) {
                throw new UnexpectedValueException('La fuente de Tonight devolvió una respuesta summary para una solicitud full.');
            }
        }
    );
    if (!is_array($result['moon_encounters'] ?? null)) {
        $portable = $calculator->calculate(
            new DateTimeImmutable($date, new DateTimeZone((string) $location['timezone'])),
            astronomyDataObserver($location),
            $detail
        );
        $result['moon_encounters'] = $portable['moon_encounters'] ?? [];
    }
    return $result;
}
