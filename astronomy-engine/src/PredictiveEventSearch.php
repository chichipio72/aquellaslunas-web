<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;

/** Internal window search shared by the predictive range calculators. */
final class PredictiveEventSearch
{
    private const ROOT_TOLERANCE_SECONDS = 0.05;
    private int $evaluations = 0;

    /**
     * @param list<int> $halfWindowsSeconds
     * @param callable(float):float $valueAt
     */
    public function locate(
        float $prediction,
        float $dayStart,
        float $dayEnd,
        bool $rising,
        array $halfWindowsSeconds,
        callable $valueAt,
    ): ?float {
        foreach ($halfWindowsSeconds as $halfWindow) {
            $left = max($dayStart, $prediction - $halfWindow);
            $right = min($dayEnd, $prediction + $halfWindow);
            if ($right <= $left) {
                continue;
            }
            $leftValue = $this->evaluate($valueAt, $left);
            $rightValue = $this->evaluate($valueAt, $right);
            $bracketed = $rising
                ? $leftValue < 0.0 && $rightValue >= 0.0
                : $leftValue >= 0.0 && $rightValue < 0.0;
            if (!$bracketed) {
                continue;
            }
            while ($right - $left > self::ROOT_TOLERANCE_SECONDS) {
                $middle = ($left + $right) / 2.0;
                $middleValue = $this->evaluate($valueAt, $middle);
                if (($leftValue < 0.0) === ($middleValue < 0.0)) {
                    $left = $middle;
                    $leftValue = $middleValue;
                } else {
                    $right = $middle;
                }
            }
            $root = ($left + $right) / 2.0;
            return $root < $dayEnd ? $root : null;
        }
        return null;
    }

    public function evaluations(): int
    {
        return $this->evaluations;
    }

    public function resetEvaluations(): void
    {
        $this->evaluations = 0;
    }

    public static function fromTimestamp(float $timestamp, DateTimeZone $zone): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp));
        return $date->setTimezone($zone);
    }

    /** @param callable(float):float $valueAt */
    private function evaluate(callable $valueAt, float $timestamp): float
    {
        $this->evaluations++;
        return $valueAt($timestamp);
    }
}
