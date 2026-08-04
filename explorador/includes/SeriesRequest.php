<?php

declare(strict_types=1);

namespace Explorador;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

final readonly class SeriesRequest
{
    public const MAXIMUM_DEVELOPMENT_DAYS = 73_050;

    /** @param list<string> $fields @param list<string> $phases */
    public function __construct(
        public DateTimeImmutable $from, public DateTimeImmutable $to,
        public float $latitude, public float $longitude, public DateTimeZone $timezone,
        public array $fields, public int $days, public array $phases
    ) {}

    /** @param array<string,mixed> $parameters */
    public static function fromParameters(array $parameters, VariableCatalog $catalog): self
    {
        foreach (['fecha_desde', 'fecha_hasta', 'lat', 'lon', 'timezone', 'campos'] as $name) {
            if (!isset($parameters[$name]) || !is_string($parameters[$name]) || trim($parameters[$name]) === '') {
                throw new InvalidArgumentException('Falta el parámetro ' . $name . '.');
            }
        }
        if (!is_numeric($parameters['lat']) || !is_numeric($parameters['lon'])) {
            throw new InvalidArgumentException('Latitud y longitud deben ser numéricas.');
        }
        $latitude = (float) $parameters['lat'];
        $longitude = (float) $parameters['lon'];
        if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) throw new InvalidArgumentException('Latitud fuera de rango.');
        if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) throw new InvalidArgumentException('Longitud fuera de rango.');
        try {
            $timezone = new DateTimeZone($parameters['timezone']);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Zona horaria inválida.', 0, $exception);
        }
        $from = self::date($parameters['fecha_desde'], $timezone, 'fecha_desde');
        $to = self::date($parameters['fecha_hasta'], $timezone, 'fecha_hasta');
        if ($from > $to) throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
        $days = (int) $from->diff($to)->days + 1;
        if ($days > self::MAXIMUM_DEVELOPMENT_DAYS) {
            throw new InvalidArgumentException('El rango supera la protección técnica temporal de 200 años.');
        }
        $fields = array_values(array_unique(array_filter(array_map('trim', explode(',', $parameters['campos'])), 'strlen')));
        if ($fields === []) throw new InvalidArgumentException('Debe seleccionarse al menos un campo.');
        $catalog->select($fields);
        $phases = [];
        if (isset($parameters['fases']) && (!is_string($parameters['fases']) || trim($parameters['fases']) !== '')) {
            if (!is_string($parameters['fases'])) throw new InvalidArgumentException('El parámetro fases debe ser texto.');
            $phases = array_values(array_unique(array_filter(array_map('trim', explode(',', $parameters['fases'])), 'strlen')));
            foreach ($phases as $phase) {
                if (!array_key_exists($phase, PhaseEventDateProvider::PHASES)) {
                    throw new InvalidArgumentException('Fase lunar no válida.');
                }
            }
        }
        return new self($from, $to, $latitude, $longitude, $timezone, $fields, $days, $phases);
    }

    /** @return array<string,mixed> */
    public function data(): array
    {
        return ['fecha_desde' => $this->from->format('Y-m-d'), 'fecha_hasta' => $this->to->format('Y-m-d'),
            'lat' => $this->latitude, 'lon' => $this->longitude, 'timezone' => $this->timezone->getName(),
            'campos' => $this->fields] + ($this->phases !== [] ? ['fases' => $this->phases] : []);
    }

    private static function date(string $value, DateTimeZone $timezone, string $name): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($name . ' debe usar YYYY-MM-DD y ser una fecha real.');
        }
        return $date;
    }
}
