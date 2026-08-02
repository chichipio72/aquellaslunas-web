<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;

interface SolarPositionCalculator
{
    public function calculate(
        DateTimeImmutable $dateTime,
        float $latitudeDegrees,
        float $longitudeDegrees,
        float $elevationMeters = 0.0,
    ): SolarPosition;
}
