<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use DateTimeImmutable;

final readonly class Tle
{
    public function __construct(
        public string $name,
        public string $line1,
        public string $line2,
        public int $catalogNumber,
        public DateTimeImmutable $epochUtc,
        public float $bstar,
        public float $inclinationRadians,
        public float $rightAscensionAscendingNodeRadians,
        public float $eccentricity,
        public float $argumentPerigeeRadians,
        public float $meanAnomalyRadians,
        public float $meanMotionRadiansPerMinute,
    ) {}
}
