<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use DateTimeImmutable;

final readonly class SatelliteLunarApproachEvent
{
    public function __construct(
        public string $satellite,
        public string $satelliteName,
        public string $classification,
        public DateTimeImmutable $maximum,
        public ?DateTimeImmutable $entry,
        public ?DateTimeImmutable $exit,
        public ?float $durationSeconds,
        public float $minimumSeparationDegrees,
        public float $moonApparentRadiusDegrees,
        public float $edgeMarginDegrees,
        public float $moonAltitudeDegrees,
        public float $moonAzimuthDegrees,
        public float $satelliteAltitudeDegrees,
        public float $satelliteAzimuthDegrees,
        public float $satelliteDistanceKilometers,
        public string $tleLine1,
        public string $tleLine2,
        public DateTimeImmutable $tleEpochUtc,
    ) {}

    /** @return array<string,mixed> */
    public function data(): array
    {
        return [
            'satellite' => $this->satellite,
            'satellite_name' => $this->satelliteName,
            'classification' => $this->classification,
            'maximum' => self::date($this->maximum),
            'entry' => self::date($this->entry),
            'exit' => self::date($this->exit),
            'duration_seconds' => $this->durationSeconds,
            'minimum_separation_degrees' => $this->minimumSeparationDegrees,
            'moon_apparent_radius_degrees' => $this->moonApparentRadiusDegrees,
            'edge_margin_degrees' => $this->edgeMarginDegrees,
            'moon_altitude_degrees' => $this->moonAltitudeDegrees,
            'moon_azimuth_degrees' => $this->moonAzimuthDegrees,
            'satellite_altitude_degrees' => $this->satelliteAltitudeDegrees,
            'satellite_azimuth_degrees' => $this->satelliteAzimuthDegrees,
            'satellite_distance_km' => $this->satelliteDistanceKilometers,
            'tle' => ['line1' => $this->tleLine1, 'line2' => $this->tleLine2, 'epoch_utc' => self::date($this->tleEpochUtc)],
        ];
    }

    private static function date(?DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d\TH:i:s.uP');
    }
}
