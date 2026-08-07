<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeZone;

/** TEME -> pseudo-Earth-fixed -> WGS84 ECEF/ENU, without refraction. */
final class SatelliteTopocentricCalculator
{
    private const WGS84_A_KM = 6378.137;
    private const WGS84_FLATTENING = 1.0 / 298.257223563;

    public function __construct(private readonly float $dut1Seconds = 0.0) {}

    public function calculate(TemeState $state, AstronomyObserver $observer): SatelliteTopocentricPosition
    {
        [$x, $y, $z] = $state->positionKilometers;
        $theta = $this->gmstRadians($state);
        $cosTheta = cos($theta);
        $sinTheta = sin($theta);
        // Vallado TEME to PEF rotation. Polar motion is intentionally zero.
        $ecef = [
            $cosTheta * $x + $sinTheta * $y,
            -$sinTheta * $x + $cosTheta * $y,
            $z,
        ];
        $observerEcef = $this->observerEcef($observer);
        $dx = $ecef[0] - $observerEcef[0];
        $dy = $ecef[1] - $observerEcef[1];
        $dz = $ecef[2] - $observerEcef[2];
        $latitude = deg2rad($observer->latitudeDegrees);
        $longitude = deg2rad($observer->longitudeDegrees);
        $sinLatitude = sin($latitude); $cosLatitude = cos($latitude);
        $sinLongitude = sin($longitude); $cosLongitude = cos($longitude);
        $east = -$sinLongitude * $dx + $cosLongitude * $dy;
        $north = -$sinLatitude * $cosLongitude * $dx - $sinLatitude * $sinLongitude * $dy + $cosLatitude * $dz;
        $up = $cosLatitude * $cosLongitude * $dx + $cosLatitude * $sinLongitude * $dy + $sinLatitude * $dz;
        $distance = sqrt($east ** 2 + $north ** 2 + $up ** 2);
        $altitude = rad2deg(asin(max(-1.0, min(1.0, $up / $distance))));
        $azimuth = rad2deg(atan2($east, $north));
        if ($azimuth < 0.0) $azimuth += 360.0;
        return new SatelliteTopocentricPosition($altitude, $azimuth, $distance);
    }

    /** @return array{float,float,float} */
    private function observerEcef(AstronomyObserver $observer): array
    {
        $latitude = deg2rad($observer->latitudeDegrees);
        $longitude = deg2rad($observer->longitudeDegrees);
        $e2 = self::WGS84_FLATTENING * (2.0 - self::WGS84_FLATTENING);
        $normal = self::WGS84_A_KM / sqrt(1.0 - $e2 * sin($latitude) ** 2);
        $height = $observer->elevationMeters / 1000.0;
        return [
            ($normal + $height) * cos($latitude) * cos($longitude),
            ($normal + $height) * cos($latitude) * sin($longitude),
            ($normal * (1.0 - $e2) + $height) * sin($latitude),
        ];
    }

    private function gmstRadians(TemeState $state): float
    {
        $utc = $state->dateTimeUtc->setTimezone(new DateTimeZone('UTC'));
        $julianDate = 2440587.5 + ((float) $utc->format('U.u') + $this->dut1Seconds) / 86400.0;
        $centuries = ($julianDate - 2451545.0) / 36525.0;
        $seconds = 67310.54841
            + (876600.0 * 3600.0 + 8640184.812866) * $centuries
            + 0.093104 * $centuries ** 2
            - 6.2e-6 * $centuries ** 3;
        $radians = fmod(deg2rad($seconds / 240.0), 2.0 * M_PI);
        return $radians < 0.0 ? $radians + 2.0 * M_PI : $radians;
    }
}
