<?php

declare(strict_types=1);

namespace AstronomyEngine\Facade;

use AstronomyEngine\EclipseObserver;
use AstronomyEngine\ConjunctionCatalog;
use AstronomyEngine\LunarEclipse;
use AstronomyEngine\LunarEclipseCalculator;
use AstronomyEngine\LunarEclipseLocalCalculator;
use AstronomyEngine\LunarEvent;
use AstronomyEngine\LunarEventCalculator;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MoonApparentSize;
use AstronomyEngine\SolarEclipse;
use AstronomyEngine\SolarEclipseCalculator;
use AstronomyEngine\SolarEclipseLocalCalculator;
use DateTimeImmutable;
use InvalidArgumentException;

/** Portable composition behind the conceptual GET /v1/astronomy/events contract. */
final class AstronomyEventsFacade
{
    public const DEFAULT_DAYS = 30;
    public const MAX_DAYS = 366;

    private const DEFAULT_TYPES = [
        'moon_phase',
        'apsis',
        'conjunction',
        'earthshine',
        'full_moon_observation',
        'libration',
        'eclipse',
    ];

    private const EXPLICIT_TYPES = ['lunar_nodes', 'libration_all'];

    private const ECLIPSE_ALIASES = [
        'eclipse',
        'eclipses',
        'lunar_eclipse',
        'lunar_eclipses',
        'lunar-eclipse',
        'lunar-eclipses',
        'solar_eclipse',
        'solar_eclipses',
        'solar-eclipse',
        'solar-eclipses',
    ];

    /**
     * @param string|list<string>|null $types
     * @param array{max_difference_minutes?:int} $options
     * @return array{start_date:string,days:int,types:list<string>,observer:array<string,mixed>,items:list<array<string,mixed>>}
     */
    public function between(
        DateTimeImmutable $startDate,
        AstronomyObserver $observer,
        int $days = self::DEFAULT_DAYS,
        string|array|null $types = null,
        array $options = [],
    ): array {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw new InvalidArgumentException('Days must be between 1 and 366.');
        }

        $selected = $this->normalizeTypes($types);
        $maxDifference = $options['max_difference_minutes'] ?? 90;
        if (!is_int($maxDifference) || $maxDifference < 1 || $maxDifference > 180) {
            throw new InvalidArgumentException('Maximum difference must be an integer between 1 and 180 minutes.');
        }

        $start = new DateTimeImmutable($startDate->setTimezone($observer->timezone)->format('Y-m-d').' 00:00:00', $observer->timezone);
        $end = $start->modify("+{$days} days");
        $items = [];

        if (array_intersect($selected, ['moon_phase', 'apsis', 'lunar_nodes', 'libration', 'libration_all', 'conjunction'])) {
            $calculator = new LunarEventCalculator(new MeeusLunarCalculator());
            $groups = [];
            if (in_array('moon_phase', $selected, true)) $groups[] = 'moon_phase';
            if (in_array('apsis', $selected, true)) $groups[] = 'lunar_apsis';
            if (in_array('lunar_nodes', $selected, true)) $groups[] = 'lunar_orbit';
            if (array_intersect($selected, ['libration', 'libration_all'])) $groups[] = 'lunar_libration';
            if (in_array('conjunction', $selected, true)) $groups[] = 'lunar_conjunction';
            foreach ($calculator->calculate($start, $end, $observer->latitudeDegrees, $observer->longitudeDegrees, $groups) as $event) {
                $normalized = $this->lunarEvent($event, $selected);
                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }
        }

        if (in_array('eclipse', $selected, true)) {
            $items = [...$items, ...$this->eclipses($start, $end, $observer)];
        }

        if (in_array('earthshine', $selected, true)) {
            $earthshine = (new EarthshineFacade())->between($start, $end, $observer);
            foreach ($earthshine['opportunities'] as $opportunity) {
                $details = $this->earthshinePublicDetails($opportunity);
                $items[] = [
                    'type' => 'earthshine',
                    'subtype' => $opportunity['period'],
                    'datetime' => $opportunity['start'],
                    'end_datetime' => $opportunity['end'],
                    'title' => $opportunity['period'] === 'morning' ? 'Oportunidad matutina de Luna fina' : 'Oportunidad vespertina de Luna fina',
                    'details' => $details,
                ];
            }
        }

        if (in_array('full_moon_observation', $selected, true)) {
            $fullMoon = (new FullMoonObservationFacade())->between($start, $end, $observer, $maxDifference);
            foreach ($fullMoon['items'] as $opportunity) {
                $items[] = [
                    'type' => 'full_moon_observation',
                    'subtype' => $opportunity['type'],
                    'datetime' => $opportunity['event_time'],
                    'end_datetime' => null,
                    'title' => $opportunity['type'] === 'morning' ? 'Observación matutina de Luna llena' : 'Observación vespertina de Luna llena',
                    'details' => $opportunity,
                ];
            }
        }

        $items = $this->deduplicate($items);
        usort($items, static fn (array $a, array $b): int => [$a['datetime'], $a['type'], $a['subtype']] <=> [$b['datetime'], $b['type'], $b['subtype']]);

        return [
            'start_date' => $start->format('Y-m-d'),
            'days' => $days,
            'types' => $selected,
            'observer' => $observer->data(),
            'items' => $items,
        ];
    }

    /**
     * Adapta el contrato interno de EarthshineFacade al contrato público histórico.
     *
     * @param array<string,mixed> $opportunity
     * @return array<string,mixed>
     */
    private function earthshinePublicDetails(array $opportunity): array
    {
        $details = $opportunity;
        $details['start_time'] = $opportunity['start'] ?? null;
        $details['end_time'] = $opportunity['end'] ?? null;
        $details['best_visible_time'] = $opportunity['representative_time'] ?? null;

        $moonTime = $this->dateTime($opportunity['moon_event_time'] ?? null);
        $solarTime = $this->dateTime($opportunity['solar_event_time'] ?? null);
        if ($moonTime !== null && $solarTime !== null) {
            $details['difference_minutes'] = (int) round(
                ((float) $moonTime->format('U.u') - (float) $solarTime->format('U.u')) / 60
            );
        }

        $earthshineWindow = is_array($opportunity['earthshine_window'] ?? null)
            ? $opportunity['earthshine_window']
            : [];
        if (is_numeric($earthshineWindow['moon_altitude_degrees'] ?? null)) {
            $details['moon_altitude_degrees'] = (float) $earthshineWindow['moon_altitude_degrees'];
        }

        return $details;
    }

    private function dateTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param string|list<string>|null $types @return list<string> */
    public function normalizeTypes(string|array|null $types): array
    {
        if ($types === null || (is_string($types) && trim($types) === '')) {
            return self::DEFAULT_TYPES;
        }

        $values = is_string($types) ? explode(',', $types) : $types;
        $supported = [...self::DEFAULT_TYPES, ...self::EXPLICIT_TYPES];
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('Event types must be non-empty strings.');
            }
            $type = strtolower(trim($value));
            if (in_array($type, self::ECLIPSE_ALIASES, true)) {
                $type = 'eclipse';
            }
            if (!in_array($type, $supported, true)) {
                throw new InvalidArgumentException("Unsupported event type: {$value}.");
            }
            if (!in_array($type, $normalized, true)) {
                $normalized[] = $type;
            }
        }
        return $normalized;
    }

    /** @param list<string> $selected @return array<string,mixed>|null */
    private function lunarEvent(LunarEvent $event, array $selected): ?array
    {
        $type = match ($event->group) {
            'moon_phase' => 'moon_phase',
            'lunar_apsis' => 'apsis',
            'lunar_orbit' => 'lunar_nodes',
            'lunar_libration' => 'libration',
            'lunar_conjunction' => 'conjunction',
            default => null,
        };
        if ($type === null || !in_array($type, $selected, true) && !($type === 'libration' && in_array('libration_all', $selected, true))) {
            return null;
        }
        if ($type === 'libration' && !in_array('libration_all', $selected, true) && !($event->data['highlighted'] ?? false)) {
            return null;
        }

        $subtype = $event->type;
        $title = match ($type) {
            'moon_phase' => match ($subtype) {
                'new_moon' => 'Luna nueva',
                'first_quarter' => 'Cuarto creciente',
                'full_moon' => 'Luna llena',
                'last_quarter' => 'Cuarto menguante',
                default => $subtype,
            },
            'apsis' => $subtype === 'perigee' ? 'Perigeo lunar' : 'Apogeo lunar',
            'lunar_nodes' => $subtype === 'ascending_node' ? 'Nodo lunar ascendente' : 'Nodo lunar descendente',
            'libration' => 'Extremo de libración lunar',
            'conjunction' => 'Conjunción de la Luna con '.$subtype,
        };
        $details = $event->data + ['calculation_group' => $event->group, 'precision_profile' => $event->precisionProfile->value];
        if (in_array($type, ['moon_phase', 'apsis'], true) && is_numeric($details['distance_km'] ?? null)) {
            $details['apparent_size_percent'] = MoonApparentSize::percentOfMean((float) $details['distance_km']);
        }
        if ($type === 'conjunction') {
            $details['object_id'] = $subtype;
            $details['object_name'] = ConjunctionCatalog::names()[$subtype] ?? $subtype;
        }

        return [
            'type' => $type,
            'subtype' => $subtype,
            'datetime' => $event->dateTime->format(DATE_ATOM),
            'end_datetime' => null,
            'title' => $title,
            'details' => $details,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function eclipses(DateTimeImmutable $start, DateTimeImmutable $end, AstronomyObserver $observer): array
    {
        $result = [];
        $eclipseObserver = new EclipseObserver(
            $observer->latitudeDegrees,
            $observer->longitudeDegrees,
            $observer->timezone->getName(),
            $observer->elevationMeters,
        );
        $lunarLocal = new LunarEclipseLocalCalculator();
        foreach ((new LunarEclipseCalculator())->events($start, $end) as $eclipse) {
            $local = $lunarLocal->calculate($eclipse, $eclipseObserver);
            $result[] = $this->lunarEclipse($eclipse, $this->normalizeValue($local));
        }
        $solarLocal = new SolarEclipseLocalCalculator();
        foreach ((new SolarEclipseCalculator())->events($start, $end) as $eclipse) {
            $local = $solarLocal->calculate($eclipse, $eclipseObserver);
            $result[] = $this->solarEclipse($eclipse, $this->normalizeValue($local));
        }
        return $result;
    }

    /** @param array<string,mixed> $local @return array<string,mixed> */
    private function lunarEclipse(LunarEclipse $eclipse, array $local): array
    {
        return [
            'type' => 'eclipse',
            'subtype' => 'lunar_eclipse',
            'datetime' => $eclipse->maximum->format(DATE_ATOM),
            'end_datetime' => $eclipse->contacts->P4->format(DATE_ATOM),
            'title' => 'Eclipse lunar '.$eclipse->classification,
            'details' => [
                'classification' => $eclipse->classification,
                'global' => [
                    'contacts' => $this->normalizeValue($eclipse->contacts->all()),
                    'magnitudes' => $eclipse->magnitudes,
                    'durations_seconds' => $eclipse->durationsSeconds,
                    'maximum_geometry' => $eclipse->maximumGeometry,
                    'calculation_model' => $eclipse->calculationModel,
                    'precision_profile' => $eclipse->precisionProfile->value,
                ],
                'local' => $local,
            ],
        ];
    }

    /** @param array<string,mixed> $local @return array<string,mixed> */
    private function solarEclipse(SolarEclipse $eclipse, array $local): array
    {
        return [
            'type' => 'eclipse',
            'subtype' => 'solar_eclipse',
            'datetime' => $eclipse->maximum->format(DATE_ATOM),
            'end_datetime' => null,
            'title' => 'Eclipse solar '.$eclipse->classification,
            'details' => [
                'classification' => $eclipse->classification,
                'global' => [
                    'maximum' => $eclipse->maximum->format(DATE_ATOM),
                    'contacts' => $this->normalizeValue($eclipse->contacts),
                    'magnitudes' => $eclipse->magnitudes,
                    'maximum_geometry' => $eclipse->maximumGeometry,
                    'contact_availability' => $eclipse->contactAvailability,
                    'calculation_model' => $eclipse->calculationModel,
                    'precision_profile' => $eclipse->precisionProfile->value,
                ],
                'local' => $local,
            ],
        ];
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function deduplicate(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $details = $item['details'];
            $natural = $details['target'] ?? $details['planet'] ?? $details['candidate_date'] ?? $details['new_moon_time'] ?? $details['full_moon_time'] ?? $details['classification'] ?? '';
            $offset = $details['offset_days'] ?? '';
            $key = implode('|', [$item['type'], $item['subtype'], $item['datetime'], (string) $natural, (string) $offset]);
            $unique[$key] ??= $item;
        }
        return array_values($unique);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->format(DATE_ATOM);
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value);
        }
        if (is_object($value)) {
            return $this->normalizeValue(get_object_vars($value));
        }
        return $value;
    }
}
