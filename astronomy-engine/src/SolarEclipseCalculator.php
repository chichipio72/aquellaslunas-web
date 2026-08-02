<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeImmutable;use DateTimeZone;use InvalidArgumentException;
/** Global solar eclipses following Meeus, Astronomical Algorithms, chapter 54. */
final class SolarEclipseCalculator
{
    private readonly MeeusEclipsePositionCalculator $positions;
    public function __construct(){$this->positions=new MeeusEclipsePositionCalculator();}
    /** @return list<SolarEclipse> */
    public function events(DateTimeImmutable $start,DateTimeImmutable $end):array
    {
        $utc=new DateTimeZone('UTC');$start=$start->setTimezone($utc);$end=$end->setTimezone($utc);if($end<=$start)throw new InvalidArgumentException('Eclipse interval end must be after start.');$estimate=((int)$start->format('Y')+((int)$start->format('n')-1)/12-2000)*12.3685;$first=(int)floor($estimate)-2;$count=(int)ceil(((float)$end->format('U.u')-(float)$start->format('U.u'))/86400/29.53)+5;$events=[];
        for($k=$first;$k<=$first+$count;$k++){$event=$this->eclipse((float)$k);if($event!==null&&$event->maximum>=$start&&$event->maximum<$end)$events[]=$event;}
        usort($events,static fn(SolarEclipse $a,SolarEclipse $b):int=>$a->maximum<=>$b->maximum);return$events;
    }
    private function eclipse(float $k):?SolarEclipse
    {
        $t=$k/1236.85;$e=1-.002516*$t-.0000074*$t*$t;$m=$this->r(2.5534+29.10535670*$k-.0000014*$t*$t-.00000011*$t**3);$mp=$this->r(201.5643+385.81693528*$k+.0107582*$t*$t+.00001238*$t**3-.000000058*$t**4);$f=$this->r(160.7108+390.67050284*$k-.0016118*$t*$t-.00000227*$t**3+.000000011*$t**4);$omega=$this->r(124.7746-1.56375588*$k+.0020672*$t*$t+.00000215*$t**3);if(abs(sin($f))>.36)return null;$a1=$this->r(299.77+.107408*$k-.009173*$t*$t);$jde=2451550.09765+29.530588853*$k+.0001337*$t*$t-.000000150*$t**3+.00000000073*$t**4;$jde+=-.4075*sin($mp)+.1721*$e*sin($m)+.0161*sin(2*$mp)-.0097*sin(2*$f)+.0073*$e*sin($mp-$m)-.0050*$e*sin($mp+$m)-.0023*sin($mp-2*$f)+.0021*$e*sin(2*$m)+.0012*sin($mp+2*$f)+.0006*$e*sin(2*$mp+$m)-.0004*sin(3*$mp)-.0003*$e*sin($m+2*$f)+.0003*sin($a1)-.0002*$e*sin($m-2*$f)-.0002*sin($omega);$year=2000+$k/12.3685;$maximum=$this->fromJulianDay($jde-$this->deltaT($year)/86400);
        $p=.2070*$e*sin($m)+.0024*$e*sin(2*$m)-.0392*sin($mp)+.0116*sin(2*$mp)-.0073*$e*sin($mp+$m)+.0067*$e*sin($mp-$m)+.0118*sin(2*$f);$q=5.2207-.0048*$e*cos($m)+.0020*$e*cos(2*$m)-.3299*cos($mp)-.0060*$e*cos($mp+$m)+.0041*$e*cos($mp-$m);$w=abs(cos($f));$gamma=($p*cos($f)+$q*sin($f))*(1-.0048*$w);$u=.0059+.0046*$e*cos($m)-.0182*cos($mp)+.0004*cos(2*$mp)-.0005*cos($m+$mp);$ag=abs($gamma);if($ag>1.5433+$u)return null;
        if($ag>.9972)$classification='partial';elseif($u<0)$classification='total';elseif($u>.0047)$classification='annular';else{$limit=.00464*sqrt(max(0,1-$gamma*$gamma));$classification=$u<$limit?'hybrid':'annular';}
        $position=$this->positions->calculate($maximum);$moonRadius=asin(1737.1/$position['moon_distance_km']);$sunRadius=asin(696340.0/$position['sun_distance_km']);$ratio=$moonRadius/$sunRadius;$magnitude=$classification==='partial'?(1.5433+$u-$ag)/(.5461+2*$u):null;
        return new SolarEclipse('eclipse','solar_eclipse',$classification,$maximum,['gamma'=>$gamma,'u'=>$u,'magnitude'=>$magnitude,'moon_sun_radius_ratio'=>$ratio],['gamma'=>$gamma,'u'=>$u,'moon_distance_km'=>$position['moon_distance_km'],'sun_distance_km'=>$position['sun_distance_km']],['MAX'=>$maximum],'meeus-54-does-not-provide-global-contacts','meeus-chapter-54-global-solar-eclipse-v1');
    }
    private function deltaT(float $year):float{if($year>=2005&&$year<2050){$t=$year-2000;return 62.92+.32217*$t+.005589*$t*$t;}if($year>=2050&&$year<2150)return-20+32*(($year-1820)/100)**2-.5628*(2150-$year);$u=($year-2000)/100;return 63.86+33.45*$u-603.74*$u**2+1727.5*$u**3+65181.4*$u**4+237359.9*$u**5;}
    private function fromJulianDay(float $jd):DateTimeImmutable{$seconds=($jd-2440587.5)*86400;return DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',$seconds),new DateTimeZone('UTC'))->setTimezone(new DateTimeZone('UTC'));}
    private function r(float $degrees):float{return deg2rad(fmod($degrees,360));}
}
