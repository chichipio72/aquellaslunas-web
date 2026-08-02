<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;

final readonly class LunarDay
{
    /** @param list<LunarVisibilityInterval> $visibilityIntervals */
    public function __construct(
        public ?DateTimeImmutable $moonrise,
        public ?DateTimeImmutable $moonset,
        public array $visibilityIntervals,
    ) {}
}
