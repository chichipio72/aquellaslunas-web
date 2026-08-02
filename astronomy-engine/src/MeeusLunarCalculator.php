<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Truncated Meeus chapter 47 lunar coordinates with topocentric parallax. */
final class MeeusLunarCalculator
{
    private const EARTH_RADIUS_KM = 6378.14;
    private const SYNODIC_MONTH_DAYS = 29.530588853;

    /** D, M, M', F, longitude coefficient (1e-6 deg), distance coefficient (1e-3 km). */
    private const LONGITUDE_DISTANCE_TERMS = [
        [0,0,1,0,6288774,-20905355], [2,0,-1,0,1274027,-3699111],
        [2,0,0,0,658314,-2955968], [0,0,2,0,213618,-569925],
        [0,1,0,0,-185116,48888], [0,0,0,2,-114332,-3149],
        [2,0,-2,0,58793,246158], [2,-1,-1,0,57066,-152138],
        [2,0,1,0,53322,-170733], [2,-1,0,0,45758,-204586],
        [0,1,-1,0,-40923,-129620], [1,0,0,0,-34720,108743],
        [0,1,1,0,-30383,104755], [2,0,0,-2,15327,10321],
        [0,0,1,2,-12528,0], [0,0,1,-2,10980,79661],
        [4,0,-1,0,10675,-34782], [0,0,3,0,10034,-23210],
        [4,0,-2,0,8548,-21636], [2,1,-1,0,-7888,24208],
    ];

    /** D, M, M', F, latitude coefficient (1e-6 deg). */
    private const LATITUDE_TERMS = [
        [0,0,0,1,5128122], [0,0,1,1,280602], [0,0,1,-1,277693],
        [2,0,0,-1,173237], [2,0,-1,1,55413], [2,0,-1,-1,46271],
        [2,0,0,1,32573], [0,0,2,1,17198], [2,0,1,-1,9266],
        [0,0,2,-1,8822], [2,-1,0,-1,8216], [2,0,-2,-1,4324],
        [2,0,1,1,4200], [2,1,0,-1,-3359], [2,-1,-1,1,2463],
        [2,-1,0,1,2211], [2,-1,-1,-1,2065], [0,1,-1,-1,-1870],
        [4,0,-1,-1,1828], [0,1,0,1,-1794],
    ];

    public function calculate(
        DateTimeImmutable $dateTime,
        float $latitudeDegrees,
        float $longitudeDegrees,
        float $elevationMeters = 0.0,
    ): LunarPosition {
        $this->validate($latitudeDegrees, $longitudeDegrees, $elevationMeters);
        $utc = $dateTime->setTimezone(new DateTimeZone('UTC'));
        $jd = 2440587.5 + (float) $utc->format('U.u') / 86400.0;
        $t = ($jd - 2451545.0) / 36525.0;
        $l = $this->normalize(218.3164477 + 481267.88123421*$t - 0.0015786*$t*$t + $t*$t*$t/538841.0 - $t**4/65194000.0);
        $d = $this->normalize(297.8501921 + 445267.1114034*$t - 0.0018819*$t*$t + $t*$t*$t/545868.0 - $t**4/113065000.0);
        $m = $this->normalize(357.5291092 + 35999.0502909*$t - 0.0001536*$t*$t + $t*$t*$t/24490000.0);
        $mp = $this->normalize(134.9633964 + 477198.8675055*$t + 0.0087414*$t*$t + $t*$t*$t/69699.0 - $t**4/14712000.0);
        $f = $this->normalize(93.2720950 + 483202.0175233*$t - 0.0036539*$t*$t - $t*$t*$t/3526000.0 + $t**4/863310000.0);
        $e = 1.0 - 0.002516*$t - 0.0000074*$t*$t;
        $sumLongitude = $sumDistance = 0.0;
        foreach (self::LONGITUDE_DISTANCE_TERMS as [$dc,$mc,$mpc,$fc,$lc,$rc]) {
            $factor = $mc === 0 ? 1.0 : $e ** abs($mc);
            $argument = deg2rad($dc*$d + $mc*$m + $mpc*$mp + $fc*$f);
            $sumLongitude += $factor * $lc * sin($argument);
            $sumDistance += $factor * $rc * cos($argument);
        }
        $a1 = 119.75 + 131.849*$t;
        $a2 = 53.09 + 479264.29*$t;
        $sumLongitude += 3958*sin(deg2rad($a1)) + 1962*sin(deg2rad($l-$f)) + 318*sin(deg2rad($a2));
        $sumLatitude = 0.0;
        foreach (self::LATITUDE_TERMS as [$dc,$mc,$mpc,$fc,$bc]) {
            $factor = $mc === 0 ? 1.0 : $e ** abs($mc);
            $sumLatitude += $factor * $bc * sin(deg2rad($dc*$d + $mc*$m + $mpc*$mp + $fc*$f));
        }
        $a3 = 313.45 + 481266.484*$t;
        $sumLatitude += -2235*sin(deg2rad($l)) + 382*sin(deg2rad($a3))
            + 175*sin(deg2rad($a1-$f)) + 175*sin(deg2rad($a1+$f))
            + 127*sin(deg2rad($l-$mp)) - 115*sin(deg2rad($l+$mp));
        $omega = 125.04 - 1934.136*$t;
        $moonLongitude = $this->normalize($l + $sumLongitude/1_000_000.0 - 0.00478*sin(deg2rad($omega)));
        $moonLatitude = $sumLatitude/1_000_000.0;
        $geocentricDistance = 385000.56 + $sumDistance/1000.0;

        $obliquity = 23.0 + (26.0 + (21.448 - $t*(46.815 + $t*(0.00059 - 0.001813*$t)))/60.0)/60.0
            + 0.00256*cos(deg2rad($omega));
        $lambda = deg2rad($moonLongitude); $beta = deg2rad($moonLatitude); $epsilon = deg2rad($obliquity);
        $ra = atan2(sin($lambda)*cos($epsilon)-tan($beta)*sin($epsilon), cos($lambda));
        $declination = asin(sin($beta)*cos($epsilon)+cos($beta)*sin($epsilon)*sin($lambda));
        $sidereal = $this->normalize(280.46061837 + 360.98564736629*($jd-2451545.0)
            + 0.000387933*$t*$t - $t*$t*$t/38710000.0);
        $hourAngle = deg2rad($this->signed($sidereal + $longitudeDegrees - rad2deg($ra)));
        $latitude = deg2rad($latitudeDegrees);
        $u = atan(0.99664719*tan($latitude));
        $heightRatio = $elevationMeters/(self::EARTH_RADIUS_KM*1000.0);
        $rhoCos = cos($u)+$heightRatio*cos($latitude);
        $rhoSin = 0.99664719*sin($u)+$heightRatio*sin($latitude);
        $parallax = asin(self::EARTH_RADIUS_KM/$geocentricDistance);
        $deltaRa = atan2(-$rhoCos*sin($parallax)*sin($hourAngle), cos($declination)-$rhoCos*sin($parallax)*cos($hourAngle));
        $topocentricDeclination = atan2(
            (sin($declination)-$rhoSin*sin($parallax))*cos($deltaRa),
            cos($declination)-$rhoCos*sin($parallax)*cos($hourAngle)
        );
        $topocentricHourAngle = $hourAngle-$deltaRa;
        $altitude = asin(sin($latitude)*sin($topocentricDeclination)
            + cos($latitude)*cos($topocentricDeclination)*cos($topocentricHourAngle));
        $azimuth = $this->normalize(rad2deg(atan2(
            sin($topocentricHourAngle),
            cos($topocentricHourAngle)*sin($latitude)-tan($topocentricDeclination)*cos($latitude)
        ))+180.0);
        $observerRadius = hypot($rhoCos, $rhoSin)*self::EARTH_RADIUS_KM;
        $cosPsi = (cos($declination)*cos($hourAngle)*$rhoCos + sin($declination)*$rhoSin) / hypot($rhoCos,$rhoSin);
        $topocentricDistance = sqrt($geocentricDistance**2 + $observerRadius**2 - 2*$geocentricDistance*$observerRadius*$cosPsi);

        [$sunLongitude,$sunDistanceAu] = $this->sunLongitudeAndDistance($t);
        $cycle = $this->normalize($moonLongitude-$sunLongitude);
        $elongation = deg2rad($cycle > 180.0 ? 360.0-$cycle : $cycle);
        $sunDistanceKm = $sunDistanceAu*149597870.7;
        $phaseAngle = atan2($sunDistanceKm*sin($elongation), $geocentricDistance-$sunDistanceKm*cos($elongation));
        $illumination = (1.0+cos($phaseAngle))/2.0;

        return new LunarPosition(
            rad2deg($altitude), $azimuth, $geocentricDistance, $topocentricDistance, $cycle,
            $illumination, $cycle/360.0*self::SYNODIC_MONTH_DAYS, $this->phaseName($cycle),
            $moonLongitude, $moonLatitude, $this->normalize(rad2deg($ra)), rad2deg($declination)
        );
    }

    /** @return array{float,float} */
    private function sunLongitudeAndDistance(float $t): array
    {
        $l = $this->normalize(280.46646+$t*(36000.76983+0.0003032*$t));
        $m = $this->normalize(357.52911+$t*(35999.05029-0.0001537*$t));
        $e = 0.016708634-$t*(0.000042037+0.0000001267*$t);
        $mr=deg2rad($m);
        $c=sin($mr)*(1.914602-$t*(0.004817+0.000014*$t))+sin(2*$mr)*(0.019993-0.000101*$t)+sin(3*$mr)*0.000289;
        $trueAnomaly=$m+$c; $omega=125.04-1934.136*$t;
        return [$this->normalize($l+$c-0.00569-0.00478*sin(deg2rad($omega))),
            1.000001018*(1-$e*$e)/(1+$e*cos(deg2rad($trueAnomaly)))];
    }

    private function phaseName(float $angle): string
    {
        $names=['luna nueva','creciente','cuarto creciente','gibosa creciente','luna llena','gibosa menguante','cuarto menguante','menguante'];
        return $names[(int) floor(fmod($angle+22.5,360.0)/45.0)];
    }

    private function validate(float $lat,float $lon,float $elevation): void
    {
        if(!is_finite($lat)||$lat < -90||$lat > 90||!is_finite($lon)||$lon < -180||$lon > 180||!is_finite($elevation)) {
            throw new InvalidArgumentException('Invalid lunar observer coordinates.');
        }
    }
    private function normalize(float $v): float { $v=fmod($v,360.0); return $v<0?$v+360.0:$v; }
    private function signed(float $v): float { $v=$this->normalize($v); return $v>180?$v-360:$v; }
}
