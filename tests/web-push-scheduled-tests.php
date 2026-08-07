<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/web-push-astronomy.php';

function scheduledPushAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function scheduledPushSubscription(PDO $connection): int
{
    $suffix = bin2hex(random_bytes(6));
    $endpoint = 'https://push.invalid.test/scheduled-' . $suffix;
    $statement = $connection->prepare(
        'INSERT INTO web_push_subscriptions (endpoint, endpoint_hash, p256dh, auth, user_agent, active) '
        . 'VALUES (:endpoint, :endpoint_hash, :p256dh, :auth, :user_agent, 1)'
    );
    $statement->execute([
        'endpoint' => $endpoint,
        'endpoint_hash' => hash('sha256', $endpoint, true),
        'p256dh' => 'scheduled-p256dh-' . $suffix,
        'auth' => 'scheduled-auth-' . $suffix,
        'user_agent' => 'Scheduled Push Test',
    ]);
    return (int) $connection->lastInsertId();
}

function scheduledPushSetTime(PDO $connection, int $testId, DateTimeImmutable $time): void
{
    $statement = $connection->prepare('UPDATE web_push_scheduled_tests SET scheduled_at_utc = :time WHERE id = :id');
    $statement->execute(['time' => astronomyPushUtc($time)->format('Y-m-d H:i:s'), 'id' => $testId]);
}

$connection = getWebDatabaseConnection();
$utc = new DateTimeZone('UTC');
$now = new DateTimeImmutable('2099-06-01 12:00:00', $utc);
$connection->beginTransaction();
try {
    $subscriptionId = scheduledPushSubscription($connection);
    astronomyPushSaveDevice($connection, $subscriptionId, [
        'device_name' => 'Dispositivo programado', 'notifications_enabled' => '1',
        'location_name' => 'Buenos Aires', 'latitude' => '-34.60', 'longitude' => '-58.38',
        'timezone' => 'America/Argentina/Buenos_Aires', 'quiet_hours_enabled' => '0',
        'quiet_start_local' => '', 'quiet_end_local' => '', 'moonrise_enabled' => '0',
    ]);

    foreach ([1, 3, 5] as $delay) {
        $scheduled = astronomyPushScheduleTest($connection, $subscriptionId, $delay, true, $now);
        scheduledPushAssert(
            $scheduled['scheduled_at_utc']->format('Y-m-d H:i:s') === $now->modify('+' . $delay . ' minutes')->format('Y-m-d H:i:s'),
            'La programación a ' . $delay . ' minutos no fue exacta.'
        );
    }
    $firstThree = astronomyPushScheduledTests($connection, $subscriptionId, 3);
    scheduledPushAssert(count($firstThree) === 3, 'No se persistieron las tres programaciones.');

    $localMessage = astronomyPushRenderNotification(
        astronomyPushNotificationType($connection, 'test'),
        ['event_time_utc' => new DateTimeImmutable('2099-06-01 15:05:00', $utc)],
        astronomyPushScheduledTestDevice($connection, $subscriptionId)
    );
    scheduledPushAssert($localMessage['body'] === 'Notificación automática programada para las 12:05.',
        'La hora UTC no se convirtió a la zona local del dispositivo.');

    $atomic = astronomyPushScheduleTest($connection, $subscriptionId, 1, true, $now);
    scheduledPushAssert(astronomyPushClaimScheduledTest($connection, $atomic['id']), 'No se reclamó la prueba pendiente.');
    scheduledPushAssert(!astronomyPushClaimScheduledTest($connection, $atomic['id']), 'La prueba se reclamó dos veces.');

    $cancel = astronomyPushScheduleTest($connection, $subscriptionId, 3, true, $now);
    scheduledPushAssert(astronomyPushCancelScheduledTest($connection, $subscriptionId, $cancel['id'], $now),
        'No se canceló la prueba pendiente.');
    scheduledPushAssert(!astronomyPushCancelScheduledTest($connection, $subscriptionId, $cancel['id'], $now),
        'Se volvió a cancelar una prueba ya finalizada.');

    $failed = false;
    try { astronomyPushScheduleTest($connection, 9223372036854775807, 1, true, $now); }
    catch (InvalidArgumentException) { $failed = true; }
    scheduledPushAssert($failed, 'Se aceptó una suscripción inexistente.');

    $sent = astronomyPushScheduleTest($connection, $subscriptionId, 1, false, $now);
    scheduledPushSetTime($connection, $sent['id'], $now);
    $senderCalls = 0;
    $result = astronomyPushProcessScheduledTests($connection, $now, false, $subscriptionId,
        static function (array $device, array $message) use (&$senderCalls): array {
            $senderCalls++;
            scheduledPushAssert($message['title'] === 'Prueba de Aquellas Lunas', 'El título de la prueba no es correcto.');
            return ['success' => 1, 'failed' => 0, 'errors' => []];
        });
    scheduledPushAssert($result['sent'] === 1 && $senderCalls === 1, 'La prueba vencida no llegó al emisor inyectado.');
    $sentRow = $connection->query('SELECT status, notification_log_id FROM web_push_scheduled_tests WHERE id = '
        . (int) $sent['id'])->fetch();
    scheduledPushAssert($sentRow['status'] === 'sent' && (int) $sentRow['notification_log_id'] > 0,
        'La prueba enviada no quedó vinculada al historial.');
    $log = $connection->query('SELECT notification_type, event_key FROM web_push_notification_log WHERE id = '
        . (int) $sentRow['notification_log_id'])->fetch();
    scheduledPushAssert($log['notification_type'] === 'test' && $log['event_key'] === 'test:' . $sent['id'],
        'La deduplicación de la prueba no usa test:{id}.');

    astronomyPushSaveDevice($connection, $subscriptionId, [
        'device_name' => 'Dispositivo programado', 'notifications_enabled' => '1',
        'location_name' => 'UTC', 'latitude' => '0', 'longitude' => '0', 'timezone' => 'UTC',
        'quiet_hours_enabled' => '1', 'quiet_start_local' => '11:00', 'quiet_end_local' => '13:00',
        'moonrise_enabled' => '0',
    ]);
    $quiet = astronomyPushScheduleTest($connection, $subscriptionId, 1, true, $now);
    scheduledPushSetTime($connection, $quiet['id'], $now);
    $quietCalls = 0;
    $quietResult = astronomyPushProcessScheduledTests($connection, $now, false, $subscriptionId,
        static function () use (&$quietCalls): array { $quietCalls++; return ['success' => 1, 'failed' => 0]; });
    scheduledPushAssert($quietResult['skipped'] === 1 && $quietCalls === 0,
        'No molestar no omitió la prueba antes del envío.');

    $ignoreQuiet = astronomyPushScheduleTest($connection, $subscriptionId, 1, false, $now);
    scheduledPushSetTime($connection, $ignoreQuiet['id'], $now);
    $ignoreCalls = 0;
    astronomyPushProcessScheduledTests($connection, $now, false, $subscriptionId,
        static function () use (&$ignoreCalls): array { $ignoreCalls++; return ['success' => 1, 'failed' => 0]; });
    scheduledPushAssert($ignoreCalls === 1, 'La prueba que ignora No molestar fue omitida.');

    $expired = astronomyPushScheduleTest($connection, $subscriptionId, 1, true, $now);
    scheduledPushSetTime($connection, $expired['id'], $now->modify('-11 minutes'));
    $expiredResult = astronomyPushProcessScheduledTests($connection, $now, false, $subscriptionId,
        static fn(): array => ['success' => 1, 'failed' => 0]);
    scheduledPushAssert($expiredResult['expired'] === 1, 'La prueba fuera de tolerancia no quedó vencida.');

    $deduplicated = astronomyPushScheduleTest($connection, $subscriptionId, 1, false, $now);
    scheduledPushSetTime($connection, $deduplicated['id'], $now);
    astronomyPushClaim($connection, $subscriptionId, 'test', 'test:' . $deduplicated['id'], $now, $now, $now);
    $dedupResult = astronomyPushProcessScheduledTests($connection, $now, false, $subscriptionId,
        static fn(): array => ['success' => 1, 'failed' => 0]);
    scheduledPushAssert($dedupResult['deduplicated'] === 1, 'No se respetó la deduplicación test:{id}.');

    $adminSource = file_get_contents(__DIR__ . '/../admin/notificaciones-astronomicas.php');
    scheduledPushAssert(is_string($adminSource) && !str_contains($adminSource, 'astronomyWebPushSend('),
        'La página administrativa contiene un envío Web Push directo.');
} finally {
    $connection->rollBack();
}

echo "OK web push scheduled tests\n";
