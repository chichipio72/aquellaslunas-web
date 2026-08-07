<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SatelliteTransitService
{
    public const SOLAR_SAFETY_WARNING = 'Nunca observar el Sol directamente ni con instrumentos sin un filtro solar certificado.';

    public function __construct(
        private SatelliteTleProvider $tleProvider,
        private SatelliteAngularTransitDetector $detector = new SatelliteAngularTransitDetector(),
    ) {}

    /** @param list<string> $satellites @param list<string> $targets */
    public function search(AstronomyObserver $observer, DateTimeImmutable $start, int $hours,
        array $satellites, array $targets = ['moon', 'sun']): SatelliteTransitServiceResult
    {
        if ($hours < 1 || $hours > 48) throw new InvalidArgumentException('Search hours must be between 1 and 48.');
        $satellites = array_values(array_unique($satellites));
        $targets = array_values(array_unique($targets));
        if ($satellites === [] || $targets === []) throw new InvalidArgumentException('Satellites and target bodies are required.');
        if (array_diff($targets, ['moon', 'sun']) !== []) throw new InvalidArgumentException('Targets must be moon and/or sun.');

        $started = hrtime(true); $resolutionStarted = hrtime(true);
        $resolved = []; $tles = []; $warnings = []; $eventWarnings = [];
        $counters = ['tle_cache_hits' => 0, 'tle_refreshes' => 0, 'tle_fallbacks' => 0, 'tle_fixtures' => 0];
        foreach ($satellites as $satellite) {
            if (!is_string($satellite) || $satellite === '') throw new InvalidArgumentException('Invalid satellite identifier.');
            $item = $this->tleProvider->resolve($satellite);
            $resolved[$satellite] = $item; $tles[$satellite] = $item->tle; $eventWarnings[$satellite] = $item->warnings;
            foreach ($item->warnings as $warning) $warnings[] = $satellite . ': ' . $warning;
            $counter = match ($item->cacheStatus) {
                'cache_hit' => 'tle_cache_hits', 'refreshed' => 'tle_refreshes',
                'fallback' => 'tle_fallbacks', 'fixture' => 'tle_fixtures', default => null,
            };
            if ($counter !== null) $counters[$counter]++;
        }
        if (in_array('sun', $targets, true)) $warnings[] = self::SOLAR_SAFETY_WARNING;
        $providers = array_map(static fn(string $target): SatelliteTransitTargetProvider => match ($target) {
            'moon' => new MoonTransitTargetProvider(), 'sun' => new SunTransitTargetProvider(),
        }, $targets);
        $resolutionMs = (hrtime(true) - $resolutionStarted) / 1_000_000.0;
        $targetWarnings = in_array('sun', $targets, true) ? ['sun' => [self::SOLAR_SAFETY_WARNING]] : [];
        $search = $this->detector->search($observer, $start, $hours, $tles, $providers, $eventWarnings, $targetWarnings);
        $metrics = $search->metrics + $counters;
        $metrics['tle_resolution_ms'] = round($resolutionMs, 3);
        $metrics['service_total_ms'] = round((hrtime(true) - $started) / 1_000_000.0, 3);
        return new SatelliteTransitServiceResult($search, $resolved, array_values(array_unique($warnings)), $metrics);
    }
}
