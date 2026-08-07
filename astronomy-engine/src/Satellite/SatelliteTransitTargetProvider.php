<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;

interface SatelliteTransitTargetProvider
{
    public function body(): string;
    public function fineStepSeconds(): float;
    public function calculate(DateTimeImmutable $date, AstronomyObserver $observer): SatelliteTransitTargetPosition;
}
