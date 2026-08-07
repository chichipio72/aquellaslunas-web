<?php

declare(strict_types=1);

use Explorador\JsonResponse;
use Explorador\ExtremaRequest;
use Explorador\ExtremaResponse;
use Explorador\LocalExtremaDetector;
use Explorador\PhaseEventDateProvider;
use Explorador\PhaseSelectionException;
use Explorador\SeriesBuilder;
use Explorador\SeriesPlanner;
use Explorador\SeriesRequest;
use Explorador\VariableCatalog;

require_once dirname(__DIR__) . '/includes/astronomy-engine-autoload.php';
require_once dirname(__DIR__) . '/includes/VariableCatalog.php';
require_once dirname(__DIR__) . '/includes/PhaseEventDateProvider.php';
require_once dirname(__DIR__) . '/includes/SeriesRequest.php';
require_once dirname(__DIR__) . '/includes/SeriesPlanner.php';
require_once dirname(__DIR__) . '/includes/SeriesBuilder.php';
require_once dirname(__DIR__) . '/includes/JsonResponse.php';
require_once dirname(__DIR__) . '/includes/ExtremaRequest.php';
require_once dirname(__DIR__) . '/includes/LocalExtremaDetector.php';
require_once dirname(__DIR__) . '/includes/ExtremaResponse.php';
require_once dirname(__DIR__, 2) . '/includes/astronomy-trace.php';

/** @param array<string,mixed> $message */
function explorerStreamEmit(array $message): void
{
    echo json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
    if (function_exists('flush')) flush();
}

/** @param list<string> $providers @return list<string> */
function explorerStreamStages(array $providers): array
{
    $available = array_fill_keys($providers, true);
    $stages = [];
    foreach (['moon_events' => 'lunar_events', 'sun_events' => 'solar_events',
        'moon_instant' => 'lunar_instant', 'sun_instant' => 'solar_instant'] as $provider => $stage) {
        if (isset($available[$provider])) $stages[] = $stage;
    }
    return $stages;
}

function explorerStreamLabel(string $stage): string
{
    return match ($stage) {
        'lunar_events' => 'Procesando eventos lunares',
        'solar_events' => 'Procesando eventos solares',
        'lunar_instant' => 'Procesando variables lunares instantáneas',
        'solar_instant' => 'Procesando variables solares instantáneas',
        'derived' => 'Calculando variables derivadas',
        'rows' => 'Construyendo filas',
        'detecting_extrema' => 'Detectando extremos locales',
        'serialization' => 'Generando respuesta',
        default => 'Procesando serie',
    };
}

$requestStarted = JsonResponse::now();
$astronomyTrace = astronomyTraceBegin();
$traceLocation = ['latitude' => (float) ($_GET['lat'] ?? 0.0), 'longitude' => (float) ($_GET['lon'] ?? 0.0),
    'elevation_meters' => 0.0, 'timezone' => is_string($_GET['timezone'] ?? null) ? $_GET['timezone'] : 'UTC'];
if (function_exists('ini_set')) {
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', '0');
}
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
while (ob_get_level() > 0) @ob_end_flush();
if (function_exists('ob_implicit_flush')) ob_implicit_flush(true);
ignore_user_abort(false);

header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, no-transform, max-age=0');
header('X-Accel-Buffering: no');
header('Content-Encoding: identity');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        explorerStreamEmit(['type' => 'error', 'message' => 'Método no permitido.']);
        exit;
    }
    $catalog = new VariableCatalog();
    $extrema = null;
    $mode = $_GET['modo'] ?? 'diario';
    if (!is_string($mode) || !in_array($mode, ['diario', 'extremos'], true)) {
        throw new InvalidArgumentException('Modo de análisis inválido.');
    }
    if ($mode === 'extremos') {
        $extrema = ExtremaRequest::fromParameters($_GET, $catalog);
        $request = $extrema->calculation;
    } else {
        $request = SeriesRequest::fromParameters($_GET, $catalog);
    }
    $selectedDates = null;
    $phaseQueryMs = 0.0;
    if ($extrema === null && $request->phases !== []) {
        require_once dirname(__DIR__, 2) . '/includes/web-database.php';
        $selection = (new PhaseEventDateProvider(static fn(): PDO => getWebDatabaseConnection()))
            ->dates($request->from, $request->to, $request->timezone, $request->phases);
        $selectedDates = $selection['items'];
        $phaseQueryMs = $selection['query_ms'];
    }
    $selectedCount = $selectedDates === null ? $request->days : count($selectedDates);
    $dateSelection = $selectedDates === null
        ? ['mode' => 'daily', 'calendar_days' => $request->days, 'selected_dates' => $request->days]
        : ['mode' => 'main_phases', 'calendar_days' => $request->days, 'selected_dates' => $selectedCount,
            'phases' => $request->phases, 'source' => 'mariadb'];
    $planningStarted = JsonResponse::now();
    $plan = (new SeriesPlanner($catalog))->plan($request->fields);
    $planningMs = JsonResponse::elapsed($planningStarted);
    $stages = explorerStreamStages($plan['providers']);
    $completed = array_fill_keys($stages, 0);
    $totalWorkUnits = $selectedCount * count($stages);
    $announcedNonAstronomicalStages = [];

    $calendarDays = $extrema ? $extrema->requested->days : $request->days;
    explorerStreamEmit(['type' => 'start', 'mode' => $extrema ? 'extremos' : 'diario',
        'calendar_days' => $calendarDays, 'selected_dates' => $selectedCount,
        'total_days' => $selectedCount, 'phases' => $request->phases, 'providers' => $stages,
        'total_work_units' => $totalWorkUnits]);

    $progress = static function (string $stage, int $processed, int $total, float $stagePercent) use (
        &$completed, $totalWorkUnits, &$announcedNonAstronomicalStages
    ): void {
        if (connection_aborted()) throw new RuntimeException('Client disconnected.');
        if (array_key_exists($stage, $completed)) {
            $completed[$stage] = max($completed[$stage], min($total, $processed));
            $completedUnits = array_sum($completed);
            explorerStreamEmit(['type' => 'progress', 'stage' => $stage, 'label' => explorerStreamLabel($stage),
                'processed_days' => $processed, 'total_days' => $total,
                'stage_percent' => round($stagePercent, 2),
                'completed_work_units' => $completedUnits, 'total_work_units' => $totalWorkUnits,
                'overall_percent' => $totalWorkUnits > 0 ? round($completedUnits / $totalWorkUnits * 100.0, 2) : 100.0]);
            return;
        }
        if (!isset($announcedNonAstronomicalStages[$stage])) {
            $announcedNonAstronomicalStages[$stage] = true;
            explorerStreamEmit(['type' => 'stage', 'stage' => $stage, 'label' => explorerStreamLabel($stage)]);
        }
    };

    $result = (new SeriesBuilder($catalog))->build($request, $plan, $progress, $selectedDates);
    if ($extrema !== null) {
        explorerStreamEmit(['type' => 'stage', 'stage' => 'detecting_extrema',
            'label' => explorerStreamLabel('detecting_extrema')]);
        $detectStarted = JsonResponse::now();
        $detected = (new LocalExtremaDetector())->detect($result['rows'], $extrema->variable,
            $extrema->requested->from, $extrema->requested->to);
        $detectionMs = JsonResponse::elapsed($detectStarted);
        $metrics = ['requested_days' => $extrema->requested->days,
            'processed_days' => $extrema->calculation->days, 'planning_ms' => $planningMs,
            'detection_ms' => $detectionMs] + $result['metrics'] + [
                'json_serialization_ms' => 0.0, 'total_ms' => 0.0, 'approximate_json_bytes' => 0,
            ];
        $payload = ExtremaResponse::build($extrema, $detected, $metrics);
    } else {
    $metadata = [];
    foreach ($request->fields as $field) $metadata[$field] = $catalog->get($field);
    $metrics = ['days' => $selectedCount, 'calendar_days' => $request->days, 'phase_query_ms' => $phaseQueryMs,
        'planning_ms' => $planningMs] + $result['metrics'] + [
        'json_serialization_ms' => 0.0, 'total_ms' => 0.0, 'approximate_json_bytes' => 0,
    ];
    $payload = ['columns' => array_merge(['date'], $request->fields), 'field_metadata' => $metadata,
        'request' => $request->data(), 'date_selection' => $dateSelection,
        'metrics' => $metrics, 'rows' => $result['rows']];
    }

    explorerStreamEmit(['type' => 'stage', 'stage' => 'serialization', 'label' => explorerStreamLabel('serialization')]);
    $serializationStarted = JsonResponse::now();
    $normalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $payload['metrics']['json_serialization_ms'] = JsonResponse::elapsed($serializationStarted);
    $payload['metrics']['total_ms'] = JsonResponse::elapsed($requestStarted);
    $payload['metrics']['approximate_json_bytes'] = strlen($normalJson);
    $normalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $payload['metrics']['approximate_json_bytes'] = strlen($normalJson);
    astronomyTraceFinish($astronomyTrace, $extrema !== null ? 'explorer_extrema' : 'explorer_series',
        'explorador/api/series-stream.php', $_GET, $traceLocation, $payload);
    explorerStreamEmit(['type' => 'complete', 'result' => $payload]);
} catch (PhaseSelectionException $exception) {
    astronomyTraceFinish($astronomyTrace, 'explorer_series', 'explorador/api/series-stream.php', $_GET,
        $traceLocation, [], 'failed', $exception);
    http_response_code(503);
    explorerStreamEmit(['type' => 'error', 'message' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    astronomyTraceFinish($astronomyTrace, 'explorer_series', 'explorador/api/series-stream.php', $_GET,
        $traceLocation, [], 'failed', $exception);
    http_response_code(400);
    explorerStreamEmit(['type' => 'error', 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    astronomyTraceFinish($astronomyTrace, 'explorer_series', 'explorador/api/series-stream.php', $_GET,
        $traceLocation, [], 'failed', $exception);
    if (!connection_aborted()) {
        error_log('Explorador astronómico stream: ' . get_debug_type($exception) . ': ' . $exception->getMessage());
        explorerStreamEmit(['type' => 'error', 'message' => 'No fue posible calcular la serie.']);
    }
}
