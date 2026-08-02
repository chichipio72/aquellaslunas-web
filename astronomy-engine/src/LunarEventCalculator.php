<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Finds geocentric lunar events from the reusable Meeus lunar coordinates. */
final class LunarEventCalculator
{
    private readonly PrincipalPhaseCalculator $phaseCalculator;private readonly LunarLibrationCalculator $librationCalculator;private readonly ApproximatePlanetCalculator $targets;private readonly MeeusSolarPositionCalculator $sun;
    public function __construct(private readonly MeeusLunarCalculator $coordinates){$this->phaseCalculator=new PrincipalPhaseCalculator();$this->librationCalculator=new LunarLibrationCalculator();$this->targets=new ApproximatePlanetCalculator();$this->sun=new MeeusSolarPositionCalculator();}

    /** @return list<LunarEvent> */
    public function calculate(DateTimeImmutable $start, DateTimeImmutable $end,float $latitudeDegrees=0,float $longitudeDegrees=0): array
    {
        $start=$start->setTimezone(new DateTimeZone('UTC'));$end=$end->setTimezone(new DateTimeZone('UTC'));
        if($end<=$start)throw new InvalidArgumentException('Event interval end must be after start.');
        $events=[...$this->phases($start,$end),...$this->extrema($start,$end),...$this->nodes($start,$end),...$this->librations($start,$end),...$this->conjunctions($start,$end,$latitudeDegrees,$longitudeDegrees)];
        usort($events,static fn(LunarEvent $a,LunarEvent $b):int=>$a->dateTime<=>$b->dateTime);
        return $events;
    }

    /** @return list<LunarEvent> */
    private function phases(DateTimeImmutable $start,DateTimeImmutable $end):array
    {
        $events=[];foreach($this->phaseCalculator->between($start,$end) as $phase){$p=$this->coordinates->calculate($phase['dateTime'],0,0);$events[]=new LunarEvent('moon_phase',$phase['type'],$phase['dateTime'],['illumination_percent'=>$p->illuminationFraction*100,'distance_km'=>$p->distanceKilometers,'calculation_model'=>'meeus-chapter-49-delta-t'],PrecisionProfile::Precise);}return$events;
    }

    /** @return list<LunarEvent> */
    private function extrema(DateTimeImmutable $start,DateTimeImmutable $end):array
    {
        $events=[];$step=21600;$from=$this->seconds($start);$limit=$this->seconds($end);$a=$from;$b=min($a+$step,$limit);$da=$this->position($a)->distanceKilometers;$db=$this->position($b)->distanceKilometers;
        for($c=min($b+$step,$limit);$c<=$limit;$c=min($c+$step,$limit)){$dc=$this->position($c)->distanceKilometers;$type=$db<$da&&$db<$dc?'perigee':($db>$da&&$db>$dc?'apogee':null);if($type!==null){$time=$this->golden($a,$c,$type==='perigee');$p=$this->position($time);if($time>=$from&&$time<$limit)$events[]=new LunarEvent('lunar_apsis',$type,$this->date($time),['distance_km'=>$p->distanceKilometers,'illumination_percent'=>$p->illuminationFraction*100.0],PrecisionProfile::Normal);}$a=$b;$b=$c;$da=$db;$db=$dc;if($c===$limit)break;}return $events;
    }

    /** @return list<LunarEvent> */
    private function nodes(DateTimeImmutable $start,DateTimeImmutable $end):array
    {
        $events=[];$step=21600;$a=$this->seconds($start);$limit=$this->seconds($end);$fa=$this->position($a)->eclipticLatitudeDegrees;
        for($b=min($a+$step,$limit);$b<=$limit;$b=min($b+$step,$limit)){$fb=$this->position($b)->eclipticLatitudeDegrees;if(($fa<0&&$fb>=0)||($fa>0&&$fb<=0)){$type=$fa<0?'ascending_node':'descending_node';$time=$this->bisect($a,$b,fn(float $t):float=>$this->position($t)->eclipticLatitudeDegrees,0.5);$p=$this->position($time);$events[]=new LunarEvent('lunar_orbit',$type,$this->date($time),['node'=>$fa<0?'ascending':'descending','ecliptic_longitude_degrees'=>$p->eclipticLongitudeDegrees,'ecliptic_latitude_degrees'=>$p->eclipticLatitudeDegrees,'moon_distance_km'=>$p->distanceKilometers],PrecisionProfile::Normal);}$a=$b;$fa=$fb;if($b===$limit)break;}return $events;
    }

    /** @return list<LunarEvent> */
    private function librations(DateTimeImmutable $start,DateTimeImmutable $end):array
    {
        $events=[];$step=21600;$from=$this->seconds($start);$limit=$this->seconds($end);foreach(['longitude_degrees'=>['libration_east','libration_west','longitude'],'latitude_degrees'=>['libration_north','libration_south','latitude']] as $key=>[$positive,$negative,$axis]){$a=$from;$b=min($a+$step,$limit);$va=$this->librationCalculator->calculate($this->date($a))[$key];$vb=$this->librationCalculator->calculate($this->date($b))[$key];for($c=min($b+$step,$limit);$c<=$limit;$c=min($c+$step,$limit)){$vc=$this->librationCalculator->calculate($this->date($c))[$key];$maximum=$vb>=$va&&$vb>$vc;$minimum=$vb<=$va&&$vb<$vc;if($maximum||$minimum){$time=$this->goldenValue($a,$c,fn(float $t):float=>$this->librationCalculator->calculate($this->date($t))[$key],!$maximum);$values=$this->librationCalculator->calculate($this->date($time));$value=$values[$key];$other=$axis==='longitude'?$values['latitude_degrees']:$values['longitude_degrees'];$p=$this->position($time);$events[]=new LunarEvent('lunar_libration',$maximum?$positive:$negative,$this->date($time),['axis'=>$axis,'direction'=>$axis==='longitude'?($value>0?'east':'west'):($value>0?'north':'south'),'value_degrees'=>$value,'absolute_value_degrees'=>abs($value),'other_axis_degrees'=>$other,'moon_distance_km'=>$p->distanceKilometers,'highlighted'=>abs($value)>=6,'calculation_model'=>'meeus-chapter-53-optical-physical'],PrecisionProfile::Normal);}$a=$b;$b=$c;$va=$vb;$vb=$vc;if($c===$limit)break;}}return$events;
    }

    /** @return list<LunarEvent> */
    private function conjunctions(DateTimeImmutable $start,DateTimeImmutable $end,float $lat,float $lon):array
    {
        $events=[];$step=3600;$from=$this->seconds($start);$limit=$this->seconds($end);foreach(ConjunctionCatalog::targets() as $target){$a=$from-172800;$last=$limit+172800;$b=$a+$step;$va=$this->separation($a,$target);$vb=$this->separation($b,$target);for($c=$b+$step;$c<=$last;$c+=$step){$vc=$this->separation($c,$target);if($vb<=$va&&$vb<$vc){$time=$this->goldenValue($a,$c,fn(float $t):float=>$this->separation($t,$target),true);$separation=$this->separation($time,$target);if($time>=$from&&$time<$limit&&$separation<=5){$p=$this->position($time);$local=$this->localConjunction($time,$target,$lat,$lon);$events[]=new LunarEvent('lunar_conjunction',$target->id,$this->date($time),['planet'=>$target->id,'object_kind'=>$target->kind,'separation_degrees'=>$separation,'distance_km'=>$p->distanceKilometers,'illumination_percent'=>$p->illuminationFraction*100,'calculation_model'=>$target->kind==='planet'?'jpl-approximate-elements-1800-2050':'hipparcos-simbad-catalog-precessed',...$local],PrecisionProfile::Normal);}}$a=$b;$b=$c;$va=$vb;$vb=$vc;}}return$events;
    }
    private function separation(float $time,ConjunctionTarget $target):float{$date=$this->date($time);$moon=$this->position($time);$other=$target->kind==='planet'?$this->targets->target($target->id,$date):$this->targets->fixed($target,$date);return$this->angular($moon->rightAscensionDegrees,$moon->declinationDegrees,$other->rightAscensionDegrees,$other->declinationDegrees);}
    /** @return array<string,float|string|bool|null> */private function localConjunction(float $time,ConjunctionTarget $target,float $lat,float $lon):array{$date=$this->date($time);$moon=$this->coordinates->calculate($date,$lat,$lon);$other=$target->kind==='planet'?$this->targets->target($target->id,$date):$this->targets->fixed($target,$date);$targetAlt=$this->altitude($other,$date,$lat,$lon);$sunPos=$this->sun->calculate($date,$lat,$lon);$sunEq=$this->targets->sun($date);$elong=$this->angular($other->rightAscensionDegrees,$other->declinationDegrees,$sunEq->rightAscensionDegrees,$sunEq->declinationDegrees);$both=$moon->altitudeDegrees>0&&$targetAlt>0;$observable=$moon->altitudeDegrees>=10&&$targetAlt>=10&&$sunPos->altitudeDegrees<=-6&&$elong>=15;$visible=[];for($offset=-10800;$offset<=10800;$offset+=600){$d=$this->date($time+$offset);$m=$this->coordinates->calculate($d,$lat,$lon);$o=$target->kind==='planet'?$this->targets->target($target->id,$d):$this->targets->fixed($target,$d);$oa=$this->altitude($o,$d,$lat,$lon);$sa=$this->sun->calculate($d,$lat,$lon)->altitudeDegrees;$se=$this->targets->sun($d);$el=$this->angular($o->rightAscensionDegrees,$o->declinationDegrees,$se->rightAscensionDegrees,$se->declinationDegrees);if($m->altitudeDegrees>=10&&$oa>=10&&$sa<=-6&&$el>=15)$visible[]=['time'=>$d,'score'=>min($m->altitudeDegrees,$oa)-$sa/100];}$classification=$observable?'visible_at_closest_approach':($visible!==[]?'visible_nearby':'not_observable');$best=null;if($visible!==[]){usort($visible,static fn(array $a,array $b):int=>$b['score']<=>$a['score']);$best=$visible[0]['time'];usort($visible,static fn(array $a,array $b):int=>$a['time']<=>$b['time']);}$windowStart=$visible!==[]?$visible[0]['time']->format(DATE_ATOM):null;$windowEnd=$visible!==[]?$visible[array_key_last($visible)]['time']->format(DATE_ATOM):null;return['moon_altitude_degrees'=>$moon->altitudeDegrees,'target_altitude_degrees'=>$targetAlt,'sun_altitude_degrees'=>$sunPos->altitudeDegrees,'solar_elongation_degrees'=>$elong,'both_above_horizon'=>$both,'visibility_classification'=>$classification,'visible_window_start'=>$windowStart,'visible_window_end'=>$windowEnd,'best_visible_time'=>$best?->format(DATE_ATOM),'not_observable_reason'=>$classification==='not_observable'?'visibility_thresholds_not_met':null];}
    private function altitude(EquatorialCoordinates $p,DateTimeImmutable $date,float $lat,float $lon):float{$jd=2440587.5+(float)$date->format('U.u')/86400;$t=($jd-2451545)/36525;$sid=$this->signed(280.46061837+360.98564736629*($jd-2451545)+.000387933*$t*$t-$t**3/38710000+$lon);$h=deg2rad($this->signed($sid-$p->rightAscensionDegrees));$phi=deg2rad($lat);$dec=deg2rad($p->declinationDegrees);return rad2deg(asin(sin($phi)*sin($dec)+cos($phi)*cos($dec)*cos($h)));}
    private function angular(float $ra1,float $d1,float $ra2,float $d2):float{$d1=deg2rad($d1);$d2=deg2rad($d2);return rad2deg(acos(max(-1,min(1,sin($d1)*sin($d2)+cos($d1)*cos($d2)*cos(deg2rad($ra1-$ra2))))));}

    private function bisect(float $a,float $b,callable $function,float $seconds):float{for($i=0;$i<60&&$b-$a>$seconds;$i++){$m=($a+$b)/2;$fa=$function($a);$fm=$function($m);if(($fa<=0&&$fm>=0)||($fa>=0&&$fm<=0))$b=$m;else$a=$m;}return($a+$b)/2;}
    private function golden(float $a,float $b,bool $minimum):float{$ratio=(sqrt(5)-1)/2;$c=$b-$ratio*($b-$a);$d=$a+$ratio*($b-$a);for($i=0;$i<24;$i++){$fc=$this->position($c)->distanceKilometers*($minimum?1:-1);$fd=$this->position($d)->distanceKilometers*($minimum?1:-1);if($fc<$fd){$b=$d;$d=$c;$c=$b-$ratio*($b-$a);}else{$a=$c;$c=$d;$d=$a+$ratio*($b-$a);}}return($a+$b)/2;}
    private function goldenValue(float $a,float $b,callable $value,bool $minimum):float{$ratio=(sqrt(5)-1)/2;$c=$b-$ratio*($b-$a);$d=$a+$ratio*($b-$a);for($i=0;$i<28;$i++){$fc=$value($c)*($minimum?1:-1);$fd=$value($d)*($minimum?1:-1);if($fc<$fd){$b=$d;$d=$c;$c=$b-$ratio*($b-$a);}else{$a=$c;$c=$d;$d=$a+$ratio*($b-$a);}}return($a+$b)/2;}
    private function position(float $seconds):LunarPosition{return $this->coordinates->calculate($this->date($seconds),0,0,0);}
    private function date(float $seconds):DateTimeImmutable{return DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',$seconds),new DateTimeZone('UTC'))->setTimezone(new DateTimeZone('UTC'));}
    private function seconds(DateTimeImmutable $date):float{return(float)$date->format('U.u');}
    private function signed(float $value):float{$value=fmod($value,360);if($value<0)$value+=360;return$value>180?$value-360:$value;}
}
