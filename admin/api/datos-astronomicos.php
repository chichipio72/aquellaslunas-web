<?php

ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/web-database.php';
require_once __DIR__ . '/../../includes/astronomy-laboratory.php';
require_once __DIR__ . '/../../includes/astronomy-laboratory-extrema.php';

sendStoreAdminHeaders();
header('Content-Type: application/json; charset=utf-8');
startStoreAdminSession();

function astronomyLaboratoryJsonError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function astronomyLaboratoryDate(mixed $value): ?DateTimeImmutable
{
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value
        ? $date
        : null;
}

if (!storeAdminIsAuthenticated()) {
    astronomyLaboratoryJsonError(401, 'Autenticación requerida.');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    astronomyLaboratoryJsonError(405, 'Método no permitido.');
}

$from = astronomyLaboratoryDate($_GET['fecha_desde'] ?? null);
$to = astronomyLaboratoryDate($_GET['fecha_hasta'] ?? null);
if ($from === null || $to === null) {
    astronomyLaboratoryJsonError(400, 'Las fechas deben usar el formato AAAA-MM-DD.');
}
if ($from > $to) {
    astronomyLaboratoryJsonError(400, 'La fecha desde no puede ser posterior a la fecha hasta.');
}

$mode = is_string($_GET['modo'] ?? null) ? trim($_GET['modo']) : 'diario';
if (!in_array($mode, ['diario', 'extremos'], true)) {
    astronomyLaboratoryJsonError(400, 'El tipo de análisis no es válido.');
}
if ($mode === 'extremos') {
    $variable = is_string($_GET['variable'] ?? null) ? trim($_GET['variable']) : '';
    $extremeType = is_string($_GET['tipo_extremo'] ?? null) ? trim($_GET['tipo_extremo']) : 'ambos';
    $extremaVariables = astronomyLaboratoryExtremaVariables();
    if (!isset($extremaVariables[$variable])) {
        astronomyLaboratoryJsonError(400, 'La variable seleccionada no admite análisis de extremos.');
    }
    if (!in_array($extremeType, ['maximo', 'minimo', 'ambos'], true)) {
        astronomyLaboratoryJsonError(400, 'El tipo de extremo no es válido.');
    }
    $variableMetadata = $extremaVariables[$variable];
    $rangeAssessment = astronomyLaboratoryExtremaRangeAssessment($from, $to, $variableMetadata);
    if (!$rangeAssessment['meets_minimum']) {
        $object = $variableMetadata['body'] === 'moon' ? 'extremos lunares' : 'extremos solares';
        astronomyLaboratoryJsonError(
            400,
            sprintf(
                'Para detectar suficientes %s, seleccioná un período de al menos %d años.',
                $object,
                $rangeAssessment['minimum_years']
            )
        );
    }
    $warning = null;
    if (!$rangeAssessment['meets_recommendation']) {
        $warning = sprintf(
            'Para observar la modulación se recomienda un período de al menos %d años.',
            $rangeAssessment['recommended_years']
        );
    }
    try {
        $series = queryAstronomyLaboratoryExtrema(
            getWebDatabaseConnection(),
            $variable,
            $from,
            $to,
            $extremeType
        );
        echo json_encode([
            'modo' => 'extremos',
            'variable' => $variable,
            'variable_label' => $variableMetadata['label'],
            'field_type' => $variableMetadata['type'],
            'scale_group' => $variableMetadata['scale_group'],
            'unidad' => $variableMetadata['unit'],
            'desde' => $from->format('Y-m-d'),
            'hasta' => $to->format('Y-m-d'),
            'tipo_extremo' => $extremeType,
            'series_labels' => [
                'maximos' => $variableMetadata['maximum_label'],
                'minimos' => $variableMetadata['minimum_label'],
            ],
            'series' => $series,
            'conteos' => [
                'maximos' => count($series['maximos']),
                'minimos' => count($series['minimos']),
            ],
            'rango' => $rangeAssessment,
            'advertencia' => $warning,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    } catch (Throwable $exception) {
        error_log('Astronomy laboratory extrema query failed [type=' . get_debug_type($exception) . '].');
        astronomyLaboratoryJsonError(503, 'No se pudieron calcular los extremos astronómicos.');
    }
}

$requestedFields = is_string($_GET['campos'] ?? null)
    ? array_values(array_unique(array_filter(array_map('trim', explode(',', $_GET['campos'])))))
    : [];
$allowedFields = astronomyLaboratoryFields();
if ($requestedFields === []) {
    astronomyLaboratoryJsonError(400, 'Seleccioná al menos un campo.');
}
foreach ($requestedFields as $field) {
    if (!array_key_exists($field, $allowedFields)) {
        astronomyLaboratoryJsonError(400, 'La selección contiene un campo no permitido.');
    }
}
$selectedScaleGroups = astronomyLaboratorySelectedScaleGroups($requestedFields);
if (count($selectedScaleGroups) > 4) {
    astronomyLaboratoryJsonError(
        400,
        'Seleccionaste más de cuatro grupos de escala. Podés elegir varias variables dentro de un mismo grupo.'
    );
}

$requestedPhases = is_string($_GET['fases'] ?? null)
    ? array_values(array_unique(array_filter(array_map('trim', explode(',', $_GET['fases'])))))
    : [];
$allowedPhases = astronomyLaboratoryPhases();
foreach ($requestedPhases as $phase) {
    if (!in_array($phase, $allowedPhases, true)) {
        astronomyLaboratoryJsonError(400, 'La selección contiene una fase lunar no permitida.');
    }
}

$parameters = [
    'fecha_desde' => $from->format('Y-m-d'),
    'fecha_hasta' => $to->format('Y-m-d'),
];
$fieldTypes = astronomyLaboratoryFieldTypes($requestedFields);
$fieldUnits = astronomyLaboratoryFieldUnits($requestedFields);
$fieldScales = astronomyLaboratoryFieldScales($requestedFields);
$definitions = astronomyLaboratoryFieldDefinitions();
$eventFields = array_values(array_filter(
    $requestedFields,
    static fn(string $field): bool => $fieldTypes[$field] === 'event_marker'
));

try {
    $connection = getWebDatabaseConnection();
    $eventThresholds = null;
    if ($eventFields !== []) {
        $eventThresholds = astronomyLaboratoryFullMoonDistanceThresholds($connection);
        if (in_array('superluna_llena', $eventFields, true)) {
            $parameters['supermoon_max_km'] = $eventThresholds['supermoon_max_km'];
        }
        if (in_array('miniluna_llena', $eventFields, true)) {
            $parameters['minimoon_min_km'] = $eventThresholds['minimoon_min_km'];
        }
    }

    $selectExpressions = ['`d`.`fecha`'];
    foreach ($requestedFields as $field) {
        $definition = $definitions[$field];
        if (($definition['event'] ?? null) === 'supermoon') {
            $expression = "CASE WHEN `fase_lunar` = 'Luna llena'"
                . ' AND `distancia_luna_km` <= :supermoon_max_km THEN 1 ELSE NULL END';
        } elseif (($definition['event'] ?? null) === 'minimoon') {
            $expression = "CASE WHEN `fase_lunar` = 'Luna llena'"
                . ' AND `distancia_luna_km` >= :minimoon_min_km THEN 1 ELSE NULL END';
        } else {
            $expression = $definition['sql'];
        }
        $selectExpressions[] = $expression . ' AS `' . $field . '`';
    }
    if ($eventFields !== []) {
        $selectExpressions[] = '`distancia_luna_km` AS `__event_distance_luna_km`';
    }

    $sql = 'SELECT ' . implode(', ', $selectExpressions)
        . ' FROM `datos_astronomicos` AS `d`'
        . ' WHERE `d`.`fecha` BETWEEN :fecha_desde AND :fecha_hasta';
    if ($requestedPhases !== []) {
        $phasePlaceholders = [];
        foreach ($requestedPhases as $index => $phase) {
            $placeholder = 'fase_' . $index;
            $phasePlaceholders[] = ':' . $placeholder;
            $parameters[$placeholder] = $phase;
        }
        $sql .= ' AND `d`.`fase_lunar` IN (' . implode(', ', $phasePlaceholders) . ')';
    }
    $sql .= ' ORDER BY `d`.`fecha` ASC';

    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $rows = [];
    while ($row = $statement->fetch()) {
        $presented = ['fecha' => (string) $row['fecha']];
        foreach ($requestedFields as $field) {
            $presented[$field] = $fieldTypes[$field] === 'time_fraction'
                ? astronomyLaboratoryTimeFraction($row[$field])
                : ($row[$field] === null ? null : (float) $row[$field]);
        }
        foreach ($eventFields as $eventField) {
            if ($presented[$eventField] === 1.0) {
                $presented['_event_marker_details'][$eventField] = [
                    'distancia_luna_km' => $row['__event_distance_luna_km'] === null
                        ? null
                        : (float) $row['__event_distance_luna_km'],
                ];
            }
        }
        $rows[] = $presented;
    }
    $response = [
        'modo' => 'diario',
        'columns' => array_merge(['fecha'], $requestedFields),
        'field_types' => $fieldTypes,
        'field_units' => $fieldUnits,
        'field_scales' => $fieldScales,
        'scale_groups' => astronomyLaboratoryScaleGroups(),
        'available_years' => [
            'min' => ASTRONOMY_LABORATORY_MIN_YEAR,
            'max' => ASTRONOMY_LABORATORY_MAX_YEAR,
        ],
        'rows' => $rows,
    ];
    if ($eventThresholds !== null) {
        $response['event_thresholds'] = $eventThresholds;
    }
    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
    );
} catch (Throwable $exception) {
    error_log('Astronomy laboratory query failed [type=' . get_debug_type($exception) . '].');
    astronomyLaboratoryJsonError(503, 'No se pudieron consultar los datos astronómicos.');
}
