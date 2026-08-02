<?php

declare(strict_types=1);

namespace AstronomyEngine;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Local Moon-to-Sun bright-limb direction in the same screen convention as the renderer. */
final class MoonBrightLimbCalculator
{
    private readonly MeeusLunarCalculator $moon;
    private readonly MeeusSolarPositionCalculator $sun;

    public function __construct()
    {
        $this->moon = new MeeusLunarCalculator();
        $this->sun = new MeeusSolarPositionCalculator();
    }

    /** @return array{phase_angle_degrees:float,bright_limb_angle_degrees:float,rotation_degrees:float,moon_altitude_degrees:float,moon_azimuth_degrees:float,sun_altitude_degrees:float,sun_azimuth_degrees:float} */
    public function calculate(DateTimeImmutable $instant, AstronomyObserver $observer): array
    {
        $utc = $instant->setTimezone(new DateTimeZone('UTC'));
        $date = $utc->format('Y-m-d');
        if ($date < '1900-01-01' || $date > '2050-12-31') {
            throw new InvalidArgumentException('Datetime must be between 1900-01-01 and 2050-12-31.');
        }
        $moon = $this->moon->calculate($utc, $observer->latitudeDegrees, $observer->longitudeDegrees, $observer->elevationMeters);
        $sun = $this->sun->calculate($utc, $observer->latitudeDegrees, $observer->longitudeDegrees, $observer->elevationMeters);
        $moonAltitude = deg2rad($moon->altitudeDegrees);
        $sunAltitude = deg2rad($sun->altitudeDegrees);
        $azimuthDifference = deg2rad($sun->azimuthDegrees - $moon->azimuthDegrees);
        $up = cos($moonAltitude) * sin($sunAltitude)
            - sin($moonAltitude) * cos($sunAltitude) * cos($azimuthDifference);
        $right = cos($sunAltitude) * sin($azimuthDifference);
        $brightLimb = $this->normalize(rad2deg(atan2($right, $up)));
        $phase = $this->normalize($moon->cycleAngleDegrees);
        $base = $phase > 0.0 && $phase < 180.0 ? 270.0 : 90.0;
        $rotation = $this->signed($base - $brightLimb);
        return [
            'phase_angle_degrees' => $phase,
            'bright_limb_angle_degrees' => $brightLimb,
            'rotation_degrees' => $rotation,
            'moon_altitude_degrees' => $moon->altitudeDegrees,
            'moon_azimuth_degrees' => $moon->azimuthDegrees,
            'sun_altitude_degrees' => $sun->altitudeDegrees,
            'sun_azimuth_degrees' => $sun->azimuthDegrees,
        ];
    }

    private function normalize(float $angle): float
    {
        $angle = fmod($angle, 360.0);
        return $angle < 0 ? $angle + 360.0 : $angle;
    }

    private function signed(float $angle): float
    {
        $angle = $this->normalize($angle + 180.0) - 180.0;
        return $angle;
    }
}
