<?php
declare(strict_types=1);
namespace AstronomyEngine\Facade;
use InvalidArgumentException;
final readonly class AstronomyRangeFacade
{
    public function __construct(private DailyAstronomyFacade $daily=new DailyAstronomyFacade()){}
    public function calculate(string $startDate,int $days,AstronomyObserver $observer):array{if($days<1||$days>366)throw new InvalidArgumentException('Days must be between 1 and 366.');$date=FacadeSupport::localDate($startDate,$observer);$items=[];for($i=0;$i<$days;$i++){$d=$date->modify("+{$i} days");$value=$this->daily->calculate($d->format('Y-m-d'),$observer,false);$items[]=['date'=>$value['date'],'moon'=>['illumination_percent'=>$value['moon']['illumination_percent'],'age_days'=>$value['moon']['age_days'],'rise'=>$value['moon']['rise'],'set'=>$value['moon']['set'],'visibility_intervals'=>$value['moon']['visibility_intervals']],'sun'=>['rise'=>$value['sun']['rise'],'set'=>$value['sun']['set'],'day_length_seconds'=>$value['sun']['day_length_seconds'],'visibility_intervals'=>$value['sun']['visibility_intervals']]];}return['start_date'=>$startDate,'days'=>$days,'location'=>$observer->data(),'items'=>$items];}
}
