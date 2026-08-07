<?php

declare(strict_types=1);

namespace Explorador;

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\LunarDayCalculator;
use AstronomyEngine\LunarEventsRangeCalculator;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\SolarDayCalculator;
use AstronomyEngine\SolarEventsRangeCalculator;
use DateTimeImmutable;

final class SeriesBuilder
{
    // Selector temporal de desarrollo. Cambiar a false restaura el proveedor diario anterior.
    private const USE_PREDICTIVE_EVENT_RANGES = true;

    public function __construct(private readonly VariableCatalog $catalog) {}

    /** @param array{selected:list<string>,required:list<string>,providers:list<string>} $plan @return array{rows:array<int,array<string,mixed>>,metrics:array<string,mixed>} */
    public function build(SeriesRequest $request, array $plan, ?callable $progress = null, ?array $selectedDates = null): array
    {
        $startedTotal = self::now();
        $astronomyMs = 0.0;
        $derivedMs = 0.0;
        $constructionMs = 0.0;
        $providers = array_fill_keys($plan['providers'], true);
        $filtered = $selectedDates !== null;
        $workDates = $selectedDates ?? self::dailyDates($request);
        $workCount = count($workDates);
        $required = array_fill_keys($plan['required'], true);
        $selected = array_fill_keys($plan['selected'], true);
        $moon = isset($providers['moon_instant']) || (isset($providers['moon_events']) && ($filtered || !self::USE_PREDICTIVE_EVENT_RANGES)) ? new MeeusLunarCalculator() : null;
        $sun = isset($providers['sun_instant']) || (isset($providers['sun_events']) && ($filtered || !self::USE_PREDICTIVE_EVENT_RANGES)) ? new MeeusSolarPositionCalculator() : null;
        $moonDay = isset($providers['moon_events']) && ($filtered || !self::USE_PREDICTIVE_EVENT_RANGES) ? new LunarDayCalculator($moon) : null;
        $sunDay = isset($providers['sun_events']) && ($filtered || !self::USE_PREDICTIVE_EVENT_RANGES) ? new SolarDayCalculator($sun) : null;
        $observer = new AstronomyObserver($request->latitude, $request->longitude, $request->timezone->getName());
        $moonEventItems = $sunEventItems = [];
        $eventProviderMetrics = [];
        $isolatedAuxiliaryDays = ['moon_events' => 0, 'sun_events' => 0];
        if (isset($providers['moon_events']) && self::USE_PREDICTIVE_EVENT_RANGES && !$filtered) {
            $started = self::now();
            $range = (new LunarEventsRangeCalculator())->calculate(
                $request->from, $request->to, $request->latitude, $request->longitude, 0.0, $progress
            );
            $astronomyMs += self::elapsed($started);
            $moonEventItems = $range['items'];
            $eventProviderMetrics['moon_events'] = $range['metrics'];
        }
        if (isset($providers['sun_events']) && self::USE_PREDICTIVE_EVENT_RANGES && !$filtered) {
            $started = self::now();
            $range = (new SolarEventsRangeCalculator())->calculate(
                $request->from, $request->to, $request->latitude, $request->longitude, 0.0, $progress
            );
            $astronomyMs += self::elapsed($started);
            $sunEventItems = $range['items'];
            $eventProviderMetrics['sun_events'] = $range['metrics'];
        }
        if (isset($providers['moon_events']) && $filtered) {
            $processed = 0;
            $lastProgressAt = microtime(true);
            self::reportProgress($progress, 'lunar_events', 0, $workCount, $lastProgressAt, true);
            foreach ($workDates as $entry) {
                $date = new DateTimeImmutable($entry['date'] . ' 00:00:00', $request->timezone);
                $started = self::now();
                $day = $moonDay->calculate($date, $request->latitude, $request->longitude);
                $riseAzimuth = isset($required['moonrise_azimuth']) && $day->moonrise
                    ? $moon->calculate($day->moonrise, $request->latitude, $request->longitude)->azimuthDegrees : null;
                $setAzimuth = isset($required['moonset_azimuth']) && $day->moonset
                    ? $moon->calculate($day->moonset, $request->latitude, $request->longitude)->azimuthDegrees : null;
                $seconds = 0.0;
                if (isset($required['moon_above_horizon_hours'])) {
                    foreach ($day->visibilityIntervals as $interval) {
                        $seconds += (float) $interval->end->format('U.u') - (float) $interval->start->format('U.u');
                    }
                }
                $previous = ['rise' => null, 'set' => null];
                if (isset($selected['moonrise_daily_difference']) || isset($selected['moonset_daily_difference'])) {
                    $previousDay = $moonDay->calculate($date->modify('-1 day'), $request->latitude, $request->longitude);
                    $previous = ['rise' => $previousDay->moonrise, 'set' => $previousDay->moonset];
                    $isolatedAuxiliaryDays['moon_events']++;
                }
                $astronomyMs += self::elapsed($started);
                $moonEventItems[] = ['date' => $entry['date'], 'rise' => $day->moonrise, 'set' => $day->moonset,
                    'riseAzimuthDegrees' => $riseAzimuth, 'setAzimuthDegrees' => $setAzimuth,
                    'aboveHorizonSeconds' => $seconds, 'previous' => $previous];
                $processed++;
                self::reportProgress($progress, 'lunar_events', $processed, $workCount, $lastProgressAt, $processed === $workCount);
            }
            $eventProviderMetrics['moon_events'] = ['selected_dates' => $workCount,
                'auxiliary_days' => $isolatedAuxiliaryDays['moon_events'],
                'full_daily_calculations' => $workCount + $isolatedAuxiliaryDays['moon_events']];
        }
        if (isset($providers['sun_events']) && $filtered) {
            $processed = 0;
            $lastProgressAt = microtime(true);
            self::reportProgress($progress, 'solar_events', 0, $workCount, $lastProgressAt, true);
            foreach ($workDates as $entry) {
                $date = new DateTimeImmutable($entry['date'] . ' 00:00:00', $request->timezone);
                $started = self::now();
                $day = $sunDay->calculate($date, $request->latitude, $request->longitude);
                $riseAzimuth = isset($required['sunrise_azimuth']) && $day->sunrise
                    ? $sun->calculate($day->sunrise, $request->latitude, $request->longitude)->azimuthDegrees : null;
                $setAzimuth = isset($required['sunset_azimuth']) && $day->sunset
                    ? $sun->calculate($day->sunset, $request->latitude, $request->longitude)->azimuthDegrees : null;
                $daylightSeconds = isset($required['daylight_hours']) || isset($required['night_hours'])
                    ? self::solarVisibilitySeconds($date, $date->modify('+1 day'), $day->sunrise, $day->sunset, $sun, $request) : 0.0;
                $previous = ['rise' => null, 'set' => null];
                if (isset($selected['sunrise_daily_difference']) || isset($selected['sunset_daily_difference'])) {
                    $previousDay = $sunDay->calculate($date->modify('-1 day'), $request->latitude, $request->longitude);
                    $previous = ['rise' => $previousDay->sunrise, 'set' => $previousDay->sunset];
                    $isolatedAuxiliaryDays['sun_events']++;
                }
                $civilSeconds = (float) $date->modify('+1 day')->format('U.u') - (float) $date->format('U.u');
                $astronomyMs += self::elapsed($started);
                $sunEventItems[] = ['date' => $entry['date'], 'rise' => $day->sunrise, 'set' => $day->sunset,
                    'riseAzimuthDegrees' => $riseAzimuth, 'setAzimuthDegrees' => $setAzimuth,
                    'daylightSeconds' => $daylightSeconds, 'nightSeconds' => $civilSeconds - $daylightSeconds,
                    'previous' => $previous];
                $processed++;
                self::reportProgress($progress, 'solar_events', $processed, $workCount, $lastProgressAt, $processed === $workCount);
            }
            $eventProviderMetrics['sun_events'] = ['selected_dates' => $workCount,
                'auxiliary_days' => $isolatedAuxiliaryDays['sun_events'],
                'full_daily_calculations' => $workCount + $isolatedAuxiliaryDays['sun_events']];
        }
        $moonInstantItems = $sunInstantItems = [];
        if (isset($providers['moon_instant'])) {
            $processed = 0;
            $lastProgressAt = microtime(true);
            self::reportProgress($progress, 'lunar_instant', 0, $workCount, $lastProgressAt, true);
            foreach ($workDates as $entry) {
                $instant = new DateTimeImmutable($entry['date'] . ' 00:00:00', $request->timezone);
                $started = self::now();
                $position = $moon->calculate($instant, $request->latitude, $request->longitude);
                $astronomyMs += self::elapsed($started);
                $illumination = max(0.0, min(1.0, $position->illuminationFraction));
                $moonInstantItems[] = [
                    'moon_illumination' => $illumination * 100,
                    'moon_phase_angle' => rad2deg(acos(max(-1.0, min(1.0, 2 * $illumination - 1)))),
                    'moon_distance_geocentric' => $position->distanceKilometers,
                    'moon_distance_topocentric' => $position->topocentricDistanceKilometers,
                    'moon_ecliptic_latitude' => $position->eclipticLatitudeDegrees,
                    'moon_right_ascension' => $position->rightAscensionDegrees / 15,
                    'moon_declination' => $position->declinationDegrees,
                    'moon_altitude' => $position->altitudeDegrees,
                    'moon_azimuth' => $position->azimuthDegrees,
                    'sun_moon_angular_distance' => $position->solarElongationDegrees,
                    'moon_apparent_diameter_arcmin' => $position->geocentricApparentDiameterArcminutes(),
                ];
                $processed++;
                self::reportProgress($progress, 'lunar_instant', $processed, $workCount, $lastProgressAt, $processed === $workCount);
            }
        }
        if (isset($providers['sun_instant'])) {
            $processed = 0;
            $lastProgressAt = microtime(true);
            self::reportProgress($progress, 'solar_instant', 0, $workCount, $lastProgressAt, true);
            foreach ($workDates as $entry) {
                $instant = new DateTimeImmutable($entry['date'] . ' 00:00:00', $request->timezone);
                $started = self::now();
                $position = $sun->calculate($instant, $request->latitude, $request->longitude);
                $astronomyMs += self::elapsed($started);
                $sunInstantItems[] = [
                    'sun_altitude' => $position->altitudeDegrees,
                    'sun_azimuth' => $position->azimuthDegrees,
                    'sun_distance_km' => $position->earthSunDistanceKilometers(),
                    'sun_equation_of_time_minutes' => $position->equationOfTimeMinutes,
                ];
                $processed++;
                self::reportProgress($progress, 'solar_instant', $processed, $workCount, $lastProgressAt, $processed === $workCount);
            }
        }
        $rows = [];
        $previousEvents = [];
        $previousDayStart = null;
        $dayIndex = 0;
        if ($progress !== null) {
            $progress('derived', 0, $workCount, 0.0);
            $progress('rows', 0, $workCount, 0.0);
        }
        $lastRowsProgressAt = microtime(true);

        foreach ($workDates as $entry) {
            $dateString = $entry['date'];
            $date = new DateTimeImmutable($dateString . ' 00:00:00', $request->timezone);
            $instant = new DateTimeImmutable($dateString . ' 00:00:00', $request->timezone);
            $raw = [];
            $eventInstants = [];
            $comparisonEvents = [];

            if (isset($providers['moon_instant'])) $raw += $moonInstantItems[$dayIndex];
            if (isset($providers['sun_instant'])) $raw += $sunInstantItems[$dayIndex];
            if (isset($providers['moon_events'])) {
                if (self::USE_PREDICTIVE_EVENT_RANGES) {
                    $eventDay = $moonEventItems[$dayIndex];
                    $moonrise = $eventDay['rise']; $moonset = $eventDay['set'];
                    $riseAzimuth = $eventDay['riseAzimuthDegrees']; $setAzimuth = $eventDay['setAzimuthDegrees'];
                    $seconds = $eventDay['aboveHorizonSeconds'];
                } else {
                    $started = self::now();
                    $day = $moonDay->calculate($date, $request->latitude, $request->longitude);
                    $riseAzimuth = $day->moonrise ? $moon->calculate($day->moonrise, $request->latitude, $request->longitude)->azimuthDegrees : null;
                    $setAzimuth = $day->moonset ? $moon->calculate($day->moonset, $request->latitude, $request->longitude)->azimuthDegrees : null;
                    $astronomyMs += self::elapsed($started);
                    $seconds = 0.0;
                    foreach ($day->visibilityIntervals as $interval) {
                        $seconds += (float) $interval->end->format('U.u') - (float) $interval->start->format('U.u');
                    }
                    $moonrise = $day->moonrise; $moonset = $day->moonset;
                    unset($day);
                }
                $raw += ['moonrise_time' => self::decimalHour($moonrise), 'moonset_time' => self::decimalHour($moonset),
                    'moonrise_azimuth' => $riseAzimuth, 'moonset_azimuth' => $setAzimuth,
                    'moon_above_horizon_hours' => $seconds / 3600];
                $eventInstants['moonrise_time'] = $moonrise;
                $eventInstants['moonset_time'] = $moonset;
                if ($filtered) {
                    $comparisonEvents['moonrise_time'] = $eventDay['previous']['rise'];
                    $comparisonEvents['moonset_time'] = $eventDay['previous']['set'];
                }
            }
            if (isset($providers['sun_events'])) {
                if (self::USE_PREDICTIVE_EVENT_RANGES) {
                    $eventDay = $sunEventItems[$dayIndex];
                    $sunrise = $eventDay['rise']; $sunset = $eventDay['set'];
                    $riseAzimuth = $eventDay['riseAzimuthDegrees']; $setAzimuth = $eventDay['setAzimuthDegrees'];
                    $daylightSeconds = $eventDay['daylightSeconds']; $nightSeconds = $eventDay['nightSeconds'];
                } else {
                    $started = self::now();
                    $day = $sunDay->calculate($date, $request->latitude, $request->longitude);
                    $sunrise = $day->sunrise; $sunset = $day->sunset;
                    $riseAzimuth = $sunrise ? $sun->calculate($sunrise, $request->latitude, $request->longitude)->azimuthDegrees : null;
                    $setAzimuth = $sunset ? $sun->calculate($sunset, $request->latitude, $request->longitude)->azimuthDegrees : null;
                    $daylightSeconds = self::solarVisibilitySeconds($instant, $instant->modify('+1 day'), $sunrise, $sunset, $sun, $request);
                    $astronomyMs += self::elapsed($started);
                    $civilSeconds = (float) $instant->modify('+1 day')->format('U.u') - (float) $instant->format('U.u');
                    $nightSeconds = $civilSeconds - $daylightSeconds;
                    unset($day);
                }
                $raw += ['sunrise_time' => self::decimalHour($sunrise), 'sunset_time' => self::decimalHour($sunset),
                    'sunrise_azimuth' => $riseAzimuth, 'sunset_azimuth' => $setAzimuth,
                    'daylight_hours' => $daylightSeconds / 3600, 'night_hours' => $nightSeconds / 3600];
                $eventInstants['sunrise_time'] = $sunrise;
                $eventInstants['sunset_time'] = $sunset;
                if ($filtered) {
                    $comparisonEvents['sunrise_time'] = $eventDay['previous']['rise'];
                    $comparisonEvents['sunset_time'] = $eventDay['previous']['set'];
                }
            }

            $started = self::now();
            self::derive($raw, $eventInstants, $filtered ? $comparisonEvents : $previousEvents,
                $instant, $filtered ? $instant->modify('-1 day') : $previousDayStart);
            $derivedMs += self::elapsed($started);
            $started = self::now();
            $row = ['date' => $dateString];
            if ($filtered) {
                $row['main_phase'] = $entry['phase_label'];
                $row['phase_instant'] = $entry['instant'];
            }
            foreach ($plan['selected'] as $key) {
                $value = $raw[$key] ?? null;
                $row[$key] = $value === null ? null : round((float) $value, (int) $this->catalog->get($key)['precision']);
            }
            $rows[] = $row;
            $constructionMs += self::elapsed($started);
            $previousEvents = $eventInstants;
            $previousDayStart = $instant;
            unset($raw, $eventInstants, $row);
            $dayIndex++;
            self::reportProgress($progress, 'rows', $dayIndex, $workCount, $lastRowsProgressAt, $dayIndex === $workCount);
        }

        return ['rows' => $rows, 'metrics' => ['days' => $workCount,
            'astronomical_calculation_ms' => $astronomyMs, 'derived_calculation_ms' => $derivedMs,
            'row_construction_ms' => $constructionMs, 'builder_total_ms' => self::elapsed($startedTotal),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'event_provider' => $filtered ? 'isolated_daily' : (self::USE_PREDICTIVE_EVENT_RANGES ? 'predictive_range' : 'daily'),
            'event_provider_metrics' => $eventProviderMetrics]];
    }

    /** @return list<array{date:string}> */
    private static function dailyDates(SeriesRequest $request): array
    {
        $items = [];
        for ($date = $request->from; $date <= $request->to; $date = $date->modify('+1 day')) {
            $items[] = ['date' => $date->format('Y-m-d')];
        }
        return $items;
    }

    /** @param array<string,float|null> $raw @param array<string,DateTimeImmutable|null> $events @param array<string,DateTimeImmutable|null> $previous */
    private static function derive(array &$raw, array $events, array $previous, DateTimeImmutable $dayStart, ?DateTimeImmutable $previousDayStart): void
    {
        $raw['moonrise_amplitude'] = isset($raw['moonrise_azimuth']) && $raw['moonrise_azimuth'] !== null ? 90 - $raw['moonrise_azimuth'] : null;
        $raw['moonset_amplitude'] = isset($raw['moonset_azimuth']) && $raw['moonset_azimuth'] !== null ? $raw['moonset_azimuth'] - 270 : null;
        $raw['sunrise_amplitude'] = isset($raw['sunrise_azimuth']) && $raw['sunrise_azimuth'] !== null ? 90 - $raw['sunrise_azimuth'] : null;
        $raw['sunset_amplitude'] = isset($raw['sunset_azimuth']) && $raw['sunset_azimuth'] !== null ? $raw['sunset_azimuth'] - 270 : null;
        foreach (['moonrise_time' => 'moonrise_daily_difference', 'moonset_time' => 'moonset_daily_difference',
            'sunrise_time' => 'sunrise_daily_difference', 'sunset_time' => 'sunset_daily_difference'] as $event => $target) {
            $current = $events[$event] ?? null;
            $before = $previous[$event] ?? null;
            if ($current && $before && $previousDayStart) {
                $eventSeconds = (float) $current->format('U.u') - (float) $before->format('U.u');
                $civilSeconds = (float) $dayStart->format('U.u') - (float) $previousDayStart->format('U.u');
                $raw[$target] = ($eventSeconds - $civilSeconds) / 60;
            } else {
                $raw[$target] = null;
            }
        }
    }

    private static function solarVisibilitySeconds(DateTimeImmutable $start, DateTimeImmutable $end,
        ?DateTimeImmutable $rise, ?DateTimeImmutable $set, MeeusSolarPositionCalculator $sun, SeriesRequest $request): float
    {
        $events = [];
        if ($rise) $events[] = ['time' => $rise, 'up' => true];
        if ($set) $events[] = ['time' => $set, 'up' => false];
        usort($events, static fn(array $a, array $b): int => $a['time'] <=> $b['time']);
        $up = $sun->calculate($start, $request->latitude, $request->longitude)->altitudeDegrees >= SolarDayCalculator::SUN_HORIZON_DEGREES;
        $from = $up ? $start : null;
        $seconds = 0.0;
        foreach ($events as $event) {
            if ($event['up'] && !$up) { $from = $event['time']; $up = true; }
            elseif (!$event['up'] && $up && $from) {
                $seconds += (float) $event['time']->format('U.u') - (float) $from->format('U.u');
                $from = null; $up = false;
            }
        }
        if ($up && $from) $seconds += (float) $end->format('U.u') - (float) $from->format('U.u');
        return $seconds;
    }

    private static function decimalHour(?DateTimeImmutable $date): ?float
    {
        if (!$date) return null;
        return (int) $date->format('G') + (int) $date->format('i') / 60 + (int) $date->format('s') / 3600;
    }

    private static function reportProgress(?callable $progress, string $stage, int $processed, int $total, float &$lastAt, bool $force): void
    {
        if ($progress === null) return;
        $step = max(25, (int) ceil($total / 100));
        $now = microtime(true);
        if (!$force && $processed % $step !== 0 && $now - $lastAt < 0.15) return;
        $lastAt = $now;
        $progress($stage, $processed, $total, $total > 0 ? $processed / $total * 100.0 : 100.0);
    }

    /** @return int|float */
    private static function now() { return function_exists('hrtime') ? hrtime(true) : microtime(true) * 1_000_000_000; }
    /** @param int|float $started */
    private static function elapsed($started): float { return max(0.0, (self::now() - $started) / 1_000_000); }
}
