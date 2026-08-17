<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/web-push-device-config.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/store-admin-auth.php';
require_once __DIR__ . '/includes/home-notification-links.php';

sendDynamicNoCacheHeaders();
astronomyPushDeviceStartSession();
$csrfToken = astronomyPushDeviceCsrfToken();
$location = astronomyLocationContext();
$locationIsValid = ($location['confirmed'] ?? false) === true
    && astronomyLocationName($location['name'] ?? null) !== null
    && astronomyLocationCoordinate($location['latitude'] ?? null, -90, 90) !== null
    && astronomyLocationCoordinate($location['longitude'] ?? null, -180, 180) !== null
    && astronomyLocationTimezone($location['timezone'] ?? null) !== null;
$notificationReturnPath = astronomyLocationReturnPath(
    (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/notificaciones.php'), PHP_URL_PATH) ?: '/notificaciones.php')
);
$notificationLocationChanged = isset($_GET['notification_location_changed'])
    && $_GET['notification_location_changed'] === '1';
$notificationType = isset($_GET['notification_type']) ? trim((string) $_GET['notification_type']) : '';
$notificationCategory = homeNotificationCategory($notificationType);
$notificationType = $notificationCategory !== null ? $notificationType : '';
$notificationLocationUrl = astronomyInternalUrl('ubicacion.php');
if ($notificationReturnPath !== null) {
    $returnQuery = ['notification_location_changed' => '1'];
    if ($notificationType !== '') $returnQuery['notification_type'] = $notificationType;
    $notificationLocationUrl .= '?return=' . rawurlencode($notificationReturnPath . '?' . http_build_query($returnQuery));
}
$publicConfig = null;
$configurationError = '';
try {
    $publicConfig = loadWebPushPublicConfig();
} catch (Throwable $exception) {
    $configurationError = 'Las notificaciones todavía no están disponibles en este momento.';
}
$pageSeo = aquellasLunasSeoPage(
    'Configurar notificaciones | Aquellas Lunas',
    'Configurá en este dispositivo los avisos astronómicos de Aquellas Lunas.',
    '/notificaciones.php',
    'website'
);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php renderSeoHead($pageSeo); renderAnalyticsTracking(); renderFaviconLinks(); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($publicConfig !== null): ?>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/push-notifications.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/notification-settings.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endif; ?>
</head>
<body>
    <?php renderAstronomySiteHeader('notifications', $location); ?>
    <main class="page notification-settings-page">
        <div class="container notification-settings"<?= $publicConfig !== null ?
            ' data-push-notifications data-push-public-key="' . htmlspecialchars($publicConfig['public_key'], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-push-worker-url="service-worker.js" data-push-worker-scope="./"'
            . ' data-push-subscribe-url="web-push/subscribe.php" data-push-unsubscribe-url="web-push/unsubscribe.php"'
            . ' data-device-config-url="web-push/device-config.php" data-device-csrf="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-requires-location="true" data-location-ready="' . ($locationIsValid ? 'true' : 'false') . '"'
            . ' data-location-change-requested="' . ($notificationLocationChanged ? 'true' : 'false') . '"'
            . ' data-default-location-name="' . htmlspecialchars((string) $location['name'], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-default-latitude="' . htmlspecialchars((string) $location['latitude'], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-default-longitude="' . htmlspecialchars((string) $location['longitude'], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-default-timezone="' . htmlspecialchars((string) $location['timezone'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
            <header class="notification-settings__heading">
                <p class="eyebrow">Avisos en este dispositivo</p>
                <h1>Configurar notificaciones</h1>
                <p>Elegí qué avisos querés recibir en este dispositivo.</p>
            </header>

            <?php if ($notificationCategory !== null): ?>
            <section class="card notification-settings__context" data-notification-context data-notification-type="<?= htmlspecialchars($notificationType, ENT_QUOTES, 'UTF-8') ?>" role="status">
                <p class="eyebrow">Configuración por categoría</p>
                <h2><?= htmlspecialchars($notificationCategory['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p>Esta opción activa los avisos para todos los eventos de este tipo, no solamente para el evento que viste en la portada.</p>
            </section>
            <?php endif; ?>

            <?php if ($configurationError !== ''): ?>
                <section class="card notification-settings__notice" role="status"><h2>No disponible</h2><p><?= htmlspecialchars($configurationError, ENT_QUOTES, 'UTF-8') ?></p></section>
            <?php else: ?>
            <section class="card notification-settings__step<?= $locationIsValid ? ' is-complete' : ' is-required' ?>" data-location-step>
                <div class="notification-settings__step-number" aria-hidden="true"><?= $locationIsValid ? '✓' : '1' ?></div>
                <div class="notification-settings__step-copy">
                    <?php if ($locationIsValid): ?>
                        <h2>Ubicación</h2>
                        <p><strong data-selected-location><?= htmlspecialchars((string) $location['name'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <?php else: ?>
                        <p class="eyebrow">Paso 1</p>
                        <h2>Elegir ubicación</h2>
                        <p class="notification-settings__location-required" role="status">Elegí una ubicación para continuar.</p>
                    <?php endif; ?>
                </div>
                <a class="button compact-secondary-button" href="<?= htmlspecialchars($notificationLocationUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $locationIsValid ? 'Cambiar ubicación' : 'Elegir ubicación' ?></a>
            </section>

            <section class="card notification-settings__status-card" data-push-status-card>
                <div class="notification-settings__section-heading"><div><p class="eyebrow" data-push-step-label>Paso 2</p><h2 data-push-heading>Activar notificaciones</h2><p data-push-ready-copy hidden>Este dispositivo está listo para recibir avisos.</p></div><span class="notification-settings__state" data-push-summary-state><?= $locationIsValid ? 'Comprobando…' : 'Esperando ubicación' ?></span></div>
                <dl class="notification-settings__status-grid">
                    <div><dt>Compatibilidad</dt><dd data-push-support-api>Comprobando…</dd></div>
                    <div><dt>Permiso</dt><dd data-push-permission>Comprobando…</dd></div>
                    <div><dt>Suscripción</dt><dd data-push-subscription>Comprobando…</dd></div>
                    <div><dt>Configuración</dt><dd data-device-config-state>Esperando…</dd></div>
                </dl>
                <span class="visually-hidden" data-push-support-worker>Comprobando Service Worker…</span>
                <div class="notification-settings__status-actions">
                    <button type="button" class="button button-primary" data-push-action<?= $locationIsValid ? '' : ' disabled aria-disabled="true"' ?>>Activar notificaciones en este dispositivo</button>
                    <button type="button" class="button compact-secondary-button" data-push-deactivate>Desactivar en este dispositivo</button>
                </div>
                <?php if (!$locationIsValid): ?><p class="notification-settings__message">Los controles de activación estarán disponibles después de elegir una ubicación válida.</p><?php endif; ?>
                <p class="notification-settings__ios-help" data-ios-pwa-help hidden>En iPhone o iPad, agregá Aquellas Lunas a la pantalla de inicio y abrila desde allí para activar las notificaciones.</p>
                <p class="notification-settings__message" role="status" aria-live="polite" data-push-status></p>
            </section>

            <section class="card notification-settings__form-card" data-device-form-card hidden>
                <span class="notification-settings__saved" data-device-last-saved></span>
                <div class="notification-settings__support-id" data-device-support hidden>
                    <div><span>ID de soporte</span><strong data-device-support-id></strong><small>Si tenés problemas con las notificaciones, contactame por Instagram y mencioná este ID para que pueda ayudarte.</small></div>
                    <button type="button" class="button compact-secondary-button" data-copy-support-id>Copiar</button>
                </div>
                <form class="notification-settings__form" data-device-form>
                    <input type="hidden" name="location_name">
                    <input type="hidden" name="latitude">
                    <input type="hidden" name="longitude">
                    <input type="hidden" name="timezone">

                    <section class="notification-settings__form-section" id="tipos-de-aviso" aria-labelledby="notification-types-heading">
                        <h3 id="notification-types-heading">Tipos de aviso</h3>
                        <div class="notification-settings__types" data-notification-types></div>
                    </section>

                    <section class="notification-settings__form-section" aria-labelledby="notification-quiet-heading">
                        <h3 id="notification-quiet-heading">No molestar</h3>
                        <label class="notification-settings__switch notification-settings__quiet-switch"><span>Horario de silencio <small data-quiet-state>Desactivado</small></span><input type="checkbox" name="quiet_hours_enabled"><span aria-hidden="true"></span></label>
                        <div class="notification-settings__quiet-times" data-quiet-times hidden>
                            <label class="control-field"><span>Desde</span><input type="time" name="quiet_start_local" value="23:00"></label>
                            <label class="control-field"><span>Hasta</span><input type="time" name="quiet_end_local" value="08:00"></label>
                        </div>
                    </section>

                    <div class="notification-settings__save-row">
                        <p class="notification-settings__message" role="status" aria-live="polite" data-device-save-status></p>
                        <button class="button button-primary" type="submit">Guardar preferencias</button>
                    </div>

                    <details class="notification-settings__device-options">
                        <summary>Opciones del dispositivo</summary>
                        <div class="notification-settings__fields">
                            <label class="control-field"><span>Nombre del dispositivo</span><input type="text" name="device_name" maxlength="100" required autocomplete="nickname"></label>
                            <label class="notification-settings__switch"><span>Recibir notificaciones astronómicas</span><input type="checkbox" name="notifications_enabled" checked><span aria-hidden="true"></span></label>
                        </div>
                    </details>
                </form>
            </section>
            <?php if (storeAdminHasValidSessionCookie()): ?><p class="notification-settings__admin-link"><a class="button compact-secondary-button" href="admin/notificaciones-prueba.php">Abrir pruebas administrativas</a></p><?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
