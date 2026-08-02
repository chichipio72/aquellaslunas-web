<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeImmutable;
final readonly class SolarEclipse
{
    /** @param array{gamma:float,u:float,magnitude:?float,moon_sun_radius_ratio:float} $magnitudes @param array{gamma:float,u:float,moon_distance_km:float,sun_distance_km:float} $maximumGeometry @param array<string,DateTimeImmutable> $contacts */
    public function __construct(public string $group,public string $subtype,public string $classification,public DateTimeImmutable $maximum,public array $magnitudes,public array $maximumGeometry,public array $contacts,public string $contactAvailability,public string $calculationModel,public PrecisionProfile $precisionProfile=PrecisionProfile::Normal){}
}
