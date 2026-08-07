<?php

declare(strict_types=1);

require_once __DIR__ . '/api-client.php';
require_once __DIR__ . '/web-push.php';

const MOONRISE_PUSH_SUBSCRIPTION_ID = 11;
const MOONRISE_PUSH_NOTIFICATION_TYPE = 'moonrise';
const MOONRISE_PUSH_LATITUDE = -34.52;
const MOONRISE_PUSH_LONGITUDE = -58.48;
const MOONRISE_PUSH_ELEVATION_METERS = 0.0;
const MOONRISE_PUSH_TIMEZONE = 'America/Argentina/Buenos_Aires';
const MOONRISE_PUSH_LOCATION = 'Vicente López';
const MOONRISE_PUSH_ADVANCE_MINUTES = 15;
// Permite cron cada cinco minutos con un minuto adicional de tolerancia.
const MOONRISE_PUSH_LOOKBACK_MINUTES = 6;
const MOONRISE_PUSH_PROCESSING_STALE_MINUTES = 10;
const MOONRISE_PUSH_TARGET_URL = './sol-y-luna.php';

function astronomyMoonrisePushUtc(DateTimeImmutable $date): DateTimeImmutable
{
    return $date->setTimezone(new DateTimeZone('UTC'));
}

function astronomyMoonrisePushNotificationTime(DateTimeImmutable $eventTimeUtc): DateTimeImmutable
{
    return astronomyMoonrisePushUtc($eventTimeUtc)->modify('-' . MOONRISE_PUSH_ADVANCE_MINUTES . ' minutes');
}

function astronomyMoonrisePushIsDue(
    DateTimeImmutable $notificationTimeUtc,
    DateTimeImmutable $nowUtc,
    int $lookbackMinutes = MOONRISE_PUSH_LOOKBACK_MINUTES
): bool {
    $notificationTimeUtc = astronomyMoonrisePushUtc($notificationTimeUtc);
    $nowUtc = astronomyMoonrisePushUtc($nowUtc);
    $notificationTimestamp = $notificationTimeUtc->getTimestamp();
    $nowTimestamp = $nowUtc->getTimestamp();
    return $notificationTimestamp <= $nowTimestamp
        && $notificationTimestamp >= $nowTimestamp - ($lookbackMinutes * 60);
}

/**
 * @param null|callable(DateTimeImmutable): ?DateTimeImmutable $moonriseForDate
 * @return list<array{local_date:string,event_local:DateTimeImmutable,event_utc:DateTimeImmutable,notification_utc:DateTimeImmutable}>
 */
function astronomyMoonrisePushEvents(DateTimeImmutable $nowLocal, ?callable $moonriseForDate = null): array
{
    $timezone = new DateTimeZone(MOONRISE_PUSH_TIMEZONE);
    $today = new DateTimeImmutable($nowLocal->setTimezone($timezone)->format('Y-m-d') . ' 00:00:00', $timezone);
    if ($moonriseForDate === null) {
        $calculator = new AstronomyEngine\LunarDayCalculator(new AstronomyEngine\MeeusLunarCalculator());
        $moonriseForDate = static function (DateTimeImmutable $localDate) use ($calculator): ?DateTimeImmutable {
            return $calculator->calculate(
                $localDate,
                MOONRISE_PUSH_LATITUDE,
                MOONRISE_PUSH_LONGITUDE,
                MOONRISE_PUSH_ELEVATION_METERS
            )->moonrise;
        };
    }

    $events = [];
    foreach ([$today, $today->modify('+1 day')] as $localDate) {
        $moonrise = $moonriseForDate($localDate);
        if (!$moonrise instanceof DateTimeImmutable) {
            continue;
        }
        $eventUtc = astronomyMoonrisePushUtc($moonrise);
        $events[] = [
            'local_date' => $localDate->format('Y-m-d'),
            'event_local' => $moonrise->setTimezone($timezone),
            'event_utc' => $eventUtc,
            'notification_utc' => astronomyMoonrisePushNotificationTime($eventUtc),
        ];
    }
    return $events;
}

/** @return array{title:string,body:string,url:string} */
function astronomyMoonrisePushMessage(DateTimeImmutable $eventLocal): array
{
    return [
        'title' => 'La Luna sale en 15 minutos',
        'body' => 'Salida prevista a las ' . $eventLocal->format('H:i') . ' en ' . MOONRISE_PUSH_LOCATION . '.',
        'url' => MOONRISE_PUSH_TARGET_URL,
    ];
}

function astronomyMoonrisePushSubscriptionIsActive(PDO $connection): bool
{
    $statement = $connection->prepare('SELECT active FROM web_push_subscriptions WHERE id = :id');
    $statement->execute(['id' => MOONRISE_PUSH_SUBSCRIPTION_ID]);
    $row = $statement->fetch();
    return is_array($row) && (int) $row['active'] === 1;
}

/** @return array{claimed:bool,reason:string,attempted_at:string} */
function astronomyMoonrisePushClaim(
    PDO $connection,
    DateTimeImmutable $eventTimeUtc,
    DateTimeImmutable $notificationTimeUtc,
    DateTimeImmutable $attemptedAtUtc,
    int $staleMinutes = MOONRISE_PUSH_PROCESSING_STALE_MINUTES
): array {
    $event = astronomyMoonrisePushUtc($eventTimeUtc)->format('Y-m-d H:i:s');
    $notification = astronomyMoonrisePushUtc($notificationTimeUtc)->format('Y-m-d H:i:s');
    $attempted = astronomyMoonrisePushUtc($attemptedAtUtc)->format('Y-m-d H:i:s');
    $staleBefore = astronomyMoonrisePushUtc($attemptedAtUtc)->modify('-' . $staleMinutes . ' minutes')->format('Y-m-d H:i:s');

    $insert = $connection->prepare(
        'INSERT IGNORE INTO web_push_notification_log '
        . '(subscription_id, notification_type, event_key, event_time_utc, notification_time_utc, status, attempted_at) '
        . 'VALUES (:subscription_id, :notification_type, :event_key, :event_time_utc, :notification_time_utc, \'processing\', :attempted_at)'
    );
    $insert->execute([
        'subscription_id' => MOONRISE_PUSH_SUBSCRIPTION_ID,
        'notification_type' => MOONRISE_PUSH_NOTIFICATION_TYPE,
        'event_key' => MOONRISE_PUSH_NOTIFICATION_TYPE,
        'event_time_utc' => $event,
        'notification_time_utc' => $notification,
        'attempted_at' => $attempted,
    ]);
    if ($insert->rowCount() === 1) {
        return ['claimed' => true, 'reason' => 'new', 'attempted_at' => $attempted];
    }

    $retry = $connection->prepare(
        'UPDATE web_push_notification_log SET status = \'processing\', attempted_at = :attempted_at, '
        . 'notification_time_utc = :notification_time_utc, sent_at = NULL, error_message = NULL '
        . 'WHERE subscription_id = :subscription_id AND notification_type = :notification_type '
        . 'AND event_key = :event_key AND event_time_utc = :event_time_utc '
        . 'AND (status = \'failed\' OR (status = \'processing\' AND attempted_at <= :stale_before))'
    );
    $retry->execute([
        'attempted_at' => $attempted,
        'notification_time_utc' => $notification,
        'subscription_id' => MOONRISE_PUSH_SUBSCRIPTION_ID,
        'notification_type' => MOONRISE_PUSH_NOTIFICATION_TYPE,
        'event_key' => MOONRISE_PUSH_NOTIFICATION_TYPE,
        'event_time_utc' => $event,
        'stale_before' => $staleBefore,
    ]);
    if ($retry->rowCount() === 1) {
        return ['claimed' => true, 'reason' => 'retry', 'attempted_at' => $attempted];
    }
    return ['claimed' => false, 'reason' => 'deduplicated', 'attempted_at' => $attempted];
}

function astronomyMoonrisePushComplete(
    PDO $connection,
    DateTimeImmutable $eventTimeUtc,
    string $attemptedAt,
    bool $success,
    ?string $errorMessage = null
): bool {
    $message = $success ? null : astronomyMoonrisePushSanitizeError($errorMessage);
    $statement = $connection->prepare(
        'UPDATE web_push_notification_log SET status = :status, sent_at = :sent_at, error_message = :error_message '
        . 'WHERE subscription_id = :subscription_id AND notification_type = :notification_type '
        . 'AND event_key = :event_key AND event_time_utc = :event_time_utc AND status = \'processing\' AND attempted_at = :attempted_at'
    );
    $statement->execute([
        'status' => $success ? 'sent' : 'failed',
        'sent_at' => $success ? gmdate('Y-m-d H:i:s') : null,
        'error_message' => $message,
        'subscription_id' => MOONRISE_PUSH_SUBSCRIPTION_ID,
        'notification_type' => MOONRISE_PUSH_NOTIFICATION_TYPE,
        'event_key' => MOONRISE_PUSH_NOTIFICATION_TYPE,
        'event_time_utc' => astronomyMoonrisePushUtc($eventTimeUtc)->format('Y-m-d H:i:s'),
        'attempted_at' => $attemptedAt,
    ]);
    return $statement->rowCount() === 1;
}

function astronomyMoonrisePushSanitizeError(?string $message): string
{
    return astronomyWebPushSanitizeError($message);
}
