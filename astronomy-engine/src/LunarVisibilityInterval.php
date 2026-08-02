<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;

final readonly class LunarVisibilityInterval
{
    public int $durationSeconds;
    public function __construct(public DateTimeImmutable $start, public DateTimeImmutable $end)
    {
        $this->durationSeconds=(int)round((float)$end->format('U.u')-(float)$start->format('U.u'));
    }
}
