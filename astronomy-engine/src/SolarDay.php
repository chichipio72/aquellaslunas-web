<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;

final readonly class SolarDay
{
    /** @param array<string, SolarPeriod> $periods */
    public function __construct(
        public ?DateTimeImmutable $sunrise,
        public ?DateTimeImmutable $sunset,
        public ?DateTimeImmutable $solarNoon,
        public array $periods,
    ) {
    }
}
