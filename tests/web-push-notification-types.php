<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/web-push-astronomy.php';
require_once __DIR__ . '/../scripts/migrations/create-web-push-notification-types.php';

function notificationTypeAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function notificationTypeTestSubscription(PDO $connection): int
{
    $suffix = bin2hex(random_bytes(6));
    $endpoint = 'https://push.invalid.test/catalog-' . $suffix;
    $statement = $connection->prepare(
        'INSERT INTO web_push_subscriptions (endpoint, endpoint_hash, p256dh, auth, user_agent, active) '
        . 'VALUES (:endpoint, :endpoint_hash, :p256dh, :auth, :user_agent, 1)'
    );
    $statement->execute(['endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint, true),
        'p256dh' => 'catalog-' . $suffix, 'auth' => 'catalog-' . $suffix, 'user_agent' => 'Catalog Test']);
    return (int) $connection->lastInsertId();
}

$connection = getWebDatabaseConnection();
runWebPushNotificationTypesMigration($connection);
runWebPushNotificationTypesMigration($connection);

$moonrise = astronomyPushNotificationType($connection, 'moonrise');
$testType = astronomyPushNotificationType($connection, 'test');
notificationTypeAssert(is_array($moonrise) && $moonrise['display_name'] === 'Salida de la Luna'
    && (int) $moonrise['available'] === 1 && (int) $moonrise['admin_only'] === 0,
    'El seed moonrise no es correcto.');
notificationTypeAssert(is_array($testType) && $testType['display_name'] === 'Prueba del sistema'
    && (int) $testType['available'] === 1 && (int) $testType['admin_only'] === 1,
    'El seed test no es correcto.');

$originalDescription = $moonrise['description'];
try {
    $connection->prepare("UPDATE web_push_notification_types SET description = 'Edición conservada' WHERE notification_type = 'moonrise'")->execute();
    runWebPushNotificationTypesMigration($connection);
    $preserved = astronomyPushNotificationType($connection, 'moonrise');
    notificationTypeAssert($preserved['description'] === 'Edición conservada',
        'La migración sobrescribió una edición administrativa.');
} finally {
    $restore = $connection->prepare('UPDATE web_push_notification_types SET description = :description WHERE notification_type = \'moonrise\'');
    $restore->execute(['description' => $originalDescription]);
}

$valid = astronomyPushValidateNotificationType($moonrise);
notificationTypeAssert($valid['title_template'] === 'La Luna sale pronto',
    'Se rechazaron marcadores válidos.');
foreach ([
    array_replace($moonrise, ['title_template' => 'Aviso {unknown}']),
    array_replace($moonrise, ['body_template' => '<strong>Aviso</strong>']),
    array_replace($moonrise, ['title_template' => str_repeat('a', 181)]),
    array_replace($moonrise, ['body_template' => str_repeat('b', 501)]),
    array_replace($moonrise, ['target_url' => 'javascript:alert(1)']),
    array_replace($moonrise, ['target_url' => 'data:text/plain,x']),
    array_replace($moonrise, ['target_url' => 'file:///tmp/x']),
    array_replace($testType, ['body_template' => 'Prueba en {lead_minutes} minutos']),
] as $invalid) {
    $failed = false;
    try { astronomyPushValidateNotificationType($invalid); }
    catch (InvalidArgumentException) { $failed = true; }
    notificationTypeAssert($failed, 'Se aceptó una plantilla o URL inválida.');
}
$titleBeforeRejectedSave = $moonrise['title_template'];
$failedSave = false;
try { astronomyPushSaveNotificationType($connection, 'moonrise', array_replace($moonrise,
    ['title_template' => 'Aviso {unknown}'])); }
catch (InvalidArgumentException) { $failedSave = true; }
notificationTypeAssert($failedSave
    && astronomyPushNotificationType($connection, 'moonrise')['title_template'] === $titleBeforeRejectedSave,
    'El guardado inválido modificó parcialmente el catálogo.');
notificationTypeAssert(astronomyPushValidateNotificationType(array_replace($moonrise,
    ['target_url' => './sol-y-luna.php']))['target_url'] === './sol-y-luna.php', 'Se rechazó una URL relativa interna.');
notificationTypeAssert(astronomyPushValidateNotificationType(array_replace($moonrise,
    ['target_url' => 'https://aquellaslunas.com.ar/astro/']))['target_url'] === 'https://aquellaslunas.com.ar/astro/',
    'Se rechazó una URL HTTPS.');

$device = ['timezone' => 'America/Argentina/Buenos_Aires', 'location_name' => 'Vicente López',
    'device_name' => 'Celular Android'];
$eventLocal = new DateTimeImmutable('2026-08-04 20:42:00', new DateTimeZone($device['timezone']));
$moonMessage = astronomyPushRenderNotification($moonrise, ['event_time_local' => $eventLocal], $device,
    ['lead_minutes' => 15]);
notificationTypeAssert($moonMessage === ['title' => 'La Luna sale pronto',
    'body' => 'Salida prevista a las 20:42 en Vicente López.', 'url' => './sol-y-luna.php'],
    'El render moonrise no coincide con el catálogo.');
$testMessage = astronomyPushRenderNotification($testType, ['event_time_local' => $eventLocal], $device);
notificationTypeAssert($testMessage['title'] === 'Prueba de Aquellas Lunas'
    && $testMessage['body'] === 'Notificación automática programada para las 20:42.',
    'El render test no coincide con el catálogo.');
$preview = astronomyPushNotificationPreview($moonrise);
notificationTypeAssert($preview['body'] === 'Salida prevista a las 20:42 en Vicente López.',
    'La vista previa segura no usa los datos simulados esperados.');

$connection->beginTransaction();
try {
    $subscriptionId = notificationTypeTestSubscription($connection);
    $connection->exec("UPDATE web_push_notification_types SET default_lead_minutes = 17 WHERE notification_type = 'moonrise'");
    astronomyPushSaveDevice($connection, $subscriptionId, [
        'device_name' => 'Catálogo', 'notifications_enabled' => '1', 'location_name' => 'UTC',
        'latitude' => '0', 'longitude' => '0', 'timezone' => 'UTC', 'quiet_hours_enabled' => '0',
        'quiet_start_local' => '', 'quiet_end_local' => '', 'notification_preferences' => ['moonrise' => '1'],
    ]);
    $preferenceBefore = $connection->query("SELECT COUNT(*) FROM web_push_notification_preferences WHERE subscription_id = "
        . $subscriptionId . " AND notification_type = 'moonrise'")->fetchColumn();
    $initialLead = $connection->query("SELECT lead_minutes FROM web_push_notification_preferences WHERE subscription_id = "
        . $subscriptionId . " AND notification_type = 'moonrise'")->fetchColumn();
    notificationTypeAssert((int) $initialLead === 17, 'La preferencia nueva no tomó el valor predeterminado del catálogo.');
    $connection->exec("UPDATE web_push_notification_types SET default_lead_minutes = 15 WHERE notification_type = 'moonrise'");
    $connection->exec("UPDATE web_push_notification_preferences SET lead_minutes = 15 WHERE subscription_id = " . $subscriptionId
        . " AND notification_type = 'moonrise'");
    $connection->exec("UPDATE web_push_notification_types SET available = 0 WHERE notification_type = 'moonrise'");
    $preferences = astronomyPushDeviceNotificationPreferences($connection, $subscriptionId);
    $moonrisePreference = array_values(array_filter($preferences,
        static fn(array $preference): bool => $preference['notification_type'] === 'moonrise'))[0] ?? null;
    notificationTypeAssert(is_array($moonrisePreference) && (int) $moonrisePreference['available'] === 0
        && (int) $preferenceBefore === 1, 'La preferencia no se conservó al desactivar globalmente el tipo.');

    $now = new DateTimeImmutable('2099-08-04 12:00:00', new DateTimeZone('UTC'));
    $resolver = static fn(array $configured, DateTimeImmutable $clock): array => [[
        'event_key' => 'moonrise', 'event_time_utc' => $clock->modify('+15 minutes'),
        'event_time_local' => $clock->modify('+15 minutes')->setTimezone(new DateTimeZone((string) $configured['timezone'])),
    ]];
    $sendCalls = 0;
    $unavailableResult = astronomyPushProcess($connection,
        astronomyPushConfiguredDevices($connection, $subscriptionId), $now, false, $resolver,
        static function () use (&$sendCalls): array { $sendCalls++; return ['success' => 1, 'failed' => 0]; });
    notificationTypeAssert($unavailableResult['skipped'] === 1 && $sendCalls === 0,
        'El tipo global desactivado llegó al emisor.');
    $reason = $connection->query("SELECT decision_reason FROM web_push_notification_log WHERE subscription_id = "
        . $subscriptionId . " AND notification_type = 'moonrise' ORDER BY id DESC LIMIT 1")->fetchColumn();
    notificationTypeAssert($reason === 'notification_type_unavailable', 'No se registró la omisión global.');

    $connection->exec("UPDATE web_push_notification_types SET available = 1, title_template = 'Catálogo {device_name}' "
        . "WHERE notification_type = 'moonrise'");
    $nextNow = $now->modify('+1 day');
    $captured = null;
    $sentResult = astronomyPushProcess($connection, astronomyPushConfiguredDevices($connection, $subscriptionId),
        $nextNow, false, $resolver, static function (array $configured, array $message) use (&$captured): array {
            $captured = $message;
            return ['success' => 1, 'failed' => 0, 'errors' => []];
        });
    notificationTypeAssert($sentResult['sent'] === 1 && $captured['title'] === 'Catálogo Catálogo',
        'Moonrise no utilizó el título administrado del catálogo.');
    $history = $connection->query("SELECT title_sent, body_sent, target_url_sent FROM web_push_notification_log "
        . "WHERE subscription_id = $subscriptionId AND notification_type = 'moonrise' AND status = 'sent' "
        . 'ORDER BY id DESC LIMIT 1')->fetch();
    notificationTypeAssert($history['title_sent'] === $captured['title']
        && $history['body_sent'] === $captured['body'] && $history['target_url_sent'] === $captured['url'],
        'El historial no conservó el contenido enviado.');

    $normalTypes = astronomyPushDeviceNotificationPreferences($connection, $subscriptionId);
    notificationTypeAssert(!in_array('test', array_column($normalTypes, 'notification_type'), true),
        'El tipo test apareció como preferencia normal del dispositivo.');
} finally {
    $connection->rollBack();
}

$adminSource = file_get_contents(__DIR__ . '/../admin/notificaciones-astronomicas.php');
$moduleSource = file_get_contents(__DIR__ . '/../includes/web-push-astronomy.php');
notificationTypeAssert(is_string($adminSource) && str_contains($adminSource, 'Probar notificaciones'),
    'La prueba administrativa dejó de estar disponible.');
notificationTypeAssert(is_string($moduleSource)
    && !str_contains($moduleSource, "'La Luna sale en '")
    && !str_contains($moduleSource, "'Prueba de Aquellas Lunas'"),
    'Quedaron textos operativos fijos para moonrise o test en el procesador.');

echo "OK web push notification types\n";
