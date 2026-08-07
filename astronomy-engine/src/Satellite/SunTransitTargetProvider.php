<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MeeusSolarPositionCalculator;
use DateTimeImmutable;

final readonly class SunTransitTargetProvider implements SatelliteTransitTargetProvider
{
    public function __construct(private MeeusSolarPositionCalculator $calculator = new MeeusSolarPositionCalculator()) {}
    public function body(): string { return 'sun'; }

    // The two-second grid only brackets candidates inside selected passes;
    // golden-section refinement and 0.02 s contact sampling provide precision.
    public function fineStepSeconds(): float { return 2.0; }

    public function calculate(DateTimeImmutable $date, AstronomyObserver $observer): SatelliteTransitTargetPosition
    {
        $position = $this->calculator->calculate($date, $observer->latitudeDegrees, $observer->longitudeDegrees, $observer->elevationMeters);
        return new SatelliteTransitTargetPosition(
            $position->altitudeDegrees,
            $position->azimuthDegrees,
            $position->apparentRadiusDegrees,
        );
    }
}
