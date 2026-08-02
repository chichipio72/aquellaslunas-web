<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;

/** Geocentric optical libration plus Meeus physical corrections (chapter 53). */
final class LunarLibrationCalculator
{
    /** @return array{longitude_degrees:float,latitude_degrees:float} */
    public function calculate(DateTimeImmutable $dateTime):array
    {
        $utc=$dateTime->setTimezone(new DateTimeZone('UTC'));$jd=2440587.5+(float)$utc->format('U.u')/86400;$t=($jd-2451545)/36525;$d=$this->r(297.8501921+445267.1114034*$t-.0018819*$t*$t+$t**3/545868-$t**4/113065000);$m=$this->r(357.5291092+35999.0502909*$t-.0001536*$t*$t+$t**3/24490000);$mp=$this->r(134.9633964+477198.8675055*$t+.0087414*$t*$t+$t**3/69699-$t**4/14712000);$f=$this->r(93.272095+483202.0175233*$t-.0036539*$t*$t-$t**3/3526000+$t**4/863310000);$o=$this->r(125.0445479-1934.1362891*$t+.0020754*$t*$t+$t**3/467441-$t**4/60616000);$p=(new MeeusLunarCalculator())->calculate($utc,0,0);$i=deg2rad(1.54242);$w=deg2rad($p->eclipticLongitudeDegrees)-$o;$beta=deg2rad($p->eclipticLatitudeDegrees);$a=atan2(sin($w)*cos($beta)*cos($i)-sin($beta)*sin($i),cos($w)*cos($beta));$optLon=$this->signed(rad2deg($a)-rad2deg($f));$optLat=rad2deg(asin(-sin($w)*cos($beta)*sin($i)-sin($beta)*cos($i)));
        $rho=-.02752*cos($mp)-.02245*sin($f)+.00684*cos($mp-2*$f)-.00293*cos(2*$f)-.00085*cos(2*$f-2*$d)-.00054*cos($mp-2*$d)-.00020*sin($mp+$f)-.00020*cos($mp+2*$f)-.00020*cos($mp-$f)+.00014*cos($mp+2*$f-2*$d);$sigma=-.02816*sin($mp)+.02244*cos($f)-.00682*sin($mp-2*$f)-.00279*sin(2*$f)-.00083*sin(2*$f-2*$d)+.00069*sin($mp-2*$d)+.00040*cos($mp+$f)-.00025*sin(2*$mp)-.00023*sin($mp+2*$f)+.00020*cos($mp-$f)+.00019*sin($mp-$f)+.00013*sin($mp+2*$f-2*$d)-.00010*cos($mp-3*$f);$tau=.02520*(1-.002516*$t-.0000074*$t*$t)*sin($m)+.00473*sin(2*$mp-2*$f)-.00467*sin($mp)+.00396*sin(deg2rad(313.45+481266.484*$t))+.00276*sin(2*$mp-2*$d)+.00196*sin($o)-.00183*cos($mp-$f)+.00115*sin($mp-2*$d)-.00096*sin($mp-$d)-.00046*sin(2*$f-2*$d)-.00039*sin($mp-$f)-.00032*sin($mp-$m-$d)+.00027*sin(2*$mp-$m-2*$d)+.00023*sin(deg2rad(119.75+131.849*$t))-.00014*sin(2*$d)+.00014*cos(2*$mp-2*$f)-.00012*sin($mp-2*$f)-.00012*sin(2*$mp)+.00011*sin(2*$mp-2*$m-2*$d);$physicalLon=-$tau+($rho*cos($a)+$sigma*sin($a))*tan(deg2rad($optLat));$physicalLat=$sigma*cos($a)-$rho*sin($a);$longitude=$optLon+$physicalLon;$latitude=$optLat+$physicalLat;$this->assertPhysicalRange($longitude,$latitude,$utc);return['longitude_degrees'=>$longitude,'latitude_degrees'=>$latitude];
    }
    private function assertPhysicalRange(float $longitude,float $latitude,DateTimeImmutable $dateTime):void{if(abs($longitude)>15||abs($latitude)>15)throw new \UnexpectedValueException(sprintf('Lunar libration outside diagnostic range at %s: longitude=%.6f latitude=%.6f',$dateTime->format(DATE_ATOM),$longitude,$latitude));}
    private function r(float $v):float{return deg2rad(fmod($v,360));}private function signed(float $v):float{$v=fmod($v,360);if($v<0)$v+=360;return$v>180?$v-360:$v;}
}
