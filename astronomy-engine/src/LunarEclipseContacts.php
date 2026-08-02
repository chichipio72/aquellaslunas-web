<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeImmutable;
final readonly class LunarEclipseContacts
{
    public function __construct(public DateTimeImmutable $P1,public ?DateTimeImmutable $U1,public ?DateTimeImmutable $U2,public DateTimeImmutable $MAX,public ?DateTimeImmutable $U3,public ?DateTimeImmutable $U4,public DateTimeImmutable $P4){}
    /** @return array<string,?DateTimeImmutable> */public function all():array{return['P1'=>$this->P1,'U1'=>$this->U1,'U2'=>$this->U2,'MAX'=>$this->MAX,'U3'=>$this->U3,'U4'=>$this->U4,'P4'=>$this->P4];}
}
