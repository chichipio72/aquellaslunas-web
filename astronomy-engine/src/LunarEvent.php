<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;

final readonly class LunarEvent
{
    /** @param array<string,float|string|null> $data */
    public function __construct(
        public string $group,
        public string $type,
        public DateTimeImmutable $dateTime,
        public array $data,
        public PrecisionProfile $precisionProfile,
    ) {}
}
