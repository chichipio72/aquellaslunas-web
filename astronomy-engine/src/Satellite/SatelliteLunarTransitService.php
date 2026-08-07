<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SatelliteLunarTransitService
{
    public function __construct(
        private SatelliteTleProvider $tleProvider,
        private SatelliteLunarTransitDetector $detector = new SatelliteLunarTransitDetector(),
    ) {}

    /** @param list<string> $satellites */
    public function search(
        AstronomyObserver $observer,
        DateTimeImmutable $start,
        int $hours,
        array $satellites,
    ): SatelliteLunarTransitServiceResult {
        if ($hours < 1 || $hours > 48) throw new InvalidArgumentException('Search hours must be between 1 and 48.');
        $satellites = array_values(array_unique($satellites));
        if ($satellites === []) throw new InvalidArgumentException('At least one satellite is required.');

        $started = hrtime(true);
        $resolutionStarted = hrtime(true);
        $resolved = []; $tles = []; $warnings = [];
        $counters = ['tle_cache_hits' => 0, 'tle_refreshes' => 0, 'tle_fallbacks' => 0, 'tle_fixtures' => 0];
        foreach ($satellites as $satellite) {
            if (!is_string($satellite) || $satellite === '') throw new InvalidArgumentException('Invalid satellite identifier.');
            $item = $this->tleProvider->resolve($satellite);
            $resolved[$satellite] = $item;
            $tles[$satellite] = $item->tle;
            foreach ($item->warnings as $warning) $warnings[] = $satellite . ': ' . $warning;
            $counter = match ($item->cacheStatus) {
                'cache_hit' => 'tle_cache_hits', 'refreshed' => 'tle_refreshes',
                'fallback' => 'tle_fallbacks', 'fixture' => 'tle_fixtures', default => null,
            };
            if ($counter !== null) $counters[$counter]++;
        }
        $resolutionMs = (hrtime(true) - $resolutionStarted) / 1_000_000.0;
        $search = $this->detector->search($observer, $start, $hours, $tles);
        $metrics = $search->metrics + $counters;
        $metrics['tle_resolution_ms'] = round($resolutionMs, 3);
        $metrics['service_total_ms'] = round((hrtime(true) - $started) / 1_000_000.0, 3);
        return new SatelliteLunarTransitServiceResult($search, $resolved, array_values(array_unique($warnings)), $metrics);
    }
}
