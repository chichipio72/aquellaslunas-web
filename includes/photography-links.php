<?php

declare(strict_types=1);

require_once __DIR__ . '/photography-scene.php';
require_once __DIR__ . '/current-datetime.php';

function photographyConjunctionGeometry(DateTimeImmutable $instant, array $location, string $objectId): ?array
{
    $targets = [];
    foreach (\AstronomyEngine\ConjunctionCatalog::targets() as $target) $targets[$target->id] = $target;
    if (!isset($targets[$objectId])) return null;
    $observer = new \AstronomyEngine\Facade\AstronomyObserver(
        (float) $location['latitude'], (float) $location['longitude'], (string) $location['timezone'],
        (float) ($location['elevation_meters'] ?? 0.0)
    );
    $moon = (new \AstronomyEngine\MeeusLunarCalculator())->calculate(
        $instant, $observer->latitudeDegrees, $observer->longitudeDegrees, $observer->elevationMeters
    );
    $target = $targets[$objectId];
    $calculator = new \AstronomyEngine\ApproximatePlanetCalculator();
    $equatorial = $target->kind === 'planet' ? $calculator->target($objectId, $instant) : $calculator->fixed($target, $instant);
    $horizontal = photographyEquatorialToHorizontal($equatorial->rightAscensionDegrees, $equatorial->declinationDegrees, $instant, $observer);
    $relative = photographyRelativeCoordinates($moon->altitudeDegrees, $moon->azimuthDegrees, $horizontal['altitude_degrees'], $horizontal['azimuth_degrees']);
    return [
        'instant' => $instant,
        'moon_altitude_degrees' => $moon->altitudeDegrees,
        'target_altitude_degrees' => (float) $horizontal['altitude_degrees'],
        'separation_degrees' => (float) $relative['separation_degrees'],
    ];
}

function photographyConjunctionObservableMoment(DateTimeImmutable $maximum, array $location, string $objectId): ?array
{
    static $cache = [];
    $localMaximum = $maximum->setTimezone(new DateTimeZone((string) $location['timezone']));
    $cacheKey = implode('|', [$localMaximum->format(DateTimeInterface::ATOM), $objectId, $location['latitude'], $location['longitude'], $location['elevation_meters'] ?? 0]);
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];
    $maximumGeometry = photographyConjunctionGeometry($localMaximum, $location, $objectId);
    if ($maximumGeometry === null) return $cache[$cacheKey] = null;
    if ($maximumGeometry['moon_altitude_degrees'] > 0.0 && $maximumGeometry['target_altitude_degrees'] > 0.0) {
        return $cache[$cacheKey] = ['moment' => $localMaximum, 'geometry' => $maximumGeometry, 'source' => 'astronomical_maximum'];
    }
    $margin = 3.0;
    $maximumSeparation = $maximumGeometry['separation_degrees'] + 2.0;
    $stepSeconds = 300;
    $limitSeconds = 12 * 3600;
    for ($offset = $stepSeconds; $offset <= $limitSeconds; $offset += $stepSeconds) {
        $candidates = [];
        foreach ([-1, 1] as $direction) {
            $candidateMoment = $localMaximum->modify(($direction < 0 ? '-' : '+') . $offset . ' seconds');
            $geometry = photographyConjunctionGeometry($candidateMoment, $location, $objectId);
            if ($geometry === null || min($geometry['moon_altitude_degrees'], $geometry['target_altitude_degrees']) < $margin || $geometry['separation_degrees'] > $maximumSeparation) continue;
            $invalidMoment = $localMaximum->modify(($direction < 0 ? '-' : '+') . max(0, $offset - $stepSeconds) . ' seconds');
            $validMoment = $candidateMoment;
            for ($iteration = 0; $iteration < 18; $iteration++) {
                $middleTimestamp = ((float) $invalidMoment->format('U.u') + (float) $validMoment->format('U.u')) / 2.0;
                $middle = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $middleTimestamp))->setTimezone($localMaximum->getTimezone());
                $middleGeometry = photographyConjunctionGeometry($middle, $location, $objectId);
                $middleVisible = $middleGeometry !== null && min($middleGeometry['moon_altitude_degrees'], $middleGeometry['target_altitude_degrees']) >= $margin && $middleGeometry['separation_degrees'] <= $maximumSeparation;
                if ($middleVisible) $validMoment = $middle; else $invalidMoment = $middle;
            }
            $geometry = photographyConjunctionGeometry($validMoment, $location, $objectId);
            if ($geometry !== null) $candidates[] = ['moment' => $validMoment, 'geometry' => $geometry, 'source' => 'nearest_simultaneous_visibility'];
        }
        if ($candidates !== []) {
            usort($candidates, static function (array $first, array $second) use ($localMaximum): int {
                $firstDistance = abs((float) $first['moment']->format('U.u') - (float) $localMaximum->format('U.u'));
                $secondDistance = abs((float) $second['moment']->format('U.u') - (float) $localMaximum->format('U.u'));
                return [$firstDistance, $first['geometry']['separation_degrees']] <=> [$secondDistance, $second['geometry']['separation_degrees']];
            });
            return $cache[$cacheKey] = $candidates[0];
        }
    }
    return $cache[$cacheKey] = null;
}

function photographyEventUrl(array $event, array $location, ?DateTimeImmutable $preferredMoment = null): ?string
{
    $type = (string) ($event['type'] ?? '');
    $subtype = strtolower((string) ($event['subtype'] ?? ''));
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $supportedObjects = ['mercury', 'venus', 'mars', 'jupiter', 'saturn', 'aldebaran', 'pollux', 'regulus', 'spica', 'antares'];
    $horizon = false; $objects = []; $scene = null; $eventContextType = $type;
    if ($type === 'conjunction' && in_array($subtype, $supportedObjects, true)) {
        $objects[] = $subtype; $scene = 'moon_with_objects';
    } elseif ($type === 'eclipse') {
        $localKey = $subtype === 'solar_eclipse' ? 'solar_eclipse_local' : 'eclipse_local';
        $visibility = strtolower((string) ($details[$localKey]['visibility_classification'] ?? ''));
        if ($visibility === 'not_visible') return null;
        $scene = 'eclipse';
    } elseif ($type === 'full_moon_observation') {
        $eventContextType = in_array($details['moon_event'] ?? null, ['moonrise', 'moonset'], true) ? (string) $details['moon_event'] : '';
        $horizon = true; $scene = 'moon_with_horizon';
    } else {
        return null;
    }
    $canonicalValue = $type === 'full_moon_observation' && is_string(($details['moon_event_time'] ?? null))
        ? $details['moon_event_time'] : ($event['datetime'] ?? null);
    if (!is_string($canonicalValue)) return null;
    try { $canonicalMoment = (new DateTimeImmutable($canonicalValue))->setTimezone(new DateTimeZone((string) $location['timezone'])); } catch (Throwable) { return null; }
    $moment = $preferredMoment?->setTimezone($canonicalMoment->getTimezone()) ?? $canonicalMoment;
    $observation = null;
    if ($type === 'full_moon_observation' && $eventContextType === 'moonset') {
        $moment = $canonicalMoment->modify('-1 minute');
    } elseif ($type === 'full_moon_observation' && $eventContextType === 'moonrise') {
        $moment = $canonicalMoment->modify('+1 minute');
    } elseif ($type === 'conjunction') {
        $observation = photographyConjunctionObservableMoment($canonicalMoment, $location, $subtype);
        if ($observation === null) return null;
        $moment = $observation['moment'];
    }
    $parameters = [
        'date' => $moment->format('Y-m-d'), 'time' => $moment->format('H:i'),
        'lat' => (float) $location['latitude'], 'lon' => (float) $location['longitude'],
        'elevation' => (float) ($location['elevation_meters'] ?? 0.0),
        'horizon' => $horizon ? 1 : 0, 'objects' => implode(',', $objects),
        'aim' => ($horizon || $objects !== []) ? 'automatic' : 'moon', 'scene' => $scene,
        'event_type' => $eventContextType,
        'event_time' => $canonicalMoment->format(DateTimeInterface::ATOM),
        'observation_time' => $moment->format(DateTimeInterface::ATOM),
    ];
    if ($type === 'conjunction') {
        $parameters['event_object'] = $subtype;
        $parameters['observation_moon_altitude'] = round((float) $observation['geometry']['moon_altitude_degrees'], 3);
        $parameters['observation_object_altitude'] = round((float) $observation['geometry']['target_altitude_degrees'], 3);
        $parameters['observation_separation'] = round((float) $observation['geometry']['separation_degrees'], 3);
    }
    return astronomyInternalUrl('fotografia.php') . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

/** @param array<string,mixed> $scene */
function photographyTonightSceneUrl(array $scene, array $location): ?string
{
    $moment = $scene['datetime'] ?? null;
    if (!$moment instanceof DateTimeImmutable) return null;
    try {
        $moment = $moment->setTimezone(new DateTimeZone((string) $location['timezone']));
    } catch (Throwable) {
        return null;
    }
    $supportedObjects = ['mercury', 'venus', 'mars', 'jupiter', 'saturn', 'aldebaran', 'pollux', 'regulus', 'spica', 'antares'];
    $objects = [];
    foreach (is_array($scene['objects'] ?? null) ? $scene['objects'] : [] as $object) {
        $id = is_array($object) ? strtolower(trim((string) ($object['id'] ?? ''))) : '';
        if (in_array($id, $supportedObjects, true)) $objects[] = $id;
    }
    $objects = array_values(array_unique($objects));
    if ($objects === []) return null;
    $anchorId = strtolower(trim((string) ($scene['anchor_id'] ?? $objects[0])));
    if (!in_array($anchorId, $objects, true)) $anchorId = $objects[0];
    $parameters = [
        'date' => $moment->format('Y-m-d'), 'time' => $moment->format('H:i'),
        'lat' => (float) $location['latitude'], 'lon' => (float) $location['longitude'],
        'elevation' => (float) ($location['elevation_meters'] ?? 0.0),
        'horizon' => !empty($scene['horizon_visible']) ? 1 : 0,
        'objects' => implode(',', $objects), 'aim' => 'automatic', 'scene' => 'moon_with_objects',
        'event_type' => 'conjunction', 'event_object' => $anchorId,
        'event_time' => $moment->format(DateTimeInterface::ATOM),
        'observation_time' => $moment->format(DateTimeInterface::ATOM),
    ];
    return astronomyInternalUrl('fotografia.php') . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

function renderPhotographyEventLink(?string $url, string $className = ''): void
{
    if ($url === null) return;
    $classes = trim('button compact-secondary-button photography-event-link ' . $className);
    ?><a class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">Explorar la escena</a><?php
}
