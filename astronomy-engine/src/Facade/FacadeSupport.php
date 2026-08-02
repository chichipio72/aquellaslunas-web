<?php
declare(strict_types=1);
namespace AstronomyEngine\Facade;
use AstronomyEngine\LunarVisibilityInterval;use AstronomyEngine\SolarDay;use AstronomyEngine\SolarDayCalculator;use AstronomyEngine\SolarPeriod;use AstronomyEngine\SolarPositionCalculator;use DateTimeImmutable;
final class FacadeSupport
{
    public static function localDate(string $date,AstronomyObserver $observer):DateTimeImmutable{$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$observer->timezone);$errors=DateTimeImmutable::getLastErrors();if(!$parsed||($errors!==false&&($errors['warning_count']||$errors['error_count']))||$parsed->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Date must use YYYY-MM-DD.');return$parsed;}
    public static function date(?DateTimeImmutable $date):?string{return$date?->format(DATE_ATOM);}
    /** @param list<LunarVisibilityInterval> $intervals */public static function lunarIntervals(array $intervals):array{return array_map(static fn($i)=>['start'=>self::date($i->start),'end'=>self::date($i->end)],$intervals);}
    public static function solarIntervals(SolarDay $day,DateTimeImmutable $localDate,AstronomyObserver $observer,SolarPositionCalculator $positions):array{$start=new DateTimeImmutable($localDate->format('Y-m-d').' 00:00:00',$observer->timezone);$end=$start->modify('+1 day');$events=[];if($day->sunrise)$events[]=['time'=>$day->sunrise,'up'=>true];if($day->sunset)$events[]=['time'=>$day->sunset,'up'=>false];usort($events,static fn($a,$b)=>$a['time']<=>$b['time']);$up=$positions->calculate($start,$observer->latitudeDegrees,$observer->longitudeDegrees,$observer->elevationMeters)->altitudeDegrees>=SolarDayCalculator::SUN_HORIZON_DEGREES;$from=$up?$start:null;$intervals=[];foreach($events as $event){if($event['up']&&!$up){$from=$event['time'];$up=true;}elseif(!$event['up']&&$up&&$from){$intervals[]=['start'=>self::date($from),'end'=>self::date($event['time'])];$from=null;$up=false;}}if($up&&$from)$intervals[]=['start'=>self::date($from),'end'=>self::date($end)];return$intervals;}
    public static function duration(array $intervals):int{$seconds=0;foreach($intervals as $i)$seconds+=(int)round((float)(new DateTimeImmutable($i['end']))->format('U.u')-(float)(new DateTimeImmutable($i['start']))->format('U.u'));return$seconds;}
    public static function period(?SolarPeriod $period):array{return['start'=>self::date($period?->start),'end'=>self::date($period?->end),'duration_seconds'=>$period?->durationSeconds];}
}
