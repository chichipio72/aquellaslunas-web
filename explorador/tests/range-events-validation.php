<?php

declare(strict_types=1);

use AstronomyEngine\LunarDayCalculator;
use AstronomyEngine\LunarEventsRangeCalculator;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\SolarDayCalculator;
use AstronomyEngine\SolarEventsRangeCalculator;

require_once dirname(__DIR__) . '/includes/astronomy-engine-autoload.php';

/** @return int|float */
function clockNow() { return function_exists('hrtime') ? hrtime(true) : microtime(true) * 1_000_000_000; }
/** @param int|float $started */
function elapsedMs($started): float { return (clockNow() - $started) / 1_000_000; }

/** @return array<string,mixed> */
function compareLocation(string $name, float $lat, float $lon, string $timezone): array
{
    $zone = new DateTimeZone($timezone);
    $from = new DateTimeImmutable('2020-01-01', $zone);
    $to = new DateTimeImmutable('2020-12-31', $zone);
    $solarRange = (new SolarEventsRangeCalculator())->calculate($from, $to, $lat, $lon);
    $lunarRange = (new LunarEventsRangeCalculator())->calculate($from, $to, $lat, $lon);
    $sunPositions = new MeeusSolarPositionCalculator();
    $moonPositions = new MeeusLunarCalculator();
    $solarDaily = new SolarDayCalculator($sunPositions);
    $lunarDaily = new LunarDayCalculator($moonPositions);
    $compared = 0; $discrepancies = 0; $maxSeconds = 0.0; $maxAzimuth = 0.0;
    $date = $from; $index = 0;
    while ($date <= $to) {
        $solar = $solarDaily->calculate($date, $lat, $lon);
        $lunar = $lunarDaily->calculate($date, $lat, $lon);
        foreach ([
            ['sunrise', $solar->sunrise, $solarRange['items'][$index]['rise'], $solarRange['items'][$index]['riseAzimuthDegrees'], $sunPositions],
            ['sunset', $solar->sunset, $solarRange['items'][$index]['set'], $solarRange['items'][$index]['setAzimuthDegrees'], $sunPositions],
            ['moonrise', $lunar->moonrise, $lunarRange['items'][$index]['rise'], $lunarRange['items'][$index]['riseAzimuthDegrees'], $moonPositions],
            ['moonset', $lunar->moonset, $lunarRange['items'][$index]['set'], $lunarRange['items'][$index]['setAzimuthDegrees'], $moonPositions],
        ] as [$type, $expected, $actual, $actualAzimuth, $positions]) {
            $compared++;
            if (($expected === null) !== ($actual === null)) { $discrepancies++; continue; }
            if ($expected === null) continue;
            if ($actual->format('Y-m-d') !== $date->format('Y-m-d')) { $discrepancies++; continue; }
            $seconds = abs((float) $expected->format('U.u') - (float) $actual->format('U.u'));
            $expectedAzimuth = $positions->calculate($expected, $lat, $lon)->azimuthDegrees;
            $azimuth = abs($expectedAzimuth - $actualAzimuth);
            $maxSeconds = max($maxSeconds, $seconds);
            $maxAzimuth = max($maxAzimuth, $azimuth);
            if ($seconds > 0.11 || $azimuth > 0.001) $discrepancies++;
        }
        $date = $date->modify('+1 day'); $index++;
    }
    return ['location' => $name, 'events_compared' => $compared, 'discrepancies' => $discrepancies,
        'max_seconds' => $maxSeconds, 'max_azimuth_degrees' => $maxAzimuth,
        'solar_metrics' => $solarRange['metrics'], 'lunar_metrics' => $lunarRange['metrics']];
}

/** @return array<string,mixed> */
function benchmarkRange(string $label, string $fromValue, string $toValue, string $body, string $implementation = 'both'): array
{
    $zone = new DateTimeZone('America/Argentina/Buenos_Aires');
    $from = new DateTimeImmutable($fromValue, $zone); $to = new DateTimeImmutable($toValue, $zone);
    $lat = -34.6037; $lon = -58.3816;
    if ($body === 'sun') {
        $positions = new MeeusSolarPositionCalculator(); $daily = new SolarDayCalculator($positions);
        $dailyMs = null;
        if ($implementation !== 'predictive') {
            $started = clockNow(); for ($date = $from; $date <= $to; $date = $date->modify('+1 day')) {
            $day = $daily->calculate($date, $lat, $lon);
            if ($day->sunrise) $positions->calculate($day->sunrise, $lat, $lon);
            if ($day->sunset) $positions->calculate($day->sunset, $lat, $lon);
            $positions->calculate($date, $lat, $lon);
            }
            $dailyMs = elapsedMs($started);
        }
        $range = null; $predictiveMs = null;
        if ($implementation !== 'daily') {
            $started = clockNow(); $range = (new SolarEventsRangeCalculator())->calculate($from, $to, $lat, $lon); $predictiveMs = elapsedMs($started);
        }
    } else {
        $positions = new MeeusLunarCalculator(); $daily = new LunarDayCalculator($positions);
        $dailyMs = null;
        if ($implementation !== 'predictive') {
            $started = clockNow(); for ($date = $from; $date <= $to; $date = $date->modify('+1 day')) {
            $day = $daily->calculate($date, $lat, $lon);
            if ($day->moonrise) $positions->calculate($day->moonrise, $lat, $lon);
            if ($day->moonset) $positions->calculate($day->moonset, $lat, $lon);
            $positions->calculate($date, $lat, $lon);
            }
            $dailyMs = elapsedMs($started);
        }
        $range = null; $predictiveMs = null;
        if ($implementation !== 'daily') {
            $started = clockNow(); $range = (new LunarEventsRangeCalculator())->calculate($from, $to, $lat, $lon); $predictiveMs = elapsedMs($started);
        }
    }
    $days = (int) $from->diff($to)->days + 1;
    return ['label' => $label, 'body' => $body, 'days' => $days, 'daily_ms' => $dailyMs,
        'implementation' => $implementation, 'predictive_ms' => $predictiveMs,
        'speedup' => $dailyMs !== null && $predictiveMs !== null ? $dailyMs / $predictiveMs : null,
        'daily_base_evaluations_lower_bound' => $days * 289,
        'predictive_counted_evaluations' => $range ? $range['metrics']['search_position_evaluations'] + $range['metrics']['output_position_evaluations'] : null,
        'fallback_days' => $range['metrics']['fallback_days'] ?? null, 'event_fallbacks' => $range['metrics']['event_fallbacks'] ?? null];
}

$mode = $argv[1] ?? 'compare';
if ($mode === 'compare') {
    $locations = [
        ['CABA', -34.6037, -58.3816, 'America/Argentina/Buenos_Aires'],
        ['Madrid', 40.4168, -3.7038, 'Europe/Madrid'],
        ['Quito', -0.1807, -78.4678, 'America/Guayaquil'],
        ['Tromso', 69.6492, 18.9553, 'Europe/Oslo'],
    ];
    $results = [];
    foreach ($locations as [$name, $lat, $lon, $timezone]) $results[] = compareLocation($name, $lat, $lon, $timezone);
} elseif ($mode === 'benchmark') {
    $ranges = [['1y', '2020-01-01', '2020-12-31'], ['10y', '2011-01-01', '2020-12-31'],
        ['2221d', '2014-01-01', '2020-01-30']];
    $requestedLabel = $argv[2] ?? null;
    $requestedBody = $argv[3] ?? null;
    $implementation = $argv[4] ?? 'both';
    if (!in_array($implementation, ['both', 'daily', 'predictive'], true)) throw new InvalidArgumentException('Unknown implementation.');
    $results = [];
    foreach ($ranges as [$label, $from, $to]) {
        if ($requestedLabel !== null && $requestedLabel !== $label) continue;
        foreach (['sun', 'moon'] as $body) {
            if ($requestedBody !== null && $requestedBody !== $body) continue;
            $results[] = benchmarkRange($label, $from, $to, $body, $implementation);
        }
    }
    if ($results === []) throw new InvalidArgumentException('Unknown benchmark range or body.');
} else {
    throw new InvalidArgumentException('Use compare or benchmark.');
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
