<?php

declare(strict_types=1);

namespace AstronomyEngine;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;
use DateTimeZone;

/** Complete local geometry needed to render the apparent lunar disk. */
final class MoonDiskAppearanceCalculator
{
    private const EARTH_RADIUS_KM = 6378.14;

    private readonly MeeusLunarCalculator $moon;
    private readonly MoonBrightLimbCalculator $brightLimb;
    private readonly LunarLibrationCalculator $libration;
    private readonly MeeusSolarPositionCalculator $sun;

    public function __construct()
    {
        $this->moon = new MeeusLunarCalculator();
        $this->brightLimb = new MoonBrightLimbCalculator();
        $this->libration = new LunarLibrationCalculator();
        $this->sun = new MeeusSolarPositionCalculator();
    }

    /** @return array<string,mixed> */
    public function calculate(DateTimeImmutable $instant, AstronomyObserver $observer): array
    {
        $utc = $instant->setTimezone(new DateTimeZone('UTC'));
        $moon = $this->moon->calculate(
            $utc,
            $observer->latitudeDegrees,
            $observer->longitudeDegrees,
            $observer->elevationMeters,
        );
        $limb = $this->brightLimb->calculate($utc, $observer);
        $geocentric = $this->libration->calculate($utc);
        $frame = $this->bodyFrame($moon, $geocentric);
        $topocentric = $this->topocentricOrientation($utc, $observer, $moon, $frame);
        $subsolar = $this->subsolarGeometry($utc, $observer, $moon, $frame, $topocentric);
        $phaseIncidence = $subsolar['observer_sun_separation_degrees'];

        return [
            'phase' => [
                'name' => $moon->phaseName,
                'cycle_angle_degrees' => $moon->cycleAngleDegrees,
                'incidence_angle_degrees' => $phaseIncidence,
                'illumination_fraction' => $subsolar['illumination_fraction'],
                'illumination_percent' => $subsolar['illumination_fraction'] * 100.0,
                'legacy_geocentric_illumination_fraction' => $moon->illuminationFraction,
                'waxing' => $moon->cycleAngleDegrees < 180.0,
            ],
            'observer' => [
                ...$observer->data(),
                'moon_altitude_degrees' => $moon->altitudeDegrees,
                'moon_azimuth_degrees' => $moon->azimuthDegrees,
                'above_horizon' => $moon->altitudeDegrees > 0.0,
            ],
            'orientation' => [
                'bright_limb_angle_degrees' => $subsolar['screen_position_angle_degrees'],
                'bright_limb_legacy_horizontal_degrees' => $limb['bright_limb_angle_degrees'],
                'subsolar_position_angle_degrees' => $subsolar['celestial_position_angle_degrees'],
                'celestial_north_screen_angle_degrees' => $topocentric['celestial_north_screen_angle_degrees'],
                'axis_position_angle_degrees' => $topocentric['axis_position_angle_degrees'],
                'lunar_north_screen_angle_degrees' => $topocentric['lunar_north_screen_angle_degrees'],
                'screen_convention' => 'clockwise_from_zenith_toward_increasing_azimuth',
            ],
            'libration' => [
                'longitude_degrees' => $topocentric['longitude_degrees'],
                'latitude_degrees' => $topocentric['latitude_degrees'],
                'reference' => 'topocentric',
                'geocentric_longitude_degrees' => $geocentric['longitude_degrees'],
                'geocentric_latitude_degrees' => $geocentric['latitude_degrees'],
                'model' => 'meeus-chapter-53-optical-physical-plus-vector-parallax',
            ],
            'surface_geometry' => [
                'coordinate_system' => 'IAU_MOON_planetocentric_positive_east',
                'subobserver' => [
                    'longitude_degrees' => $topocentric['longitude_degrees'],
                    'latitude_degrees' => $topocentric['latitude_degrees'],
                    'body_fixed_unit_vector' => $this->bodyVector(
                        $topocentric['longitude_degrees'],
                        $topocentric['latitude_degrees'],
                    ),
                ],
                'subsolar' => [
                    'longitude_degrees' => $subsolar['longitude_degrees'],
                    'latitude_degrees' => $subsolar['latitude_degrees'],
                    'body_fixed_unit_vector' => $this->bodyVector(
                        $subsolar['longitude_degrees'],
                        $subsolar['latitude_degrees'],
                    ),
                ],
                'observer_sun_separation_degrees' => $subsolar['observer_sun_separation_degrees'],
                'illumination_fraction_from_surface_geometry' => $subsolar['illumination_fraction'],
                'body_vector_axes' => 'x=east_at_equator,y=north,z=lon_0_lat_0',
            ],
        ];
    }

    /** @param array<string,array{float,float,float}> $frame */
    private function topocentricOrientation(
        DateTimeImmutable $utc,
        AstronomyObserver $observer,
        LunarPosition $moon,
        array $frame,
    ): array {
        $moonDirection = $frame['moon_direction'];
        $pole = $frame['pole'];
        $primeMeridian = $frame['prime_meridian'];
        $longitude90 = $frame['longitude_90'];

        $observerVector = $this->observerEquatorialVector($utc, $observer);
        $topocentricDirection = $this->normalize($this->add(
            $this->scale($moonDirection, $moon->distanceKilometers),
            $this->scale($observerVector, -1.0),
        ));
        $subObserver = $this->scale($topocentricDirection, -1.0);
        $topocentricLatitude = asin(max(-1.0, min(1.0, $this->dot($subObserver, $pole))));
        $topocentricLongitude = atan2(
            $this->dot($subObserver, $longitude90),
            $this->dot($subObserver, $primeMeridian),
        );

        [$topocentricNorth, $topocentricEast] = $this->skyBasis($topocentricDirection);
        $topocentricPositionAngle = atan2(
            $this->dot($pole, $topocentricEast),
            $this->dot($pole, $topocentricNorth),
        );
        $celestialNorthScreen = $this->celestialNorthScreenAngle(
            $observer->latitudeDegrees,
            $moon->altitudeDegrees,
            $moon->azimuthDegrees,
        );
        $lunarNorthScreen = $this->signed($celestialNorthScreen - rad2deg($topocentricPositionAngle));

        return [
            'longitude_degrees' => $this->signed(rad2deg($topocentricLongitude)),
            'latitude_degrees' => rad2deg($topocentricLatitude),
            'axis_position_angle_degrees' => $this->signed(rad2deg($topocentricPositionAngle)),
            'celestial_north_screen_angle_degrees' => $celestialNorthScreen,
            'lunar_north_screen_angle_degrees' => $lunarNorthScreen,
        ];
    }

    /** @param array{longitude_degrees:float,latitude_degrees:float,axis_position_angle_degrees:float} $geocentric */
    private function bodyFrame(LunarPosition $moon, array $geocentric): array
    {
        $moonDirection = $this->equatorialUnit($moon->rightAscensionDegrees, $moon->declinationDegrees);
        [$celestialNorth, $celestialEast] = $this->skyBasis($moonDirection);
        $latitude = deg2rad($geocentric['latitude_degrees']);
        $positionAngle = deg2rad($geocentric['axis_position_angle_degrees']);
        $poleTangent = $this->add(
            $this->scale($celestialNorth, cos($positionAngle)),
            $this->scale($celestialEast, sin($positionAngle)),
        );
        $pole = $this->normalize($this->add(
            $this->scale($poleTangent, cos($latitude)),
            $this->scale($moonDirection, -sin($latitude)),
        ));
        $subEarth = $this->scale($moonDirection, -1.0);
        $equatorialPrime = $this->normalize($this->add($subEarth, $this->scale($pole, -sin($latitude))));
        $equatorialEast = $this->normalize($this->cross($pole, $equatorialPrime));
        $longitude = deg2rad($geocentric['longitude_degrees']);

        return [
            'moon_direction' => $moonDirection,
            'pole' => $pole,
            'prime_meridian' => $this->add(
                $this->scale($equatorialPrime, cos($longitude)),
                $this->scale($equatorialEast, -sin($longitude)),
            ),
            'longitude_90' => $this->add(
                $this->scale($equatorialPrime, sin($longitude)),
                $this->scale($equatorialEast, cos($longitude)),
            ),
        ];
    }

    /** @param array<string,array{float,float,float}> $frame */
    private function subsolarGeometry(
        DateTimeImmutable $utc,
        AstronomyObserver $observer,
        LunarPosition $moon,
        array $frame,
        array $topocentric,
    ): array {
        $sun = $this->sun->calculate(
            $utc,
            $observer->latitudeDegrees,
            $observer->longitudeDegrees,
            $observer->elevationMeters,
        );
        $earthToSun = $this->scale(
            $this->equatorialUnit($sun->rightAscensionDegrees, $sun->declinationDegrees),
            $sun->earthSunDistanceKilometers(),
        );
        $earthToMoon = $this->scale($frame['moon_direction'], $moon->distanceKilometers);
        $moonToSun = $this->normalize($this->add($earthToSun, $this->scale($earthToMoon, -1.0)));
        $longitude = $this->signed(rad2deg(atan2(
            $this->dot($moonToSun, $frame['longitude_90']),
            $this->dot($moonToSun, $frame['prime_meridian']),
        )));
        $latitude = rad2deg(asin(max(-1.0, min(1.0, $this->dot($moonToSun, $frame['pole'])))));
        $subobserverVector = $this->surfaceVector(
            $topocentric['longitude_degrees'],
            $topocentric['latitude_degrees'],
            $frame,
        );
        $separation = rad2deg(acos(max(-1.0, min(1.0, $this->dot($subobserverVector, $moonToSun)))));
        $topocentricDirection = $this->scale($subobserverVector, -1.0);
        [$north, $east] = $this->skyBasis($topocentricDirection);
        $celestialPosition = $this->normalizeDegrees(rad2deg(atan2(
            $this->dot($moonToSun, $east),
            $this->dot($moonToSun, $north),
        )));

        return [
            'longitude_degrees' => $longitude,
            'latitude_degrees' => $latitude,
            'observer_sun_separation_degrees' => $separation,
            'illumination_fraction' => (1.0 + cos(deg2rad($separation))) / 2.0,
            'celestial_position_angle_degrees' => $celestialPosition,
            'screen_position_angle_degrees' => $this->normalizeDegrees(
                $topocentric['celestial_north_screen_angle_degrees'] - $celestialPosition
            ),
        ];
    }

    /** @param array<string,array{float,float,float}> $frame */
    private function surfaceVector(float $longitude, float $latitude, array $frame): array
    {
        $lon = deg2rad($longitude);
        $lat = deg2rad($latitude);
        return $this->add(
            $this->scale($frame['prime_meridian'], cos($lat) * cos($lon)),
            $this->add(
                $this->scale($frame['longitude_90'], cos($lat) * sin($lon)),
                $this->scale($frame['pole'], sin($lat)),
            ),
        );
    }

    /** @return array{x:float,y:float,z:float} */
    private function bodyVector(float $longitude, float $latitude): array
    {
        $lon = deg2rad($longitude);
        $lat = deg2rad($latitude);
        return ['x' => cos($lat) * sin($lon), 'y' => sin($lat), 'z' => cos($lat) * cos($lon)];
    }

    /** @return array{float,float,float} */
    private function observerEquatorialVector(DateTimeImmutable $utc, AstronomyObserver $observer): array
    {
        $jd = 2440587.5 + (float) $utc->format('U.u') / 86400.0;
        $t = ($jd - 2451545.0) / 36525.0;
        $sidereal = $this->normalizeDegrees(280.46061837 + 360.98564736629 * ($jd - 2451545.0)
            + 0.000387933 * $t * $t - $t * $t * $t / 38710000.0);
        $theta = deg2rad($sidereal + $observer->longitudeDegrees);
        $latitude = deg2rad($observer->latitudeDegrees);
        $u = atan(0.99664719 * tan($latitude));
        $heightRatio = $observer->elevationMeters / (self::EARTH_RADIUS_KM * 1000.0);
        $rhoCos = cos($u) + $heightRatio * cos($latitude);
        $rhoSin = 0.99664719 * sin($u) + $heightRatio * sin($latitude);
        return [
            self::EARTH_RADIUS_KM * $rhoCos * cos($theta),
            self::EARTH_RADIUS_KM * $rhoCos * sin($theta),
            self::EARTH_RADIUS_KM * $rhoSin,
        ];
    }

    /** @return array{array{float,float,float},array{float,float,float}} */
    private function skyBasis(array $direction): array
    {
        $rightAscension = atan2($direction[1], $direction[0]);
        $declination = asin(max(-1.0, min(1.0, $direction[2])));
        return [
            [-cos($rightAscension) * sin($declination), -sin($rightAscension) * sin($declination), cos($declination)],
            [-sin($rightAscension), cos($rightAscension), 0.0],
        ];
    }

    private function celestialNorthScreenAngle(float $observerLatitude, float $altitude, float $azimuth): float
    {
        $phi = deg2rad($observerLatitude);
        $alt = deg2rad($altitude);
        $az = deg2rad($azimuth);
        $up = -cos($phi) * sin($alt) * cos($az) + sin($phi) * cos($alt);
        $right = -cos($phi) * sin($az);
        return $this->signed(rad2deg(atan2($right, $up)));
    }

    /** @return array{float,float,float} */
    private function equatorialUnit(float $rightAscension, float $declination): array
    {
        $ra = deg2rad($rightAscension); $dec = deg2rad($declination);
        return [cos($dec) * cos($ra), cos($dec) * sin($ra), sin($dec)];
    }

    private function dot(array $a, array $b): float { return $a[0]*$b[0]+$a[1]*$b[1]+$a[2]*$b[2]; }
    private function add(array $a, array $b): array { return [$a[0]+$b[0],$a[1]+$b[1],$a[2]+$b[2]]; }
    private function scale(array $a, float $factor): array { return [$a[0]*$factor,$a[1]*$factor,$a[2]*$factor]; }
    private function cross(array $a, array $b): array { return [$a[1]*$b[2]-$a[2]*$b[1],$a[2]*$b[0]-$a[0]*$b[2],$a[0]*$b[1]-$a[1]*$b[0]]; }
    private function normalize(array $vector): array { $length=sqrt($this->dot($vector,$vector));return $this->scale($vector,1.0/$length); }
    private function normalizeDegrees(float $value): float { $value=fmod($value,360.0);return$value<0?$value+360.0:$value; }
    private function signed(float $value): float { $value=$this->normalizeDegrees($value);return$value>180?$value-360:$value; }
}
