<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;

final readonly class SolarPeriod
{
    public ?int $durationSeconds;

    public function __construct(
        public ?DateTimeImmutable $start,
        public ?DateTimeImmutable $end,
    ) {
        $this->durationSeconds = $start !== null && $end !== null && $end >= $start
            ? (int) round((float) $end->format('U.u') - (float) $start->format('U.u'))
            : null;
    }
}
