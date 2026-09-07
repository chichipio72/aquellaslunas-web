<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/web-push-astronomy.php';

function astronomyPushTest(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function astronomyPushTestConfig(array $changes = []): array
{
    return array_replace([
        'device_name' => 'Dispositivo de prueba', 'notifications_enabled' => '1',
        'location_name' => 'Buenos Aires', 'latitude' => '-34.60', 'longitude' => '-58.38',
        'timezone' => 'America/Argentina/Buenos_Aires', 'quiet_hours_enabled' => '0',
        'quiet_start_local' => '23:00', 'quiet_end_local' => '08:00', 'moonrise_enabled' => '1',
    ], $changes);
}

function astronomyPushTestSubscription(PDO $connection, string $suffix): int
{
    $endpoint = 'https://push.invalid.test/' . $suffix;
    $statement = $connection->prepare(
        'INSERT INTO web_push_subscriptions (endpoint, endpoint_hash, p256dh, auth, user_agent, active) '
        . 'VALUES (:endpoint, :endpoint_hash, :p256dh, :auth, :user_agent, 1)'
    );
    $statement->execute([
        'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint, true),
        'p256dh' => 'test-p256dh-' . $suffix, 'auth' => 'test-auth-' . $suffix,
        'user_agent' => 'Astronomy Push Test ' . $suffix,
    ]);
    return (int) $connection->lastInsertId();
}

$valid = astronomyPushValidateDeviceConfig(astronomyPushTestConfig());
astronomyPushTest($valid['timezone'] === 'America/Argentina/Buenos_Aires', 'No se validó la zona horaria válida.');
foreach ([
    ['latitude' => '91'], ['longitude' => '-181'], ['timezone' => 'Invalid/Timezone'],
    ['device_name' => ''], ['location_name' => ''],
] as $invalid) {
    $failed = false;
    try { astronomyPushValidateDeviceConfig(astronomyPushTestConfig($invalid)); }
    catch (InvalidArgumentException) { $failed = true; }
    astronomyPushTest($failed, 'Se aceptó una configuración inválida: ' . array_key_first($invalid));
}
$failed = false;
try { astronomyPushValidateDeviceConfig(astronomyPushTestConfig([
    'quiet_hours_enabled' => '1', 'quiet_start_local' => '08:00', 'quiet_end_local' => '08:00',
])); } catch (InvalidArgumentException) { $failed = true; }
astronomyPushTest($failed, 'Se aceptó un horario de silencio sin duración.');

$utc = new DateTimeZone('UTC');
$normalQuiet = astronomyPushValidateDeviceConfig(astronomyPushTestConfig([
    'timezone' => 'UTC', 'quiet_hours_enabled' => '1', 'quiet_start_local' => '08:00', 'quiet_end_local' => '23:00',
]));
astronomyPushTest(astronomyPushIsQuietAt(new DateTimeImmutable('2026-01-01 08:00:00', $utc), $normalQuiet),
    'El límite inicial normal no fue incluido.');
astronomyPushTest(astronomyPushIsQuietAt(new DateTimeImmutable('2026-01-01 22:59:00', $utc), $normalQuiet),
    'El interior del horario normal no fue incluido.');
astronomyPushTest(!astronomyPushIsQuietAt(new DateTimeImmutable('2026-01-01 23:00:00', $utc), $normalQuiet),
    'El límite final normal no fue excluido.');
$crossQuiet = astronomyPushValidateDeviceConfig(astronomyPushTestConfig([
    'timezone' => 'UTC', 'quiet_hours_enabled' => '1', 'quiet_start_local' => '23:00', 'quiet_end_local' => '08:00',
]));
astronomyPushTest(astronomyPushIsQuietAt(new DateTimeImmutable('2026-01-01 23:00:00', $utc), $crossQuiet)
    && astronomyPushIsQuietAt(new DateTimeImmutable('2026-01-02 07:59:00', $utc), $crossQuiet),
    'El horario cruzando medianoche no se aplicó.');
astronomyPushTest(!astronomyPushIsQuietAt(new DateTimeImmutable('2026-01-02 08:00:00', $utc), $crossQuiet)
    && !astronomyPushIsQuietAt(new DateTimeImmutable('2026-01-01 12:00:00', $utc), $crossQuiet),
    'El horario cruzado incluyó horas externas.');

$event = new DateTimeImmutable('2026-01-01 12:00:00', $utc);
astronomyPushTest(astronomyPushNotificationTime($event, 15)->format('H:i:s') === '11:45:00',
    'El momento nominal no quedó 15 minutos antes.');
$moonriseTransport = astronomyPushTransportOptions('moonrise', $event, $event->modify('-15 minutes'));
astronomyPushTest($moonriseTransport === ['TTL' => 900, 'urgency' => 'high'],
    'Moonrise no conserva el push hasta el instante del evento con urgencia alta.');
astronomyPushTest(astronomyPushTransportOptions('test', $event, $event->modify('-15 minutes'))
    === ['TTL' => 300, 'urgency' => 'normal'], 'Los demás tipos no conservaron el transporte predeterminado.');
astronomyPushTest(astronomyPushTransportOptions('moonrise', $event, $event->modify('-10 seconds'))['TTL'] === 60,
    'No se aplicó el mínimo defensivo del TTL lunar.');
astronomyPushTest(astronomyPushTransportOptions('moonrise', $event, $event->modify('-2 hours'))['TTL'] === 1800,
    'No se aplicó el máximo defensivo del TTL lunar.');
astronomyPushTest(astronomyPushValidateParametersJson('{"minimum":10}') === ['minimum' => 10],
    'No se validó un objeto JSON futuro.');
$failed = false;
try { astronomyPushValidateParametersJson('[1,2]'); } catch (InvalidArgumentException) { $failed = true; }
astronomyPushTest($failed, 'Se aceptó una lista JSON como parámetros específicos.');

$connection = getWebDatabaseConnection();
$seedDevice = astronomyPushAdminDevice($connection, 11);
astronomyPushTest(is_array($seedDevice) && $seedDevice['device_name'] === 'Celular Android'
    && $seedDevice['location_name'] === 'Vicente López' && (int) $seedDevice['moonrise_enabled'] === 1
    && (int) $seedDevice['lead_minutes'] === 15, 'La suscripción 11 no quedó sembrada de forma compatible.');

$connection->beginTransaction();
try {
    $firstId = astronomyPushTestSubscription($connection, 'first-' . bin2hex(random_bytes(4)));
    $secondId = astronomyPushTestSubscription($connection, 'second-' . bin2hex(random_bytes(4)));
    $thirdId = astronomyPushTestSubscription($connection, 'third-' . bin2hex(random_bytes(4)));
    astronomyPushSaveDevice($connection, $firstId, astronomyPushTestConfig([
        'device_name' => 'Primero', 'timezone' => 'UTC', 'latitude' => '0', 'longitude' => '0',
        'location_name' => 'Origen',
    ]));
    astronomyPushSaveDevice($connection, $secondId, astronomyPushTestConfig([
        'device_name' => 'Segundo', 'timezone' => 'UTC', 'latitude' => '0', 'longitude' => '0',
        'location_name' => 'Origen',
    ]));
    astronomyPushSaveDevice($connection, $thirdId, astronomyPushTestConfig([
        'device_name' => 'Tercero', 'timezone' => 'UTC', 'latitude' => '10', 'longitude' => '20',
        'location_name' => 'Otro lugar',
    ]));
    astronomyPushTest(count(astronomyPushConfiguredDevices($connection, $firstId)) === 1
        && count(astronomyPushConfiguredDevices($connection, $secondId)) === 1,
        'No se seleccionaron múltiples configuraciones habilitadas.');

    $dedupEvent = new DateTimeImmutable('2099-01-01 12:00:00', $utc);
    $dedupNotice = astronomyPushNotificationTime($dedupEvent, 15);
    $attempt = new DateTimeImmutable('2099-01-01 11:45:00', $utc);
    $claim = astronomyPushClaim($connection, $firstId, 'moonrise', 'moonrise', $dedupEvent, $dedupNotice, $attempt);
    astronomyPushTest($claim['claimed'], 'No se reclamó el evento con event_key.');
    $duplicate = astronomyPushClaim($connection, $firstId, 'moonrise', 'moonrise', $dedupEvent, $dedupNotice,
        $attempt->modify('+1 minute'));
    astronomyPushTest(!$duplicate['claimed'], 'La deduplicación con event_key falló.');
    $differentKey = astronomyPushClaim($connection, $firstId, 'moonrise', 'moonrise-secondary', $dedupEvent,
        $dedupNotice, $attempt);
    astronomyPushTest($differentKey['claimed'], 'event_key no distingue eventos con la misma hora.');

    $now = new DateTimeImmutable('2099-02-01 23:45:00', $utc);
    $quietDevice = array_replace(astronomyPushConfiguredDevices($connection, $firstId)[0], [
        'quiet_hours_enabled' => 1, 'quiet_start_local' => '23:00', 'quiet_end_local' => '08:00',
    ]);
    $resolver = static fn(array $device, DateTimeImmutable $clock): array => [[
        'event_key' => 'moonrise', 'event_time_utc' => $clock->modify('+15 minutes'),
        'event_time_local' => $clock->modify('+15 minutes')->setTimezone(new DateTimeZone((string) $device['timezone'])),
    ]];
    $senderCalls = 0;
    $sender = static function () use (&$senderCalls): array { $senderCalls++; return ['success' => 1, 'failed' => 0, 'errors' => []]; };
    $quietResult = astronomyPushProcess($connection, [$quietDevice], $now, false, $resolver, $sender);
    astronomyPushTest($quietResult['skipped'] === 1 && $senderCalls === 0,
        'No molestar no omitió el aviso antes del envío.');
    $quietLog = $connection->prepare(
        "SELECT status, decision_reason, error_message FROM web_push_notification_log WHERE subscription_id = ? "
        . "AND event_time_utc = '2099-02-02 00:00:00'"
    );
    $quietLog->execute([$firstId]);
    $quietRow = $quietLog->fetch();
    astronomyPushTest(is_array($quietRow) && $quietRow['status'] === 'skipped_quiet_hours'
        && $quietRow['decision_reason'] === 'quiet_hours' && $quietRow['error_message'] === null,
        'La omisión no quedó registrada como decisión normal.');

    $beforeDryRun = (int) $connection->query('SELECT COUNT(*) FROM web_push_notification_log')->fetchColumn();
    $drySenderCalls = 0;
    astronomyPushProcess($connection, [$quietDevice], $now->modify('+1 day'), true,
        $resolver, static function () use (&$drySenderCalls): array { $drySenderCalls++; return []; });
    $afterDryRun = (int) $connection->query('SELECT COUNT(*) FROM web_push_notification_log')->fetchColumn();
    astronomyPushTest($beforeDryRun === $afterDryRun && $drySenderCalls === 0,
        'El dry-run escribió o intentó enviar.');

    $devices = [
        astronomyPushConfiguredDevices($connection, $firstId)[0],
        astronomyPushConfiguredDevices($connection, $secondId)[0],
        astronomyPushConfiguredDevices($connection, $thirdId)[0],
    ];
    $resolverCalls = 0;
    astronomyPushProcess($connection, $devices, $now->modify('+2 days'), true,
        static function (array $device, DateTimeImmutable $clock) use (&$resolverCalls): array {
            $resolverCalls++;
            return [[
                'event_key' => 'moonrise', 'event_time_utc' => $clock->modify('+15 minutes'),
                'event_time_local' => $clock->modify('+15 minutes')->setTimezone(new DateTimeZone((string) $device['timezone'])),
            ]];
        });
    astronomyPushTest($resolverCalls === 2,
        'No se agruparon ubicaciones iguales o se mezclaron ubicaciones diferentes.');
} finally {
    $connection->rollBack();
}

echo "OK web push astronomy\n";
