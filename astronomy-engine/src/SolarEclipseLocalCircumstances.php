<?php
declare(strict_types=1);
namespace AstronomyEngine;
use DateTimeImmutable;
final readonly class SolarEclipseLocalCircumstances
{
    public function __construct(public bool $visible,public ?string $localClassification,public array $contacts,public array $visibleContacts,public ?DateTimeImmutable $sunrise,public ?DateTimeImmutable $sunset,public ?array $visibleInterval,public ?float $localMagnitude,public ?float $obscuration,public ?int $centralDurationSeconds,public array $maximumLocalGeometry,public string $timezone,public array $observer,public string $calculationModel,public PrecisionProfile $precisionProfile=PrecisionProfile::Normal){}
}
