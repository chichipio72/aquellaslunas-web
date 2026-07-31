<?php

require_once __DIR__ . '/../includes/astronomy-laboratory.php';

function astronomyLaboratoryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$cases = [
    '00:00:00' => 0.0,
    '06:00:00' => 0.25,
    '12:00:00' => 0.5,
    '18:00:00' => 0.75,
    '24:00:00' => 1.0,
    '18:32:00' => 1112 / 1440,
    '20:58:36' => 75516 / 86400,
];
foreach ($cases as $time => $expected) {
    $actual = astronomyLaboratoryTimeFraction($time);
    astronomyLaboratoryAssert(
        $actual !== null && abs($actual - $expected) < 0.000000001,
        'Conversión incorrecta para ' . $time
    );
}
astronomyLaboratoryAssert(astronomyLaboratoryTimeFraction(null) === null, 'NULL no se conservó.');
$moonDurationSql = astronomyLaboratoryMoonHorizonDurationSql();
astronomyLaboratoryAssert(
    str_contains($moonDurationSql, 'TIMESTAMP(`d`.`fecha`, `d`.`hora_salida_luna`)')
        && str_contains($moonDurationSql, 'TIMESTAMP(`p`.`fecha`, `p`.`hora_puesta_luna`)')
        && str_contains($moonDurationSql, '`p`.`fecha` = DATE_ADD(`d`.`fecha`, INTERVAL 1 DAY)')
        && str_contains($moonDurationSql, 'BETWEEN 1 AND 86400'),
    'La duración lunar no empareja datetimes completos con un límite seguro.'
);

$types = astronomyLaboratoryFieldTypes(['hora_salida_luna', 'iluminacion_porc']);
astronomyLaboratoryAssert(
    $types === ['fecha' => 'date', 'hora_salida_luna' => 'time_fraction', 'iluminacion_porc' => 'number'],
    'Los tipos de campo son incorrectos.'
);
astronomyLaboratoryAssert(
    astronomyLaboratoryPhases() === ['Luna nueva', 'Cuarto creciente', 'Luna llena', 'Cuarto menguante'],
    'La lista controlada de fases cambió.'
);
astronomyLaboratoryAssert(
    astronomyLaboratoryFieldTypes(['duracion_dia', 'superluna_llena'])
        === ['fecha' => 'date', 'duracion_dia' => 'time_duration', 'superluna_llena' => 'event_marker'],
    'Los tipos derivados son incorrectos.'
);
astronomyLaboratoryAssert(
    astronomyLaboratoryFieldUnits(['amplitud_salida_luna']) === ['amplitud_salida_luna' => '°'],
    'La amplitud no conserva su unidad.'
);
$groups = astronomyLaboratoryFieldGroups();
astronomyLaboratoryAssert(
    isset($groups['moon']['times']['fields']['hora_salida_luna'])
        && isset($groups['sun']['times']['fields']['hora_salida_sol'])
        && isset($groups['moon']['amplitudes']['fields']['amplitud_puesta_luna'])
        && isset($groups['sun']['other']['fields']['duracion_noche']),
    'La organización Luna/Sol perdió campos o grupos.'
);
astronomyLaboratoryAssert(
    ASTRONOMY_LABORATORY_MIN_YEAR === 1900 && ASTRONOMY_LABORATORY_MAX_YEAR === 2100,
    'El rango centralizado de años cambió.'
);
astronomyLaboratoryAssert(
    !array_key_exists('hora_fase_lunar', astronomyLaboratoryFields()),
    'Hora de la fase lunar todavía está disponible.'
);
$scales = astronomyLaboratoryFieldScales([
    'distancia_luna_km',
    'distancia_sol_km',
    'iluminacion_porc',
    'hora_salida_luna',
    'duracion_dia',
    'amplitud_salida_sol',
]);
astronomyLaboratoryAssert(
    $scales === [
        'distancia_luna_km' => 'lunar_distance_km',
        'distancia_sol_km' => 'solar_distance_km',
        'iluminacion_porc' => 'percentage',
        'hora_salida_luna' => 'time_fraction',
        'duracion_dia' => 'time_duration',
        'amplitud_salida_sol' => 'signed_angle_degrees',
    ],
    'La asignación central de escalas es incorrecta.'
);
astronomyLaboratoryAssert(
    astronomyLaboratorySelectedScaleGroups(array_keys($scales))
        === ['lunar_distance_km', 'solar_distance_km', 'percentage', 'time_fraction', 'time_duration', 'signed_angle_degrees'],
    'No se deduplicaron correctamente los grupos de escala.'
);
$pairs = astronomyLaboratoryLayoutPairs([
    'hora_salida_luna' => 'Salida',
    'hora_puesta_luna' => 'Puesta',
    'diferencia_salida_luna_min' => 'Diferencia de salida',
    'diferencia_puesta_luna_min' => 'Diferencia de puesta',
]);
astronomyLaboratoryAssert(
    $pairs === [
        ['hora_salida_luna', 'hora_puesta_luna'],
        ['diferencia_salida_luna_min', 'diferencia_puesta_luna_min'],
    ],
    'Las parejas conceptuales no se conservaron.'
);
$scaleGroups = astronomyLaboratoryScaleGroups();
astronomyLaboratoryAssert(
    $scaleGroups['percentage']['intervals'] === 8
        && $scaleGroups['percentage']['natural_min'] === 0
        && $scaleGroups['percentage']['natural_max'] === 100
        && $scaleGroups['signed_angle_degrees']['center_zero'] === true
        && $scaleGroups['lunar_distance_km']['steps'] === [500, 1000, 2000, 5000, 10000]
        && $scaleGroups['solar_distance_km']['steps'][0] === 100000,
    'Los metadatos centralizados de escala son incorrectos.'
);

fwrite(STDOUT, "astronomy laboratory tests: ok\n");
