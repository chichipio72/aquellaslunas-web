<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Predictive solar rise/set series. Each item contains date, rise, set,
 * riseAzimuthDegrees, setAzimuthDegrees, daylightSeconds and nightSeconds.
 * Date values are DateTimeImmutable instances in the requested local zone.
 */
final class SolarEventsRangeCalculator
{
    private const HALF_WINDOWS_SECONDS = [300, 1800, 7200];

    private readonly SolarDayCalculator $daily;
    private readonly PredictiveEventSearch $search;

    public function __construct(private readonly MeeusSolarPositionCalculator $positions = new MeeusSolarPositionCalculator())
    {
        $this->daily = new SolarDayCalculator($this->positions);
        $this->search = new PredictiveEventSearch();
    }

    /** @return array{items:list<array<string,mixed>>,metrics:array<string,int>} */
    public function calculate(DateTimeImmutable $from, DateTimeImmutable $to, float $latitude, float $longitude, float $elevation = 0.0, ?callable $progress = null): array
    {
        if ($from > $to || $from->getTimezone()->getName() !== $to->getTimezone()->getName()) {
            throw new InvalidArgumentException('Invalid solar range.');
        }
        $this->search->resetEvaluations();
        $items = [];
        $totalDays = (int) $from->diff($to)->days + 1;
        $processedDays = 0;
        $lastProgressAt = microtime(true);
        $this->reportProgress($progress, 0, $totalDays, $lastProgressAt, true);
        $previous = ['rise' => null, 'set' => null];
        $fallbackDays = 0;
        $eventFallbacks = 0;
        $positionEvaluations = 0;
        $date = $from;
        while ($date <= $to) {
            $zone = $date->getTimezone();
            $start = new DateTimeImmutable($date->format('Y-m-d') . ' 00:00:00', $zone);
            $end = $start->modify('+1 day');
            $startTs = (float) $start->format('U.u');
            $endTs = (float) $end->format('U.u');
            $fallback = null;
            $usedFallback = false;
            $events = [];
            foreach (['rise' => true, 'set' => false] as $type => $rising) {
                $event = null;
                if ($previous[$type] instanceof DateTimeImmutable) {
                    $prediction = (float) $previous[$type]->format('U.u') + 86400.0;
                    $root = $this->search->locate($prediction, $startTs, $endTs, $rising,
                        self::HALF_WINDOWS_SECONDS, function (float $timestamp) use ($zone, $latitude, $longitude, $elevation): float {
                            return $this->positions->calculate(
                                PredictiveEventSearch::fromTimestamp($timestamp, $zone), $latitude, $longitude, $elevation
                            )->altitudeDegrees - SolarDayCalculator::SUN_HORIZON_DEGREES;
                        });
                    if ($root !== null) {
                        $event = PredictiveEventSearch::fromTimestamp($root, $zone);
                    }
                }
                if (!$event) {
                    $fallback ??= $this->daily->calculate($date, $latitude, $longitude, $elevation);
                    $event = $type === 'rise' ? $fallback->sunrise : $fallback->sunset;
                    $eventFallbacks++;
                    $usedFallback = true;
                }
                $events[$type] = $event;
                $previous[$type] = $event;
            }
            if ($usedFallback) $fallbackDays++;
            $riseAzimuth = $events['rise'] ? $this->positions->calculate($events['rise'], $latitude, $longitude, $elevation)->azimuthDegrees : null;
            $setAzimuth = $events['set'] ? $this->positions->calculate($events['set'], $latitude, $longitude, $elevation)->azimuthDegrees : null;
            $positionEvaluations += ($events['rise'] ? 1 : 0) + ($events['set'] ? 1 : 0);
            $daylight = $this->visibilitySeconds($start, $end, $events['rise'], $events['set'], $latitude, $longitude, $elevation);
            $positionEvaluations++;
            $civilSeconds = $endTs - $startTs;
            $items[] = ['date' => $date->format('Y-m-d'), 'rise' => $events['rise'], 'set' => $events['set'],
                'riseAzimuthDegrees' => $riseAzimuth, 'setAzimuthDegrees' => $setAzimuth,
                'daylightSeconds' => $daylight, 'nightSeconds' => $civilSeconds - $daylight];
            $processedDays++;
            $this->reportProgress($progress, $processedDays, $totalDays, $lastProgressAt, $processedDays === $totalDays);
            $date = $date->modify('+1 day');
        }
        return ['items' => $items, 'metrics' => ['search_position_evaluations' => $this->search->evaluations(),
            'output_position_evaluations' => $positionEvaluations, 'fallback_days' => $fallbackDays,
            'event_fallbacks' => $eventFallbacks]];
    }

    private function reportProgress(?callable $progress, int $processed, int $total, float &$lastAt, bool $force): void
    {
        if ($progress === null) return;
        $step = max(25, (int) ceil($total / 100));
        $now = microtime(true);
        if (!$force && $processed % $step !== 0 && $now - $lastAt < 0.15) return;
        $lastAt = $now;
        $progress('solar_events', $processed, $total, $total > 0 ? $processed / $total * 100.0 : 100.0);
    }

    private function visibilitySeconds(DateTimeImmutable $start, DateTimeImmutable $end,
        ?DateTimeImmutable $rise, ?DateTimeImmutable $set, float $latitude, float $longitude, float $elevation): float
    {
        $events = [];
        if ($rise) $events[] = ['time' => $rise, 'up' => true];
        if ($set) $events[] = ['time' => $set, 'up' => false];
        usort($events, static fn(array $a, array $b): int => $a['time'] <=> $b['time']);
        $up = $this->positions->calculate($start, $latitude, $longitude, $elevation)->altitudeDegrees
            >= SolarDayCalculator::SUN_HORIZON_DEGREES;
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
}
