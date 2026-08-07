<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;

final readonly class SatelliteSolarTransitDetector
{
    private SatelliteAngularTransitDetector $detector;
    public function __construct(float $dut1Seconds = 0.0) { $this->detector = new SatelliteAngularTransitDetector($dut1Seconds); }

    /** @param array<string,Tle> $satellites */
    public function search(AstronomyObserver $observer, DateTimeImmutable $start, int $hours, array $satellites): SatelliteTransitSearchResult
    {
        return $this->detector->search($observer, $start, $hours, $satellites, [new SunTransitTargetProvider()]);
    }
}
