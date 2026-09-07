<?php

declare(strict_types=1);

require_once __DIR__ . '/astronomy-events.php';
require_once __DIR__ . '/current-datetime.php';

use AstronomyEngine\MoonApparentSize;

/** @return array{reference:DateTimeImmutable,controls:bool} */
function fullMoonSizeEmbedOptions(array $query, DateTimeZone $timezone, DateTimeImmutable $current): array
{
    $reference = $current->setTimezone($timezone);
    if (array_key_exists('date', $query) && is_string($query['date'])) {
        $value = $query['date'];
        $forcedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if ($forcedDate !== false && $forcedDate->format('Y-m-d') === $value) {
            $reference = $forcedDate;
        }
    }
    return [
        'reference' => $reference,
        'controls' => lunarEmbedBoolean($query['controls'] ?? null, true),
    ];
}

/** @return list<array{datetime:string,date:string,distance_km:float,diameter_arcminutes:float,size_percent:float}> */
function nextFullMoonSizes(DateTimeImmutable $reference, array $location): array
{
    $timezone = new DateTimeZone((string) $location['timezone']);
    $result = astronomyEvents([
        'start_date' => $reference->setTimezone($timezone)->format('Y-m-d'),
        'days' => 366,
        'types' => 'moon_phase',
        'latitude' => (float) $location['latitude'],
        'longitude' => (float) $location['longitude'],
        'elevation_meters' => (float) ($location['elevation_meters'] ?? 0.0),
        'timezone' => $timezone->getName(),
    ], 'full moon size embed');

    $referenceUtc = $reference->setTimezone(new DateTimeZone('UTC'));
    $moons = [];
    foreach ($result['items'] ?? [] as $item) {
        if (($item['subtype'] ?? null) !== 'full_moon' || !is_string($item['datetime'] ?? null)) continue;
        $instant = new DateTimeImmutable($item['datetime']);
        $details = is_array($item['details'] ?? null) ? $item['details'] : [];
        if ($instant < $referenceUtc || !is_numeric($details['distance_km'] ?? null)) continue;
        $distance = (float) $details['distance_km'];
        $moons[] = [
            'datetime' => $instant->format(DateTimeInterface::ATOM),
            'date' => $instant->setTimezone($timezone)->format('Y-m-d'),
            'distance_km' => $distance,
            'diameter_arcminutes' => MoonApparentSize::angularDiameterArcminutes($distance),
            'size_percent' => MoonApparentSize::percentOfMean($distance),
        ];
        if (count($moons) === 12) break;
    }
    if (count($moons) !== 12) {
        throw new RuntimeException('No se pudieron calcular las próximas doce lunas llenas.');
    }
    return $moons;
}
