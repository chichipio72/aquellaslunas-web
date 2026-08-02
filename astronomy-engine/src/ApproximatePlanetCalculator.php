<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeImmutable;use DateTimeZone;use InvalidArgumentException;
/** JPL approximate Keplerian elements for 1800–2050; no binary ephemeris. */
final class ApproximatePlanetCalculator
{
    private const E=[
        'mercury'=>[[.38709927,.20563593,7.00497902,252.25032350,77.45779628,48.33076593],[.00000037,.00001906,-.00594749,149472.67411175,.16047689,-.12534081]],
        'venus'=>[[.72333566,.00677672,3.39467605,181.97909950,131.60246718,76.67984255],[.00000390,-.00004107,-.00078890,58517.81538729,.00268329,-.27769418]],
        'earth'=>[[1.00000261,.01671123,-.00001531,100.46457166,102.93768193,0],[.00000562,-.00004392,-.01294668,35999.37244981,.32327364,0]],
        'mars'=>[[1.52371034,.09339410,1.84969142,-4.55343205,-23.94362959,49.55953891],[.00001847,.00007882,-.00813131,19140.30268499,.44441088,-.29257343]],
        'jupiter'=>[[5.20288700,.04838624,1.30439695,34.39644051,14.72847983,100.47390909],[-.00011607,-.00013253,-.00183714,3034.74612775,.21252668,.20469106]],
        'saturn'=>[[9.53667594,.05386179,2.48599187,49.95424423,92.59887831,113.66242448],[-.00125060,-.00050991,.00193609,1222.49362201,-.41897216,-.28867794]],
    ];
    public function target(string $id,DateTimeImmutable $date):EquatorialCoordinates{$earth=$this->vector('earth',$date);$planet=$this->vector($id,$date);return$this->equatorial([$planet[0]-$earth[0],$planet[1]-$earth[1],$planet[2]-$earth[2]],$date);}
    public function sun(DateTimeImmutable $date):EquatorialCoordinates{$e=$this->vector('earth',$date);return$this->equatorial([-$e[0],-$e[1],-$e[2]],$date);}
    public function fixed(ConjunctionTarget $target,DateTimeImmutable $date):EquatorialCoordinates{$year=2000+($this->jd($date)-2451545)/365.25;$ra=(float)$target->raDegrees+$target->raMasPerYear*($year-1991.25)/3600000;$dec=(float)$target->decDegrees+$target->decMasPerYear*($year-1991.25)/3600000;return$this->precess(new EquatorialCoordinates($ra,$dec),$date);}
    /** @return array{float,float,float} */private function vector(string $id,DateTimeImmutable $date):array{if(!isset(self::E[$id]))throw new InvalidArgumentException("Unknown planet {$id}");$t=($this->jd($date)-2451545)/36525;[$b,$r]=self::E[$id];$v=[];for($i=0;$i<6;$i++)$v[$i]=$b[$i]+$r[$i]*$t;[$a,$e,$i,$l,$peri,$node]=$v;$m=deg2rad($this->norm($l-$peri));$ecc=$m;for($n=0;$n<12;$n++)$ecc-=(($ecc-$e*sin($ecc)-$m)/(1-$e*cos($ecc)));$x=$a*(cos($ecc)-$e);$y=$a*sqrt(1-$e*$e)*sin($ecc);$w=deg2rad($peri-$node);$node=deg2rad($node);$i=deg2rad($i);return[$x*(cos($w)*cos($node)-sin($w)*sin($node)*cos($i))+$y*(-sin($w)*cos($node)-cos($w)*sin($node)*cos($i)),$x*(cos($w)*sin($node)+sin($w)*cos($node)*cos($i))+$y*(-sin($w)*sin($node)+cos($w)*cos($node)*cos($i)),$x*sin($w)*sin($i)+$y*cos($w)*sin($i)];}
    private function equatorial(array $v,DateTimeImmutable $date):EquatorialCoordinates{$eps=deg2rad(23.43929111);$x=$v[0];$y=$v[1]*cos($eps)-$v[2]*sin($eps);$z=$v[1]*sin($eps)+$v[2]*cos($eps);return$this->precess(new EquatorialCoordinates($this->norm(rad2deg(atan2($y,$x))),rad2deg(atan2($z,hypot($x,$y)))),$date);}
    private function precess(EquatorialCoordinates $c,DateTimeImmutable $date):EquatorialCoordinates{$t=($this->jd($date)-2451545)/36525;$zeta=deg2rad((2306.2181*$t+.30188*$t*$t+.017998*$t**3)/3600);$z=deg2rad((2306.2181*$t+1.09468*$t*$t+.018203*$t**3)/3600);$theta=deg2rad((2004.3109*$t-.42665*$t*$t-.041833*$t**3)/3600);$a=deg2rad($c->rightAscensionDegrees);$d=deg2rad($c->declinationDegrees);$A=cos($d)*sin($a+$zeta);$B=cos($theta)*cos($d)*cos($a+$zeta)-sin($theta)*sin($d);$C=sin($theta)*cos($d)*cos($a+$zeta)+cos($theta)*sin($d);return new EquatorialCoordinates($this->norm(rad2deg(atan2($A,$B)+$z)),rad2deg(asin($C)));}
    private function jd(DateTimeImmutable $d):float{return 2440587.5+(float)$d->setTimezone(new DateTimeZone('UTC'))->format('U.u')/86400;}private function norm(float $v):float{$v=fmod($v,360);return$v<0?$v+360:$v;}
}
