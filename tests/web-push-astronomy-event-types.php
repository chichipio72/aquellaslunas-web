<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/web-push-astronomy.php';
require_once __DIR__ . '/../includes/web-push-device-config.php';
require_once __DIR__ . '/../scripts/migrations/create-web-push-astronomy-event-types.php';

use AstronomyEngine\Satellite\FixtureSatelliteTleProvider;
use AstronomyEngine\Satellite\SatelliteTransitEvent;
use AstronomyEngine\Satellite\SatelliteTransitService;

function astronomyEventPushAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$connection = getWebDatabaseConnection();
runWebPushAstronomyEventTypesMigration($connection);
$connection->prepare("UPDATE web_push_notification_types SET description='Edición preservada' WHERE notification_type='eclipse'")->execute();
runWebPushAstronomyEventTypesMigration($connection);
astronomyEventPushAssert($connection->query("SELECT description FROM web_push_notification_types WHERE notification_type='eclipse'")->fetchColumn() === 'Edición preservada',
    'La migración repetida sobrescribió una edición administrativa.');
$connection->prepare("UPDATE web_push_notification_types SET description='Aviso a las 08:00 del día anterior para eclipses visibles desde tu ubicación.' WHERE notification_type='eclipse'")->execute();

$catalog = astronomyPushNotificationTypes($connection);
foreach (['eclipse', 'lunar_conjunction', 'satellite_transit'] as $code) {
    $type = array_values(array_filter($catalog, static fn(array $row): bool => $row['notification_type'] === $code))[0] ?? null;
    astronomyEventPushAssert(is_array($type) && (int) $type['available'] === 1 && (int) $type['admin_only'] === 0,
        'El catálogo no sembró correctamente ' . $code . '.');
}

$madrid = ['latitude' => 40.4168, 'longitude' => -3.7038, 'timezone' => 'Europe/Madrid',
    'notification_type' => 'eclipse', 'parameters_json' => null];
$buenosAires = ['latitude' => -34.53, 'longitude' => -58.48,
    'timezone' => 'America/Argentina/Buenos_Aires', 'notification_type' => 'eclipse', 'parameters_json' => null];
$madridEclipses = astronomyPushEclipseEvents($madrid, new DateTimeImmutable('2026-01-01T00:00:00Z'));
$baEclipses = astronomyPushEclipseEvents($buenosAires, new DateTimeImmutable('2026-01-01T00:00:00Z'));
astronomyEventPushAssert((bool) array_filter($madridEclipses, static fn(array $event): bool => str_starts_with($event['event_key'], 'solar:partial:')),
    'No encontró el eclipse solar localmente visible de Madrid.');
astronomyEventPushAssert((bool) array_filter($baEclipses, static fn(array $event): bool => str_starts_with($event['event_key'], 'lunar:total:')),
    'No encontró el eclipse lunar localmente visible de Buenos Aires.');
astronomyEventPushAssert(!(bool) array_filter($baEclipses, static fn(array $event): bool => str_starts_with($event['event_key'], 'solar:')),
    'Incluyó un eclipse solar global no relevante para Buenos Aires.');

$conjunctionDevice = array_replace($buenosAires, ['notification_type' => 'lunar_conjunction']);
$conjunctions = astronomyPushLunarConjunctionEvents($conjunctionDevice, new DateTimeImmutable('2026-06-15T12:00:00Z'));
astronomyEventPushAssert(count($conjunctions) === 3 && $conjunctions[0]['object_name'] === 'Mercurio'
    && $conjunctions[0]['separation'] === '2,5°', 'El filtro o formato de conjunciones es incorrecto.');
$strictDevice = $conjunctionDevice;
$strictDevice['parameters_json'] = '{"maximum_separation_deg":1}';
$strict = astronomyPushLunarConjunctionEvents($strictDevice, new DateTimeImmutable('2026-06-15T12:00:00Z'));
astronomyEventPushAssert(count($strict) === 1 && $strict[0]['object_name'] === 'Venus',
    'No descartó conjunciones por encima del límite configurado.');

$fixtureService = new SatelliteTransitService(new FixtureSatelliteTleProvider(__DIR__ . '/fixtures/satellite'));
$satelliteDevice = array_replace($buenosAires, ['notification_type' => 'satellite_transit']);
$historical = astronomyPushSatelliteTransitEvents($satelliteDevice,
    new DateTimeImmutable('2026-07-25T18:00:00-03:00'), $fixtureService);
astronomyEventPushAssert(count($historical) === 1 && $historical[0]['satellite_name'] === 'Tiangong'
    && $historical[0]['target_name'] === 'Luna' && str_contains($historical[0]['event_key'], ':transit:'),
    'El proveedor satelital perdió el tránsito histórico de Tiangong.');

$baseTime = new DateTimeImmutable('2026-08-08T12:00:00Z');
$tleEpoch = new DateTimeImmutable('2026-08-08T00:00:00Z');
$satelliteEvent = static fn(string $satellite, string $target, string $classification): SatelliteTransitEvent =>
    new SatelliteTransitEvent($target, $satellite, 'nombre TLE', $classification, $baseTime, null, null, null,
        0.1, 0.25, -0.15, 30, 40, 30, 40, 800, 'line1', 'line2', $tleEpoch);
$timezone = new DateTimeZone('UTC');
foreach ([['iss','sun','transit'], ['iss','moon','very_close'], ['tiangong','sun','transit'],
    ['tiangong','moon','very_close']] as [$satellite, $target, $classification]) {
    astronomyEventPushAssert(astronomyPushSatelliteEventPayload($satelliteEvent($satellite, $target, $classification),
        ['transit', 'very_close'], $timezone) !== null, 'Descartó una combinación satelital permitida.');
}
foreach (['close', 'near_pass'] as $classification) {
    astronomyEventPushAssert(astronomyPushSatelliteEventPayload($satelliteEvent('iss', 'moon', $classification),
        ['transit', 'very_close'], $timezone) === null, 'Aceptó la clasificación pública ' . $classification . '.');
}

$eventUtc = new DateTimeImmutable('2026-08-28T04:14:11Z');
$fixed = astronomyPushScheduledNotificationTime($eventUtc,
    ['schedule_mode' => 'fixed_local_time', 'delivery_local_time' => '08:00', 'delivery_day_offset' => -1],
    $buenosAires);
astronomyEventPushAssert($fixed->format('Y-m-d H:i:s') === '2026-08-27 11:00:00',
    'El eclipse no quedó a las 08:00 locales del día anterior.');
$sameDay = astronomyPushScheduledNotificationTime(new DateTimeImmutable('2026-06-17T20:33:03Z'),
    ['schedule_mode' => 'fixed_local_time', 'delivery_local_time' => '08:00', 'delivery_day_offset' => 0],
    $buenosAires);
astronomyEventPushAssert($sameDay->format('Y-m-d H:i:s') === '2026-06-17 11:00:00',
    'La conjunción no quedó a las 08:00 locales del mismo día.');
$before = astronomyPushScheduledNotificationTime($baseTime,
    ['schedule_mode' => 'before_event', 'lead_minutes' => 60], $buenosAires);
astronomyEventPushAssert($before->format('H:i:s') === '11:00:00', 'El satélite no quedó 60 minutos antes.');
$quietDevice = $buenosAires + ['quiet_hours_enabled' => 1, 'quiet_start_local' => '23:00', 'quiet_end_local' => '08:00'];
$postponed = astronomyPushQuietEnd(new DateTimeImmutable('2026-08-27T09:00:00Z'), $quietDevice);
astronomyEventPushAssert($postponed->format('Y-m-d H:i:s') === '2026-08-27 11:00:00',
    'No molestar no postergó hasta las 08:00 locales.');

$renderType = astronomyPushNotificationType($connection, 'satellite_transit');
$rendered = astronomyPushRenderNotification($renderType, $historical[0],
    $satelliteDevice + ['location_name' => 'Trujui', 'device_name' => 'Celular'], ['lead_minutes' => 60]);
astronomyEventPushAssert(str_contains($rendered['title'], 'Tiangong') && str_contains($rendered['body'], 'tránsito')
    && !str_contains($rendered['body'], 'transit'), 'El render común expuso códigos internos.');

$failureDevices = [[
    'subscription_id' => 900001, 'device_name' => 'Falla', 'location_name' => 'A', 'latitude' => 1.0,
    'longitude' => 1.0, 'timezone' => 'UTC', 'notification_type' => 'eclipse', 'parameters_json' => null,
    'schedule_mode' => 'fixed_local_time', 'delivery_local_time' => '08:00', 'delivery_day_offset' => -1,
    'quiet_policy' => 'postpone', 'quiet_hours_enabled' => 0,
], [
    'subscription_id' => 900002, 'device_name' => 'Continúa', 'location_name' => 'B', 'latitude' => 2.0,
    'longitude' => 2.0, 'timezone' => 'UTC', 'notification_type' => 'eclipse', 'parameters_json' => null,
    'schedule_mode' => 'fixed_local_time', 'delivery_local_time' => '08:00', 'delivery_day_offset' => -1,
    'quiet_policy' => 'postpone', 'quiet_hours_enabled' => 0,
]];
$failureClock = new DateTimeImmutable('2026-08-27T08:00:00Z');
$isolated = astronomyPushProcess($connection, $failureDevices, $failureClock, true,
    static function (array $device) use ($failureClock): array {
        if ((float) $device['latitude'] === 1.0) throw new RuntimeException('secreto que no debe registrarse');
        return [['event_key' => 'lunar:partial:synthetic', 'event_time_utc' => $failureClock->modify('+20 hours'),
            'event_time_local' => $failureClock->modify('+20 hours')]];
    });
astronomyEventPushAssert($isolated['failed'] === 1 && $isolated['due'] === 1,
    'Un proveedor fallido detuvo la evaluación de otra ubicación.');

$connection->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(5));
    $endpoint = 'https://push.invalid.test/events-' . $suffix;
    $insert = $connection->prepare('INSERT INTO web_push_subscriptions '
        . '(endpoint, endpoint_hash, p256dh, auth, user_agent, active) VALUES (?,?,?,?,?,1)');
    $insert->execute([$endpoint, hash('sha256', $endpoint, true), 'key-' . $suffix, 'auth-' . $suffix, 'Event Test']);
    $subscriptionId = (int) $connection->lastInsertId();
    astronomyPushSaveDevice($connection, $subscriptionId, [
        'device_name' => 'Eventos', 'notifications_enabled' => true, 'location_name' => 'Buenos Aires',
        'latitude' => -34.53, 'longitude' => -58.48, 'timezone' => 'America/Argentina/Buenos_Aires',
        'quiet_hours_enabled' => false, 'quiet_start_local' => '', 'quiet_end_local' => '',
        'notification_preferences' => ['eclipse' => true, 'lunar_conjunction' => true, 'satellite_transit' => true],
    ]);
    $publicCodes = array_column(astronomyPushPublicNotificationTypes($connection, $subscriptionId), 'notification_type');
    astronomyEventPushAssert(array_diff(['moonrise', 'eclipse', 'lunar_conjunction', 'satellite_transit'], $publicCodes) === [],
        'La configuración pública no expuso los cuatro tipos.');
    $storedParameters = $connection->query('SELECT notification_type, parameters_json FROM web_push_notification_preferences '
        . 'WHERE subscription_id=' . $subscriptionId)->fetchAll(PDO::FETCH_KEY_PAIR);
    astronomyEventPushAssert((float) json_decode((string) $storedParameters['lunar_conjunction'], true)['maximum_separation_deg'] === 3.0
        && json_decode((string) $storedParameters['satellite_transit'], true)['classifications'] === ['transit', 'very_close'],
        'Las preferencias nuevas no conservaron los parámetros predeterminados.');
    foreach ([
        ['eclipse', $baEclipses[0]],
        ['lunar_conjunction', $conjunctions[0]],
        ['satellite_transit', $historical[0]],
    ] as [$type, $event]) {
        $first = astronomyPushClaim($connection, $subscriptionId, $type, $event['event_key'],
            $event['event_time_utc'], $event['event_time_utc']->modify('-1 hour'), $baseTime);
        $duplicate = astronomyPushClaim($connection, $subscriptionId, $type, $event['event_key'],
            $event['event_time_utc'], $event['event_time_utc']->modify('-1 hour'), $baseTime);
        astronomyEventPushAssert($first['claimed'] && !$duplicate['claimed'], 'Falló la deduplicación de ' . $type . '.');
    }
} finally {
    $connection->rollBack();
}

echo "OK web push astronomy event types\n";
