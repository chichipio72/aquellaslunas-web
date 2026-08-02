<?php

declare(strict_types=1);

namespace AstronomyEngine;

final readonly class LunarPosition
{
    public function __construct(
        public float $altitudeDegrees,
        public float $azimuthDegrees,
        public float $distanceKilometers,
        public float $topocentricDistanceKilometers,
        public float $cycleAngleDegrees,
        public float $illuminationFraction,
        public float $ageDays,
        public string $phaseName,
        public float $eclipticLongitudeDegrees,
        public float $eclipticLatitudeDegrees,
        public float $rightAscensionDegrees,
        public float $declinationDegrees,
    ) {
    }
}
