<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;

final class LunarDayCalculator
{
    private const SAMPLE_SECONDS=300;
    private const MOON_RADIUS_KM=1737.4;
    public function __construct(private readonly MeeusLunarCalculator $calculator) {}

    public function calculate(DateTimeImmutable $localDate,float $latitude,float $longitude,float $elevation=0.0): LunarDay
    {
        $zone=$localDate->getTimezone();
        $start=new DateTimeImmutable($localDate->format('Y-m-d').' 00:00:00',$zone);
        $end=$start->modify('+1 day');
        $startTs=(float)$start->format('U.u'); $endTs=(float)$end->format('U.u');
        $events=[]; $previousTs=$startTs; $previous=$this->horizonValue($previousTs,$zone,$latitude,$longitude,$elevation);
        $isUp=$previous>=0; $intervalStart=$isUp?$start:null; $intervals=[];
        for($ts=$startTs+self::SAMPLE_SECONDS;$ts<=$endTs;$ts=min($ts+self::SAMPLE_SECONDS,$endTs)) {
            $value=$this->horizonValue($ts,$zone,$latitude,$longitude,$elevation);
            if(($previous<0&&$value>=0)||($previous>=0&&$value<0)) {
                $root=$this->bisect($previousTs,$ts,$zone,$latitude,$longitude,$elevation);
                if($root<$endTs) $events[]=['time'=>$this->fromTimestamp($root,$zone),'rising'=>$value>$previous];
            }
            if($ts>=$endTs) break;
            $previousTs=$ts; $previous=$value;
        }
        foreach($events as $event) {
            if($event['rising']&&!$isUp){$intervalStart=$event['time'];$isUp=true;}
            elseif(!$event['rising']&&$isUp&&$intervalStart!==null){$intervals[]=new LunarVisibilityInterval($intervalStart,$event['time']);$intervalStart=null;$isUp=false;}
        }
        if($isUp&&$intervalStart!==null)$intervals[]=new LunarVisibilityInterval($intervalStart,$end);
        $rise=null;$set=null;
        foreach($events as $event){if($event['rising']&&$rise===null)$rise=$event['time'];if(!$event['rising']&&$set===null)$set=$event['time'];}
        return new LunarDay($rise,$set,$intervals);
    }
    private function horizonValue(float $ts,DateTimeZone $zone,float $lat,float $lon,float $elevation): float
    {
        $p=$this->calculator->calculate($this->fromTimestamp($ts,$zone),$lat,$lon,$elevation);
        $angularRadius=rad2deg(asin(self::MOON_RADIUS_KM/$p->topocentricDistanceKilometers));
        return $p->altitudeDegrees-(-(34.0/60.0)-$angularRadius);
    }
    private function bisect(float $left,float $right,DateTimeZone $zone,float $lat,float $lon,float $elevation): float
    {
        $lv=$this->horizonValue($left,$zone,$lat,$lon,$elevation);
        while($right-$left>0.05){$mid=($left+$right)/2;$mv=$this->horizonValue($mid,$zone,$lat,$lon,$elevation);if(($lv<0)===($mv<0)){$left=$mid;$lv=$mv;}else{$right=$mid;}}
        return ($left+$right)/2;
    }
    private function fromTimestamp(float $ts,DateTimeZone $zone): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',$ts))->setTimezone($zone);
    }
}
