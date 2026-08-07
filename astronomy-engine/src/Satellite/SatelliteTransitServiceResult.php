<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

final readonly class SatelliteTransitServiceResult
{
    /** @param array<string,ResolvedTle> $tleMetadata @param list<string> $warnings @param array<string,int|float> $metrics */
    public function __construct(
        public SatelliteTransitSearchResult $search,
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
        $eventMetricKeys = ['propagation_ms','topocentric_ms','moon_ms','sun_ms','coarse_search_ms','fine_search_ms',
            'refinement_ms','contacts_ms','total_samples','total_ms','service_total_ms'];
        $eventMetrics = array_intersect_key($this->metrics, array_flip($eventMetricKeys));
        foreach ($payload['events'] as &$event) $event['metrics'] = $eventMetrics;
        unset($event);
        return $payload;
    }
}
