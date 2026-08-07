<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Satellite\Sgp4\Sgp4Propagator;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class SatelliteAngularTransitDetector
{
    private const COARSE_STEP_SECONDS = 30.0;
    private const PASS_ALTITUDE_MARGIN_DEGREES = -5.0;
    private const CANDIDATE_PREFILTER_DEGREES = 3.5;
    private const REFINEMENT_ITERATIONS = 36;
    private const CONTACT_STEP_SECONDS = 0.02;
    private const CONTACT_MAX_SECONDS = 12.0;
    private const CONTACT_BISECTION_ITERATIONS = 24;
    private const VERY_CLOSE_EDGE_DEGREES = 0.25;
    private const CLOSE_EDGE_DEGREES = 1.0;
    private const NEAR_PASS_EDGE_DEGREES = 3.0;

    /** @var array<string,int|float> */
    private array $metrics = [];
    private readonly SatelliteTopocentricCalculator $topocentric;

    public function __construct(private readonly float $dut1Seconds = 0.0)
    {
        if (!is_finite($dut1Seconds) || abs($dut1Seconds) > 1.0) throw new InvalidArgumentException('DUT1 must be between -1 and 1 seconds.');
        $this->topocentric = new SatelliteTopocentricCalculator($dut1Seconds);
    }

    /**
     * @param array<string,Tle> $satellites
     * @param list<SatelliteTransitTargetProvider> $targets
     * @param array<string,list<string>> $satelliteWarnings
     * @param array<string,list<string>> $targetWarnings
     */
    public function search(AstronomyObserver $observer, DateTimeImmutable $start, int $hours, array $satellites,
        array $targets, array $satelliteWarnings = [], array $targetWarnings = []): SatelliteTransitSearchResult
    {
        if ($hours < 1 || $hours > 48) throw new InvalidArgumentException('Search hours must be between 1 and 48.');
        if ($satellites === [] || $targets === []) throw new InvalidArgumentException('Satellites and target bodies are required.');
        foreach ($satellites as $id => $tle) if (!is_string($id) || $id === '' || !$tle instanceof Tle) throw new InvalidArgumentException('Invalid satellite catalog.');
        $bodies = [];
        foreach ($targets as $target) {
            if (!$target instanceof SatelliteTransitTargetProvider || isset($bodies[$target->body()])) throw new InvalidArgumentException('Invalid or duplicate target provider.');
            $bodies[$target->body()] = true;
        }
        $utc = new DateTimeZone('UTC');
        $startUtc = $start->setTimezone($utc);
        $endUtc = self::date((float) $startUtc->format('U.u') + $hours * 3600.0);
        $this->resetMetrics(array_keys($bodies));
        $started = hrtime(true); $events = [];
        foreach ($targets as $target) {
            foreach ($satellites as $id => $tle) {
                $propagator = new Sgp4Propagator($tle);
                foreach ($this->candidates($propagator, $target, $observer, $startUtc, $endUtc) as $maximum) {
                    $geometry = $this->geometryAt($propagator, $target, $observer, $maximum, 'refinement_samples');
                    if (!$geometry['eligible']) continue;
                    $classification = self::classify($geometry['separation_degrees'], $geometry['target_radius_degrees']);
                    if ($classification === 'none') continue;
                    $entry = $exit = null; $duration = null;
                    if ($classification === 'transit') {
                        [$entry, $exit] = $this->contacts($propagator, $target, $observer, $maximum);
                        if ($entry !== null && $exit !== null) $duration = (float) $exit->format('U.u') - (float) $entry->format('U.u');
                    }
                    $zone = $observer->timezone;
                    $events[] = new SatelliteTransitEvent(
                        $target->body(), $id, $tle->name, $classification, $maximum->setTimezone($zone),
                        $entry?->setTimezone($zone), $exit?->setTimezone($zone), $duration,
                        $geometry['separation_degrees'], $geometry['target_radius_degrees'],
                        $geometry['target_radius_degrees'] - $geometry['separation_degrees'],
                        $geometry['target_altitude_degrees'], $geometry['target_azimuth_degrees'],
                        $geometry['satellite_altitude_degrees'], $geometry['satellite_azimuth_degrees'],
                        $geometry['satellite_distance_km'], $tle->line1, $tle->line2, $tle->epochUtc,
                        array_values(array_unique(array_merge($satelliteWarnings[$id] ?? [], $targetWarnings[$target->body()] ?? []))),
                    );
                }
            }
        }
        usort($events, static fn(SatelliteTransitEvent $a, SatelliteTransitEvent $b): int => $a->maximum <=> $b->maximum);
        $this->metrics['events'] = count($events);
        $this->metrics['total_ms'] = self::elapsed($started);
        return new SatelliteTransitSearchResult(
            $startUtc->setTimezone($observer->timezone), $endUtc->setTimezone($observer->timezone), $observer,
            array_keys($bodies), $events, $this->roundedMetrics(), $this->dut1Seconds,
        );
    }

    public static function classify(float $separationDegrees, float $radiusDegrees): string
    {
        $edge = $separationDegrees - $radiusDegrees;
        if ($edge <= 0.0) return 'transit';
        if ($edge <= self::VERY_CLOSE_EDGE_DEGREES) return 'very_close';
        if ($edge <= self::CLOSE_EDGE_DEGREES) return 'close';
        if ($edge <= self::NEAR_PASS_EDGE_DEGREES) return 'near_pass';
        return 'none';
    }

    /**
     * Technical diagnostic only: returns the closest eligible local minimum
     * for every satellite/target pair, including minima classified as none.
     *
     * @param array<string,Tle> $satellites
     * @param list<SatelliteTransitTargetProvider> $targets
     * @return list<SatelliteTransitEvent>
     */
    public function nearestApproaches(AstronomyObserver $observer, DateTimeImmutable $start, int $hours,
        array $satellites, array $targets): array
    {
        if ($hours < 1 || $hours > 48) throw new InvalidArgumentException('Search hours must be between 1 and 48.');
        if ($satellites === [] || $targets === []) throw new InvalidArgumentException('Satellites and target bodies are required.');
        foreach ($satellites as $id => $tle) if (!is_string($id) || $id === '' || !$tle instanceof Tle) throw new InvalidArgumentException('Invalid satellite catalog.');
        $bodies = [];
        foreach ($targets as $target) {
            if (!$target instanceof SatelliteTransitTargetProvider || isset($bodies[$target->body()])) throw new InvalidArgumentException('Invalid or duplicate target provider.');
            $bodies[$target->body()] = true;
        }
        $utc = new DateTimeZone('UTC');
        $startUtc = $start->setTimezone($utc);
        $endUtc = self::date((float) $startUtc->format('U.u') + $hours * 3600.0);
        $this->resetMetrics(array_keys($bodies));
        $nearest = [];
        foreach ($targets as $target) {
            foreach ($satellites as $id => $tle) {
                $propagator = new Sgp4Propagator($tle);
                $bestDate = null; $bestGeometry = null;
                foreach ($this->candidates($propagator, $target, $observer, $startUtc, $endUtc, true) as $maximum) {
                    $geometry = $this->geometryAt($propagator, $target, $observer, $maximum, 'refinement_samples');
                    if (!$geometry['eligible'] || ($bestGeometry !== null
                        && $geometry['separation_degrees'] >= $bestGeometry['separation_degrees'])) continue;
                    $bestDate = $maximum; $bestGeometry = $geometry;
                }
                if ($bestDate === null || $bestGeometry === null) continue;
                $eventAgeHours = abs((float) $bestDate->format('U.u') - (float) $tle->epochUtc->format('U.u')) / 3600.0;
                $warnings = $eventAgeHours > 24.0
                    ? [sprintf('TLE epoch is %.1f hours from the event and is not contemporaneous.', $eventAgeHours)]
                    : [];
                $nearest[] = new SatelliteTransitEvent(
                    $target->body(), $id, $tle->name,
                    self::classify($bestGeometry['separation_degrees'], $bestGeometry['target_radius_degrees']),
                    $bestDate->setTimezone($observer->timezone), null, null, null,
                    $bestGeometry['separation_degrees'], $bestGeometry['target_radius_degrees'],
                    $bestGeometry['target_radius_degrees'] - $bestGeometry['separation_degrees'],
                    $bestGeometry['target_altitude_degrees'], $bestGeometry['target_azimuth_degrees'],
                    $bestGeometry['satellite_altitude_degrees'], $bestGeometry['satellite_azimuth_degrees'],
                    $bestGeometry['satellite_distance_km'], $tle->line1, $tle->line2, $tle->epochUtc, $warnings,
                );
            }
        }
        usort($nearest, static fn(SatelliteTransitEvent $a, SatelliteTransitEvent $b): int =>
            [$a->satellite, $a->targetBody] <=> [$b->satellite, $b->targetBody]);
        return $nearest;
    }

    /** @return list<DateTimeImmutable> */
    private function candidates(Sgp4Propagator $propagator, SatelliteTransitTargetProvider $target,
        AstronomyObserver $observer, DateTimeImmutable $start, DateTimeImmutable $end,
        bool $includeBeyondPrefilter = false): array
    {
        $coarseStarted = hrtime(true);
        $coarse = $this->timestamps((float) $start->format('U.u'), (float) $end->format('U.u'), self::COARSE_STEP_SECONDS);
        $visible = [];
        foreach ($coarse as $timestamp) {
            $position = $this->satelliteAt($propagator, $observer, $timestamp, 'coarse_samples');
            $visible[] = $position->altitudeDegrees >= self::PASS_ALTITUDE_MARGIN_DEGREES;
        }
        $runs = $this->trueRuns($visible);
        $this->metrics['pass_runs'] += count($runs);
        $this->metrics['coarse_search_ms'] += self::elapsed($coarseStarted);

        $fineStarted = hrtime(true); $candidates = [];
        foreach ($runs as [$first, $last]) {
            $fineStart = max((float) $start->format('U.u'), $coarse[max(0, $first - 1)] - 30.0);
            $fineEnd = min((float) $end->format('U.u'), $coarse[min(count($coarse) - 1, $last + 1)] + 30.0);
            $targetVisible = false;
            foreach ([$fineStart, ($fineStart + $fineEnd) / 2.0, $fineEnd] as $visibilityTimestamp) {
                if ($this->targetAt($target, self::date($visibilityTimestamp), $observer)->altitudeDegrees > 0.0) {
                    $targetVisible = true; break;
                }
            }
            if (!$targetVisible) continue;
            $samples = $this->timestamps($fineStart, $fineEnd, $target->fineStepSeconds());
            $separations = [];
            foreach ($samples as $timestamp) {
                $geometry = $this->geometryAt($propagator, $target, $observer, self::date($timestamp), 'fine_samples');
                $separations[] = $geometry['eligible'] ? $geometry['separation_degrees'] : INF;
            }
            for ($index = 1, $count = count($samples) - 1; $index < $count; $index++) {
                if (!$includeBeyondPrefilter && $separations[$index] > self::CANDIDATE_PREFILTER_DEGREES) continue;
                if ($separations[$index] <= $separations[$index - 1] && $separations[$index] < $separations[$index + 1]) {
                    $this->metrics['prefilter_candidates']++;
                    $refinementStarted = hrtime(true);
                    $candidates[] = $this->refineMinimum($propagator, $target, $observer, $samples[$index - 1], $samples[$index + 1]);
                    $this->metrics['refinement_ms'] += self::elapsed($refinementStarted);
                }
            }
        }
        $this->metrics['fine_search_ms'] += self::elapsed($fineStarted);
        usort($candidates, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
        $unique = [];
        foreach ($candidates as $candidate) {
            if ($unique === [] || (float) $candidate->format('U.u') - (float) $unique[array_key_last($unique)]->format('U.u') > 10.0) $unique[] = $candidate;
            elseif ($this->separationAt($propagator, $target, $observer, $candidate, 'refinement_samples')
                < $this->separationAt($propagator, $target, $observer, $unique[array_key_last($unique)], 'refinement_samples')) {
                $unique[array_key_last($unique)] = $candidate;
            }
        }
        $this->metrics['refined_candidates'] += count($unique);
        return $unique;
    }

    private function refineMinimum(Sgp4Propagator $propagator, SatelliteTransitTargetProvider $target,
        AstronomyObserver $observer, float $left, float $right): DateTimeImmutable
    {
        $ratio = (sqrt(5.0) - 1.0) / 2.0; $a = $left; $b = $right;
        $c = $b - $ratio * ($b - $a); $d = $a + $ratio * ($b - $a);
        $fc = $this->separationAt($propagator, $target, $observer, self::date($c), 'refinement_samples');
        $fd = $this->separationAt($propagator, $target, $observer, self::date($d), 'refinement_samples');
        for ($iteration = 0; $iteration < self::REFINEMENT_ITERATIONS; $iteration++) {
            if ($fc <= $fd) { $b = $d; $d = $c; $fd = $fc; $c = $b - $ratio * ($b - $a); $fc = $this->separationAt($propagator, $target, $observer, self::date($c), 'refinement_samples'); }
            else { $a = $c; $c = $d; $fc = $fd; $d = $a + $ratio * ($b - $a); $fd = $this->separationAt($propagator, $target, $observer, self::date($d), 'refinement_samples'); }
        }
        return self::date(($a + $b) / 2.0);
    }

    /** @return array{?DateTimeImmutable,?DateTimeImmutable} */
    private function contacts(Sgp4Propagator $propagator, SatelliteTransitTargetProvider $target,
        AstronomyObserver $observer, DateTimeImmutable $maximum): array
    {
        $started = hrtime(true); $center = (float) $maximum->format('U.u');
        if ($this->marginAt($propagator, $target, $observer, $maximum) > 0.0) return [null, null];
        $brackets = [];
        $maxSteps = (int) ceil(self::CONTACT_MAX_SECONDS / self::CONTACT_STEP_SECONDS);
        foreach ([-1, 1] as $direction) {
            $bracket = null;
            for ($index = 1; $index <= $maxSteps; $index++) {
                $candidate = $center + $direction * $index * self::CONTACT_STEP_SECONDS;
                if ($this->marginAt($propagator, $target, $observer, self::date($candidate)) > 0.0) {
                    $bracket = [min($center, $candidate), max($center, $candidate)]; break;
                }
            }
            $brackets[] = $bracket;
        }
        if ($brackets[0] === null || $brackets[1] === null) {
            $this->metrics['contacts_ms'] += self::elapsed($started); return [null, null];
        }
        $roots = [];
        foreach ($brackets as [$left, $right]) {
            $leftMargin = $this->marginAt($propagator, $target, $observer, self::date($left));
            for ($iteration = 0; $iteration < self::CONTACT_BISECTION_ITERATIONS; $iteration++) {
                $middle = ($left + $right) / 2.0;
                $middleMargin = $this->marginAt($propagator, $target, $observer, self::date($middle));
                if (($leftMargin <= 0.0) === ($middleMargin <= 0.0)) { $left = $middle; $leftMargin = $middleMargin; } else $right = $middle;
            }
            $roots[] = self::date(($left + $right) / 2.0);
        }
        $this->metrics['contacts_ms'] += self::elapsed($started);
        return $roots;
    }

    private function marginAt(Sgp4Propagator $propagator, SatelliteTransitTargetProvider $target,
        AstronomyObserver $observer, DateTimeImmutable $date): float
    {
        $geometry = $this->geometryAt($propagator, $target, $observer, $date, 'contact_samples');
        return $geometry['eligible'] ? $geometry['separation_degrees'] - $geometry['target_radius_degrees'] : INF;
    }

    private function separationAt(Sgp4Propagator $propagator, SatelliteTransitTargetProvider $target,
        AstronomyObserver $observer, DateTimeImmutable $date, string $counter): float
    {
        $geometry = $this->geometryAt($propagator, $target, $observer, $date, $counter);
        return $geometry['eligible'] ? $geometry['separation_degrees'] : INF;
    }

    /** @return array<string,float|bool> */
    private function geometryAt(Sgp4Propagator $propagator, SatelliteTransitTargetProvider $target,
        AstronomyObserver $observer, DateTimeImmutable $date, string $counter): array
    {
        $satellite = $this->satelliteAt($propagator, $observer, $date, $counter);
        $body = $this->targetAt($target, $date, $observer);
        $eligible = $body->altitudeDegrees > 0.0 && $satellite->altitudeDegrees > 0.0;
        return [
            'eligible' => $eligible,
            'separation_degrees' => $eligible ? self::angularSeparation($body->altitudeDegrees, $body->azimuthDegrees,
                $satellite->altitudeDegrees, $satellite->azimuthDegrees) : INF,
            'target_radius_degrees' => $body->apparentRadiusDegrees,
            'target_altitude_degrees' => $body->altitudeDegrees, 'target_azimuth_degrees' => $body->azimuthDegrees,
            'satellite_altitude_degrees' => $satellite->altitudeDegrees, 'satellite_azimuth_degrees' => $satellite->azimuthDegrees,
            'satellite_distance_km' => $satellite->distanceKilometers,
        ];
    }

    private function targetAt(SatelliteTransitTargetProvider $target, DateTimeImmutable $date,
        AstronomyObserver $observer): SatelliteTransitTargetPosition
    {
        $started = hrtime(true); $body = $target->calculate($date, $observer);
        $this->metrics[$target->body() . '_ms'] += self::elapsed($started);
        return $body;
    }

    private function satelliteAt(Sgp4Propagator $propagator, AstronomyObserver $observer,
        DateTimeImmutable|float $date, string $counter): SatelliteTopocentricPosition
    {
        $date = is_float($date) ? self::date($date) : $date;
        $started = hrtime(true); $state = $propagator->propagate($date);
        $this->metrics['propagation_ms'] += self::elapsed($started);
        $started = hrtime(true); $position = $this->topocentric->calculate($state, $observer);
        $this->metrics['topocentric_ms'] += self::elapsed($started);
        $this->metrics[$counter]++; $this->metrics['total_samples']++;
        return $position;
    }

    private static function angularSeparation(float $altitude1, float $azimuth1, float $altitude2, float $azimuth2): float
    {
        $a1 = deg2rad($altitude1); $z1 = deg2rad($azimuth1); $a2 = deg2rad($altitude2); $z2 = deg2rad($azimuth2);
        $u = [cos($a1) * sin($z1), cos($a1) * cos($z1), sin($a1)];
        $v = [cos($a2) * sin($z2), cos($a2) * cos($z2), sin($a2)];
        $cross = [$u[1] * $v[2] - $u[2] * $v[1], $u[2] * $v[0] - $u[0] * $v[2], $u[0] * $v[1] - $u[1] * $v[0]];
        return rad2deg(atan2(sqrt($cross[0] ** 2 + $cross[1] ** 2 + $cross[2] ** 2), $u[0] * $v[0] + $u[1] * $v[1] + $u[2] * $v[2]));
    }

    /** @return list<float> */
    private function timestamps(float $start, float $end, float $step): array
    {
        $values = []; for ($timestamp = $start; $timestamp <= $end; $timestamp += $step) $values[] = $timestamp;
        if ($values === [] || $values[array_key_last($values)] < $end) $values[] = $end;
        return $values;
    }

    /** @param list<bool> $values @return list<array{int,int}> */
    private function trueRuns(array $values): array
    {
        $runs = [];
        for ($index = 0, $count = count($values); $index < $count; $index++) {
            if (!$values[$index]) continue; $first = $index;
            while ($index + 1 < $count && $values[$index + 1]) $index++;
            $runs[] = [$first, $index];
        }
        return $runs;
    }

    /** @param list<string> $bodies */
    private function resetMetrics(array $bodies): void
    {
        $this->metrics = [
            'propagation_ms' => 0.0, 'topocentric_ms' => 0.0, 'coarse_search_ms' => 0.0,
            'fine_search_ms' => 0.0, 'refinement_ms' => 0.0, 'contacts_ms' => 0.0,
            'coarse_samples' => 0, 'fine_samples' => 0, 'refinement_samples' => 0, 'contact_samples' => 0,
            'total_samples' => 0, 'pass_runs' => 0, 'prefilter_candidates' => 0, 'refined_candidates' => 0, 'events' => 0,
        ];
        foreach ($bodies as $body) $this->metrics[$body . '_ms'] = 0.0;
    }

    /** @return array<string,int|float> */
    private function roundedMetrics(): array
    {
        foreach ($this->metrics as $key => $value) if (str_ends_with($key, '_ms')) $this->metrics[$key] = round((float) $value, 3);
        return $this->metrics;
    }

    private static function date(float $timestamp): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp));
        if (!$date instanceof DateTimeImmutable) throw new InvalidArgumentException('Invalid search timestamp.');
        return $date->setTimezone(new DateTimeZone('UTC'));
    }
    private static function elapsed(int $started): float { return (hrtime(true) - $started) / 1_000_000.0; }
}
