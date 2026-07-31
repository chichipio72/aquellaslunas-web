<?php

const ASTRONOMY_LABORATORY_MIN_YEAR = 1900;
const ASTRONOMY_LABORATORY_MAX_YEAR = 2100;
const ASTRONOMY_LABORATORY_MAX_MOON_HORIZON_SECONDS = 86400;

function astronomyLaboratoryMoonHorizonDurationSql(): string
{
    $rise = 'TIMESTAMP(`d`.`fecha`, `d`.`hora_salida_luna`)';
    $sameDaySet = 'TIMESTAMP(`d`.`fecha`, `d`.`hora_puesta_luna`)';
    $followingDaySet = '(SELECT TIMESTAMP(`p`.`fecha`, `p`.`hora_puesta_luna`)'
        . ' FROM `datos_astronomicos` AS `p`'
        . ' WHERE `p`.`fecha` = DATE_ADD(`d`.`fecha`, INTERVAL 1 DAY)'
        . ' AND `p`.`hora_puesta_luna` IS NOT NULL LIMIT 1)';
    $nextSet = '(CASE WHEN `d`.`hora_puesta_luna` IS NOT NULL'
        . ' AND ' . $sameDaySet . ' > ' . $rise
        . ' THEN ' . $sameDaySet . ' ELSE ' . $followingDaySet . ' END)';
    $duration = 'TIMESTAMPDIFF(SECOND, ' . $rise . ', ' . $nextSet . ')';

    return 'CASE WHEN `d`.`hora_salida_luna` IS NULL THEN NULL'
        . ' WHEN ' . $duration . ' BETWEEN 1 AND '
        . ASTRONOMY_LABORATORY_MAX_MOON_HORIZON_SECONDS
        . ' THEN CAST(' . $duration . ' AS DECIMAL(20,10)) / 86400 ELSE NULL END';
}

function astronomyLaboratoryFieldDefinitions(): array
{
    return [
        'hora_salida_luna' => ['label' => 'Salida de la Luna', 'short_label' => 'Salida', 'type' => 'time_fraction', 'scale_group' => 'time_fraction', 'body' => 'moon', 'group' => 'times', 'sql' => '`hora_salida_luna`'],
        'hora_puesta_luna' => ['label' => 'Puesta de la Luna', 'short_label' => 'Puesta', 'type' => 'time_fraction', 'scale_group' => 'time_fraction', 'body' => 'moon', 'group' => 'times', 'sql' => '`hora_puesta_luna`'],
        'hora_salida_sol' => ['label' => 'Salida del Sol', 'short_label' => 'Salida', 'type' => 'time_fraction', 'scale_group' => 'time_fraction', 'body' => 'sun', 'group' => 'times', 'sql' => '`hora_salida_sol`'],
        'hora_puesta_sol' => ['label' => 'Puesta del Sol', 'short_label' => 'Puesta', 'type' => 'time_fraction', 'scale_group' => 'time_fraction', 'body' => 'sun', 'group' => 'times', 'sql' => '`hora_puesta_sol`'],

        'diferencia_salida_luna_min' => ['label' => 'Diferencia de salida de la Luna (min)', 'short_label' => 'Diferencia de salida', 'type' => 'number', 'scale_group' => 'difference_minutes', 'unit' => ' min', 'body' => 'moon', 'group' => 'differences', 'sql' => '`diferencia_salida_luna_min`'],
        'diferencia_puesta_luna_min' => ['label' => 'Diferencia de puesta de la Luna (min)', 'short_label' => 'Diferencia de puesta', 'type' => 'number', 'scale_group' => 'difference_minutes', 'unit' => ' min', 'body' => 'moon', 'group' => 'differences', 'sql' => '`diferencia_puesta_luna_min`'],
        'diferencia_salida_sol_min' => ['label' => 'Diferencia de salida del Sol (min)', 'short_label' => 'Diferencia de salida', 'type' => 'number', 'scale_group' => 'difference_minutes', 'unit' => ' min', 'body' => 'sun', 'group' => 'differences', 'sql' => '`diferencia_salida_sol_min`'],
        'diferencia_puesta_sol_min' => ['label' => 'Diferencia de puesta del Sol (min)', 'short_label' => 'Diferencia de puesta', 'type' => 'number', 'scale_group' => 'difference_minutes', 'unit' => ' min', 'body' => 'sun', 'group' => 'differences', 'sql' => '`diferencia_puesta_sol_min`'],

        'amplitud_salida_luna' => ['label' => 'Amplitud de salida de la Luna (°)', 'short_label' => 'Amplitud de salida', 'type' => 'number', 'scale_group' => 'signed_angle_degrees', 'unit' => '°', 'body' => 'moon', 'group' => 'amplitudes', 'sql' => '(`azimut_salida_luna` - 90)'],
        'amplitud_puesta_luna' => ['label' => 'Amplitud de puesta de la Luna (°)', 'short_label' => 'Amplitud de puesta', 'type' => 'number', 'scale_group' => 'signed_angle_degrees', 'unit' => '°', 'body' => 'moon', 'group' => 'amplitudes', 'sql' => '(`azimut_puesta_luna` - 270)'],
        'amplitud_salida_sol' => ['label' => 'Amplitud de salida del Sol (°)', 'short_label' => 'Amplitud de salida', 'type' => 'number', 'scale_group' => 'signed_angle_degrees', 'unit' => '°', 'body' => 'sun', 'group' => 'amplitudes', 'sql' => '(`azimut_salida_sol` - 90)'],
        'amplitud_puesta_sol' => ['label' => 'Amplitud de puesta del Sol (°)', 'short_label' => 'Amplitud de puesta', 'type' => 'number', 'scale_group' => 'signed_angle_degrees', 'unit' => '°', 'body' => 'sun', 'group' => 'amplitudes', 'sql' => '(`azimut_puesta_sol` - 270)'],

        'azimut_salida_luna' => ['label' => 'Azimut de salida de la Luna (°)', 'short_label' => 'Azimut de salida', 'type' => 'number', 'scale_group' => 'angle_degrees', 'unit' => '°', 'body' => 'moon', 'group' => 'azimuths', 'sql' => '`azimut_salida_luna`'],
        'azimut_puesta_luna' => ['label' => 'Azimut de puesta de la Luna (°)', 'short_label' => 'Azimut de puesta', 'type' => 'number', 'scale_group' => 'angle_degrees', 'unit' => '°', 'body' => 'moon', 'group' => 'azimuths', 'sql' => '`azimut_puesta_luna`'],
        'azimut_salida_sol' => ['label' => 'Azimut de salida del Sol (°)', 'short_label' => 'Azimut de salida', 'type' => 'number', 'scale_group' => 'angle_degrees', 'unit' => '°', 'body' => 'sun', 'group' => 'azimuths', 'sql' => '`azimut_salida_sol`'],
        'azimut_puesta_sol' => ['label' => 'Azimut de puesta del Sol (°)', 'short_label' => 'Azimut de puesta', 'type' => 'number', 'scale_group' => 'angle_degrees', 'unit' => '°', 'body' => 'sun', 'group' => 'azimuths', 'sql' => '`azimut_puesta_sol`'],

        'distancia_luna_km' => ['label' => 'Distancia de la Luna (km)', 'short_label' => 'Distancia', 'type' => 'number', 'scale_group' => 'lunar_distance_km', 'unit' => ' km', 'body' => 'moon', 'group' => 'other', 'sql' => '`distancia_luna_km`'],
        'iluminacion_porc' => ['label' => 'Iluminación lunar (%)', 'short_label' => 'Iluminación', 'type' => 'number', 'scale_group' => 'percentage', 'unit' => '%', 'body' => 'moon', 'group' => 'other', 'sql' => '`iluminacion_porc`'],
        'dia_ciclo_lunar' => ['label' => 'Día del ciclo lunar', 'short_label' => 'Día del ciclo lunar', 'type' => 'number', 'scale_group' => 'cycle_days', 'body' => 'moon', 'group' => 'other', 'sql' => '`dia_ciclo_lunar`'],
        'latitud_ecliptica_luna' => ['label' => 'Latitud eclíptica de la Luna (°)', 'short_label' => 'Latitud eclíptica', 'type' => 'number', 'scale_group' => 'signed_angle_degrees', 'unit' => '°', 'body' => 'moon', 'group' => 'other', 'sql' => '`latitud_ecliptica_luna`'],
        'tiempo_luna_sobre_horizonte' => ['label' => 'Tiempo de la Luna sobre el horizonte', 'short_label' => 'Tiempo sobre el horizonte', 'type' => 'time_duration', 'scale_group' => 'time_duration', 'body' => 'moon', 'group' => 'other', 'sql' => astronomyLaboratoryMoonHorizonDurationSql()],
        'superluna_llena' => ['label' => 'Superluna llena', 'short_label' => 'Superluna llena', 'type' => 'event_marker', 'scale_group' => null, 'body' => 'moon', 'group' => 'other', 'event' => 'supermoon'],
        'miniluna_llena' => ['label' => 'Miniluna llena', 'short_label' => 'Miniluna llena', 'type' => 'event_marker', 'scale_group' => null, 'body' => 'moon', 'group' => 'other', 'event' => 'minimoon'],

        'distancia_sol_km' => ['label' => 'Distancia del Sol (km)', 'short_label' => 'Distancia', 'type' => 'number', 'scale_group' => 'solar_distance_km', 'unit' => ' km', 'body' => 'sun', 'group' => 'other', 'sql' => '`distancia_sol_km`'],
        'duracion_dia' => ['label' => 'Duración del día', 'short_label' => 'Duración del día', 'type' => 'time_duration', 'scale_group' => 'time_duration', 'body' => 'sun', 'group' => 'other', 'sql' => 'CASE WHEN `hora_salida_sol` IS NULL OR `hora_puesta_sol` IS NULL THEN NULL ELSE CAST(MOD(TIME_TO_SEC(`hora_puesta_sol`) - TIME_TO_SEC(`hora_salida_sol`) + 86400, 86400) AS DECIMAL(20,10)) / 86400 END'],
        'duracion_noche' => ['label' => 'Duración de la noche', 'short_label' => 'Duración de la noche', 'type' => 'time_duration', 'scale_group' => 'time_duration', 'body' => 'sun', 'group' => 'other', 'sql' => 'CASE WHEN `hora_salida_sol` IS NULL OR `hora_puesta_sol` IS NULL THEN NULL ELSE 1 - (CAST(MOD(TIME_TO_SEC(`hora_puesta_sol`) - TIME_TO_SEC(`hora_salida_sol`) + 86400, 86400) AS DECIMAL(20,10)) / 86400) END'],
        'fraccion_anio_tropico' => ['label' => 'Fracción del año trópico', 'short_label' => 'Fracción del año trópico', 'type' => 'number', 'scale_group' => 'fraction', 'body' => 'sun', 'group' => 'other', 'sql' => '`fraccion_anio_tropico`'],
        'angulo_nodo_sol' => ['label' => 'Ángulo del nodo solar (°)', 'short_label' => 'Ángulo del nodo', 'type' => 'number', 'scale_group' => 'angle_degrees', 'unit' => '°', 'body' => 'sun', 'group' => 'other', 'sql' => '`angulo_nodo_sol`'],
    ];
}

function astronomyLaboratoryFields(): array
{
    return array_map(static fn(array $definition): string => $definition['label'], astronomyLaboratoryFieldDefinitions());
}

function astronomyLaboratoryFieldTypes(array $fields): array
{
    $definitions = astronomyLaboratoryFieldDefinitions();
    $types = ['fecha' => 'date'];
    foreach ($fields as $field) {
        if (isset($definitions[$field])) {
            $types[$field] = $definitions[$field]['type'];
        }
    }
    return $types;
}

function astronomyLaboratoryFieldUnits(array $fields): array
{
    $definitions = astronomyLaboratoryFieldDefinitions();
    $units = [];
    foreach ($fields as $field) {
        if (isset($definitions[$field]['unit'])) {
            $units[$field] = $definitions[$field]['unit'];
        }
    }
    return $units;
}

function astronomyLaboratoryScaleGroups(): array
{
    return [
        'lunar_distance_km' => ['label' => 'Distancia lunar (km)', 'unit' => 'km', 'format' => 'integer', 'center_zero' => false, 'steps' => [500, 1000, 2000, 5000, 10000], 'intervals' => 8],
        'solar_distance_km' => ['label' => 'Distancia Tierra–Sol (km)', 'unit' => 'km', 'format' => 'integer', 'center_zero' => false, 'steps' => [100000, 250000, 500000, 1000000, 2000000], 'intervals' => 8],
        'percentage' => ['label' => 'Porcentaje (%)', 'unit' => '%', 'format' => 'number', 'center_zero' => false, 'steps' => [5, 10, 20, 25], 'natural_min' => 0, 'natural_max' => 100, 'intervals' => 8],
        'time_fraction' => ['label' => 'Hora', 'unit' => 'hora', 'format' => 'HH:MM', 'center_zero' => false, 'steps' => [0.0104166666666667, 0.0208333333333333, 0.0416666666666667, 0.0833333333333333, 0.125, 0.25], 'natural_min' => 0, 'natural_max' => 1, 'intervals' => 8],
        'time_duration' => ['label' => 'Duración', 'unit' => 'hora', 'format' => 'HH:MM', 'center_zero' => false, 'steps' => [0.0104166666666667, 0.0208333333333333, 0.0416666666666667, 0.0833333333333333, 0.125, 0.25], 'natural_min' => 0, 'natural_max' => 1, 'intervals' => 8],
        'angle_degrees' => ['label' => 'Ángulo (°)', 'unit' => '°', 'format' => 'number', 'center_zero' => false, 'steps' => [1, 2, 5, 10, 15, 20, 30, 45], 'natural_min' => 0, 'natural_max' => 360, 'intervals' => 8],
        'signed_angle_degrees' => ['label' => 'Ángulo respecto de cero (°)', 'unit' => '°', 'format' => 'number', 'center_zero' => true, 'steps' => [1, 2, 5, 10, 15, 20, 30, 45], 'intervals' => 8],
        'difference_minutes' => ['label' => 'Diferencia (min)', 'unit' => 'min', 'format' => 'number', 'center_zero' => true, 'steps' => [1, 2, 5, 10, 15, 30, 60], 'intervals' => 8],
        'cycle_days' => ['label' => 'Días', 'unit' => 'días', 'format' => 'number', 'center_zero' => false, 'steps' => [0.25, 0.5, 1, 2, 5, 10], 'natural_min' => 0, 'intervals' => 8],
        'fraction' => ['label' => 'Fracción', 'unit' => 'fracción', 'format' => 'number', 'center_zero' => false, 'steps' => [0.01, 0.02, 0.05, 0.1, 0.2, 0.25], 'natural_min' => 0, 'natural_max' => 1, 'intervals' => 8],
    ];
}

function astronomyLaboratoryFieldScales(array $fields): array
{
    $definitions = astronomyLaboratoryFieldDefinitions();
    $scales = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $definitions)) {
            $scales[$field] = $definitions[$field]['scale_group'];
        }
    }
    return $scales;
}

function astronomyLaboratorySelectedScaleGroups(array $fields): array
{
    return array_values(array_unique(array_filter(
        astronomyLaboratoryFieldScales($fields),
        static fn(mixed $group): bool => is_string($group) && $group !== ''
    )));
}

function astronomyLaboratoryLayoutPairs(array $fields): array
{
    $conceptualPairs = [
        'hora_salida_luna' => 'hora_puesta_luna',
        'hora_salida_sol' => 'hora_puesta_sol',
        'diferencia_salida_luna_min' => 'diferencia_puesta_luna_min',
        'diferencia_salida_sol_min' => 'diferencia_puesta_sol_min',
        'amplitud_salida_luna' => 'amplitud_puesta_luna',
        'amplitud_salida_sol' => 'amplitud_puesta_sol',
        'azimut_salida_luna' => 'azimut_puesta_luna',
        'azimut_salida_sol' => 'azimut_puesta_sol',
        'duracion_dia' => 'duracion_noche',
    ];
    $reversePairs = array_flip($conceptualPairs);
    $pairs = [];
    $pendingSingles = [];
    foreach (array_keys($fields) as $field) {
        if (isset($reversePairs[$field])) {
            continue;
        }
        if (isset($conceptualPairs[$field], $fields[$conceptualPairs[$field]])) {
            if ($pendingSingles !== []) {
                $pairs[] = $pendingSingles;
                $pendingSingles = [];
            }
            $pairs[] = [$field, $conceptualPairs[$field]];
            continue;
        }
        $pendingSingles[] = $field;
        if (count($pendingSingles) === 2) {
            $pairs[] = $pendingSingles;
            $pendingSingles = [];
        }
    }
    if ($pendingSingles !== []) {
        $pairs[] = $pendingSingles;
    }
    return $pairs;
}

function astronomyLaboratoryFieldGroups(): array
{
    $groups = [
        'times' => 'Horarios: salidas y puestas',
        'differences' => 'Diferencias diarias de salidas y puestas',
        'amplitudes' => 'Amplitudes de salidas y puestas',
        'azimuths' => 'Azimutes de salidas y puestas',
        'other' => 'Distancias y otras variables',
    ];
    $result = ['moon' => [], 'sun' => []];
    foreach ($result as $body => $_) {
        foreach ($groups as $group => $label) {
            $result[$body][$group] = ['label' => $label, 'fields' => []];
        }
    }
    foreach (astronomyLaboratoryFieldDefinitions() as $field => $definition) {
        $result[$definition['body']][$definition['group']]['fields'][$field] = $definition['short_label'];
    }
    return $result;
}

function astronomyLaboratoryPhases(): array
{
    return ['Luna nueva', 'Cuarto creciente', 'Luna llena', 'Cuarto menguante'];
}

function astronomyLaboratoryTimeFraction(?string $time): ?float
{
    if ($time === null) {
        return null;
    }
    if (preg_match('/^(\d{1,3}):([0-5]\d):([0-5]\d)$/', $time, $parts) !== 1) {
        throw new UnexpectedValueException('Formato horario inesperado en la base de datos.');
    }
    return (((int) $parts[1] * 3600) + ((int) $parts[2] * 60) + (int) $parts[3]) / 86400;
}

function astronomyLaboratoryFullMoonDistanceThresholds(PDO $connection): array
{
    $statement = $connection->prepare(
        'SELECT `distancia_luna_km` FROM `datos_astronomicos`'
        . ' WHERE `fase_lunar` = :phase AND `distancia_luna_km` IS NOT NULL'
        . ' AND `fecha` BETWEEN :from AND :to ORDER BY `distancia_luna_km` ASC'
    );
    $statement->execute([
        'phase' => 'Luna llena',
        'from' => ASTRONOMY_LABORATORY_MIN_YEAR . '-01-01',
        'to' => ASTRONOMY_LABORATORY_MAX_YEAR . '-12-31',
    ]);
    $distances = array_map('floatval', $statement->fetchAll(PDO::FETCH_COLUMN));
    if ($distances === []) {
        throw new RuntimeException('No hay distancias de lunas llenas para calcular los indicadores.');
    }
    $lastIndex = count($distances) - 1;
    return [
        'supermoon_max_km' => $distances[(int) floor($lastIndex * 0.10)],
        'minimoon_min_km' => $distances[(int) ceil($lastIndex * 0.90)],
        'population' => count($distances),
        'from_year' => ASTRONOMY_LABORATORY_MIN_YEAR,
        'to_year' => ASTRONOMY_LABORATORY_MAX_YEAR,
    ];
}
