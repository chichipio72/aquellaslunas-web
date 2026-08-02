<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeImmutable;use DateTimeZone;
/** Standard geocentric-to-topocentric vector reduction (Meeus chapters 13 and 40). */
final class EclipseTopocentricGeometry
{
    private const EARTH_RADIUS_KM=6378.14;private const MOON_RADIUS_KM=1737.1;private const SUN_RADIUS_KM=696340.;
    public function __construct(private readonly MeeusEclipsePositionCalculator $positions=new MeeusEclipsePositionCalculator()){}
    /** @return array<string,mixed> */public function calculate(DateTimeImmutable $date,EclipseObserver $observer):array{$utc=$date->setTimezone(new DateTimeZone('UTC'));$jd=2440587.5+(float)$utc->format('U.u')/86400;$t=($jd-2451545)/36525;$p=$this->positions->calculate($utc);$epsilon=deg2rad(23+(26+(21.448-$t*(46.815+$t*(.00059-.001813*$t)))/60)/60+.00256*cos(deg2rad(125.04-1934.136*$t)));$moon=$this->body(deg2rad($p['moon_longitude_degrees']),deg2rad($p['moon_latitude_degrees']),$p['moon_distance_km'],$epsilon);$sun=$this->body(deg2rad($p['sun_longitude_degrees']),0.,$p['sun_distance_km'],$epsilon);$theta=deg2rad($this->n(280.46061837+360.98564736629*($jd-2451545)+.000387933*$t*$t-$t**3/38710000+$observer->longitudeDegrees));$lat=deg2rad($observer->latitudeDegrees);$u=atan(.99664719*tan($lat));$h=$observer->elevationMeters/(self::EARTH_RADIUS_KM*1000);$rhoCos=cos($u)+$h*cos($lat);$rhoSin=.99664719*sin($u)+$h*sin($lat);$o=[self::EARTH_RADIUS_KM*$rhoCos*cos($theta),self::EARTH_RADIUS_KM*$rhoCos*sin($theta),self::EARTH_RADIUS_KM*$rhoSin];$moon=$this->topo($moon,$o,$theta,$lat,self::MOON_RADIUS_KM);$sun=$this->topo($sun,$o,$theta,$lat,self::SUN_RADIUS_KM);$dot=$moon['unit'][0]*$sun['unit'][0]+$moon['unit'][1]*$sun['unit'][1]+$moon['unit'][2]*$sun['unit'][2];return['date_time_utc'=>$utc,'moon'=>$moon,'sun'=>$sun,'separation_radians'=>acos(max(-1,min(1,$dot)))];}
    private function body(float $lon,float $lat,float $distance,float $e):array{$x=$distance*cos($lat)*cos($lon);$y=$distance*(cos($lat)*sin($lon)*cos($e)-sin($lat)*sin($e));$z=$distance*(cos($lat)*sin($lon)*sin($e)+sin($lat)*cos($e));return[$x,$y,$z];}
    private function topo(array $body,array $observer,float $theta,float $lat,float $radius):array{$v=[$body[0]-$observer[0],$body[1]-$observer[1],$body[2]-$observer[2]];$d=sqrt($v[0]**2+$v[1]**2+$v[2]**2);$unit=[$v[0]/$d,$v[1]/$d,$v[2]/$d];$ra=atan2($v[1],$v[0]);$dec=asin($unit[2]);$hour=$theta-$ra;$alt=asin(sin($lat)*sin($dec)+cos($lat)*cos($dec)*cos($hour));$az=$this->n(rad2deg(atan2(sin($hour),cos($hour)*sin($lat)-tan($dec)*cos($lat)))+180);return['altitude_degrees'=>rad2deg($alt),'azimuth_degrees'=>$az,'distance_km'=>$d,'angular_radius_radians'=>asin($radius/$d),'unit'=>$unit];}
    private function n(float $v):float{$v=fmod($v,360);return$v<0?$v+360:$v;}
}
