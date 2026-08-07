<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use DateTimeImmutable;

final readonly class TemeState
{
    /** @param array{float,float,float} $positionKilometers @param array{float,float,float} $velocityKilometersPerSecond */
    public function __construct(
        public DateTimeImmutable $dateTimeUtc,
        public array $positionKilometers,
        public array $velocityKilometersPerSecond,
        public int $errorCode = 0,
    ) {}

    public function geocentricDistanceKilometers(): float
    {
        return sqrt($this->positionKilometers[0] ** 2 + $this->positionKilometers[1] ** 2 + $this->positionKilometers[2] ** 2);
    }
}
