<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;

final readonly class SatelliteTransitSearchResult
{
    /** @param list<string> $targets @param list<SatelliteTransitEvent> $events @param array<string,int|float> $metrics */
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public AstronomyObserver $observer,
        public array $targets,
        public array $events,
        public array $metrics,
        public float $dut1Seconds,
    ) {}

    /** @return array<string,mixed> */
    public function data(): array
    {
        return [
            'search_window' => [
                'start' => $this->start->format('Y-m-d\TH:i:s.uP'),
                'end' => $this->end->format('Y-m-d\TH:i:s.uP'),
                'hours' => ((float) $this->end->format('U.u') - (float) $this->start->format('U.u')) / 3600.0,
            ],
            'observer' => $this->observer->data(),
            'targets' => $this->targets,
            'dut1_seconds' => $this->dut1Seconds,
            'events' => array_map(static fn(SatelliteTransitEvent $event): array => $event->data(), $this->events),
            'metrics' => $this->metrics,
        ];
    }
}
