<?php

declare(strict_types=1);

namespace Explorador;

final class ExtremaResponse
{
    /** @param array{maximos:list<array{fecha:string,valor:float}>,minimos:list<array{fecha:string,valor:float}>} $detected @param array<string,mixed> $metrics @return array<string,mixed> */
    public static function build(ExtremaRequest $request, array $detected, array $metrics): array
    {
        $maxima = $request->type === 'minimo' ? [] : $detected['maximos'];
        $minima = $request->type === 'maximo' ? [] : $detected['minimos'];
        return [
            'modo' => 'extremos', 'variable' => $request->variable,
            'variable_label' => $request->definition['extrema_label'],
            'field_type' => $request->definition['type'], 'scale_group' => $request->definition['scaleGroup'],
            'unit' => $request->definition['unit'], 'precision' => $request->definition['precision'],
            'request' => ['fecha_desde' => $request->requested->from->format('Y-m-d'),
                'fecha_hasta' => $request->requested->to->format('Y-m-d'),
                'lat' => $request->requested->latitude, 'lon' => $request->requested->longitude,
                'timezone' => $request->requested->timezone->getName(), 'tipo_extremo' => $request->type],
            'series_labels' => ['maximos' => $request->definition['extrema_maximum_label'],
                'minimos' => $request->definition['extrema_minimum_label']],
            'series' => ['maximos' => $maxima, 'minimos' => $minima],
            'counts' => ['maximos' => count($maxima), 'minimos' => count($minima)],
            'metrics' => $metrics, 'warning' => $request->warning,
            'resolution' => 'daily_samples',
        ];
    }
}
