<?php

declare(strict_types=1);

if (!defined('AQUELLAS_LUNAS_NOTIFICATIONS_ADMIN_VIEW')) {
    http_response_code(404);
    exit;
}

$orchestratorState = is_array($systemState['orchestrator'] ?? null) ? $systemState['orchestrator'] : [];
$lockBusy = is_array($systemState) ? scheduledTaskLockIsBusy() : null;
$pendingMigrations = is_array($systemState)
    ? count($systemState['automatic_pending'] ?? []) + count($systemState['manual_pending'] ?? []) : 0;
$selectedSubscription = null;
foreach ($subscriptions as $subscription) {
    if ((int) $subscription['subscription_id'] === (int) $selectedId) { $selectedSubscription = $subscription; break; }
}
$lastSuccess = is_array($device) ? (string) ($device['last_success_at'] ?? '') : '';
$lastError = is_array($device) ? (string) ($device['last_error_at'] ?? '') : '';
// Estos dos campos históricos se escriben con CURRENT_TIMESTAMP de MySQL y ya están en hora local del hosting.
$lastDelivery = $lastError !== '' && ($lastSuccess === '' || $lastError > $lastSuccess)
    ? 'Fallida · ' . astronomyPushAdminShortDate($lastError)
    : ($lastSuccess !== '' ? 'Exitosa · ' . astronomyPushAdminShortDate($lastSuccess) : 'Sin envíos');
$availableTypeCount = count(array_filter($notificationTypes,
    static fn(array $type): bool => (int) $type['available'] === 1 && (int) $type['admin_only'] !== 1));
$typeLabels = [];
foreach ($notificationTypes as $notificationType) {
    $typeLabels[(string) $notificationType['notification_type']] = (string) $notificationType['display_name'];
}
?>

<section class="card astronomy-notifications-admin__panel astronomy-notifications-admin__automation" data-scheduler-block>
    <div class="astronomy-notifications-admin__panel-heading admin-live-status__heading">
        <div><p class="eyebrow">Automatización</p><h2>Estado del scheduler</h2></div>
        <div class="admin-live-status__controls"><button type="button" class="button compact-secondary-button" data-refresh-button>Actualizar</button><span data-refresh-feedback aria-live="polite">Datos cargados con la página</span></div>
    </div>
    <?php if (!is_array($systemState)): ?><p class="store-admin-empty">El estado de la automatización no está disponible.</p><?php else: ?>
    <dl class="astronomy-notifications-admin__automation-metrics">
        <div><dt>Última ejecución</dt><dd data-scheduler-last-started-at><?= astronomyPushAdminHtml(astronomyPushAdminShortDate($orchestratorState['last_started_at'] ?? null, $deviceTimezone)) ?></dd></div>
        <div><dt>Estado</dt><dd class="status-<?= astronomyPushAdminHtml($orchestratorState['last_status'] ?? '') ?>" data-scheduler-status><?= ($orchestratorState['last_status'] ?? null) === 'failed' ? 'Error' : astronomyPushAdminHtml(astronomyPushAdminStatusLabel($orchestratorState['last_status'] ?? null)) ?></dd></div>
        <div><dt>Último éxito</dt><dd data-scheduler-last-success-at><?= astronomyPushAdminHtml(astronomyPushAdminShortDate($orchestratorState['last_success_at'] ?? null, $deviceTimezone)) ?></dd></div>
        <div><dt>Lock</dt><dd><?= $lockBusy === null ? 'No disponible' : ($lockBusy ? 'En ejecución' : 'Libre') ?></dd></div>
        <?php if ($pendingMigrations > 0): ?><div><dt>Migraciones pendientes</dt><dd><?= $pendingMigrations ?></dd></div><?php endif; ?>
        <?php if (!empty($orchestratorState['last_error'])): ?><div class="astronomy-notifications-admin__automation-error"><dt>Último error</dt><dd data-scheduler-last-error><?= astronomyPushAdminHtml(astronomyWebPushSanitizeError((string) $orchestratorState['last_error'])) ?></dd></div><?php endif; ?>
    </dl>
    <?php endif; ?>
</section>

<div class="astronomy-notifications-admin__test-tools" aria-label="Herramientas de prueba">
    <details class="card astronomy-notifications-admin__tool">
        <summary><span>Envío inmediato</span><span class="astronomy-notifications-admin__summary-action">Abrir</span></summary>
        <div class="astronomy-notifications-admin__tool-body">
            <p class="push-admin__note">Envía ahora una notificación al dispositivo elegido, sin esperar al scheduler.</p>
            <form method="post" class="astronomy-notifications-admin__configuration-form" data-notification-action>
                <input type="hidden" name="csrf_token" value="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="action" value="send_manual_test"><input type="hidden" name="subscription_id" value="<?= (int) ($selectedId ?? 0) ?>">
                <div class="astronomy-notifications-admin__fields">
                    <label class="astronomy-notifications-admin__field">Destino<select name="destination" required><option value="all">Todas las suscripciones activas</option><?php foreach ($subscriptions as $subscription): $id = (int) $subscription['subscription_id']; $active = (int) $subscription['active'] === 1; ?><option value="<?= $id ?>"<?= $selectedId === $id && $active ? ' selected' : '' ?><?= $active ? '' : ' disabled' ?>><?= astronomyPushAdminHtml($subscription['device_name'] ?: 'Suscripción ' . $id) ?> — <?= astronomyPushAdminHtml($subscription['support_id'] ?: 'sin ID de soporte') ?> — <?= astronomyPushAdminHtml(astronomyPushAdminAgent($subscription['user_agent'])) ?> — ID interno <?= $id ?> · <?= $active ? 'activa' : 'inactiva' ?></option><?php endforeach; ?></select></label>
                    <label class="astronomy-notifications-admin__field">Título<input type="text" name="title" maxlength="120" required value="Aquellas Lunas"></label>
                    <label class="astronomy-notifications-admin__field astronomy-notifications-admin__field--full">Mensaje<textarea name="body" maxlength="500" rows="3" required>Esta es una notificación de prueba.</textarea></label>
                    <label class="astronomy-notifications-admin__field astronomy-notifications-admin__field--full">URL a abrir<input type="text" name="target_url" maxlength="2048" required value="./"></label>
                </div>
                <div class="astronomy-notifications-admin__actions"><button type="submit" class="button button-primary">Enviar ahora</button></div>
            </form>
        </div>
    </details>

    <details class="card astronomy-notifications-admin__tool">
        <summary><span>Prueba programada</span><span class="astronomy-notifications-admin__summary-action">Abrir</span></summary>
        <div class="astronomy-notifications-admin__tool-body">
            <?php if (!is_array($testNotificationType) || (int) $testNotificationType['available'] !== 1 || (int) $testNotificationType['admin_only'] !== 1): ?>
                <p class="store-admin-empty">Las pruebas programadas no están disponibles globalmente.</p>
            <?php else: ?>
            <form method="post" class="astronomy-notifications-admin__test-form" data-notification-action>
                <input type="hidden" name="csrf_token" value="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="action" value="schedule_test">
                <label class="astronomy-notifications-admin__field">Dispositivo<select name="subscription_id" required><?php foreach ($subscriptions as $subscription): $id = (int) $subscription['subscription_id']; ?><option value="<?= $id ?>"<?= $selectedId === $id ? ' selected' : '' ?>><?= astronomyPushAdminHtml($subscription['device_name'] ?: 'Sin configurar') ?> — <?= astronomyPushAdminHtml($subscription['support_id'] ?: 'sin ID de soporte') ?> — <?= astronomyPushAdminHtml(astronomyPushAdminAgent($subscription['user_agent'])) ?> — ID interno <?= $id ?> · <?= (int) $subscription['active'] === 1 ? 'activa' : 'inactiva' ?></option><?php endforeach; ?></select></label>
                <fieldset class="astronomy-notifications-admin__choices"><legend>Enviar dentro de</legend><?php foreach ([1, 3, 5] as $delay): ?><label class="astronomy-notifications-admin__choice"><input type="radio" name="delay_minutes" value="<?= $delay ?>"<?= $delay === 1 ? ' checked' : '' ?>><span><?= $delay ?> <?= $delay === 1 ? 'minuto' : 'minutos' ?></span></label><?php endforeach; ?></fieldset>
                <label class="astronomy-notifications-admin__switch"><span>Respetar No molestar</span><input type="checkbox" name="respect_quiet_hours" value="1" checked><span class="astronomy-notifications-admin__switch-control" aria-hidden="true"></span></label>
                <button type="submit" class="button button-primary">Programar prueba</button>
            </form>
            <?php endif; ?>
            <h3>Pruebas del dispositivo seleccionado</h3>
            <?php if ($scheduledTests === []): ?><p class="store-admin-empty">Todavía no hay pruebas programadas para este dispositivo.</p><?php endif; ?>
            <div class="astronomy-notifications-admin__responsive-table"><table class="push-admin__table"><thead><tr><th>Programada</th><th>No molestar</th><th>Estado</th><th>Procesada</th><th>Resultado</th><th>Acción</th></tr></thead><tbody data-tests-body>
            <?php foreach ($scheduledTests as $test): ?><tr><td data-label="Programada"><?= astronomyPushAdminHtml(astronomyPushAdminDate($test['scheduled_at_utc'], $deviceTimezone, 'Y-m-d H:i:s')) ?></td><td data-label="No molestar"><?= (int) $test['respect_quiet_hours'] === 1 ? 'Sí' : 'No' ?></td><td data-label="Estado"><?= astronomyPushAdminHtml(astronomyPushAdminStatusLabel((string) $test['status'])) ?></td><td data-label="Procesada"><?= astronomyPushAdminHtml(astronomyPushAdminDate($test['processed_at'], $deviceTimezone, 'Y-m-d H:i:s')) ?></td><td data-label="Resultado"><?= astronomyPushAdminHtml($test['error_message'] ? astronomyWebPushSanitizeError((string) $test['error_message']) : ($test['notification_log_id'] ? 'Log #' . (int) $test['notification_log_id'] : '—')) ?></td><td data-label="Acción"><?php if ($test['status'] === 'pending'): ?><form method="post" data-notification-action><input type="hidden" name="csrf_token" value="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>"><input type="hidden" name="subscription_id" value="<?= (int) $device['subscription_id'] ?>"><input type="hidden" name="action" value="cancel_test"><input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>"><button type="submit" class="button compact-secondary-button">Cancelar</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </details>
</div>

<?php if (is_array($device)): ?>
<section class="card astronomy-notifications-admin__panel astronomy-notifications-admin__configuration">
    <div class="astronomy-notifications-admin__configuration-head"><div><p class="eyebrow">Dispositivo</p><h2>Configuración y avisos</h2></div><a class="astronomy-notifications-admin__catalog-link" href="tipos-notificaciones.php">Administrar tipos de notificación <small><?= $availableTypeCount ?> disponibles</small></a></div>
    <?php if ($subscriptions === []): ?><p class="store-admin-empty">No hay suscripciones Web Push guardadas.</p><?php else: ?>
    <form method="get" class="astronomy-notifications-admin__selector-form"><label class="astronomy-notifications-admin__field">Dispositivo<select name="subscription_id" onchange="this.form.submit()"><?php foreach ($subscriptions as $subscription): $id = (int) $subscription['subscription_id']; ?><option value="<?= $id ?>"<?= $selectedId === $id ? ' selected' : '' ?>><?= astronomyPushAdminHtml($subscription['device_name'] ?: 'Sin configurar') ?> — <?= astronomyPushAdminHtml($subscription['support_id'] ?: 'sin ID de soporte') ?> — <?= astronomyPushAdminHtml(astronomyPushAdminAgent($subscription['user_agent'])) ?> — ID interno <?= $id ?> · <?= (int) $subscription['active'] === 1 ? 'activa' : 'inactiva' ?></option><?php endforeach; ?></select></label><noscript><button class="button compact-secondary-button" type="submit">Cargar</button></noscript></form>
    <?php endif; ?>
    <?php if (!$configured): ?><p class="astronomy-notifications-admin__unconfigured">Completá los detalles del dispositivo antes de calcular avisos o programar pruebas.</p><?php endif; ?>
    <div class="astronomy-notifications-admin__device-summary">
        <div><strong><?= astronomyPushAdminHtml($device['device_name'] ?: 'Dispositivo sin nombre') ?></strong><span><?= (int) $device['active'] === 1 ? 'Activo' : 'Inactivo' ?> · <?= astronomyPushAdminHtml($device['location_name'] ?: 'Sin ubicación') ?></span></div>
        <div><span>Notificaciones</span><strong><?= (int) ($device['notifications_enabled'] ?? 0) === 1 ? 'Habilitadas' : 'Deshabilitadas' ?></strong></div>
        <div><span>Último envío</span><strong><?= astronomyPushAdminHtml($lastDelivery) ?></strong></div>
    </div>

    <form method="post" class="astronomy-notifications-admin__configuration-form">
        <input type="hidden" name="csrf_token" value="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>"><input type="hidden" name="subscription_id" value="<?= (int) $device['subscription_id'] ?>"><input type="hidden" name="action" value="save_device">
        <section class="astronomy-notifications-admin__preferences-section"><h3>Avisos activos</h3><div class="astronomy-notifications-admin__preferences">
        <?php foreach ($devicePreferences as $preference): $mode = (string) ($preference['default_schedule_mode'] ?? ''); $lead = $preference['default_lead_minutes'] ?? null; $time = isset($preference['default_delivery_time']) ? substr((string) $preference['default_delivery_time'], 0, 5) : ''; $offset = (int) ($preference['default_delivery_day_offset'] ?? 0); $brief = $mode === 'before_event' && $lead !== null ? (int) $lead . ' minutos antes' : ($time !== '' ? $time . ($offset < 0 ? ' del día anterior' : ($offset > 0 ? ' del día siguiente' : ' del día del evento')) : 'Según configuración'); ?>
            <div class="astronomy-notifications-admin__preference"><div><strong><?= astronomyPushAdminHtml($preference['display_name']) ?></strong><p><?= astronomyPushAdminHtml($brief) ?></p></div><label class="astronomy-notifications-admin__switch astronomy-notifications-admin__switch--compact"><span class="visually-hidden">Activar <?= astronomyPushAdminHtml($preference['display_name']) ?></span><input type="checkbox" name="notification_preferences[<?= astronomyPushAdminHtml($preference['notification_type']) ?>]" value="1"<?= (int) ($preference['enabled'] ?? 0) === 1 ? ' checked' : '' ?><?= (int) $preference['available'] !== 1 ? ' disabled' : '' ?>><span class="astronomy-notifications-admin__switch-control" aria-hidden="true"></span></label></div>
        <?php endforeach; ?></div></section>

        <section class="astronomy-notifications-admin__quiet-compact"><div><h3>No molestar</h3><p data-quiet-summary data-active-summary="<?= astronomyPushAdminHtml($form['quiet_start_local'] . ' → ' . $form['quiet_end_local']) ?>"><?= $form['quiet_hours_enabled'] ? astronomyPushAdminHtml($form['quiet_start_local'] . ' → ' . $form['quiet_end_local']) : 'Desactivado' ?></p></div><label class="astronomy-notifications-admin__switch astronomy-notifications-admin__switch--compact"><span class="visually-hidden">Activar No molestar</span><input type="checkbox" name="quiet_hours_enabled" value="1" data-quiet-toggle<?= $form['quiet_hours_enabled'] ? ' checked' : '' ?>><span class="astronomy-notifications-admin__switch-control" aria-hidden="true"></span></label><div class="astronomy-notifications-admin__quiet-times" data-quiet-times<?= $form['quiet_hours_enabled'] ? '' : ' hidden' ?>><label class="astronomy-notifications-admin__field">Desde<input type="time" name="quiet_start_local" value="<?= astronomyPushAdminHtml($form['quiet_start_local']) ?>"></label><label class="astronomy-notifications-admin__field">Hasta<input type="time" name="quiet_end_local" value="<?= astronomyPushAdminHtml($form['quiet_end_local']) ?>"></label></div></section>

        <details class="astronomy-notifications-admin__device-details"><summary>Ver detalles del dispositivo</summary><div class="astronomy-notifications-admin__details-body">
            <dl class="astronomy-notifications-admin__technical-state"><div><dt>Subscription ID</dt><dd><?= (int) $device['subscription_id'] ?></dd></div><div><dt>Estado técnico</dt><dd><?= (int) $device['active'] === 1 ? 'Activa' : 'Inactiva' ?></dd></div><div><dt>Fecha de alta</dt><dd><?= astronomyPushAdminHtml($selectedSubscription['created_at'] ?? '—') ?></dd></div><div><dt>Última actualización</dt><dd><?= astronomyPushAdminHtml($selectedSubscription['updated_at'] ?? '—') ?></dd></div><?php if ($device['last_error_at']): ?><div><dt>Último error</dt><dd><?= astronomyPushAdminHtml($device['last_error_at']) ?> · <?= astronomyPushAdminHtml(astronomyWebPushSanitizeError((string) $device['last_error_message'])) ?></dd></div><?php endif; ?></dl>
            <div class="astronomy-notifications-admin__fields astronomy-notifications-admin__fields--location"><label class="astronomy-notifications-admin__field">Nombre del dispositivo<input type="text" name="device_name" maxlength="100" required value="<?= astronomyPushAdminHtml($form['device_name']) ?>"></label><label class="astronomy-notifications-admin__field">Ubicación<input type="text" name="location_name" maxlength="150" required value="<?= astronomyPushAdminHtml($form['location_name']) ?>"></label><label class="astronomy-notifications-admin__field">Latitud<input type="number" name="latitude" min="-90" max="90" step="0.000001" required value="<?= astronomyPushAdminHtml($form['latitude']) ?>"></label><label class="astronomy-notifications-admin__field">Longitud<input type="number" name="longitude" min="-180" max="180" step="0.000001" required value="<?= astronomyPushAdminHtml($form['longitude']) ?>"></label><label class="astronomy-notifications-admin__field">Zona horaria<input type="text" name="timezone" maxlength="64" required value="<?= astronomyPushAdminHtml($form['timezone']) ?>"></label></div>
            <label class="astronomy-notifications-admin__switch"><span>Habilitar notificaciones del dispositivo</span><input type="checkbox" name="notifications_enabled" value="1"<?= $form['notifications_enabled'] ? ' checked' : '' ?>><span class="astronomy-notifications-admin__switch-control" aria-hidden="true"></span></label>
            <details class="astronomy-notifications-admin__parameters"><summary>Parámetros técnicos de avisos</summary><dl><?php foreach ($devicePreferences as $preference): ?><div><dt><?= astronomyPushAdminHtml($preference['display_name']) ?></dt><dd><code><?= astronomyPushAdminHtml(json_encode(astronomyPushEventParameters($preference), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></dd></div><?php endforeach; ?></dl></details>
        </div></details>
        <div class="astronomy-notifications-admin__actions"><button type="submit" class="button button-primary">Guardar configuración</button></div>
    </form>
</section>

<section class="card astronomy-notifications-admin__panel astronomy-notifications-admin__coming"><div class="astronomy-notifications-admin__panel-heading"><p class="eyebrow">Agenda</p><h2>Próximas notificaciones</h2></div>
    <?php if (!$configured): ?><p class="store-admin-empty">Guardá una ubicación para calcular próximos avisos.</p><?php elseif ($upcoming === []): ?><p class="store-admin-empty">No hay tipos disponibles para calcular.</p><?php else: ?><div class="astronomy-notifications-admin__upcoming-list"><?php foreach ($upcoming as $next): $event = $next['event'] ?? null; $notice = $next['effective_time_utc'] ?? $next['notification_time_utc'] ?? null; ?><article class="astronomy-notifications-admin__upcoming-item"><time><?= $notice instanceof DateTimeImmutable ? astronomyPushAdminHtml($notice->setTimezone(new DateTimeZone((string) $device['timezone']))->format('d M · H:i')) : '—' ?></time><div><h3><?= astronomyPushAdminHtml($next['display_name']) ?></h3><p><?php if (!empty($next['error'])): ?>Proveedor con error: <?= astronomyPushAdminHtml($next['error']) ?><?php elseif (!is_array($event)): ?>Sin eventos próximos<?php else: ?>Evento: <?= astronomyPushAdminHtml($event['event_time_local']->format('d M · H:i')) ?><?php if ($next['quiet']): ?> · <?= (string) $next['quiet_policy'] === 'postpone' ? 'Aviso postergado por No molestar' : 'Se omitirá por No molestar' ?><?php endif; ?><?php endif; ?></p></div></article><?php endforeach; ?></div><?php endif; ?>
</section>

<section class="card astronomy-notifications-admin__panel astronomy-notifications-admin__history"><div class="astronomy-notifications-admin__panel-heading"><p class="eyebrow">Actividad reciente</p><h2>Últimas notificaciones</h2></div><?php if ($logs === []): ?><p class="store-admin-empty">Todavía no hay registros para este dispositivo.</p><?php endif; ?><div class="astronomy-notifications-admin__responsive-table"><table class="push-admin__table"><thead><tr><th>Tipo</th><th>Momento</th><th>Estado</th><th>Decisión</th><th>Resultado</th></tr></thead><tbody data-logs-body><?php foreach ($logs as $row): ?><tr><td data-label="Tipo"><?= astronomyPushAdminHtml($typeLabels[(string) $row['notification_type']] ?? $row['notification_type']) ?></td><td data-label="Momento"><?= astronomyPushAdminHtml(astronomyPushAdminShortDate($row['attempted_at'], $deviceTimezone)) ?></td><td data-label="Estado"><?= astronomyPushAdminHtml(astronomyPushAdminStatusLabel((string) $row['status'])) ?></td><td data-label="Decisión"><?= astronomyPushAdminHtml($row['decision_reason'] ?: '—') ?></td><td data-label="Resultado"><?= astronomyPushAdminHtml($row['sent_at'] ? 'Entregada · ' . astronomyPushAdminShortDate($row['sent_at'], $deviceTimezone) : ($row['error_message'] ? astronomyWebPushSanitizeError((string) $row['error_message']) : '—')) ?><details class="astronomy-notifications-admin__row-details"><summary>Detalles</summary><dl><div><dt>Evento</dt><dd><?= astronomyPushAdminHtml($row['event_key']) ?></dd></div><div><dt>Evento UTC</dt><dd><?= astronomyPushAdminHtml($row['event_time_utc']) ?></dd></div><div><dt>Aviso UTC</dt><dd><?= astronomyPushAdminHtml($row['notification_time_utc']) ?></dd></div><div><dt>Título</dt><dd><?= astronomyPushAdminHtml($row['title_sent'] ?: '—') ?></dd></div><div><dt>Mensaje</dt><dd><?= astronomyPushAdminHtml($row['body_sent'] ?: '—') ?></dd></div><div><dt>URL</dt><dd><?= astronomyPushAdminHtml($row['target_url_sent'] ?: '—') ?></dd></div></dl></details></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
