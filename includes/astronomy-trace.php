<?php

declare(strict_types=1);

require_once __DIR__ . '/site-configuration.php';

const ASTRONOMY_TRACE_COOKIE = 'astro_trace_session';

function astronomyTraceEnabled(): bool
{
    if (array_key_exists('astronomy_trace_enabled_override', $GLOBALS)) {
        return $GLOBALS['astronomy_trace_enabled_override'] === true;
    }
    return astronomySiteConfigBool('astronomy.trace.enabled', false);
}

function astronomyTraceSessionId(): string
{
    $stored = $_COOKIE[ASTRONOMY_TRACE_COOKIE] ?? null;
    if (is_string($stored) && preg_match('/^[a-f0-9]{32}$/', $stored) === 1) return $stored;
    $generated = $GLOBALS['astronomy_trace_generated_session_id'] ?? null;
    if (is_string($generated) && preg_match('/^[a-f0-9]{32}$/', $generated) === 1) return $generated;
    $generated = bin2hex(random_bytes(16));
    $GLOBALS['astronomy_trace_generated_session_id'] = $generated;
    if (!headers_sent()) {
        setcookie(ASTRONOMY_TRACE_COOKIE, $generated, [
            'expires' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return $generated;
}

function astronomyTraceRequestId(): string
{
    return bin2hex(random_bytes(16));
}

/** @return array{latitude:float,longitude:float,elevation_meters:float,timezone:string} */
function astronomyTraceLocation(array $location): array
{
    return [
        'latitude' => (float) ($location['latitude'] ?? $location['lat'] ?? 0.0),
        'longitude' => (float) ($location['longitude'] ?? $location['lon'] ?? 0.0),
        'elevation_meters' => (float) ($location['elevation_meters'] ?? $location['elevation'] ?? 0.0),
        'timezone' => (string) ($location['timezone'] instanceof DateTimeZone
            ? $location['timezone']->getName() : ($location['timezone'] ?? 'UTC')),
    ];
}

/** @return array{instant:string,simulated:bool} */
function astronomyTraceClock(string $timezoneName): array
{
    try { $timezone = new DateTimeZone($timezoneName); }
    catch (Throwable) { $timezone = new DateTimeZone('UTC'); }
    $simulated = function_exists('astronomyCurrentDateTimeIsSimulated')
        && astronomyCurrentDateTimeIsSimulated();
    try {
        $instant = function_exists('get_current_datetime')
            ? get_current_datetime($timezone->getName())
            : new DateTimeImmutable('now', $timezone);
    } catch (Throwable) {
        $instant = new DateTimeImmutable('now', $timezone);
        $simulated = false;
    }
    return ['instant' => $instant->format('Y-m-d\TH:i:s.uP'), 'simulated' => $simulated];
}

function astronomyTraceCodeVersion(): string
{
    static $version = null;
    if (is_string($version)) return $version;
    $configured = trim((string) getenv('ASTRONOMY_CODE_VERSION'));
    if (preg_match('/^[A-Za-z0-9._-]{1,32}$/', $configured) === 1) return $version = $configured;
    $files = [__FILE__, __DIR__ . '/astronomy-data.php', __DIR__ . '/astronomy-events.php',
        __DIR__ . '/home-satellite-context.php', dirname(__DIR__) . '/astronomy-engine/src/Satellite/SatelliteTransitService.php'];
    $fingerprint = [];
    foreach ($files as $file) $fingerprint[] = is_file($file) ? $file . ':' . filesize($file) . ':' . filemtime($file) : $file . ':missing';
    return $version = substr(hash('sha256', implode('|', $fingerprint)), 0, 12);
}

function astronomyTraceIsAdminSession(): bool
{
    if (!function_exists('storeAdminHasValidSessionCookie')) require_once __DIR__ . '/store-admin-auth.php';
    try { return storeAdminHasValidSessionCookie(); }
    catch (Throwable) { return false; }
}

function astronomyTraceStatus(array $response, ?string $explicit = null): string
{
    if (in_array($explicit, ['success', 'no_results', 'failed', 'disabled'], true)) return $explicit;
    $collections = 0;
    $results = 0;
    foreach (['items', 'days', 'rows', 'events'] as $key) {
        if (array_key_exists($key, $response) && is_array($response[$key])) {
            $collections++;
            $results += count($response[$key]);
        }
    }
    if ($collections > 0 && $results === 0) return 'no_results';
    return 'success';
}

/** @param null|callable():PDO $connectionFactory */
function astronomyTraceRecord(array $record, ?callable $connectionFactory = null): void
{
    if (!astronomyTraceEnabled()) return;
    try {
        $location = astronomyTraceLocation(is_array($record['location'] ?? null) ? $record['location'] : []);
        $clock = astronomyTraceClock($location['timezone']);
        $input = is_array($record['input'] ?? null) ? $record['input'] : [];
        $input['_trace_origin'] = ['script' => (string) ($_SERVER['SCRIPT_NAME'] ?? 'cli')];
        $inputJson = json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $responseJson = json_encode($record['response'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $connection = $connectionFactory !== null ? $connectionFactory() : getWebDatabaseConnection();
        $statement = $connection->prepare(
            'INSERT INTO astronomy_request_log '
            . '(request_id,session_trace_id,created_at,source,operation,latitude,longitude,elevation_meters,timezone,'
            . 'effective_clock,clock_simulated,input_json,response_json,status,total_ms,code_version,technical_error,is_admin_session) '
            . 'VALUES (:request_id,:session_trace_id,CURRENT_TIMESTAMP(6),:source,:operation,:latitude,:longitude,'
            . ':elevation_meters,:timezone,:effective_clock,:clock_simulated,:input_json,:response_json,:status,'
            . ':total_ms,:code_version,:technical_error,:is_admin_session)'
        );
        $statement->execute([
            'request_id' => (string) $record['request_id'],
            'session_trace_id' => (string) $record['session_trace_id'],
            'source' => substr((string) ($record['source'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'unknown')), 0, 190),
            'operation' => substr((string) ($record['operation'] ?? 'unknown'), 0, 100),
            'latitude' => $location['latitude'], 'longitude' => $location['longitude'],
            'elevation_meters' => $location['elevation_meters'], 'timezone' => $location['timezone'],
            'effective_clock' => $clock['instant'], 'clock_simulated' => $clock['simulated'] ? 1 : 0,
            'input_json' => $inputJson, 'response_json' => $responseJson,
            'status' => astronomyTraceStatus(is_array($record['response'] ?? null) ? $record['response'] : [], $record['status'] ?? null),
            'total_ms' => max(0.0, (float) ($record['total_ms'] ?? 0.0)),
            'code_version' => astronomyTraceCodeVersion(),
            'technical_error' => isset($record['technical_error']) ? substr((string) $record['technical_error'], 0, 2000) : null,
            'is_admin_session' => astronomyTraceIsAdminSession() ? 1 : 0,
        ]);
        $GLOBALS['astronomy_trace_diagnostics'][] = [
            'request_id' => $record['request_id'], 'session_trace_id' => $record['session_trace_id'],
            'operation' => $record['operation'] ?? 'unknown',
        ];
    } catch (Throwable $exception) {
        error_log('Astronomy trace write failed [type=' . get_debug_type($exception) . ']: ' . $exception->getMessage());
    }
}

/** @return null|array{request_id:string,session_trace_id:string,started:int} */
function astronomyTraceBegin(): ?array
{
    try {
        if (!astronomyTraceEnabled()) return null;
        return ['request_id' => astronomyTraceRequestId(), 'session_trace_id' => astronomyTraceSessionId(), 'started' => hrtime(true)];
    } catch (Throwable $exception) {
        error_log('Astronomy trace initialization failed [type=' . get_debug_type($exception) . ']: ' . $exception->getMessage());
        return null;
    }
}

function astronomyTraceFinish(?array $trace, string $operation, string $source, array $input, array $location,
    array $response, ?string $status = null, ?Throwable $error = null, ?string $technicalError = null): void
{
    if ($trace === null) return;
    astronomyTraceRecord([
        'request_id' => $trace['request_id'], 'session_trace_id' => $trace['session_trace_id'],
        'source' => $source, 'operation' => $operation, 'location' => $location,
        'input' => $input, 'response' => $response,
        'status' => $error !== null ? 'failed' : $status,
        'total_ms' => (hrtime(true) - $trace['started']) / 1_000_000,
        'technical_error' => $error !== null
            ? get_debug_type($error) . ': ' . $error->getMessage()
            : $technicalError,
    ]);
}

/** Ejecuta y registra una única operación astronómica de alto nivel. */
function astronomyTraceExecute(string $operation, string $source, array $input, array $location, callable $calculation,
    ?callable $responseMapper = null): mixed
{
    if (!astronomyTraceEnabled()) return $calculation();
    $trace = astronomyTraceBegin();
    try {
        $result = $calculation();
        $response = $responseMapper !== null ? $responseMapper($result) : $result;
        $response = is_array($response) ? $response : ['value' => $response];
        $resultState = is_array($result) ? ($result['status'] ?? null) : null;
        $explicitStatus = match ($resultState) {
            'disabled' => 'disabled', 'unavailable', 'failed' => 'failed', default => null,
        };
        $returnedError = is_array($result) && is_string($result['error'] ?? null) ? $result['error'] : null;
        astronomyTraceFinish($trace, $operation, $source, $input, $location, $response, $explicitStatus, null, $returnedError);
        return $result;
    } catch (Throwable $exception) {
        astronomyTraceFinish($trace, $operation, $source, $input, $location, [], 'failed', $exception);
        throw $exception;
    }
}
