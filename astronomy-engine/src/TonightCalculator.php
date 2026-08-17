<?php

declare(strict_types=1);

namespace AstronomyEngine;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Portable observer-local visibility for one civil night. */
final class TonightCalculator
{
    private const STEP = 300;
    public const MOON_ENCOUNTER_MAX_SEPARATION_DEGREES = 10.0;
    private const STATUS_ORDER = ['visible_now'=>0,'visible_later'=>1,'visible_earlier'=>2];
    private readonly MeeusSolarPositionCalculator $sun;
    private readonly MeeusLunarCalculator $moon;
    private readonly ApproximatePlanetCalculator $planets;

    public function __construct()
    {
        $this->sun = new MeeusSolarPositionCalculator();
        $this->moon = new MeeusLunarCalculator();
        $this->planets = new ApproximatePlanetCalculator();
    }

    /** @return array<string,mixed> */
    public function calculate(DateTimeImmutable $date, AstronomyObserver $observer, string $detail='summary', ?DateTimeImmutable $now=null): array
    {
        if (!in_array($detail,['summary','full'],true)) throw new InvalidArgumentException('Detail must be summary or full.');
        $localDate = new DateTimeImmutable($date->setTimezone($observer->timezone)->format('Y-m-d').' 00:00:00',$observer->timezone);
        $year=(int)$localDate->format('Y'); if($year<1900||$year>2050) throw new InvalidArgumentException('Date must be between 1900-01-01 and 2050-12-31.');
        $start=new DateTimeImmutable($localDate->format('Y-m-d').' 12:00:00',$observer->timezone);
        $end=new DateTimeImmutable($localDate->modify('+1 day')->format('Y-m-d').' 12:00:00',$observer->timezone);
        $samples=$this->samples($start,$end); $now=($now??new DateTimeImmutable('now',new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $sun=[];$moon=[];
        foreach($samples as $instant){$sun[]=$this->sun->calculate($instant,$observer->latitudeDegrees,$observer->longitudeDegrees,$observer->elevationMeters);$moon[]=$this->moon->calculate($instant,$observer->latitudeDegrees,$observer->longitudeDegrees,$observer->elevationMeters);}
        $nightRuns=$this->runs($samples,array_map(static fn(SolarPosition $p):float=>-6.0-$p->altitudeDegrees,$sun));
        $midnight=new DateTimeImmutable($localDate->modify('+1 day')->format('Y-m-d').' 00:00:00',$observer->timezone);
        $nightRun=$this->selectNightRun($nightRuns,$midnight); $nightBounds=$nightRun===null?null:[$nightRun['start'],$nightRun['end']];
        $continuous=count($nightRuns)===1&&array_all($sun,static fn(SolarPosition $p):bool=>$p->altitudeDegrees<=-6.0);
        $night=['condition'=>'civil_darkness','sun_altitude_limit_degrees'=>-6.0,'polar_state'=>$nightRun===null?'no_civil_darkness':($continuous?'continuous_darkness':'normal')];
        if($nightRun!==null){$night['start']=$nightRun['start']->setTimezone($observer->timezone)->format(DATE_ATOM);$night['end']=$nightRun['end']->setTimezone($observer->timezone)->format(DATE_ATOM);}

        $planetDefs=[['mercury','Mercurio'],['venus','Venus'],['mars','Marte'],['jupiter','Júpiter'],['saturn','Saturno']];
        $planetResults=[];
        foreach($planetDefs as [$id,$name]){$positions=[];foreach($samples as $instant){$eq=$this->planets->target($id,$instant);$positions[]=$this->horizontal($eq,$instant,$observer);}$planetResults[]=$this->object($id,$name,'planet',$positions,10,-6,null,'naked_eye','easy',null,$samples,$sun,$moon,$now,$observer,$nightBounds,$detail==='full');}
        $moonResult=$stars=$deep=null;
        if($detail==='full'){
            $moonPositions=array_map(static fn(LunarPosition $p):array=>['alt'=>$p->altitudeDegrees,'az'=>$p->azimuthDegrees,'longitude'=>$p->eclipticLongitudeDegrees],$moon);
            $moonResult=$this->object('moon','Luna','moon',$moonPositions,10,-6,null,'naked_eye','easy',null,$samples,$sun,$moon,$now,$observer,$nightBounds,true);
            if($moonResult['visibility_status']==='not_visible_tonight')$moonResult=null;
            $stars=[];foreach(TonightCatalog::stars() as $entry){$positions=[];foreach($samples as $instant)$positions[]=$this->horizontal($this->starCoordinates($entry,$instant),$instant,$observer);$r=$this->object((string)$entry['id'],(string)$entry['name'],'star',$positions,10,-12,(float)$entry['magnitude'],'naked_eye','easy',$entry,$samples,$sun,$moon,$now,$observer,$nightBounds,true);if($r['visibility_status']!=='not_visible_tonight')$stars[]=$r;}
            usort($stars,static fn(array $a,array $b):int=>[self::STATUS_ORDER[$a['visibility_status']],$a['magnitude']??INF]<=>[self::STATUS_ORDER[$b['visibility_status']],$b['magnitude']??INF]);
            $deep=[];foreach(TonightCatalog::clusters() as $entry){$positions=[];$eq=new EquatorialCoordinates((float)$entry['ra'],(float)$entry['dec']);foreach($samples as $instant)$positions[]=$this->horizontal($this->precess($eq,$instant),$instant,$observer);$r=$this->object((string)$entry['id'],(string)$entry['name'],'open_cluster',$positions,15,-18,null,'binoculars','moderate',$entry,$samples,$sun,$moon,$now,$observer,$nightBounds,true);if($r['visibility_status']!=='not_visible_tonight')$deep[]=$r;}
        }
        $count=count(array_filter($planetResults,static fn(array $p):bool=>$p['visibility_status']!=='not_visible_tonight'));
        $reason=$nightRun===null?'no_civil_darkness':($count===0?'no_visible_planets':null);
        $encounters=$nightBounds!==null?$this->moonEncounters($samples,$sun,$moon,$observer,$nightBounds):[];
        $result=['date'=>$localDate->format('Y-m-d'),'timezone'=>$observer->timezone->getName(),'location'=>['latitude'=>$observer->latitudeDegrees,'longitude'=>$observer->longitudeDegrees],'detail'=>$detail,'night'=>$night,'generated_at'=>$now->setTimezone($observer->timezone)->setTime((int)$now->setTimezone($observer->timezone)->format('H'),(int)$now->setTimezone($observer->timezone)->format('i'),(int)$now->setTimezone($observer->timezone)->format('s'))->format(DATE_ATOM),'planets'=>$planetResults,'summary'=>['visible_planet_count'=>$count,'has_visible_planets'=>$count>0,'night_available'=>$nightRun!==null]];
        $result['moon_encounters']=$encounters;
        if($reason!==null)$result['summary']['reason']=$reason;
        if($detail==='full'){$result['stars']=$stars;$result['deep_sky_objects']=$deep;if($moonResult!==null)$result['moon']=$moonResult;}
        return $result;
    }

    /** @return list<DateTimeImmutable> */ private function samples(DateTimeImmutable $start,DateTimeImmutable $end):array{$zone=new DateTimeZone('UTC');$a=[];$s=$start->getTimestamp();$e=$end->getTimestamp();for($t=$s;$t<$e;$t+=self::STEP)$a[]=(new DateTimeImmutable('@'.$t))->setTimezone($zone);$a[]=(new DateTimeImmutable('@'.$e))->setTimezone($zone);return$a;}
    /** @param list<DateTimeImmutable> $times @param list<float> $margins @return list<array{start:DateTimeImmutable,end:DateTimeImmutable,indexes:list<int>}> */
    private function runs(array $times,array $margins):array{$runs=[];$n=count($times);for($i=0;$i<$n;$i++){if($margins[$i]<0)continue;$first=$i;while($i+1<$n&&$margins[$i+1]>=0)$i++;$last=$i;$start=$times[$first];$end=$times[$last];if($first>0)$start=$this->crossing($times[$first-1],$times[$first],$margins[$first-1],$margins[$first]);if($last+1<$n)$end=$this->crossing($times[$last],$times[$last+1],$margins[$last],$margins[$last+1]);$runs[]=['start'=>$start,'end'=>$end,'indexes'=>range($first,$last)];}return$runs;}
    private function crossing(DateTimeImmutable $left,DateTimeImmutable $right,float $lv,float $rv):DateTimeImmutable{$den=$rv-$lv;$f=$den==0?0.5:max(0,min(1,-$lv/$den));$ts=$left->getTimestamp()+($right->getTimestamp()-$left->getTimestamp())*$f;return DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',$ts))->setTimezone(new DateTimeZone('UTC'));}
    private function selectNightRun(array $runs,DateTimeImmutable $midnight):?array{if($runs===[])return null;$m=$midnight->getTimestamp();foreach($runs as $r)if($r['start']->getTimestamp()<=$m&&$m<=$r['end']->getTimestamp())return$r;usort($runs,static fn(array $a,array $b):int=>($b['end']->getTimestamp()-$b['start']->getTimestamp())<=>($a['end']->getTimestamp()-$a['start']->getTimestamp()));return$runs[0];}

    /** @param list<array<string,float>> $positions @param list<SolarPosition> $sun @param list<LunarPosition> $moon @return array<string,mixed> */
    private function object(string $id,string $name,string $kind,array $positions,float $minAlt,float $maxSun,?float $magnitude,string $aid,string $difficulty,?array $catalog,array $samples,array $sun,array $moon,DateTimeImmutable $now,AstronomyObserver $observer,?array $nightBounds,bool $constellation):array
    {
        $base=['id'=>$id,'name'=>$name,'object_kind'=>$kind,'visibility_status'=>'not_visible_tonight','visible_now'=>false,'observation_aid'=>$aid,'difficulty'=>$difficulty];if($magnitude!==null)$base['magnitude']=$magnitude;
        if($catalog!==null){if($kind==='star'){$base['catalog']='hipparcos';$base['catalog_id']='HIP '.$catalog['hip'];}else{$base['catalog']=$catalog['catalog'];$base['catalog_id']=$catalog['catalogId'];$base['reference_point']='cluster_center';}if($constellation)$base['constellation']=$this->declaredConstellation((string)$catalog['abbr'],(string)$catalog['constellation']);}
        if($nightBounds===null)return$base;$m=[];foreach($samples as $i=>$instant)$m[]=($instant>=$nightBounds[0]&&$instant<=$nightBounds[1])?min($positions[$i]['alt']-$minAlt,$maxSun-$sun[$i]->altitudeDegrees):-999;
        $runs=$this->runs($samples,$m);if($runs===[])return$base;$selected=null;$status='visible_earlier';foreach($runs as $run)if($run['start']<=$now&&$now<=$run['end']){$selected=$run;$status='visible_now';break;}if($selected===null)foreach($runs as $run)if($run['start']>$now){$selected=$run;$status='visible_later';break;}if($selected===null)$selected=$runs[array_key_last($runs)];
        $base['visibility_status']=$status;$base['visible_now']=$status==='visible_now';$base['visibility_start']=$selected['start']->setTimezone($observer->timezone)->format(DATE_ATOM);$base['visibility_end']=$selected['end']->setTimezone($observer->timezone)->format(DATE_ATOM);
        $relevant=$status==='visible_now'?$now:($status==='visible_later'?$selected['start']:$selected['end']);$index=$this->nearestIndex($samples,$relevant);
        if($status==='visible_now'){$position=match($kind){'moon'=>$this->moon->calculate($now,$observer->latitudeDegrees,$observer->longitudeDegrees,$observer->elevationMeters),'planet'=>$this->horizontal($this->planets->target($id,$now),$now,$observer),'star'=>$this->horizontal($this->starCoordinates($catalog,$now),$now,$observer),default=>$this->horizontal($this->precess(new EquatorialCoordinates((float)$catalog['ra'],(float)$catalog['dec']),$now),$now,$observer)};$base['altitude_degrees']=round($position instanceof LunarPosition?$position->altitudeDegrees:$position['alt'],1);$base['azimuth_degrees']=round(fmod(($position instanceof LunarPosition?$position->azimuthDegrees:$position['az'])+360,360),1);$base['direction']=$this->direction($base['azimuth_degrees'],$base['altitude_degrees']);}
        if($constellation&&$catalog===null){$longitude=$kind==='moon'?$moon[$index]->eclipticLongitudeDegrees:$this->eclipticLongitude($this->planets->target($id,$relevant),$relevant);$base['constellation']=$this->zodiacConstellation($longitude);}
        if($kind!=='moon'){$indexes=[];foreach($runs as $run)foreach($run['indexes'] as $i)$indexes[$i]=true;$best=null;$sep=INF;foreach(array_keys($indexes) as $i){$s=$this->separation($positions[$i]['alt'],$positions[$i]['az'],$moon[$i]->altitudeDegrees,$moon[$i]->azimuthDegrees);if($s<$sep){$sep=$s;$best=$i;}}$rounded=round($sep,1);$base['near_moon']=$rounded<=10;$base['moon_separation_degrees']=$rounded;$base['moon_separation_at']=$samples[$best]->setTimezone($observer->timezone)->format(DATE_ATOM);if($rounded<=5)$base['moon_proximity']='very_close';elseif($rounded<=10)$base['moon_proximity']='close';}
        return$base;
    }

    /** @param list<DateTimeImmutable> $samples @param list<SolarPosition> $sun @param list<LunarPosition> $moon @param array{0:DateTimeImmutable,1:DateTimeImmutable} $nightBounds @return list<array<string,mixed>> */
    private function moonEncounters(array $samples,array $sun,array $moon,AstronomyObserver $observer,array $nightBounds):array
    {
        $names=ConjunctionCatalog::names();$results=[];
        foreach(ConjunctionCatalog::targets() as $target){
            $best=null;$minimum=INF;$visibleIndexes=[];$maxSun=$target->kind==='planet'?-6.0:($target->kind==='star'?-12.0:-18.0);$minAltitude=$target->kind==='open_cluster'?15.0:10.0;
            foreach($samples as $i=>$instant){
                if($instant<$nightBounds[0]||$instant>$nightBounds[1])continue;
                $equatorial=$target->kind==='planet'?$this->planets->target($target->id,$instant):$this->planets->fixed($target,$instant);
                $position=$this->horizontal($equatorial,$instant,$observer);
                if($moon[$i]->altitudeDegrees<10.0||$position['alt']<$minAltitude||$sun[$i]->altitudeDegrees>$maxSun)continue;
                $visibleIndexes[]=$i;$separation=$this->separation($position['alt'],$position['az'],$moon[$i]->altitudeDegrees,$moon[$i]->azimuthDegrees);
                if($separation<$minimum){$minimum=$separation;$best=$i;}
            }
            if($best===null||$minimum>self::MOON_ENCOUNTER_MAX_SEPARATION_DEGREES)continue;
            $results[]=['id'=>$target->id,'name'=>$names[$target->id]??$target->id,'object_kind'=>$target->kind,'minimum_separation_degrees'=>round($minimum,1),'minimum_separation_at'=>$samples[$best]->setTimezone($observer->timezone)->format(DATE_ATOM),'visibility_start'=>$samples[$visibleIndexes[0]]->setTimezone($observer->timezone)->format(DATE_ATOM),'visibility_end'=>$samples[$visibleIndexes[array_key_last($visibleIndexes)]]->setTimezone($observer->timezone)->format(DATE_ATOM)];
        }
        usort($results,static fn(array $a,array $b):int=>[$a['minimum_separation_at'],$a['minimum_separation_degrees']]<=>[$b['minimum_separation_at'],$b['minimum_separation_degrees']]);return$results;
    }

    /** @param array<string,int|float|string> $entry */ private function starCoordinates(array $entry,DateTimeImmutable $date):EquatorialCoordinates{$jd=2440587.5+$date->getTimestamp()/86400;$years=2000+($jd-2451545)/365.25-1991.25;$dec=(float)$entry['dec']+(float)$entry['pmDec']*$years/3600000;$cos=max(1e-9,cos(deg2rad($dec)));$ra=(float)$entry['ra']+(float)$entry['pmRa']*$years/(3600000*$cos);return$this->precess(new EquatorialCoordinates($this->norm($ra),$dec),$date);}
    private function precess(EquatorialCoordinates $c,DateTimeImmutable $date):EquatorialCoordinates{$t=((2440587.5+$date->getTimestamp()/86400)-2451545)/36525;$zeta=deg2rad((2306.2181*$t+.30188*$t*$t+.017998*$t**3)/3600);$z=deg2rad((2306.2181*$t+1.09468*$t*$t+.018203*$t**3)/3600);$theta=deg2rad((2004.3109*$t-.42665*$t*$t-.041833*$t**3)/3600);$a=deg2rad($c->rightAscensionDegrees);$d=deg2rad($c->declinationDegrees);$A=cos($d)*sin($a+$zeta);$B=cos($theta)*cos($d)*cos($a+$zeta)-sin($theta)*sin($d);$C=sin($theta)*cos($d)*cos($a+$zeta)+cos($theta)*sin($d);return new EquatorialCoordinates($this->norm(rad2deg(atan2($A,$B)+$z)),rad2deg(asin($C)));}
    /** @return array{alt:float,az:float} */ private function horizontal(EquatorialCoordinates $eq,DateTimeImmutable $date,AstronomyObserver $o):array{$jd=2440587.5+$date->getTimestamp()/86400;$t=($jd-2451545)/36525;$sid=$this->norm(280.46061837+360.98564736629*($jd-2451545)+.000387933*$t*$t-$t**3/38710000);$h=deg2rad($this->signed($sid+$o->longitudeDegrees-$eq->rightAscensionDegrees));$lat=deg2rad($o->latitudeDegrees);$dec=deg2rad($eq->declinationDegrees);$alt=asin(sin($lat)*sin($dec)+cos($lat)*cos($dec)*cos($h));$az=atan2(sin($h),cos($h)*sin($lat)-tan($dec)*cos($lat));return['alt'=>rad2deg($alt),'az'=>$this->norm(rad2deg($az)+180)];}
    private function nearestIndex(array $samples,DateTimeImmutable $time):int{$best=0;$distance=INF;foreach($samples as $i=>$sample){$d=abs($sample->getTimestamp()-$time->getTimestamp());if($d<$distance){$best=$i;$distance=$d;}}return$best;}
    private function separation(float $a1,float $z1,float $a2,float $z2):float{$a1=deg2rad($a1);$a2=deg2rad($a2);$dz=deg2rad($z1-$z2);return rad2deg(acos(max(-1,min(1,sin($a1)*sin($a2)+cos($a1)*cos($a2)*cos($dz)))));}
    private function direction(float $az,float $alt):string{if($alt>=70)return'arriba';$names=['norte','noreste','este','sureste','sur','suroeste','oeste','noroeste'];return$names[((int)floor(($this->norm($az)+22.5)/45))%8];}
    /** @return array{id:string,name:string,iau_abbreviation:string} */ private function declaredConstellation(string $abbr,string $name):array{return['id'=>$this->slug($name),'name'=>$name,'iau_abbreviation'=>$abbr];}
    /** @return array{id:string,name:string,iau_abbreviation:string} */ private function zodiacConstellation(float $longitude):array{$items=[[29,'Psc','Piscis'],[54,'Ari','Aries'],[90,'Tau','Tauro'],[118,'Gem','Géminis'],[138,'Cnc','Cáncer'],[174,'Leo','Leo'],[218,'Vir','Virgo'],[241,'Lib','Libra'],[248,'Sco','Escorpio'],[266,'Oph','Ofiuco'],[300,'Sgr','Sagitario'],[327,'Cap','Capricornio'],[351,'Aqr','Acuario'],[360,'Psc','Piscis']];$l=$this->norm($longitude);foreach($items as [$limit,$abbr,$name])if($l<$limit)return$this->declaredConstellation($abbr,$name);return$this->declaredConstellation('Psc','Piscis');}
    private function eclipticLongitude(EquatorialCoordinates $eq,DateTimeImmutable $date):float{$t=((2440587.5+$date->getTimestamp()/86400)-2451545)/36525;$eps=deg2rad(23.43929111-.0130042*$t);$ra=deg2rad($eq->rightAscensionDegrees);$dec=deg2rad($eq->declinationDegrees);return$this->norm(rad2deg(atan2(sin($ra)*cos($eps)+tan($dec)*sin($eps),cos($ra))));}
    private function slug(string $v):string{$v=strtr($v,['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);return trim((string)preg_replace('/[^a-z0-9]+/','_',strtolower($v)),'_');}
    private function norm(float $v):float{$v=fmod($v,360);return$v<0?$v+360:$v;}private function signed(float $v):float{$v=$this->norm($v);return$v>180?$v-360:$v;}
}
