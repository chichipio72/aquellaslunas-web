<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/site-header.php';

function notificationLocationAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function notificationLocationHeader(string $requestUri, string $scriptName): string
{
    $_SERVER['REQUEST_URI'] = $requestUri;
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    ob_start();
    renderAstronomySiteHeader('notifications', [
        'name' => 'Buenos Aires', 'latitude' => -34.53, 'longitude' => -58.48,
        'timezone' => 'America/Argentina/Buenos_Aires', 'mode' => 'manual',
        'confirmed' => true, 'initial' => false, 'stored_invalid' => false,
    ]);
    $html = ob_get_clean();
    return is_string($html) ? $html : '';
}

$previousRequestUri = $_SERVER['REQUEST_URI'] ?? null;
$previousScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
try {
    $local = notificationLocationHeader('/notificaciones.php', '/notificaciones.php');
    notificationLocationAssert(str_contains($local,
        'ubicacion.php?return=%2Fnotificaciones.php'), 'El retorno local no está en el enlace de ubicación.');

    $production = notificationLocationHeader('/astro/notificaciones.php', '/astro/notificaciones.php');
    notificationLocationAssert(str_contains($production,
        'ubicacion.php?return=%2Fastro%2Fnotificaciones.php'), 'El retorno productivo perdió /astro/.');

    notificationLocationAssert(astronomyLocationReturnPath('/notificaciones.php', '/ubicacion.php')
        === '/notificaciones.php', 'El selector rechazó el retorno local válido.');
    notificationLocationAssert(astronomyLocationReturnPath('/astro/notificaciones.php', '/astro/ubicacion.php')
        === '/astro/notificaciones.php', 'El selector rechazó el retorno productivo válido.');
    foreach (['https://evil.test/', '//evil.test/', 'javascript:alert(1)', 'data:text/plain,x', '/astro/../admin/'] as $unsafe) {
        notificationLocationAssert(astronomyLocationReturnPath($unsafe, '/astro/ubicacion.php') === null,
            'Se aceptó un retorno inseguro: ' . $unsafe);
    }
    notificationLocationAssert(astronomyLocationReturnLabel('/astro/notificaciones.php')
        === 'Volver a Configurar notificaciones', 'Falta la etiqueta amigable del retorno.');

    $settingsScript = file_get_contents(__DIR__ . '/../assets/js/notification-settings.js');
    $settingsPage = file_get_contents(__DIR__ . '/../notificaciones.php');
    $pushScript = file_get_contents(__DIR__ . '/../assets/js/push-notifications.js');
    $subscribeEndpoint = file_get_contents(__DIR__ . '/../web-push/subscribe.php');
    notificationLocationAssert(is_string($settingsScript)
        && str_contains($settingsScript, "!useGeneralLocation && device?.location_name")
        && str_contains($settingsScript, "root.dataset.defaultLatitude")
        && str_contains($settingsScript, "if (!device && !locationReady)"),
        'La ubicación propia no conserva prioridad sobre el valor general inicial.');
    notificationLocationAssert(is_string($settingsPage)
        && str_contains($settingsPage, '?notification_location_changed=1')
        && str_contains($settingsPage, 'type="hidden" name="latitude"')
        && !str_contains($settingsPage, 'data-use-current-location'),
        'La interfaz pública no usa exclusivamente el selector común de ubicación.');
    $typesPosition = strpos($settingsPage, 'id="notification-types-heading"');
    $quietPosition = strpos($settingsPage, 'id="notification-quiet-heading"');
    $savePosition = strpos($settingsPage, 'type="submit">Guardar preferencias');
    $devicePosition = strpos($settingsPage, '<summary>Opciones del dispositivo</summary>');
    notificationLocationAssert($typesPosition !== false && $quietPosition !== false
        && $savePosition !== false && $devicePosition !== false
        && $typesPosition < $quietPosition && $quietPosition < $savePosition && $savePosition < $devicePosition,
        'El formulario público no conserva el orden compacto solicitado.');
    notificationLocationAssert(str_contains($settingsPage, 'data-quiet-times hidden')
        && str_contains($settingsScript, 'updateQuietHours')
        && str_contains($settingsScript, 'typeDescription(type)'),
        'No molestar no inicia colapsado o las descripciones no evitan repeticiones.');
    notificationLocationAssert(is_string($pushScript)
        && str_contains($pushScript, "root.dataset.locationReady === 'true'")
        && str_contains($pushScript, "if (!locationReady)"),
        'El alta técnica no está bloqueada en el cliente cuando falta ubicación.');
    notificationLocationAssert(is_string($subscribeEndpoint)
        && str_contains($subscribeEndpoint, "location['confirmed'] ?? false")
        && str_contains($subscribeEndpoint, 'Elegí una ubicación válida antes de activar las notificaciones.'),
        'El endpoint no rechaza una suscripción nueva sin ubicación confirmada.');
} finally {
    if ($previousRequestUri === null) unset($_SERVER['REQUEST_URI']); else $_SERVER['REQUEST_URI'] = $previousRequestUri;
    if ($previousScriptName === null) unset($_SERVER['SCRIPT_NAME']); else $_SERVER['SCRIPT_NAME'] = $previousScriptName;
}

echo "OK notification location return\n";
