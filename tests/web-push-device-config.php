<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/web-push-device-config.php';

function pushDeviceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function pushDeviceBase64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function pushDeviceSubscription(string $suffix): array
{
    return [
        'endpoint' => 'https://push.invalid.test/device-config-' . $suffix,
        'keys' => [
            'p256dh' => pushDeviceBase64Url("\x04" . str_repeat("\x01", 64)),
            'auth' => pushDeviceBase64Url(str_repeat("\x02", 16)),
        ],
    ];
}

$connection = getWebDatabaseConnection();
$subscriptionElevenBefore = $connection->query(
    'SELECT active, updated_at FROM web_push_subscriptions WHERE id = 11'
)->fetch();
$connection->beginTransaction();
try {
    $subscription = pushDeviceSubscription(bin2hex(random_bytes(6)));
    astronomyWebPushSaveSubscription($connection, astronomyWebPushValidateSubscription($subscription),
        'Mozilla/5.0 (Linux; Android 14) Chrome/150.0');
    $state = astronomyPushCurrentDeviceState($connection, $subscription);
    pushDeviceAssert($state['configured'] === false && $state['device'] === null,
        'Una suscripción nueva apareció configurada.');
    pushDeviceAssert($state['suggested_device_name'] === 'Celular Android',
        'No se sugirió un nombre legible para Android.');
    pushDeviceAssert(in_array('moonrise', array_column($state['notification_types'], 'notification_type'), true),
        'Moonrise no aparece en el catálogo público.');
    pushDeviceAssert(!in_array('test', array_column($state['notification_types'], 'notification_type'), true),
        'El tipo administrativo test quedó expuesto públicamente.');
    $publicStateJson = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    pushDeviceAssert(is_string($publicStateJson) && !str_contains($publicStateJson, 'endpoint')
        && !str_contains($publicStateJson, 'p256dh') && !str_contains($publicStateJson, '"auth"'),
        'La respuesta pública expone datos secretos de la suscripción.');

    $saved = astronomyPushSaveCurrentDevice($connection, $subscription, [
        'device_name' => 'Mi teléfono', 'notifications_enabled' => true,
        'location_name' => 'Vicente López', 'latitude' => '-34.52', 'longitude' => '-58.48',
        'timezone' => 'America/Argentina/Buenos_Aires', 'quiet_hours_enabled' => true,
        'quiet_start_local' => '23:00', 'quiet_end_local' => '08:00',
        'notification_preferences' => ['moonrise' => true],
    ]);
    pushDeviceAssert($saved['configured'] === true && $saved['device']['device_name'] === 'Mi teléfono'
        && $saved['device']['location_name'] === 'Vicente López'
        && $saved['device']['quiet_hours_enabled'] === true,
        'La configuración inicial no se guardó correctamente.');
    $supportId = astronomyPushNormalizeSupportId($saved['device']['support_id'] ?? null);
    pushDeviceAssert($supportId !== null && $supportId === $saved['device']['support_id'],
        'La configuración no recibió un ID de soporte público válido.');
    $moonrise = array_values(array_filter($saved['notification_types'],
        static fn(array $type): bool => $type['notification_type'] === 'moonrise'))[0] ?? null;
    pushDeviceAssert(is_array($moonrise) && $moonrise['enabled'] === true && $moonrise['lead_minutes'] === 15,
        'La preferencia moonrise no tomó el valor predeterminado esperado.');

    $edited = astronomyPushSaveCurrentDevice($connection, $subscription, [
        'device_name' => 'Teléfono editado', 'notifications_enabled' => false,
        'location_name' => 'San Isidro', 'latitude' => '-34.47', 'longitude' => '-58.51',
        'timezone' => 'America/Argentina/Buenos_Aires', 'quiet_hours_enabled' => false,
        'quiet_start_local' => '23:00', 'quiet_end_local' => '08:00',
        'notification_preferences' => [],
    ]);
    pushDeviceAssert($edited['device']['device_name'] === 'Teléfono editado'
        && $edited['device']['notifications_enabled'] === false,
        'La edición posterior no se conservó.');
    pushDeviceAssert($edited['device']['support_id'] === $supportId,
        'El ID de soporte cambió al editar la configuración.');

    $wrongKeys = $subscription;
    $wrongKeys['keys']['auth'] = pushDeviceBase64Url(str_repeat("\x03", 16));
    $denied = false;
    try { astronomyPushCurrentDeviceState($connection, $wrongKeys); }
    catch (AstronomyPushSubscriptionAccessException) { $denied = true; }
    pushDeviceAssert($denied, 'Se identificó una suscripción con credenciales distintas.');

    $invalidType = false;
    try {
        astronomyPushSaveCurrentDevice($connection, $subscription, [
            'device_name' => 'Intento', 'notifications_enabled' => true,
            'location_name' => 'Lugar', 'latitude' => 0, 'longitude' => 0, 'timezone' => 'UTC',
            'quiet_hours_enabled' => false, 'quiet_start_local' => '23:00', 'quiet_end_local' => '08:00',
            'notification_preferences' => ['test' => true],
        ]);
    } catch (InvalidArgumentException) { $invalidType = true; }
    pushDeviceAssert($invalidType, 'La configuración pública aceptó un tipo administrativo.');
    $afterRejectedType = astronomyPushCurrentDeviceState($connection, $subscription);
    pushDeviceAssert($afterRejectedType['device']['device_name'] === 'Teléfono editado',
        'Un guardado rechazado dejó cambios parciales.');

    astronomyWebPushDeactivateSubscription($connection, $subscription['endpoint']);
    $inactiveDenied = false;
    try { astronomyPushCurrentDeviceState($connection, $subscription); }
    catch (AstronomyPushSubscriptionAccessException) { $inactiveDenied = true; }
    pushDeviceAssert($inactiveDenied, 'Una suscripción desactivada conservó acceso público.');
    astronomyWebPushSaveSubscription($connection, astronomyWebPushValidateSubscription($subscription),
        'Mozilla/5.0 (Linux; Android 14) Chrome/150.0');
    $reactivated = astronomyPushCurrentDeviceState($connection, $subscription);
    pushDeviceAssert($reactivated['configured'] === true,
        'La reactivación no conservó la configuración existente.');
    pushDeviceAssert($reactivated['device']['support_id'] === $supportId,
        'El ID de soporte cambió al reactivar la misma suscripción.');

    pushDeviceAssert(astronomyPushDeviceRequestIsSameOrigin([
        'HTTP_HOST' => 'example.test', 'HTTP_ORIGIN' => 'https://example.test', 'HTTP_SEC_FETCH_SITE' => 'same-origin',
    ]), 'Se rechazó un origen propio.');
    pushDeviceAssert(!astronomyPushDeviceRequestIsSameOrigin([
        'HTTP_HOST' => 'example.test', 'HTTP_ORIGIN' => 'https://attacker.test', 'HTTP_SEC_FETCH_SITE' => 'cross-site',
    ]), 'Se aceptó un origen externo.');

    foreach ([
        ['timezone' => 'Zona/Inexistente'],
        ['quiet_hours_enabled' => true, 'quiet_start_local' => '23:00', 'quiet_end_local' => '23:00'],
    ] as $invalidEdit) {
        $failed = false;
        try { astronomyPushSaveCurrentDevice($connection, $subscription, array_replace([
            'device_name' => 'Validación', 'notifications_enabled' => true, 'location_name' => 'Lugar',
            'latitude' => 0, 'longitude' => 0, 'timezone' => 'UTC', 'quiet_hours_enabled' => false,
            'quiet_start_local' => '23:00', 'quiet_end_local' => '08:00', 'notification_preferences' => [],
        ], $invalidEdit)); } catch (InvalidArgumentException) { $failed = true; }
        pushDeviceAssert($failed, 'Se aceptó una configuración pública inválida.');
    }
} finally {
    $connection->rollBack();
}

$subscriptionElevenAfter = $connection->query(
    'SELECT active, updated_at FROM web_push_subscriptions WHERE id = 11'
)->fetch();
pushDeviceAssert($subscriptionElevenBefore === $subscriptionElevenAfter,
    'La prueba modificó la suscripción 11.');

$page = file_get_contents(__DIR__ . '/../notificaciones.php');
$script = file_get_contents(__DIR__ . '/../assets/js/notification-settings.js');
pushDeviceAssert(is_string($page) && !str_contains($page, 'subscription_id'),
    'La página pública usa un ID de suscripción como credencial.');
pushDeviceAssert(is_string($script) && !str_contains($script, 'navigator.geolocation.getCurrentPosition')
    && str_contains($script, "Notification.permission !== 'granted'")
    && str_contains($script, 'display-mode: standalone'),
    'Faltan estados o ayuda PWA, o se reintrodujo una ubicación paralela en el cliente.');
pushDeviceAssert(!str_contains($script, 'p256dh') && !str_contains($script, '.auth'),
    'El cliente manipula innecesariamente secretos de la suscripción.');

echo "OK web push device config\n";
