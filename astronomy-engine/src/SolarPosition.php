<?php

declare(strict_types=1);

namespace AstronomyEngine;

final readonly class SolarPosition
{
    public function __construct(
        public float $altitudeDegrees,
        public float $azimuthDegrees,
    ) {
    }
}
