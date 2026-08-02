<?php

declare(strict_types=1);

namespace AstronomyEngine;

enum PrecisionProfile: string
{
    case Fast = 'rapido';
    case Normal = 'normal';
    case Precise = 'preciso';
}
