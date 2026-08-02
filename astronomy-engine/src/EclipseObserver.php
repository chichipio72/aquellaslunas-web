<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeZone;use InvalidArgumentException;
final readonly class EclipseObserver
{
    public DateTimeZone $timezone;
    public function __construct(public float $latitudeDegrees,public float $longitudeDegrees,string $timezone,public float $elevationMeters=0.0){if(!is_finite($latitudeDegrees)||$latitudeDegrees < -90||$latitudeDegrees > 90||!is_finite($longitudeDegrees)||$longitudeDegrees < -180||$longitudeDegrees > 180||!is_finite($elevationMeters)||$elevationMeters < -500||$elevationMeters > 10000)throw new InvalidArgumentException('Invalid eclipse observer.');$this->timezone=new DateTimeZone($timezone);}
    public function data():array{return['latitude_degrees'=>$this->latitudeDegrees,'longitude_degrees'=>$this->longitudeDegrees,'elevation_meters'=>$this->elevationMeters,'timezone'=>$this->timezone->getName()];}
}
