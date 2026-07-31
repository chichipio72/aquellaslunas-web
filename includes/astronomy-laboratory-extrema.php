<?php

require_once __DIR__ . '/astronomy-laboratory.php';

function astronomyLaboratoryExtremaVariables(): array
{
    $catalog = [
        'distancia_luna_km' => ['analysis_label' => 'distancia lunar', 'minimum_years' => 2, 'recommended_years' => 5, 'maximum_label' => 'Apogeos', 'minimum_label' => 'Perigeos'],
        'amplitud_salida_luna' => ['analysis_label' => 'amplitud de salida lunar', 'minimum_years' => 2, 'recommended_years' => 5],
        'amplitud_puesta_luna' => ['analysis_label' => 'amplitud de puesta lunar', 'minimum_years' => 2, 'recommended_years' => 5],
        'tiempo_luna_sobre_horizonte' => ['analysis_label' => 'tiempo lunar sobre el horizonte', 'minimum_years' => 2, 'recommended_years' => 5],
        'diferencia_salida_luna_min' => ['analysis_label' => 'diferencia diaria de salida lunar', 'minimum_years' => 2, 'recommended_years' => 5],
        'duracion_dia' => ['analysis_label' => 'duración del día', 'minimum_years' => 5, 'recommended_years' => 10],
        'amplitud_salida_sol' => ['analysis_label' => 'amplitud de salida solar', 'minimum_years' => 5, 'recommended_years' => 10],
        'amplitud_puesta_sol' => ['analysis_label' => 'amplitud de puesta solar', 'minimum_years' => 5, 'recommended_years' => 10],
    ];
    $definitions = astronomyLaboratoryFieldDefinitions();
    foreach ($catalog as $field => &$metadata) {
        if (!isset($definitions[$field]['sql'])) {
            throw new LogicException('El catálogo de extremos contiene una variable sin expresión controlada.');
        }
        $metadata += [
            'label' => $definitions[$field]['label'],
            'body' => $definitions[$field]['body'],
            'type' => $definitions[$field]['type'],
            'unit' => trim((string) ($definitions[$field]['unit'] ?? '')),
            'scale_group' => $definitions[$field]['scale_group'],
            'sql' => $definitions[$field]['sql'],
            'maximum_label' => 'Máximos de ' . $metadata['analysis_label'],
            'minimum_label' => 'Mínimos de ' . $metadata['analysis_label'],
        ];
    }
    unset($metadata);
    return $catalog;
}

function astronomyLaboratoryExtremaRangeAssessment(
    DateTimeImmutable $from,
    DateTimeImmutable $to,
    array $variable
): array {
    $minimumYears = (int) $variable['minimum_years'];
    $recommendedYears = (int) $variable['recommended_years'];
    $meetsMinimum = $to >= $from->modify('+' . $minimumYears . ' years');
    $meetsRecommendation = $to >= $from->modify('+' . $recommendedYears . ' years');
    return [
        'meets_minimum' => $meetsMinimum,
        'meets_recommendation' => $meetsRecommendation,
        'minimum_years' => $minimumYears,
        'recommended_years' => $recommendedYears,
    ];
}

function queryAstronomyLaboratoryExtrema(
    PDO $connection,
    string $variable,
    DateTimeImmutable $from,
    DateTimeImmutable $to,
    string $extremeType
): array {
    $variables = astronomyLaboratoryExtremaVariables();
    if (!isset($variables[$variable])) {
        throw new InvalidArgumentException('Variable de extremos no permitida.');
    }
    if (!in_array($extremeType, ['maximo', 'minimo', 'ambos'], true)) {
        throw new InvalidArgumentException('Tipo de extremo no permitido.');
    }
    $expression = $variables[$variable]['sql'];
    $typePredicate = match ($extremeType) {
        'maximo' => "`tipo_extremo` = 'maximo'",
        'minimo' => "`tipo_extremo` = 'minimo'",
        default => '`tipo_extremo` IS NOT NULL',
    };
    $sql = 'WITH `calculada` AS ('
        . ' SELECT `d`.`fecha`, ' . $expression . ' AS `valor`'
        . ' FROM `datos_astronomicos` AS `d`'
        . ' WHERE `d`.`fecha` BETWEEN :fecha_desde AND :fecha_hasta'
        . '), `base` AS ('
        . ' SELECT `fecha`, `valor` FROM `calculada` WHERE `valor` IS NOT NULL'
        . '), `serie` AS ('
        . ' SELECT `fecha`, `valor`,'
        . ' LAG(`fecha`) OVER (ORDER BY `fecha`) AS `fecha_anterior`,'
        . ' LEAD(`fecha`) OVER (ORDER BY `fecha`) AS `fecha_siguiente`,'
        . ' LAG(`valor`) OVER (ORDER BY `fecha`) AS `valor_anterior`,'
        . ' LEAD(`valor`) OVER (ORDER BY `fecha`) AS `valor_siguiente`'
        . ' FROM `base`'
        . '), `clasificada` AS ('
        . ' SELECT `fecha`, `valor`, CASE'
        . ' WHEN DATEDIFF(`fecha`, `fecha_anterior`) = 1'
        . ' AND DATEDIFF(`fecha_siguiente`, `fecha`) = 1'
        . " AND `valor` > `valor_anterior` AND `valor` >= `valor_siguiente` THEN 'maximo'"
        . ' WHEN DATEDIFF(`fecha`, `fecha_anterior`) = 1'
        . ' AND DATEDIFF(`fecha_siguiente`, `fecha`) = 1'
        . " AND `valor` < `valor_anterior` AND `valor` <= `valor_siguiente` THEN 'minimo'"
        . ' END AS `tipo_extremo` FROM `serie`'
        . ') SELECT `fecha`, `valor`, `tipo_extremo` FROM `clasificada`'
        . ' WHERE ' . $typePredicate . ' ORDER BY `fecha` ASC';

    $statement = $connection->prepare($sql);
    $statement->execute([
        'fecha_desde' => $from->format('Y-m-d'),
        'fecha_hasta' => $to->format('Y-m-d'),
    ]);
    $series = ['maximos' => [], 'minimos' => []];
    while ($row = $statement->fetch()) {
        $series[$row['tipo_extremo'] === 'maximo' ? 'maximos' : 'minimos'][] = [
            'fecha' => (string) $row['fecha'],
            'valor' => (float) $row['valor'],
        ];
    }
    return $series;
}
