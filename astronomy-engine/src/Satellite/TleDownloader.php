<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

interface TleDownloader
{
    public function download(int $catalogNumber): string;
}
