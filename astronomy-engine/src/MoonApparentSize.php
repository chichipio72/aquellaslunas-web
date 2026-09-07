<?php

declare(strict_types=1);

namespace AstronomyEngine;

final class MoonApparentSize
{
    public const RADIUS_KILOMETERS = 1737.4;
    public const MEAN_DISTANCE_KILOMETERS = 384400.0;

    public static function percentOfMean(float $distanceKilometers): float
    {
        $diameter = self::angularDiameterRadians($distanceKilometers);
        $meanDiameter = self::angularDiameterRadians(self::MEAN_DISTANCE_KILOMETERS);
        return $diameter / $meanDiameter * 100.0;
    }

    public static function angularDiameterArcminutes(float $distanceKilometers): float
    {
        return rad2deg(self::angularDiameterRadians($distanceKilometers)) * 60.0;
    }

    private static function angularDiameterRadians(float $distanceKilometers): float
    {
        if ($distanceKilometers <= self::RADIUS_KILOMETERS) {
            throw new \InvalidArgumentException('La distancia lunar debe superar el radio de la Luna.');
        }
        return 2.0 * atan(self::RADIUS_KILOMETERS / $distanceKilometers);
    }
}
