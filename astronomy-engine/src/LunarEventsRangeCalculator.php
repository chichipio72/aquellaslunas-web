<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Predictive lunar rise/set series. Each item contains date, rise, set,
 * riseAzimuthDegrees, setAzimuthDegrees and aboveHorizonSeconds.
 */
final class LunarEventsRangeCalculator
{
    private const MOON_RADIUS_KM = 1737.4;
    private const HALF_WINDOWS_SECONDS = [600, 3600, 14400];

    private readonly LunarDayCalculator $daily;
    private readonly PredictiveEventSearch $search;

    public function __construct(private readonly MeeusLunarCalculator $positions = new MeeusLunarCalculator())
    {
        $this->daily = new LunarDayCalculator($this->positions);
        $this->search = new PredictiveEventSearch();
    }

    /** @return array{items:list<array<string,mixed>>,metrics:array<string,int>} */
    public function calculate(DateTimeImmutable $from, DateTimeImmutable $to, float $latitude, float $longitude, float $elevation = 0.0, ?callable $progress = null): array
    {
        if ($from > $to || $from->getTimezone()->getName() !== $to->getTimezone()->getName()) {
            throw new InvalidArgumentException('Invalid lunar range.');
        }
        $this->search->resetEvaluations();
        $items = [];
        $totalDays = (int) $from->diff($to)->days + 1;
        $processedDays = 0;
        $lastProgressAt = microtime(true);
        $this->reportProgress($progress, 0, $totalDays, $lastProgressAt, true);
        $states = [
            'rise' => ['last' => null, 'lastStart' => null, 'previous' => null, 'previousStart' => null],
            'set' => ['last' => null, 'lastStart' => null, 'previous' => null, 'previousStart' => null],
        ];
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
                $state = $states[$type];
                $event = null;
                if ($state['last'] instanceof DateTimeImmutable && $state['lastStart'] instanceof DateTimeImmutable) {
                    $civilStep = $startTs - (float) $state['lastStart']->format('U.u');
                    $drift = 0.0;
                    if ($state['previous'] instanceof DateTimeImmutable && $state['previousStart'] instanceof DateTimeImmutable) {
                        $eventStep = (float) $state['last']->format('U.u') - (float) $state['previous']->format('U.u');
                        $previousCivilStep = (float) $state['lastStart']->format('U.u') - (float) $state['previousStart']->format('U.u');
                        $drift = $eventStep - $previousCivilStep;
                    }
                    $prediction = (float) $state['last']->format('U.u') + $civilStep + $drift;
                    $root = $this->search->locate($prediction, $startTs, $endTs, $rising,
                        self::HALF_WINDOWS_SECONDS, function (float $timestamp) use ($zone, $latitude, $longitude, $elevation): float {
                            $position = $this->positions->calculate(
                                PredictiveEventSearch::fromTimestamp($timestamp, $zone), $latitude, $longitude, $elevation
                            );
                            $angularRadius = rad2deg(asin(self::MOON_RADIUS_KM / $position->topocentricDistanceKilometers));
                            return $position->altitudeDegrees - (-(34.0 / 60.0) - $angularRadius);
                        });
                    if ($root !== null) $event = PredictiveEventSearch::fromTimestamp($root, $zone);
                }
                if (!$event) {
                    $fallback ??= $this->daily->calculate($date, $latitude, $longitude, $elevation);
                    $event = $type === 'rise' ? $fallback->moonrise : $fallback->moonset;
                    $eventFallbacks++;
                    $usedFallback = true;
                }
                $events[$type] = $event;
                $states[$type] = $event ? ['last' => $event, 'lastStart' => $start,
                    'previous' => $state['last'], 'previousStart' => $state['lastStart']]
                    : ['last' => null, 'lastStart' => null, 'previous' => null, 'previousStart' => null];
            }
            if ($usedFallback) $fallbackDays++;
            $riseAzimuth = $events['rise'] ? $this->positions->calculate($events['rise'], $latitude, $longitude, $elevation)->azimuthDegrees : null;
            $setAzimuth = $events['set'] ? $this->positions->calculate($events['set'], $latitude, $longitude, $elevation)->azimuthDegrees : null;
            $positionEvaluations += ($events['rise'] ? 1 : 0) + ($events['set'] ? 1 : 0);
            $above = $this->visibilitySeconds($start, $end, $events['rise'], $events['set'], $latitude, $longitude, $elevation);
            $positionEvaluations++;
            $items[] = ['date' => $date->format('Y-m-d'), 'rise' => $events['rise'], 'set' => $events['set'],
                'riseAzimuthDegrees' => $riseAzimuth, 'setAzimuthDegrees' => $setAzimuth,
                'aboveHorizonSeconds' => $above];
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
        $progress('lunar_events', $processed, $total, $total > 0 ? $processed / $total * 100.0 : 100.0);
    }

    private function visibilitySeconds(DateTimeImmutable $start, DateTimeImmutable $end,
        ?DateTimeImmutable $rise, ?DateTimeImmutable $set, float $latitude, float $longitude, float $elevation): float
    {
        $events = [];
        if ($rise) $events[] = ['time' => $rise, 'up' => true];
        if ($set) $events[] = ['time' => $set, 'up' => false];
        usort($events, static fn(array $a, array $b): int => $a['time'] <=> $b['time']);
        $position = $this->positions->calculate($start, $latitude, $longitude, $elevation);
        $angularRadius = rad2deg(asin(self::MOON_RADIUS_KM / $position->topocentricDistanceKilometers));
        $up = $position->altitudeDegrees >= (-(34.0 / 60.0) - $angularRadius);
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
