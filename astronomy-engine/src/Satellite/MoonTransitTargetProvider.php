<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MeeusLunarCalculator;
use DateTimeImmutable;

final readonly class MoonTransitTargetProvider implements SatelliteTransitTargetProvider
{
    private const MOON_RADIUS_KILOMETERS = 1737.4;

    public function __construct(private MeeusLunarCalculator $calculator = new MeeusLunarCalculator()) {}
    public function body(): string { return 'moon'; }
    public function fineStepSeconds(): float { return 4.0; }

    public function calculate(DateTimeImmutable $date, AstronomyObserver $observer): SatelliteTransitTargetPosition
    {
        $position = $this->calculator->calculate($date, $observer->latitudeDegrees, $observer->longitudeDegrees, $observer->elevationMeters);
        return new SatelliteTransitTargetPosition(
            $position->altitudeDegrees,
            $position->azimuthDegrees,
            rad2deg(asin(self::MOON_RADIUS_KILOMETERS / $position->topocentricDistanceKilometers)),
        );
    }
}
