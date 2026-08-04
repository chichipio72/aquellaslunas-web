<?php

declare(strict_types=1);

namespace Explorador;

use InvalidArgumentException;

final readonly class ExtremaRequest
{
    /** @param array<string,mixed> $definition */
    public function __construct(
        public SeriesRequest $requested,
        public SeriesRequest $calculation,
        public string $variable,
        public string $type,
        public array $definition,
        public ?string $warning
    ) {}

    /** @param array<string,mixed> $parameters */
    public static function fromParameters(array $parameters, VariableCatalog $catalog): self
    {
        if (($parameters['modo'] ?? null) !== 'extremos') {
            throw new InvalidArgumentException('Modo de análisis inválido.');
        }
        if (!isset($parameters['variable']) || !is_string($parameters['variable']) || trim($parameters['variable']) === '') {
            throw new InvalidArgumentException('Falta el parámetro variable.');
        }
        $variable = trim($parameters['variable']);
        $definition = $catalog->get($variable);
        if (($definition['extrema_supported'] ?? false) !== true) {
            throw new InvalidArgumentException('La variable seleccionada no admite extremos locales.');
        }
        $type = $parameters['tipo_extremo'] ?? null;
        if (!is_string($type) || !in_array($type, ['maximo', 'minimo', 'ambos'], true)) {
            throw new InvalidArgumentException('Tipo de extremo inválido.');
        }
        if (isset($parameters['fases']) && (!is_string($parameters['fases']) || trim($parameters['fases']) !== '')) {
            throw new InvalidArgumentException('Extremos locales requiere una serie diaria continua y no admite fases.');
        }
        if (isset($parameters['campos']) && !is_string($parameters['campos'])) {
            throw new InvalidArgumentException('Extremos locales admite una sola variable.');
        }
        if (isset($parameters['campos']) && is_string($parameters['campos']) && trim($parameters['campos']) !== '') {
            $fields = array_values(array_unique(array_filter(array_map('trim', explode(',', $parameters['campos'])), 'strlen')));
            if ($fields !== [$variable]) throw new InvalidArgumentException('Extremos locales admite una sola variable.');
        }
        $dailyParameters = $parameters;
        $dailyParameters['campos'] = $variable;
        unset($dailyParameters['fases']);
        $requested = SeriesRequest::fromParameters($dailyParameters, $catalog);
        $needsDerivedHistory = str_ends_with($variable, '_daily_difference');
        $daysBefore = $needsDerivedHistory ? 2 : 1;
        $calculationFrom = $requested->from->modify('-' . $daysBefore . ' days');
        $calculationTo = $requested->to->modify('+1 day');
        $calculation = new SeriesRequest($calculationFrom, $calculationTo, $requested->latitude,
            $requested->longitude, $requested->timezone, [$variable], $requested->days + $daysBefore + 1, []);
        $minimum = (int) $definition['extrema_minimum_years'];
        $recommended = (int) $definition['extrema_recommended_years'];
        $warning = null;
        if ($requested->to < $requested->from->modify('+' . $minimum . ' years')->modify('-1 day')) {
            $warning = 'Rango corto: para esta variable se sugieren al menos ' . $minimum . ' años.';
        } elseif ($requested->to < $requested->from->modify('+' . $recommended . ' years')->modify('-1 day')) {
            $warning = 'Para observar mejor el patrón se recomienda un rango de ' . $recommended . ' años.';
        }
        return new self($requested, $calculation, $variable, $type, $definition, $warning);
    }
}
