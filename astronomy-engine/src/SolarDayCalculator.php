<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;

/** Calculates one local civil day from repeated geometric solar positions. */
final class SolarDayCalculator
{
    public const SUN_HORIZON_DEGREES = -50.0 / 60.0;
    private const THRESHOLDS = [-18.0, -12.0, -6.0, -4.0, 6.0, self::SUN_HORIZON_DEGREES];
    private const SAMPLE_SECONDS = 300;
    private const ROOT_TOLERANCE_SECONDS = 0.05;

    public function __construct(private readonly SolarPositionCalculator $positionCalculator)
    {
    }

    public function calculate(
        DateTimeImmutable $localDate,
        float $latitudeDegrees,
        float $longitudeDegrees,
        float $elevationMeters = 0.0,
    ): SolarDay {
        $zone = $localDate->getTimezone();
        $start = new DateTimeImmutable($localDate->format('Y-m-d') . ' 00:00:00', $zone);
        $end = $start->modify('+1 day');
        $startTimestamp = (float) $start->format('U.u');
        $endTimestamp = (float) $end->format('U.u');

        /** @var array<string, array{rising: list<DateTimeImmutable>, falling: list<DateTimeImmutable>}> $crossings */
        $crossings = [];
        foreach (self::THRESHOLDS as $threshold) {
            $crossings[$this->thresholdKey($threshold)] = ['rising' => [], 'falling' => []];
        }

        $previousTimestamp = $startTimestamp;
        $previousAltitude = $this->altitudeAt(
            $previousTimestamp, $zone, $latitudeDegrees, $longitudeDegrees, $elevationMeters
        );
        for ($timestamp = $startTimestamp + self::SAMPLE_SECONDS;
            $timestamp <= $endTimestamp;
            $timestamp = min($timestamp + self::SAMPLE_SECONDS, $endTimestamp)
        ) {
            $altitude = $this->altitudeAt(
                $timestamp, $zone, $latitudeDegrees, $longitudeDegrees, $elevationMeters
            );
            foreach (self::THRESHOLDS as $threshold) {
                $before = $previousAltitude - $threshold;
                $after = $altitude - $threshold;
                if (($before < 0.0 && $after >= 0.0) || ($before >= 0.0 && $after < 0.0)) {
                    $root = $this->bisectCrossing(
                        $previousTimestamp,
                        $timestamp,
                        $threshold,
                        $zone,
                        $latitudeDegrees,
                        $longitudeDegrees,
                        $elevationMeters,
                    );
                    if ($root < $endTimestamp) {
                        $direction = $after > $before ? 'rising' : 'falling';
                        $crossings[$this->thresholdKey($threshold)][$direction][] =
                            $this->dateTimeFromTimestamp($root, $zone);
                    }
                }
            }
            if ($timestamp >= $endTimestamp) {
                break;
            }
            $previousTimestamp = $timestamp;
            $previousAltitude = $altitude;
        }

        $rising = fn (float $threshold): ?DateTimeImmutable =>
            $crossings[$this->thresholdKey($threshold)]['rising'][0] ?? null;
        $falling = fn (float $threshold): ?DateTimeImmutable =>
            $crossings[$this->thresholdKey($threshold)]['falling'][0] ?? null;
        $sunrise = $rising(self::SUN_HORIZON_DEGREES);
        $sunset = $falling(self::SUN_HORIZON_DEGREES);

        return new SolarDay(
            sunrise: $sunrise,
            sunset: $sunset,
            solarNoon: $this->findUpperMeridianTransit(
                $startTimestamp, $endTimestamp, $zone,
                $latitudeDegrees, $longitudeDegrees, $elevationMeters,
            ),
            periods: [
                'morning_astronomical_twilight' => new SolarPeriod($rising(-18.0), $rising(-12.0)),
                'morning_nautical_twilight' => new SolarPeriod($rising(-12.0), $rising(-6.0)),
                'morning_civil_twilight' => new SolarPeriod($rising(-6.0), $sunrise),
                'evening_civil_twilight' => new SolarPeriod($sunset, $falling(-6.0)),
                'evening_nautical_twilight' => new SolarPeriod($falling(-6.0), $falling(-12.0)),
                'evening_astronomical_twilight' => new SolarPeriod($falling(-12.0), $falling(-18.0)),
                'morning_blue_hour' => new SolarPeriod($rising(-6.0), $rising(-4.0)),
                'morning_golden_hour' => new SolarPeriod($rising(-4.0), $rising(6.0)),
                'evening_golden_hour' => new SolarPeriod($falling(6.0), $falling(-4.0)),
                'evening_blue_hour' => new SolarPeriod($falling(-4.0), $falling(-6.0)),
            ],
        );
    }

    private function bisectCrossing(
        float $left,
        float $right,
        float $threshold,
        DateTimeZone $zone,
        float $latitude,
        float $longitude,
        float $elevation,
    ): float {
        $leftValue = $this->altitudeAt($left, $zone, $latitude, $longitude, $elevation) - $threshold;
        while ($right - $left > self::ROOT_TOLERANCE_SECONDS) {
            $middle = ($left + $right) / 2.0;
            $middleValue = $this->altitudeAt($middle, $zone, $latitude, $longitude, $elevation) - $threshold;
            if (($leftValue < 0.0) === ($middleValue < 0.0)) {
                $left = $middle;
                $leftValue = $middleValue;
            } else {
                $right = $middle;
            }
        }

        return ($left + $right) / 2.0;
    }

    private function findUpperMeridianTransit(
        float $start,
        float $end,
        DateTimeZone $zone,
        float $latitude,
        float $longitude,
        float $elevation,
    ): DateTimeImmutable {
        $left = $start;
        $leftValue = $this->meridianValueAt($left, $zone, $latitude, $longitude, $elevation);
        $right = min($start + 1800.0, $end);
        while ($right <= $end) {
            $rightValue = $this->meridianValueAt($right, $zone, $latitude, $longitude, $elevation);
            if ($leftValue > 0.0 && $rightValue <= 0.0) {
                break;
            }
            if ($right >= $end) {
                return $this->dateTimeFromTimestamp(($start + $end) / 2.0, $zone);
            }
            $left = $right;
            $leftValue = $rightValue;
            $right = min($right + 1800.0, $end);
        }
        while ($right - $left > self::ROOT_TOLERANCE_SECONDS) {
            $middle = ($left + $right) / 2.0;
            $middleValue = $this->meridianValueAt(
                $middle, $zone, $latitude, $longitude, $elevation
            );
            if ($middleValue > 0.0) {
                $left = $middle;
            } else {
                $right = $middle;
            }
        }

        return $this->dateTimeFromTimestamp(($left + $right) / 2.0, $zone);
    }

    private function meridianValueAt(
        float $timestamp,
        DateTimeZone $zone,
        float $latitude,
        float $longitude,
        float $elevation,
    ): float {
        $position = $this->positionCalculator->calculate(
            $this->dateTimeFromTimestamp($timestamp, $zone), $latitude, $longitude, $elevation
        );

        return sin(deg2rad($position->azimuthDegrees));
    }

    private function altitudeAt(
        float $timestamp,
        DateTimeZone $zone,
        float $latitude,
        float $longitude,
        float $elevation,
    ): float {
        return $this->positionCalculator->calculate(
            $this->dateTimeFromTimestamp($timestamp, $zone), $latitude, $longitude, $elevation
        )->altitudeDegrees;
    }

    private function dateTimeFromTimestamp(float $timestamp, DateTimeZone $zone): DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp));

        return $dateTime->setTimezone($zone);
    }

    private function thresholdKey(float $threshold): string
    {
        return sprintf('%.9F', $threshold);
    }
}
