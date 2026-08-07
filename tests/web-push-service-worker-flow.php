<?php

declare(strict_types=1);

function serviceWorkerFlowAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pushScript = file_get_contents(__DIR__ . '/../assets/js/push-notifications.js');
$settingsScript = file_get_contents(__DIR__ . '/../assets/js/notification-settings.js');
$page = file_get_contents(__DIR__ . '/../notificaciones.php');
$worker = file_get_contents(__DIR__ . '/../service-worker.js');

serviceWorkerFlowAssert(is_string($pushScript) && is_string($settingsScript) && is_string($page),
    'No se pudieron leer los archivos del flujo Web Push.');
serviceWorkerFlowAssert(str_contains($pushScript, 'ensureActiveServiceWorker')
    && str_contains($pushScript, "navigator.serviceWorker.register(workerUrl, {scope: workerScope})")
    && str_contains($pushScript, "addEventListener('statechange'")
    && str_contains($pushScript, "addEventListener('controllerchange'")
    && str_contains($pushScript, "registration.active.state !== 'activated'")
    && str_contains($pushScript, 'workerActivationTimeoutMs')
    && str_contains($pushScript, 'controllerTimeoutMs'),
    'La activación no espera worker activo y control con timeout.');
serviceWorkerFlowAssert(strpos($pushScript, 'ensureActiveServiceWorker();')
        < strpos($pushScript, 'registration.pushManager.subscribe({'),
    'La suscripción ocurre antes de asegurar el Service Worker.');
serviceWorkerFlowAssert(str_contains($pushScript, 'activationInProgress')
    && str_contains($pushScript, 'finally')
    && str_contains($pushScript, 'reloadPending'),
    'No se protege el doble intento o la restauración del botón.');
serviceWorkerFlowAssert(str_contains($pushScript, 'sessionStorage.setItem(reloadMarker')
    && str_contains($pushScript, 'sessionStorage.removeItem(reloadMarker')
    && substr_count($pushScript, 'reloadPanel();') >= 2,
    'La recarga controlada no tiene protección contra bucles.');
serviceWorkerFlowAssert(!str_contains($pushScript, 'navigator.serviceWorker.ready')
    && !str_contains($pushScript, 'Preparando las notificaciones'),
    'El flujo conserva una espera indefinida o el estado anterior.');
serviceWorkerFlowAssert(str_contains($pushScript,
    'Permiso concedido. Falta activar el servicio de notificaciones.')
    && str_contains($pushScript,
        'No se pudo activar el servicio de notificaciones. Recargá la página e intentá nuevamente.'),
    'Faltan los estados recuperables solicitados.');
serviceWorkerFlowAssert(str_contains($settingsScript, 'AstronomyPushServiceWorker')
    && str_contains($settingsScript, 'ensureActiveServiceWorker()')
    && !str_contains($settingsScript, 'navigator.serviceWorker.register('),
    'La configuración no reutiliza el ciclo de activación común.');
serviceWorkerFlowAssert(str_contains($page, "versionedAssetUrl('assets/js/push-notifications.js')")
    && str_contains($page, "versionedAssetUrl('assets/js/notification-settings.js')"),
    'Los scripts públicos no usan versionado de assets.');
serviceWorkerFlowAssert(is_string($worker) && !str_contains($worker, 'skipWaiting')
    && !str_contains($worker, 'clients.claim'),
    'El Service Worker fue alterado para forzar activación o control.');

echo "OK web push service worker flow\n";
