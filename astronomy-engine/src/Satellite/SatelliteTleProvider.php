<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

interface SatelliteTleProvider
{
    public function resolve(string $satellite): ResolvedTle;
}
