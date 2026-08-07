<?php

declare(strict_types=1);

namespace AstronomyEngine;

final readonly class SolarPosition
{
    public const ASTRONOMICAL_UNIT_KILOMETERS = 149_597_870.7;

    public function __construct(
        public float $altitudeDegrees,
        public float $azimuthDegrees,
        public float $earthSunDistanceAu = 1.0,
        public float $apparentRadiusDegrees = 0.2666,
        public float $equationOfTimeMinutes = 0.0,
    ) {
    }

    public function earthSunDistanceKilometers(): float
    {
        return $this->earthSunDistanceAu * self::ASTRONOMICAL_UNIT_KILOMETERS;
    }
}
