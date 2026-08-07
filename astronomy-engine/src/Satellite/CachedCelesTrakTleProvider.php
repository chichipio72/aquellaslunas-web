<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class CachedCelesTrakTleProvider implements SatelliteTleProvider
{
    public const DEFAULT_TTL_SECONDS = 21600;
    private const CATALOG = ['iss' => 25544, 'tiangong' => 48274];
    private readonly TleParser $parser;
    private readonly Closure $clock;

    public function __construct(
        private readonly string $cachePath,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        private readonly TleDownloader $downloader = new CelesTrakTleDownloader(),
        ?callable $clock = null,
    ) {
        if ($cachePath === '') throw new RuntimeException('TLE cache path cannot be empty.');
        if ($ttlSeconds < 60 || $ttlSeconds > 604800) throw new RuntimeException('TLE cache TTL must be between 60 seconds and 7 days.');
        $this->parser = new TleParser();
        $this->clock = $clock === null
            ? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : Closure::fromCallable($clock);
    }

    public function resolve(string $satellite): ResolvedTle
    {
        if (!isset(self::CATALOG[$satellite])) throw new RuntimeException('Unsupported satellite: ' . $satellite . '.');
        $directory = dirname($this->cachePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the TLE cache directory.');
        }
        $handle = @fopen($this->cachePath, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            throw new RuntimeException('Could not lock the TLE cache.');
        }
        try {
            $now = ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
            $cache = $this->readCache($handle);
            $cached = $this->cachedEntry($cache[$satellite] ?? null, self::CATALOG[$satellite]);
            if ($cached !== null && $now->getTimestamp() - $cached['downloaded']->getTimestamp() < $this->ttlSeconds) {
                return $this->result($satellite, $cached['tle'], $cached['downloaded'], 'cache_hit', $now, []);
            }

            try {
                $tle = $this->parseResponse($this->downloader->download(self::CATALOG[$satellite]), self::CATALOG[$satellite]);
                $cache[$satellite] = [
                    'norad_catalog_number' => $tle->catalogNumber,
                    'downloaded_at_utc' => $now->format('Y-m-d\TH:i:s.uP'),
                    'name' => $tle->name, 'line1' => $tle->line1, 'line2' => $tle->line2,
                ];
                $this->writeCache($handle, $cache);
                return $this->result($satellite, $tle, $now, 'refreshed', $now, []);
            } catch (Throwable $exception) {
                if ($cached === null) {
                    throw new RuntimeException('Could not obtain a valid TLE for ' . $satellite . ': download failed and no valid cache is available.', 0, $exception);
                }
                return $this->result($satellite, $cached['tle'], $cached['downloaded'], 'fallback', $now,
                    ['TLE download failed; using the last valid cached element set.']);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<string,mixed> */
    private function readCache($handle): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        if (!is_string($contents) || trim($contents) === '') return [];
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array{tle:Tle,downloaded:DateTimeImmutable}|null */
    private function cachedEntry(mixed $entry, int $catalogNumber): ?array
    {
        if (!is_array($entry) || ($entry['norad_catalog_number'] ?? null) !== $catalogNumber
            || !is_string($entry['name'] ?? null) || !is_string($entry['line1'] ?? null)
            || !is_string($entry['line2'] ?? null) || !is_string($entry['downloaded_at_utc'] ?? null)) return null;
        try {
            $tle = $this->parser->parse($entry['name'], $entry['line1'], $entry['line2']);
            if ($tle->catalogNumber !== $catalogNumber) return null;
            $downloaded = new DateTimeImmutable($entry['downloaded_at_utc'], new DateTimeZone('UTC'));
            return ['tle' => $tle, 'downloaded' => $downloaded->setTimezone(new DateTimeZone('UTC'))];
        } catch (Throwable) {
            return null;
        }
    }

    private function parseResponse(string $response, int $catalogNumber): Tle
    {
        $lines = preg_split('/\R/', trim($response));
        if (!is_array($lines) || count($lines) !== 3) throw new RuntimeException('CelesTrak returned an invalid three-line TLE response.');
        $tle = $this->parser->parse($lines[0], $lines[1], $lines[2]);
        if ($tle->catalogNumber !== $catalogNumber) throw new RuntimeException('CelesTrak returned a different NORAD catalog number.');
        return $tle;
    }

    /** @param array<string,mixed> $cache */
    private function writeCache($handle, array $cache): void
    {
        $json = json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Could not persist the TLE cache.');
        }
    }

    /** @param list<string> $warnings */
    private function result(string $satellite, Tle $tle, DateTimeImmutable $downloaded, string $status, DateTimeImmutable $now, array $warnings): ResolvedTle
    {
        $age = max(0.0, ((float) $now->format('U.u') - (float) $tle->epochUtc->format('U.u')) / 3600.0);
        $confidence = 'normal';
        if ($age > 72.0) {
            $confidence = 'low';
            $warnings[] = 'TLE epoch is more than 72 hours old; prediction confidence is low.';
        } elseif ($age > 24.0) {
            $confidence = 'warning';
            $warnings[] = 'TLE epoch is between 24 and 72 hours old.';
        }
        return new ResolvedTle($satellite, $tle, $downloaded, $status, round($age, 3), $confidence, $warnings);
    }
}
