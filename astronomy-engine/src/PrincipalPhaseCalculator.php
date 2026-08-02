<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;

/** Principal lunar phases from Meeus, Astronomical Algorithms, chapter 49. */
final class PrincipalPhaseCalculator
{
    /** @return list<array{type:string,dateTime:DateTimeImmutable}> */
    public function between(DateTimeImmutable $start,DateTimeImmutable $end):array
    {
        $start=$start->setTimezone(new DateTimeZone('UTC'));$end=$end->setTimezone(new DateTimeZone('UTC'));$year=(int)$start->format('Y');$month=(int)$start->format('n');$estimate=($year+($month-1)/12-2000)*12.3685;$first=(int)floor($estimate)-2;$last=$first+(int)ceil(($end->getTimestamp()-$start->getTimestamp())/86400/29.53)+5;$events=[];
        for($lunation=$first;$lunation<=$last;$lunation++)foreach([[0.0,'new_moon'],[0.25,'first_quarter'],[0.5,'full_moon'],[0.75,'last_quarter']] as [$fraction,$type]){$date=$this->phase($lunation+$fraction,$fraction);if($date>=$start&&$date<$end)$events[]=['type'=>$type,'dateTime'=>$date];}
        usort($events,static fn(array $a,array $b):int=>$a['dateTime']<=>$b['dateTime']);return$events;
    }
    private function phase(float $k,float $fraction):DateTimeImmutable
    {
        $t=$k/1236.85;$e=1-0.002516*$t-0.0000074*$t*$t;$m=$this->r(2.5534+29.10535670*$k-0.0000014*$t*$t-0.00000011*$t**3);$mp=$this->r(201.5643+385.81693528*$k+0.0107582*$t*$t+0.00001238*$t**3-0.000000058*$t**4);$f=$this->r(160.7108+390.67050284*$k-0.0016118*$t*$t-0.00000227*$t**3+0.000000011*$t**4);$o=$this->r(124.7746-1.56375588*$k+0.0020672*$t*$t+0.00000215*$t**3);$jde=2451550.09765+29.530588853*$k+0.0001337*$t*$t-0.000000150*$t**3+0.00000000073*$t**4;
        if($fraction===0.0||$fraction===0.5){$full=$fraction===0.5;$jde+=(-($full?.40614:.40720)*sin($mp)+($full?.17302:.17241)*$e*sin($m)+($full?.01614:.01608)*sin(2*$mp)+($full?.01043:.01039)*sin(2*$f)+($full?.00734:.00739)*$e*sin($mp-$m)-($full?.00515:.00514)*$e*sin($mp+$m)+($full?.00209:.00208)*$e*$e*sin(2*$m)-.00111*sin($mp-2*$f)-.00057*sin($mp+2*$f)+.00056*$e*sin(2*$mp+$m)-.00042*sin(3*$mp)+.00042*$e*sin($m+2*$f)+.00038*$e*sin($m-2*$f)-.00024*$e*sin(2*$mp-$m)-.00017*sin($o)-.00007*sin($mp+2*$m)+.00004*sin(2*$mp-2*$f)+.00004*sin(3*$m)+.00003*sin($mp+$m-2*$f)+.00003*sin(2*$mp+2*$f)-.00003*sin($mp+$m+2*$f)+.00003*sin($mp-$m+2*$f)-.00002*sin($mp-$m-2*$f)-.00002*sin(3*$mp+$m)+.00002*sin(4*$mp));}
        else{$jde+=-.62801*sin($mp)+.17172*$e*sin($m)-.01183*$e*sin($mp+$m)+.00862*sin(2*$mp)+.00804*sin(2*$f)+.00454*$e*sin($mp-$m)+.00204*$e*$e*sin(2*$m)-.00180*sin($mp-2*$f)-.00070*sin($mp+2*$f)-.00040*sin(3*$mp)-.00034*$e*sin(2*$mp-$m)+.00032*$e*sin($m+2*$f)+.00032*$e*sin($m-2*$f)-.00028*$e*$e*sin($mp+2*$m)+.00027*$e*sin(2*$mp+$m)-.00017*sin($o)-.00005*sin($mp-$m-2*$f)+.00004*sin(2*$mp+2*$f)-.00004*sin($mp+$m+2*$f)+.00004*sin($mp-2*$m)+.00003*sin($mp+$m-2*$f)+.00003*sin(3*$m)+.00002*sin(2*$mp-2*$f)+.00002*sin($mp-$m+2*$f)-.00002*sin(3*$mp+$m);$w=.00306-.00038*$e*cos($m)+.00026*cos($mp)-.00002*cos($mp-$m)+.00002*cos($mp+$m)+.00002*cos(2*$f);$jde+=$fraction===.25?$w:-$w;}
        $year=2000+$k/12.3685;$deltaT=$this->deltaT($year);return$this->fromJulianDay($jde-$deltaT/86400);
    }
    private function deltaT(float $year):float{if($year>=2005&&$year<2050){$t=$year-2000;return 62.92+.32217*$t+.005589*$t*$t;}if($year>=2050&&$year<2150)return-20+32*(($year-1820)/100)**2-.5628*(2150-$year);$u=($year-2000)/100;return 63.86+33.45*$u-603.74*$u**2+1727.5*$u**3+65181.4*$u**4+237359.9*$u**5;}
    private function fromJulianDay(float $jd):DateTimeImmutable{$seconds=($jd-2440587.5)*86400;return DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',$seconds),new DateTimeZone('UTC'))->setTimezone(new DateTimeZone('UTC'));}
    private function r(float $degrees):float{return deg2rad(fmod($degrees,360));}
}
