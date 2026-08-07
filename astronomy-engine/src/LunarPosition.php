<?php

declare(strict_types=1);

namespace AstronomyEngine;

final readonly class LunarPosition
{
    public const RADIUS_KILOMETERS = 1737.4;

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
        public float $solarElongationDegrees = 0.0,
    ) {
    }

    public function geocentricApparentDiameterArcminutes(): float
    {
        return rad2deg(2.0 * atan(self::RADIUS_KILOMETERS / $this->distanceKilometers)) * 60.0;
    }
}
