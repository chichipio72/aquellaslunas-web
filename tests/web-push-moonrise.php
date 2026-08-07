<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/web-push-moonrise.php';

function moonrisePushAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function moonrisePushLogRow(PDO $connection, DateTimeImmutable $eventUtc): ?array
{
    $statement = $connection->prepare(
        'SELECT status, attempted_at, sent_at, error_message FROM web_push_notification_log '
        . 'WHERE subscription_id = ? AND notification_type = ? AND event_time_utc = ?'
    );
    $statement->execute([
        MOONRISE_PUSH_SUBSCRIPTION_ID,
        MOONRISE_PUSH_NOTIFICATION_TYPE,
        astronomyMoonrisePushUtc($eventUtc)->format('Y-m-d H:i:s'),
    ]);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

$timezone = new DateTimeZone(MOONRISE_PUSH_TIMEZONE);
$utc = new DateTimeZone('UTC');
$localEvent = new DateTimeImmutable('2026-08-04 00:10:00', $timezone);
$eventUtc = astronomyMoonrisePushUtc($localEvent);
$notificationUtc = astronomyMoonrisePushNotificationTime($eventUtc);

moonrisePushAssert($eventUtc->format('Y-m-d H:i:s') === '2026-08-04 03:10:00', 'Falló la conversión de hora local a UTC.');
moonrisePushAssert($notificationUtc->format('Y-m-d H:i:s') === '2026-08-04 02:55:00', 'El aviso nominal no quedó 15 minutos antes.');
moonrisePushAssert(astronomyMoonrisePushIsDue($notificationUtc, new DateTimeImmutable('2026-08-04 02:55:00', $utc)), 'El instante nominal no entró en la ventana.');
moonrisePushAssert(astronomyMoonrisePushIsDue($notificationUtc, new DateTimeImmutable('2026-08-04 03:01:00', $utc)), 'El límite retrospectivo no entró en la ventana.');
moonrisePushAssert(!astronomyMoonrisePushIsDue($notificationUtc, new DateTimeImmutable('2026-08-04 02:54:59', $utc)), 'Se eligió un aviso futuro.');
moonrisePushAssert(!astronomyMoonrisePushIsDue($notificationUtc, new DateTimeImmutable('2026-08-04 03:01:01', $utc)), 'Se eligió un aviso vencido.');

$datesSeen = [];
$syntheticEvents = astronomyMoonrisePushEvents(
    new DateTimeImmutable('2026-08-03 23:58:00', $timezone),
    static function (DateTimeImmutable $date) use (&$datesSeen, $timezone): ?DateTimeImmutable {
        $datesSeen[] = $date->format('Y-m-d');
        return new DateTimeImmutable($date->format('Y-m-d') . ' 20:00:00', $timezone);
    }
);
moonrisePushAssert($datesSeen === ['2026-08-03', '2026-08-04'], 'No se calcularon hoy y mañana locales.');
moonrisePushAssert(count($syntheticEvents) === 2, 'No se devolvieron ambos eventos sintéticos.');
$withoutNull = astronomyMoonrisePushEvents(
    new DateTimeImmutable('2026-08-03 12:00:00', $timezone),
    static fn(DateTimeImmutable $date): ?DateTimeImmutable => $date->format('Y-m-d') === '2026-08-03' ? null : $localEvent
);
moonrisePushAssert(count($withoutNull) === 1 && $withoutNull[0]['local_date'] === '2026-08-04', 'moonrise null no fue ignorado correctamente.');

$message = astronomyMoonrisePushMessage($localEvent);
moonrisePushAssert($message === [
    'title' => 'La Luna sale en 15 minutos',
    'body' => 'Salida prevista a las 00:10 en Vicente López.',
    'url' => './sol-y-luna.php',
], 'El contenido de la notificación no es exacto.');

$realEvents = astronomyMoonrisePushEvents(new DateTimeImmutable('2026-08-03 12:00:00', $timezone));
moonrisePushAssert(count($realEvents) >= 1 && count($realEvents) <= 2, 'El motor lunar no produjo una salida válida para hoy o mañana.');

$connection = getWebDatabaseConnection();
$connection->beginTransaction();
try {
    $testEvent = new DateTimeImmutable('2099-12-30 12:34:56', $utc);
    $testNotice = astronomyMoonrisePushNotificationTime($testEvent);
    $attemptOne = new DateTimeImmutable('2099-12-30 12:20:00', $utc);
    $first = astronomyMoonrisePushClaim($connection, $testEvent, $testNotice, $attemptOne);
    moonrisePushAssert($first['claimed'] && $first['reason'] === 'new', 'No se reclamó por primera vez el evento.');
    $duplicateProcessing = astronomyMoonrisePushClaim($connection, $testEvent, $testNotice, $attemptOne->modify('+1 minute'));
    moonrisePushAssert(!$duplicateProcessing['claimed'], 'Se reclamó dos veces un processing vigente.');
    moonrisePushAssert(astronomyMoonrisePushComplete($connection, $testEvent, $first['attempted_at'], true), 'No se completó el evento como sent.');
    $sentRetry = astronomyMoonrisePushClaim($connection, $testEvent, $testNotice, $attemptOne->modify('+20 minutes'));
    moonrisePushAssert(!$sentRetry['claimed'], 'Se volvió a reclamar un evento sent.');

    $failedEvent = $testEvent->modify('+1 second');
    $failedFirst = astronomyMoonrisePushClaim($connection, $failedEvent, astronomyMoonrisePushNotificationTime($failedEvent), $attemptOne);
    moonrisePushAssert(astronomyMoonrisePushComplete($connection, $failedEvent, $failedFirst['attempted_at'], false, 'Fallo temporal'), 'No se completó el evento como failed.');
    $failedRetry = astronomyMoonrisePushClaim($connection, $failedEvent, astronomyMoonrisePushNotificationTime($failedEvent), $attemptOne->modify('+1 minute'));
    moonrisePushAssert($failedRetry['claimed'] && $failedRetry['reason'] === 'retry', 'No se recuperó un evento failed.');

    $staleEvent = $testEvent->modify('+2 seconds');
    $staleFirst = astronomyMoonrisePushClaim($connection, $staleEvent, astronomyMoonrisePushNotificationTime($staleEvent), $attemptOne);
    moonrisePushAssert($staleFirst['claimed'], 'No se creó el processing para probar abandono.');
    $staleRetry = astronomyMoonrisePushClaim($connection, $staleEvent, astronomyMoonrisePushNotificationTime($staleEvent), $attemptOne->modify('+11 minutes'));
    moonrisePushAssert($staleRetry['claimed'] && $staleRetry['reason'] === 'retry', 'No se recuperó un processing vencido.');

    $dryRunEvent = $realEvents[0];
    $simulatedNow = $dryRunEvent['notification_utc']->setTimezone($timezone)->format('Y-m-d H:i:s');
    $before = moonrisePushLogRow($connection, $dryRunEvent['event_utc']);
    $command = escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(__DIR__ . '/../web-push/send-moonrise-reminder.php') . ' --dry-run --now=' . escapeshellarg($simulatedNow);
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    moonrisePushAssert($exitCode === 0, 'El dry-run terminó con error: ' . implode(' ', $output));
    moonrisePushAssert(str_contains(implode("\n", $output), 'dry-run sin escritura ni envío'), 'El dry-run no anunció la decisión esperada.');
    moonrisePushAssert(moonrisePushLogRow($connection, $dryRunEvent['event_utc']) === $before,
        'El dry-run alteró la fila existente en la tabla de deduplicación.');
} finally {
    $connection->rollBack();
}

echo "OK web push moonrise\n";
