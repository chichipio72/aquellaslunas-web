<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Geometric topocentric solar position using the compact Meeus/NOAA equations.
 *
 * Atmospheric refraction is deliberately not applied. The apparent solar
 * longitude includes the standard compact nutation/aberration correction.
 */
final class MeeusSolarPositionCalculator implements SolarPositionCalculator
{
    private const EARTH_EQUATORIAL_RADIUS_METERS = 6_378_140.0;
    private const SOLAR_RADIUS_KILOMETERS = 695_700.0;

    public function calculate(
        DateTimeImmutable $dateTime,
        float $latitudeDegrees,
        float $longitudeDegrees,
        float $elevationMeters = 0.0,
    ): SolarPosition {
        $this->validateObserver($latitudeDegrees, $longitudeDegrees, $elevationMeters);

        $utc = $dateTime->setTimezone(new DateTimeZone('UTC'));
        $julianDay = 2440587.5
            + ((float) $utc->format('U')) / 86400.0
            + ((float) $utc->format('u')) / 86400000000.0;
        $centuries = ($julianDay - 2451545.0) / 36525.0;

        $meanLongitude = $this->normalizeDegrees(
            280.46646 + $centuries * (36000.76983 + 0.0003032 * $centuries)
        );
        $meanAnomaly = $this->normalizeDegrees(
            357.52911 + $centuries * (35999.05029 - 0.0001537 * $centuries)
        );
        $eccentricity = 0.016708634
            - $centuries * (0.000042037 + 0.0000001267 * $centuries);
        $anomalyRadians = deg2rad($meanAnomaly);
        $equationOfCenter = sin($anomalyRadians)
                * (1.914602 - $centuries * (0.004817 + 0.000014 * $centuries))
            + sin(2.0 * $anomalyRadians) * (0.019993 - 0.000101 * $centuries)
            + sin(3.0 * $anomalyRadians) * 0.000289;
        $trueLongitude = $meanLongitude + $equationOfCenter;
        $trueAnomaly = $meanAnomaly + $equationOfCenter;
        $omega = 125.04 - 1934.136 * $centuries;
        $apparentLongitude = $trueLongitude
            - 0.00569
            - 0.00478 * sin(deg2rad($omega));

        $meanObliquity = 23.0
            + (26.0 + (21.448
                - $centuries * (46.815
                    + $centuries * (0.00059 - 0.001813 * $centuries))) / 60.0) / 60.0;
        $obliquity = $meanObliquity + 0.00256 * cos(deg2rad($omega));
        $longitudeRadians = deg2rad($apparentLongitude);
        $obliquityRadians = deg2rad($obliquity);
        $meanLongitudeRadians = deg2rad($meanLongitude);
        $equationFactor = tan($obliquityRadians / 2.0) ** 2;
        $equationOfTimeMinutes = 4.0 * rad2deg(
            $equationFactor * sin(2.0 * $meanLongitudeRadians)
            - 2.0 * $eccentricity * sin($anomalyRadians)
            + 4.0 * $eccentricity * $equationFactor * sin($anomalyRadians)
                * cos(2.0 * $meanLongitudeRadians)
            - 0.5 * $equationFactor * $equationFactor * sin(4.0 * $meanLongitudeRadians)
            - 1.25 * $eccentricity * $eccentricity * sin(2.0 * $anomalyRadians)
        );

        $rightAscension = atan2(
            cos($obliquityRadians) * sin($longitudeRadians),
            cos($longitudeRadians),
        );
        $declination = asin(sin($obliquityRadians) * sin($longitudeRadians));

        $siderealDegrees = $this->normalizeDegrees(
            280.46061837
            + 360.98564736629 * ($julianDay - 2451545.0)
            + 0.000387933 * $centuries * $centuries
            - $centuries * $centuries * $centuries / 38710000.0
        );
        $hourAngle = deg2rad($this->normalizeSignedDegrees(
            $siderealDegrees + $longitudeDegrees - rad2deg($rightAscension)
        ));

        $earthSunDistanceAu = 1.000001018 * (1.0 - $eccentricity * $eccentricity)
            / (1.0 + $eccentricity * cos(deg2rad($trueAnomaly)));
        $horizontalParallax = deg2rad((8.794 / 3600.0) / $earthSunDistanceAu);
        $latitude = deg2rad($latitudeDegrees);
        $reducedLatitude = atan(0.99664719 * tan($latitude));
        $heightRatio = $elevationMeters / self::EARTH_EQUATORIAL_RADIUS_METERS;
        $rhoCosLatitude = cos($reducedLatitude) + $heightRatio * cos($latitude);
        $rhoSinLatitude = 0.99664719 * sin($reducedLatitude)
            + $heightRatio * sin($latitude);

        $rightAscensionParallax = atan2(
            -$rhoCosLatitude * sin($horizontalParallax) * sin($hourAngle),
            cos($declination)
                - $rhoCosLatitude * sin($horizontalParallax) * cos($hourAngle),
        );
        $topocentricDeclination = atan2(
            (sin($declination) - $rhoSinLatitude * sin($horizontalParallax))
                * cos($rightAscensionParallax),
            cos($declination)
                - $rhoCosLatitude * sin($horizontalParallax) * cos($hourAngle),
        );
        $topocentricHourAngle = $hourAngle - $rightAscensionParallax;

        $altitude = asin(
            sin($latitude) * sin($topocentricDeclination)
            + cos($latitude) * cos($topocentricDeclination)
                * cos($topocentricHourAngle)
        );
        $azimuth = atan2(
            sin($topocentricHourAngle),
            cos($topocentricHourAngle) * sin($latitude)
                - tan($topocentricDeclination) * cos($latitude),
        );

        return new SolarPosition(
            altitudeDegrees: rad2deg($altitude),
            azimuthDegrees: $this->normalizeDegrees(rad2deg($azimuth) + 180.0),
            earthSunDistanceAu: $earthSunDistanceAu,
            apparentRadiusDegrees: rad2deg(asin(
                self::SOLAR_RADIUS_KILOMETERS
                / (SolarPosition::ASTRONOMICAL_UNIT_KILOMETERS * $earthSunDistanceAu)
            )),
            equationOfTimeMinutes: $equationOfTimeMinutes,
            rightAscensionDegrees: $this->normalizeDegrees(rad2deg($rightAscension)),
            declinationDegrees: rad2deg($declination),
            apparentEclipticLongitudeDegrees: $this->normalizeDegrees($apparentLongitude),
        );
    }

    private function validateObserver(
        float $latitudeDegrees,
        float $longitudeDegrees,
        float $elevationMeters,
    ): void {
        if (!is_finite($latitudeDegrees) || $latitudeDegrees < -90.0 || $latitudeDegrees > 90.0) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90 degrees.');
        }
        if (!is_finite($longitudeDegrees) || $longitudeDegrees < -180.0 || $longitudeDegrees > 180.0) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180 degrees.');
        }
        if (!is_finite($elevationMeters) || $elevationMeters < -500.0 || $elevationMeters > 10000.0) {
            throw new InvalidArgumentException('Elevation must be between -500 and 10000 meters.');
        }
    }

    private function normalizeDegrees(float $degrees): float
    {
        $normalized = fmod($degrees, 360.0);

        return $normalized < 0.0 ? $normalized + 360.0 : $normalized;
    }

    private function normalizeSignedDegrees(float $degrees): float
    {
        $normalized = $this->normalizeDegrees($degrees);

        return $normalized > 180.0 ? $normalized - 360.0 : $normalized;
    }
}
