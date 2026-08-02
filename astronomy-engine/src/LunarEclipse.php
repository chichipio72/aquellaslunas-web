<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeImmutable;
final readonly class LunarEclipse
{
    /** @param array{umbral:float,penumbral:float} $magnitudes @param array{penumbral:int,umbral:?int,total:?int} $durationsSeconds @param array{closest_approach_radians:float,moon_radius_radians:float,penumbra_radius_radians:float,umbra_radius_radians:float} $maximumGeometry */
    public function __construct(public string $group,public string $subtype,public string $classification,public DateTimeImmutable $maximum,public LunarEclipseContacts $contacts,public array $magnitudes,public array $durationsSeconds,public array $maximumGeometry,public string $calculationModel,public PrecisionProfile $precisionProfile=PrecisionProfile::Precise){}
}
