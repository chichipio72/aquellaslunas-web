<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeImmutable;
final readonly class LunarEclipseLocalCircumstances
{
    public function __construct(public bool $visible,public string $visibilityClass,public array $contacts,public array $visibleContacts,public ?DateTimeImmutable $moonrise,public ?DateTimeImmutable $moonset,public ?array $visibleInterval,public array $maximumLocalGeometry,public string $timezone,public array $observer,public string $calculationModel,public PrecisionProfile $precisionProfile=PrecisionProfile::Normal){}
}
