<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final readonly class FixtureSatelliteTleProvider implements SatelliteTleProvider
{
    private const CATALOG = ['iss' => 25544, 'tiangong' => 48274];

    public function __construct(private string $fixtureDirectory) {}

    public function resolve(string $satellite): ResolvedTle
    {
        if (!isset(self::CATALOG[$satellite])) throw new RuntimeException('Unsupported satellite: ' . $satellite . '.');
        $path = rtrim($this->fixtureDirectory, '/') . '/' . $satellite . '.tle';
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || count($lines) !== 3) throw new RuntimeException('Missing offline TLE fixture for ' . $satellite . '.');
        $tle = (new TleParser())->parse($lines[0], $lines[1], $lines[2]);
        if ($tle->catalogNumber !== self::CATALOG[$satellite]) throw new RuntimeException('Offline TLE fixture has the wrong NORAD catalog number.');
        $downloaded = (new DateTimeImmutable('@' . (string) (filemtime($path) ?: 0)))->setTimezone(new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $age = max(0.0, ((float) $now->format('U.u') - (float) $tle->epochUtc->format('U.u')) / 3600.0);
        $confidence = $age > 72.0 ? 'low' : ($age > 24.0 ? 'warning' : 'normal');
        $warnings = ['Offline fixture mode: no TLE download was attempted.'];
        if ($age > 72.0) $warnings[] = 'TLE epoch is more than 72 hours old; prediction confidence is low.';
        elseif ($age > 24.0) $warnings[] = 'TLE epoch is between 24 and 72 hours old.';
        return new ResolvedTle($satellite, $tle, $downloaded, 'fixture', round($age, 3), $confidence, $warnings);
    }
}
