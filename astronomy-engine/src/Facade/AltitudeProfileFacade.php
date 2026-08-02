<?php

declare(strict_types=1);

namespace AstronomyEngine\Facade;

use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\SolsticeCalculator;
use DateTimeImmutable;
use InvalidArgumentException;

/** Portable composition for daily Sun or Moon altitude profiles. */
final class AltitudeProfileFacade
{
    public const DEFAULT_INTERVAL_MINUTES = 15;

    /**
     * @param array{target?:string,interval_minutes?:int} $options
     * @return array<string,mixed>
     */
    public function calculate(DateTimeImmutable $date, AstronomyObserver $observer, array $options = []): array
    {
        $target = $options['target'] ?? 'moon';
        $interval = $options['interval_minutes'] ?? self::DEFAULT_INTERVAL_MINUTES;
        if (!in_array($target, ['sun', 'moon'], true)) {
            throw new InvalidArgumentException('Altitude profile target must be sun or moon.');
        }
        if (!is_int($interval) || $interval < 5 || $interval > 60) {
            throw new InvalidArgumentException('Interval minutes must be an integer between 5 and 60.');
        }

        $requested = new DateTimeImmutable($date->format('Y-m-d').' 00:00:00', $observer->timezone);
        $series = $target === 'moon'
            ? $this->moonSeries($requested, $observer, $interval)
            : $this->sunSeries($requested, $observer, $interval);

        return [
            'target' => $target,
            'requested_date' => $requested->format('Y-m-d'),
            'location' => [
                'latitude' => $observer->latitudeDegrees,
                'longitude' => $observer->longitudeDegrees,
                'timezone' => $observer->timezone->getName(),
            ],
            'interval_minutes' => $interval,
            'series' => $series,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function moonSeries(DateTimeImmutable $requested, AstronomyObserver $observer, int $interval): array
    {
        $calculator = new MeeusLunarCalculator();
        return [
            $this->series('previous_date', $requested->modify('-1 day'), null, $observer, $interval, $calculator),
            $this->series('requested_date', $requested, null, $observer, $interval, $calculator),
            $this->series('next_date', $requested->modify('+1 day'), null, $observer, $interval, $calculator),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function sunSeries(DateTimeImmutable $requested, AstronomyObserver $observer, int $interval): array
    {
        $solstices = (new SolsticeCalculator())->forYear((int) $requested->format('Y'));
        $june = $solstices['june'];
        $december = $solstices['december'];
        $winter = $observer->latitudeDegrees >= 0 ? $december : $june;
        $summer = $observer->latitudeDegrees >= 0 ? $june : $december;
        $calculator = new MeeusSolarPositionCalculator();
        return [
            $this->series('winter_solstice', $winter->setTimezone($observer->timezone), $winter, $observer, $interval, $calculator),
            $this->series('requested_date', $requested, null, $observer, $interval, $calculator),
            $this->series('summer_solstice', $summer->setTimezone($observer->timezone), $summer, $observer, $interval, $calculator),
        ];
    }

    private function series(string $role, DateTimeImmutable $date, ?DateTimeImmutable $reference, AstronomyObserver $observer, int $interval, object $calculator): array
    {
        $start = new DateTimeImmutable($date->setTimezone($observer->timezone)->format('Y-m-d').' 00:00:00', $observer->timezone);
        $end = $start->modify('+1 day');
        $points = [];
        for ($time = $start; $time < $end; $time = $time->modify("+{$interval} minutes")) {
            $points[] = $this->point($time, $observer, $calculator);
        }
        $points[] = $this->point($end, $observer, $calculator);
        $result = ['role' => $role, 'local_date' => $start->format('Y-m-d')];
        if ($reference !== null) {
            $result['reference_instant'] = $reference->setTimezone($observer->timezone)->format(DATE_ATOM);
        }
        $result['points'] = $points;
        return $result;
    }

    private function point(DateTimeImmutable $time, AstronomyObserver $observer, object $calculator): array
    {
        $position = $calculator->calculate($time, $observer->latitudeDegrees, $observer->longitudeDegrees, $observer->elevationMeters);
        return [
            'local_time' => $time->format(DATE_ATOM),
            'altitude_degrees' => round($position->altitudeDegrees, 3),
            'azimuth_degrees' => round($position->azimuthDegrees, 3),
            'above_horizon' => $position->altitudeDegrees > 0,
        ];
    }
}
