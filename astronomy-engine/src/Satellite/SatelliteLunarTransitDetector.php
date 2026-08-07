<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;

/** Backward-compatible lunar entry point backed by the shared angular search. */
final readonly class SatelliteLunarTransitDetector
{
    private SatelliteAngularTransitDetector $detector;

    public function __construct(private float $dut1Seconds = 0.0)
    {
        $this->detector = new SatelliteAngularTransitDetector($dut1Seconds);
    }

    /** @param array<string,Tle> $satellites */
    public function search(AstronomyObserver $observer, DateTimeImmutable $start, int $hours, array $satellites): SatelliteLunarSearchResult
    {
        $shared = $this->detector->search($observer, $start, $hours, $satellites, [new MoonTransitTargetProvider()]);
        $events = array_map(static fn(SatelliteTransitEvent $event): SatelliteLunarApproachEvent => new SatelliteLunarApproachEvent(
            $event->satellite, $event->satelliteName, $event->classification, $event->maximum, $event->entry, $event->exit,
            $event->durationSeconds, $event->minimumSeparationDegrees, $event->targetApparentRadiusDegrees,
            $event->edgeMarginDegrees, $event->targetAltitudeDegrees, $event->targetAzimuthDegrees,
            $event->satelliteAltitudeDegrees, $event->satelliteAzimuthDegrees, $event->satelliteDistanceKilometers,
            $event->tleLine1, $event->tleLine2, $event->tleEpochUtc,
        ), $shared->events);
        $metrics = $shared->metrics;
        $metrics['lunar_ms'] = $metrics['moon_ms'] ?? 0.0;
        unset($metrics['moon_ms']);
        return new SatelliteLunarSearchResult($shared->start, $shared->end, $shared->observer, $events, $metrics, $this->dut1Seconds);
    }

    public static function classify(float $separationDegrees, float $moonRadiusDegrees): string
    {
        return SatelliteAngularTransitDetector::classify($separationDegrees, $moonRadiusDegrees);
    }
}
