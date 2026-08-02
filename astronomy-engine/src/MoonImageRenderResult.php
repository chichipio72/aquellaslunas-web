<?php

declare(strict_types=1);

namespace AstronomyEngine;

final readonly class MoonImageRenderResult
{
    /** @param array<string,mixed> $metadata @param array<string,float> $timings */
    public function __construct(
        public string $png,
        public string $path,
        public string $cacheKey,
        public bool $cacheHit,
        public array $metadata,
        public array $timings,
    ) {}
}
