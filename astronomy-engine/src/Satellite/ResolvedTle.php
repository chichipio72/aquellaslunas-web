<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use DateTimeImmutable;

final readonly class ResolvedTle
{
    /** @param list<string> $warnings */
    public function __construct(
        public string $satellite,
        public Tle $tle,
        public DateTimeImmutable $downloadedAtUtc,
        public string $cacheStatus,
        public float $epochAgeHours,
        public string $confidence,
        public array $warnings,
    ) {}

    /** @return array<string,mixed> */
    public function data(): array
    {
        return [
            'satellite' => $this->satellite,
            'name' => $this->tle->name,
            'norad_catalog_number' => $this->tle->catalogNumber,
            'epoch_utc' => $this->tle->epochUtc->format('Y-m-d\TH:i:s.uP'),
            'downloaded_at_utc' => $this->downloadedAtUtc->format('Y-m-d\TH:i:s.uP'),
            'epoch_age_hours' => $this->epochAgeHours,
            'confidence' => $this->confidence,
            'cache_status' => $this->cacheStatus,
            'warnings' => $this->warnings,
            'line1' => $this->tle->line1,
            'line2' => $this->tle->line2,
        ];
    }
}
