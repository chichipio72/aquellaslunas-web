<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

final readonly class SatelliteLunarTransitServiceResult
{
    /** @param array<string,ResolvedTle> $tleMetadata @param list<string> $warnings @param array<string,int|float> $metrics */
    public function __construct(
        public SatelliteLunarSearchResult $search,
        public array $tleMetadata,
        public array $warnings,
        public array $metrics,
    ) {}

    /** @return array<string,mixed> */
    public function data(): array
    {
        $payload = $this->search->data();
        $payload['tle_metadata'] = array_map(static fn(ResolvedTle $resolved): array => $resolved->data(), $this->tleMetadata);
        $payload['warnings'] = $this->warnings;
        $payload['metrics'] = $this->metrics;
        return $payload;
    }
}
