<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

final readonly class SatelliteTopocentricPosition
{
    public function __construct(
        public float $altitudeDegrees,
        public float $azimuthDegrees,
        public float $distanceKilometers,
    ) {}
}
