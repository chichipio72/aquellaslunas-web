<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use DateTimeImmutable;

final readonly class SatelliteTransitEvent
{
    /** @param list<string> $warnings */
    public function __construct(
        public string $targetBody,
        public string $satellite,
        public string $satelliteName,
        public string $classification,
        public DateTimeImmutable $maximum,
        public ?DateTimeImmutable $entry,
        public ?DateTimeImmutable $exit,
        public ?float $durationSeconds,
        public float $minimumSeparationDegrees,
        public float $targetApparentRadiusDegrees,
        public float $edgeMarginDegrees,
        public float $targetAltitudeDegrees,
        public float $targetAzimuthDegrees,
        public float $satelliteAltitudeDegrees,
        public float $satelliteAzimuthDegrees,
        public float $satelliteDistanceKilometers,
        public string $tleLine1,
        public string $tleLine2,
        public DateTimeImmutable $tleEpochUtc,
        public array $warnings = [],
    ) {}

    /** @return array<string,mixed> */
    public function data(): array
    {
        return [
            'target_body' => $this->targetBody,
            'satellite' => $this->satellite,
            'satellite_name' => $this->satelliteName,
            'classification' => $this->classification,
            'maximum' => self::date($this->maximum),
            'entry' => self::date($this->entry),
            'exit' => self::date($this->exit),
            'duration_seconds' => $this->durationSeconds,
            'minimum_separation_degrees' => $this->minimumSeparationDegrees,
            'target_apparent_radius_degrees' => $this->targetApparentRadiusDegrees,
            'edge_margin_degrees' => $this->edgeMarginDegrees,
            'target_altitude_degrees' => $this->targetAltitudeDegrees,
            'target_azimuth_degrees' => $this->targetAzimuthDegrees,
            'satellite_altitude_degrees' => $this->satelliteAltitudeDegrees,
            'satellite_azimuth_degrees' => $this->satelliteAzimuthDegrees,
            'satellite_distance_km' => $this->satelliteDistanceKilometers,
            'tle' => ['line1' => $this->tleLine1, 'line2' => $this->tleLine2, 'epoch_utc' => self::date($this->tleEpochUtc)],
            'warnings' => $this->warnings,
        ];
    }

    private static function date(?DateTimeImmutable $date): ?string { return $date?->format('Y-m-d\TH:i:s.uP'); }
}
