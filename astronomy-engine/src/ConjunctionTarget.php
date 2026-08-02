<?php
declare(strict_types=1);
namespace AstronomyEngine;
final readonly class ConjunctionTarget{public function __construct(public string $id,public string $kind,public ?float $raDegrees=null,public ?float $decDegrees=null,public float $raMasPerYear=0,public float $decMasPerYear=0){}}
