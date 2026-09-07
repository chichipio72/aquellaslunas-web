<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;

/** Geocentric Sun–Moon shadow geometry in inertial and rotating terrestrial frames. */
final class SolarEclipseShadowGeometry
{
    public const EARTH_RADIUS_KM = 6378.14;
    public const MOON_RADIUS_KM = 1737.1;
    public const SUN_RADIUS_KM = 696340.0;

    public function __construct(private readonly MeeusEclipsePositionCalculator $positions = new MeeusEclipsePositionCalculator())
    {
    }

    /** @return array<string,mixed> */
    public function calculate(DateTimeImmutable $instant): array
    {
        $utc = $instant->setTimezone(new DateTimeZone('UTC'));
        $position = $this->positions->calculate($utc);
        $jd = 2440587.5 + (float) $utc->format('U.u') / 86400.0;
        $centuries = ($jd - 2451545.0) / 36525.0;
        $sidereal = deg2rad($this->greenwichSiderealDegrees($jd, $centuries));
        $moonInertial = $this->equatorialInertialVector(
            $centuries,
            (float) $position['moon_longitude_degrees'],
            (float) $position['moon_latitude_degrees'],
            (float) $position['moon_distance_km']
        );
        $sunInertial = $this->equatorialInertialVector(
            $centuries,
            (float) $position['sun_longitude_degrees'],
            0.0,
            (float) $position['sun_distance_km']
        );
        $moon = $this->inertialToEarthFixed($moonInertial, $sidereal);
        $sun = $this->inertialToEarthFixed($sunInertial, $sidereal);
        $sunToMoon = $this->subtract($moon, $sun);
        $sunMoonDistance = $this->length($sunToMoon);
        $axis = $this->scale($sunToMoon, 1.0 / $sunMoonDistance);
        $axisEarthDistance = -$this->dot($moon, $axis);
        $closest = $this->add($moon, $this->scale($axis, $axisEarthDistance));
        $closestDistance = $this->length($closest);
        $penumbraRadius = self::MOON_RADIUS_KM
            + $axisEarthDistance * (self::SUN_RADIUS_KM + self::MOON_RADIUS_KM) / $sunMoonDistance;
        $signedCoreRadius = self::MOON_RADIUS_KM
            - $axisEarthDistance * (self::SUN_RADIUS_KM - self::MOON_RADIUS_KM) / $sunMoonDistance;
        $axisIntersection = $this->raySphereIntersection($moon, $axis, self::EARTH_RADIUS_KM);
        $nearestSurface = $axisIntersection['point'] ?? ($closestDistance > 1e-9
            ? $this->scale($closest, self::EARTH_RADIUS_KM / $closestDistance)
            : [0.0, 0.0, self::EARTH_RADIUS_KM]);
        $axisInertial = $this->normalize($this->subtract($moonInertial, $sunInertial));
        $nearestSurfaceInertial = $this->earthFixedToInertial($nearestSurface, $sidereal);

        return [
            'datetime' => $utc->format(DateTimeImmutable::ATOM),
            'earth_radius_km' => self::EARTH_RADIUS_KM,
            'moon_radius_km' => self::MOON_RADIUS_KM,
            'sun_radius_km' => self::SUN_RADIUS_KM,
            'sun_position_earth_fixed_km' => $this->vectorData($sun),
            'moon_position_earth_fixed_km' => $this->vectorData($moon),
            'shadow_axis_unit_earth_fixed' => $this->vectorData($axis),
            'sun_position_inertial_km' => $this->vectorData($sunInertial),
            'moon_position_inertial_km' => $this->vectorData($moonInertial),
            'shadow_axis_unit_inertial' => $this->vectorData($axisInertial),
            'nearest_shadow_surface_point_inertial_km' => $this->vectorData($nearestSurfaceInertial),
            'greenwich_sidereal_angle_degrees' => rad2deg($sidereal),
            'sun_moon_distance_km' => $sunMoonDistance,
            'axis_distance_to_earth_center_km' => $closestDistance,
            'axis_distance_from_moon_to_earth_plane_km' => $axisEarthDistance,
            'penumbra_radius_at_earth_plane_km' => $penumbraRadius,
            'core_radius_at_earth_plane_km' => abs($signedCoreRadius),
            'core_kind_at_earth_plane' => $signedCoreRadius >= 0.0 ? 'umbra' : 'antumbra',
            'axis_surface_intersection_earth_fixed_km' => $axisIntersection === null ? null : $this->vectorData($axisIntersection['point']),
            'nearest_shadow_surface_point_earth_fixed_km' => $this->vectorData($nearestSurface),
            'penumbra_intersects_earth' => $closestDistance <= self::EARTH_RADIUS_KM + $penumbraRadius,
            'core_intersects_earth' => $closestDistance <= self::EARTH_RADIUS_KM + abs($signedCoreRadius),
        ];
    }

    public function contactMetric(DateTimeImmutable $instant, string $kind): float
    {
        $geometry = $this->calculate($instant);
        $radius = $kind === 'core'
            ? (float) $geometry['core_radius_at_earth_plane_km']
            : (float) $geometry['penumbra_radius_at_earth_plane_km'];
        return (float) $geometry['axis_distance_to_earth_center_km'] - self::EARTH_RADIUS_KM - $radius;
    }

    /** @return list<float> */
    private function equatorialInertialVector(float $centuries, float $longitudeDegrees, float $latitudeDegrees, float $distance): array
    {
        $obliquity = deg2rad(23.43929111 - 0.013004167 * $centuries);
        $longitude = deg2rad($longitudeDegrees);
        $latitude = deg2rad($latitudeDegrees);
        $eclipticX = $distance * cos($latitude) * cos($longitude);
        $eclipticY = $distance * cos($latitude) * sin($longitude);
        $eclipticZ = $distance * sin($latitude);
        return [
            $eclipticX,
            $eclipticY * cos($obliquity) - $eclipticZ * sin($obliquity),
            $eclipticY * sin($obliquity) + $eclipticZ * cos($obliquity),
        ];
    }

    /** @param list<float> $inertial @return list<float> */
    private function inertialToEarthFixed(array $inertial, float $sidereal): array
    {
        return [
            cos($sidereal) * $inertial[0] + sin($sidereal) * $inertial[1],
            -sin($sidereal) * $inertial[0] + cos($sidereal) * $inertial[1],
            $inertial[2],
        ];
    }

    /** @param list<float> $earthFixed @return list<float> */
    private function earthFixedToInertial(array $earthFixed, float $sidereal): array
    {
        return [
            cos($sidereal) * $earthFixed[0] - sin($sidereal) * $earthFixed[1],
            sin($sidereal) * $earthFixed[0] + cos($sidereal) * $earthFixed[1],
            $earthFixed[2],
        ];
    }

    private function greenwichSiderealDegrees(float $jd, float $centuries): float
    {
        return $this->normalizeDegrees(
            280.46061837 + 360.98564736629 * ($jd - 2451545.0)
            + 0.000387933 * $centuries * $centuries - $centuries ** 3 / 38710000.0
        );
    }

    /** @param list<float> $origin @param list<float> $direction @return array{distance:float,point:list<float>}|null */
    private function raySphereIntersection(array $origin, array $direction, float $radius): ?array
    {
        $projection = $this->dot($origin, $direction);
        $discriminant = $projection * $projection - ($this->dot($origin, $origin) - $radius * $radius);
        if ($discriminant < 0.0) {
            return null;
        }
        $root = sqrt($discriminant);
        $first = -$projection - $root;
        $second = -$projection + $root;
        $distance = $first >= 0.0 ? $first : ($second >= 0.0 ? $second : null);
        return $distance === null ? null : ['distance' => $distance, 'point' => $this->add($origin, $this->scale($direction, $distance))];
    }

    /** @param list<float> $value @return array{x:float,y:float,z:float} */
    private function vectorData(array $value): array
    {
        return ['x' => $value[0], 'y' => $value[1], 'z' => $value[2]];
    }

    /** @param list<float> $first @param list<float> $second @return list<float> */
    private function add(array $first, array $second): array { return [$first[0] + $second[0], $first[1] + $second[1], $first[2] + $second[2]]; }
    /** @param list<float> $first @param list<float> $second @return list<float> */
    private function subtract(array $first, array $second): array { return [$first[0] - $second[0], $first[1] - $second[1], $first[2] - $second[2]]; }
    /** @param list<float> $value @return list<float> */
    private function scale(array $value, float $factor): array { return [$value[0] * $factor, $value[1] * $factor, $value[2] * $factor]; }
    /** @param list<float> $first @param list<float> $second */
    private function dot(array $first, array $second): float { return $first[0] * $second[0] + $first[1] * $second[1] + $first[2] * $second[2]; }
    /** @param list<float> $value */
    private function length(array $value): float { return sqrt($this->dot($value, $value)); }
    /** @param list<float> $value @return list<float> */
    private function normalize(array $value): array { return $this->scale($value, 1.0 / max(1e-30, $this->length($value))); }
    private function normalizeDegrees(float $value): float { $value = fmod($value, 360.0); return $value < 0.0 ? $value + 360.0 : $value; }
}
