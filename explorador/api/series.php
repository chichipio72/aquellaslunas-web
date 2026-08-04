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

$requestStarted = JsonResponse::now();
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        JsonResponse::send(['error' => 'Método no permitido.'], $requestStarted, 405);
    }
    $catalog = new VariableCatalog();
    $mode = $_GET['modo'] ?? 'diario';
    if (!is_string($mode) || !in_array($mode, ['diario', 'extremos'], true)) {
        throw new InvalidArgumentException('Modo de análisis inválido.');
    }
    if ($mode === 'extremos') {
        $extrema = ExtremaRequest::fromParameters($_GET, $catalog);
        $started = JsonResponse::now();
        $plan = (new SeriesPlanner($catalog))->plan([$extrema->variable]);
        $planningMs = JsonResponse::elapsed($started);
        $result = (new SeriesBuilder($catalog))->build($extrema->calculation, $plan);
        $detectStarted = JsonResponse::now();
        $detected = (new LocalExtremaDetector())->detect($result['rows'], $extrema->variable,
            $extrema->requested->from, $extrema->requested->to);
        $detectionMs = JsonResponse::elapsed($detectStarted);
        $metrics = ['requested_days' => $extrema->requested->days,
            'processed_days' => $extrema->calculation->days, 'planning_ms' => $planningMs,
            'detection_ms' => $detectionMs] + $result['metrics'] + [
                'json_serialization_ms' => 0.0, 'total_ms' => 0.0, 'approximate_json_bytes' => 0,
            ];
        JsonResponse::send(ExtremaResponse::build($extrema, $detected, $metrics), $requestStarted);
    }
    $request = SeriesRequest::fromParameters($_GET, $catalog);
    $selectedDates = null;
    $phaseQueryMs = 0.0;
    if ($request->phases !== []) {
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
    $started = JsonResponse::now();
    $plan = (new SeriesPlanner($catalog))->plan($request->fields);
    $planningMs = JsonResponse::elapsed($started);
    $result = (new SeriesBuilder($catalog))->build($request, $plan, null, $selectedDates);
    $metadata = [];
    foreach ($request->fields as $field) $metadata[$field] = $catalog->get($field);
    $metrics = ['days' => $selectedCount, 'calendar_days' => $request->days, 'phase_query_ms' => $phaseQueryMs,
        'planning_ms' => $planningMs] + $result['metrics'] + [
        'json_serialization_ms' => 0.0, 'total_ms' => 0.0, 'approximate_json_bytes' => 0,
    ];
    JsonResponse::send(['columns' => array_merge(['date'], $request->fields), 'field_metadata' => $metadata,
        'request' => $request->data(), 'date_selection' => $dateSelection,
        'metrics' => $metrics, 'rows' => $result['rows']], $requestStarted);
} catch (PhaseSelectionException $exception) {
    JsonResponse::send(['error' => $exception->getMessage()], $requestStarted, 503);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['error' => $exception->getMessage()], $requestStarted, 400);
} catch (Throwable $exception) {
    error_log('Explorador astronómico: ' . get_debug_type($exception) . ': ' . $exception->getMessage());
    JsonResponse::send(['error' => 'No fue posible calcular la serie.'], $requestStarted, 500);
}
