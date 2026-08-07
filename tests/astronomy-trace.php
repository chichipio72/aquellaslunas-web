<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/astronomy-trace.php';
require_once __DIR__ . '/../scripts/cleanup-astronomy-request-log.php';

function traceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$connection = getWebDatabaseConnection();
$connection->beginTransaction();
$GLOBALS['astronomy_trace_enabled_override'] = true;
$GLOBALS['astronomy_trace_diagnostics'] = [];
$previousCookie = $_COOKIE[ASTRONOMY_TRACE_COOKIE] ?? null;
try {
    $sessionA = str_repeat('a', 32);
    $_COOKIE[ASTRONOMY_TRACE_COOKIE] = $sessionA;
    $first = astronomyTraceExecute('test_success', 'tests', ['date' => '2026-08-05'],
        ['latitude' => -34.53, 'longitude' => -58.48, 'timezone' => 'America/Argentina/Buenos_Aires'],
        static fn(): array => ['items' => [['value' => 1]], 'reproduction' => ['date' => '2026-08-05']]);
    $second = astronomyTraceExecute('test_empty', 'tests', ['date' => '2026-08-06'],
        ['latitude' => -34.80, 'longitude' => -58.48, 'timezone' => 'America/Argentina/Buenos_Aires'],
        static fn(): array => ['items' => []]);
    traceAssert(count($first['items']) === 1 && $second['items'] === [], 'Las operaciones trazadas alteraron sus respuestas.');
    try {
        astronomyTraceExecute('test_failed', 'tests', ['case' => 'failure'],
            ['latitude' => -34.80, 'longitude' => -58.48, 'timezone' => 'UTC'],
            static function (): array { throw new RuntimeException('fallo sintético'); });
    } catch (RuntimeException) {}
    astronomyTraceExecute('test_disabled', 'tests', ['enabled' => false],
        ['latitude' => 0, 'longitude' => 0, 'timezone' => 'UTC'],
        static fn(): array => ['status' => 'disabled', 'items' => []]);

    $sessionB = str_repeat('b', 32);
    $_COOKIE[ASTRONOMY_TRACE_COOKIE] = $sessionB;
    astronomyTraceExecute('test_other_session', 'tests', ['date' => '2026-08-07'],
        ['latitude' => 10, 'longitude' => 20, 'timezone' => 'UTC'],
        static fn(): array => ['items' => [['value' => 2]]]);

    $rows = $connection->query("SELECT request_id,session_trace_id,operation,latitude,input_json,response_json,status,technical_error FROM astronomy_request_log WHERE source='tests' ORDER BY id DESC LIMIT 5")->fetchAll();
    traceAssert(count($rows) === 5, 'No se guardó una fila por consulta de alto nivel.');
    $byOperation = [];
    foreach ($rows as $row) $byOperation[$row['operation']] = $row;
    traceAssert($byOperation['test_success']['session_trace_id'] === $sessionA
        && $byOperation['test_empty']['session_trace_id'] === $sessionA
        && $byOperation['test_success']['request_id'] !== $byOperation['test_empty']['request_id'],
        'Una sesión no conservó su traza o reutilizó request_id.');
    traceAssert($byOperation['test_other_session']['session_trace_id'] === $sessionB,
        'Otra sesión no obtuvo otro session_trace_id.');
    traceAssert((float) $byOperation['test_success']['latitude'] !== (float) $byOperation['test_empty']['latitude'],
        'El cambio de ubicación no quedó registrado.');
    traceAssert($byOperation['test_success']['status'] === 'success'
        && $byOperation['test_empty']['status'] === 'no_results'
        && $byOperation['test_failed']['status'] === 'failed'
        && $byOperation['test_disabled']['status'] === 'disabled', 'Los estados de trazabilidad son incorrectos.');
    traceAssert(json_decode($byOperation['test_success']['input_json'], true)['date'] === '2026-08-05'
        && json_decode($byOperation['test_success']['response_json'], true)['reproduction']['date'] === '2026-08-05',
        'Entrada y respuesta no permiten reproducir el caso.');

    $beforeDisabled = (int) $connection->query("SELECT COUNT(*) FROM astronomy_request_log WHERE source='tests'")->fetchColumn();
    $GLOBALS['astronomy_trace_enabled_override'] = false;
    astronomyTraceExecute('test_must_not_write', 'tests', [], ['timezone' => 'UTC'], static fn(): array => ['items' => []]);
    $afterDisabled = (int) $connection->query("SELECT COUNT(*) FROM astronomy_request_log WHERE source='tests'")->fetchColumn();
    traceAssert($beforeDisabled === $afterDisabled, 'La trazabilidad deshabilitada escribió registros.');

    $GLOBALS['astronomy_trace_enabled_override'] = true;
    astronomyTraceRecord([
        'request_id' => astronomyTraceRequestId(), 'session_trace_id' => $sessionB, 'source' => 'write_failure',
        'operation' => 'write_failure', 'location' => ['timezone' => 'UTC'], 'input' => [], 'response' => [],
    ], static function (): PDO { throw new RuntimeException('base no disponible'); });

    $oldId = $byOperation['test_success']['request_id'];
    $statement = $connection->prepare("UPDATE astronomy_request_log SET created_at=UTC_TIMESTAMP(6)-INTERVAL 40 DAY WHERE request_id=:request_id");
    $statement->execute(['request_id' => $oldId]);
    traceAssert(astronomyTraceCleanup($connection, 30) >= 1, 'La limpieza no eliminó el registro antiguo.');
    traceAssert((int) $connection->query("SELECT COUNT(*) FROM astronomy_request_log WHERE request_id='" . $oldId . "'")->fetchColumn() === 0,
        'El registro vencido sobrevivió a la limpieza.');
} finally {
    if ($connection->inTransaction()) $connection->rollBack();
    if ($previousCookie === null) unset($_COOKIE[ASTRONOMY_TRACE_COOKIE]); else $_COOKIE[ASTRONOMY_TRACE_COOKIE] = $previousCookie;
    unset($GLOBALS['astronomy_trace_enabled_override'], $GLOBALS['astronomy_trace_diagnostics']);
}

echo "OK astronomy trace\n";

