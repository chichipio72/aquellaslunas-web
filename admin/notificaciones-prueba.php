<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/web-push-astronomy.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

function pushAdminHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pushAdminUserAgent(mixed $value): string
{
    $value = preg_replace('/\s+/', ' ', trim((string) $value));
    if (!is_string($value) || $value === '') {
        return 'No informado';
    }
    return strlen($value) > 120 ? substr($value, 0, 117) . '…' : $value;
}

function pushAdminFullUserAgent(mixed $value): string
{
    $value = preg_replace('/\s+/', ' ', trim((string) $value));
    return is_string($value) && $value !== '' ? $value : 'No informado';
}

function pushAdminDeviceSummary(mixed $value): string
{
    $agent = pushAdminFullUserAgent($value);
    if ($agent === 'No informado') return $agent;

    if (stripos($agent, 'Android') !== false) $platform = 'Android';
    elseif (stripos($agent, 'iPhone') !== false || stripos($agent, 'iPad') !== false) $platform = 'iPhone / iPad';
    elseif (stripos($agent, 'Windows') !== false) $platform = 'Windows';
    elseif (stripos($agent, 'Macintosh') !== false || stripos($agent, 'Mac OS') !== false) $platform = 'macOS';
    elseif (stripos($agent, 'Linux') !== false) $platform = 'Linux';
    else $platform = 'Dispositivo';

    if (stripos($agent, 'Edg/') !== false || stripos($agent, 'EdgiOS/') !== false || stripos($agent, 'EdgA/') !== false) $browser = 'Edge';
    elseif (stripos($agent, 'Firefox/') !== false || stripos($agent, 'FxiOS/') !== false) $browser = 'Firefox';
    elseif (stripos($agent, 'CriOS/') !== false || stripos($agent, 'Chrome/') !== false) $browser = 'Chrome';
    elseif (stripos($agent, 'Safari/') !== false) $browser = 'Safari';
    else $browser = 'Navegador no identificado';

    return $platform . ' · ' . $browser;
}

$title = 'Aquellas Lunas';
$body = 'Esta es una notificación de prueba.';
$targetUrl = './';
$selectedDestination = 'all';
$errors = [];
$sendResult = null;
$rows = [];
$connection = null;
$publicConfig = null;
$serverConfigAvailable = false;
$search = trim((string) ($_GET['q'] ?? ''));
$diagnosticDevice = null;
$diagnosticPreferences = [];
$diagnosticLogs = [];

try {
    $connection = getWebDatabaseConnection();
    $rows = astronomyPushAdminSubscriptions($connection);
} catch (Throwable $exception) {
    error_log('Web Push admin list failed [type=' . get_debug_type($exception) . '].');
    $errors[] = 'No se pudieron cargar las suscripciones guardadas.';
}
try {
    $publicConfig = loadWebPushPublicConfig();
    loadWebPushServerConfig();
    if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
        throw new RuntimeException('Web Push vendor missing.');
    }
    if (!extension_loaded('curl') || !function_exists('openssl_pkey_get_private') || !function_exists('iconv')) {
        throw new RuntimeException('Web Push PHP requirements missing.');
    }
    $serverConfigAvailable = true;
} catch (Throwable $exception) {
    $errors[] = 'La configuración VAPID o las dependencias Web Push del hosting todavía no están completas.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $targetUrl = trim((string) ($_POST['target_url'] ?? ''));
    $selectedDestination = trim((string) ($_POST['destination'] ?? 'all'));
    if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión expiró o el token CSRF no es válido. Recargá la página.';
    } elseif (!$connection instanceof PDO) {
        $errors[] = 'No hay conexión disponible para realizar el envío.';
    } else {
        try {
            $subscriptionId = null;
            if ($selectedDestination !== 'all') {
                $validatedId = filter_var($selectedDestination, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($validatedId === false) {
                    throw new InvalidArgumentException('El destino elegido no es válido.');
                }
                $subscriptionId = (int) $validatedId;
            }
            $title = astronomyWebPushNormalizeMessage($title, 120, 'El título');
            $body = astronomyWebPushNormalizeMessage($body, 500, 'El mensaje');
            $targetUrl = astronomyWebPushNormalizeTargetUrl($targetUrl);
            $sendResult = astronomyWebPushSend($connection, loadWebPushServerConfig(), $subscriptionId, $title, $body, $targetUrl);
            $rows = astronomyPushAdminSubscriptions($connection);
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Web Push admin send failed [type=' . get_debug_type($exception) . '].');
            $errors[] = 'No se pudo completar el envío Web Push.';
        }
    }
}

$allRows = $rows;
if ($search !== '') {
    $normalizedSupportId = astronomyPushNormalizeSupportId($search);
    $rows = array_values(array_filter($rows, static function (array $row) use ($search, $normalizedSupportId): bool {
        if ($normalizedSupportId !== null && hash_equals((string) ($row['support_id'] ?? ''), $normalizedSupportId)) return true;
        return stripos((string) ($row['device_name'] ?? ''), $search) !== false;
    }));
}
if ($connection instanceof PDO && count($rows) === 1) {
    $diagnosticId = (int) $rows[0]['subscription_id'];
    try {
        $diagnosticDevice = astronomyPushAdminDevice($connection, $diagnosticId);
        $diagnosticPreferences = astronomyPushDeviceNotificationPreferences($connection, $diagnosticId);
        $diagnosticLogs = astronomyPushRecentLog($connection, $diagnosticId, 50);
    } catch (Throwable $exception) {
        error_log('Web Push support diagnostic failed [type=' . get_debug_type($exception) . '].');
        $errors[] = 'No se pudo completar el diagnóstico del dispositivo encontrado.';
    }
}
$activeRows = array_values(array_filter($allRows, static fn(array $row): bool => (int) $row['active'] === 1));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Suscripciones · Aquellas Lunas</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= pushAdminHtml('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <script src="<?= pushAdminHtml('../' . versionedAssetUrl('assets/js/admin-notification-status.js')) ?>" defer></script>
    <?php if ($publicConfig !== null): ?><script src="<?= pushAdminHtml('../' . versionedAssetUrl('assets/js/push-notifications.js')) ?>" defer></script><?php endif; ?>
    <style>
        .push-test-admin { display: grid; gap: 1.15rem; max-width: 96rem; }
        .push-test-admin > :is(.store-admin-alert, .store-admin-success) { margin: 0; }
        .push-test-admin__top { display: grid; grid-template-columns: minmax(20rem, .62fr) minmax(0, 1fr); gap: 1.15rem; align-items: stretch; }
        .push-test-admin__panel { display: grid; align-content: start; gap: 1rem; min-width: 0; padding: clamp(1rem, 2vw, 1.35rem); }
        .push-test-admin__heading :is(h2, p) { margin: 0; }
        .push-test-admin__heading h2 { margin-top: .2rem; }
        .push-test-admin__status-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; margin: 0; }
        .push-test-admin__status-grid div {
            min-width: 0; padding: .72rem .78rem; border: 1px solid rgba(151, 172, 213, .2);
            border-radius: .62rem; background: rgba(5, 10, 21, .38);
        }
        .push-test-admin__status-grid dt { color: #9facbf; font-size: .72rem; line-height: 1.3; }
        .push-test-admin__status-grid dd { margin: .24rem 0 0; color: #dce3f0; font-size: .88rem; font-weight: 750; line-height: 1.35; }
        .push-test-admin__status.is-active .push-test-admin__status-grid [data-push-subscription] { color: #b9d7ac; }
        .push-test-admin__device-actions { display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; }
        .push-test-admin__device-actions .button { min-height: 2.45rem; padding: .48rem .72rem; font-size: .8rem; }
        .push-test-admin__device-message { min-height: 1.35em; margin: 0; color: #aeb9ce; font-size: .8rem; }
        .push-test-admin__note { margin: -.35rem 0 0; color: #8997af; font-size: .78rem; line-height: 1.45; }
        .push-test-admin__send-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem 1rem; align-items: end; }
        .push-test-admin__field { display: grid; align-content: start; gap: .4rem; min-width: 0; color: #cbd3e2; font-size: .84rem; font-weight: 680; }
        .push-test-admin__field--full { grid-column: 1 / -1; }
        .push-test-admin__field :is(input, select, textarea) {
            width: 100%; min-width: 0; min-height: 2.75rem; margin: 0; padding: .62rem .72rem;
            border: 1px solid rgba(151, 172, 213, .34); border-radius: .62rem;
            background: #09111f; color: #eef2fa; font: inherit;
        }
        .push-test-admin__field textarea { line-height: 1.45; resize: vertical; }
        .push-test-admin__field :is(input, select, textarea):focus-visible { border-color: #e2bd5f; outline: 2px solid #e2bd5f; outline-offset: 2px; }
        .push-test-admin__field small { color: #8997af; font-size: .74rem; font-weight: 450; line-height: 1.4; }
        .push-test-admin__send-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 1rem; align-items: start; grid-column: 1 / -1; }
        .push-test-admin__send-button { min-width: 12rem; min-height: 2.75rem; align-self: start; padding-block: .62rem; white-space: nowrap; }
        .push-test-admin__support-search { display: grid; grid-template-columns: minmax(16rem, 1fr) auto auto; gap: .7rem; align-items: end; }
        .push-test-admin__support-search .button { min-height: 2.75rem; }
        .push-test-admin__diagnostic h3 { margin: .25rem 0 0; }
        .push-test-admin__support-code { color: #e9cd82; font-size: 1rem; font-weight: 750; letter-spacing: .04em; }
        .push-test-admin__subscriptions { width: 100%; }
        .push-test-admin__table-wrap { max-width: 100%; overflow-x: auto; scrollbar-color: #465676 #0b1323; }
        .push-test-admin__table { table-layout: auto; }
        .push-test-admin__table :is(th, td) { white-space: nowrap; }
        .push-test-admin__table th:last-child,
        .push-test-admin__table td:last-child { width: 20rem; white-space: normal; }
        .push-test-admin__date { color: #c4cede; font-variant-numeric: tabular-nums; font-size: .78rem; }
        .push-test-admin__empty-value { color: #738099; }
        .push-test-admin__device { min-width: 0; }
        .push-test-admin__device strong { display: block; color: #e6ebf5; font-size: .82rem; }
        .push-test-admin__device small {
            display: block; max-width: 24rem; margin-top: .25rem; color: #8997af;
            font-size: .7rem; line-height: 1.35; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        @media (max-width: 64rem) {
            .push-test-admin__top { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 42rem) {
            .push-test-admin__status-grid,
            .push-test-admin__send-form,
            .push-test-admin__send-row,
            .push-test-admin__support-search { grid-template-columns: minmax(0, 1fr); }
            .push-test-admin__field--full { grid-column: auto; }
            .push-test-admin__send-row { grid-column: auto; }
            .push-test-admin__send-button { width: 100%; min-width: 0; }
            .push-test-admin__device-actions { display: grid; }
            .push-test-admin__device-actions .button { width: 100%; }
            .push-test-admin__table { min-width: 48rem; }
        }
    </style>
</head>
<body class="store-admin">
<?php renderStoreAdminNavigation('notifications', 'Suscripciones'); ?>
<main class="store-admin-main push-test-admin" data-live-admin-status data-status-kind="subscriptions" data-status-url="api/suscripciones.php<?= $search !== '' ? '?q=' . rawurlencode($search) : '' ?>">
    <?php if ($errors !== []): ?><section class="store-admin-alert" role="alert"><strong>No se pudo completar todo</strong><ul><?php foreach (array_unique($errors) as $error): ?><li><?= pushAdminHtml($error) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
    <?php if ($sendResult !== null): ?>
    <section class="store-admin-success push-admin__result" role="status">
        <strong>Envío finalizado</strong>
        <span><?= (int) $sendResult['sent'] ?> procesadas · <?= (int) $sendResult['success'] ?> exitosas · <?= (int) $sendResult['failed'] ?> fallidas</span>
        <?php if ($sendResult['errors'] !== []): ?><ul><?php foreach ($sendResult['errors'] as $failure): ?><li>Suscripción <?= (int) $failure['id'] ?>: <?= pushAdminHtml($failure['message']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="push-test-admin__top">
    <section class="card push-test-admin__panel push-test-admin__status"<?= $publicConfig !== null ? ' data-push-notifications data-push-public-key="' . pushAdminHtml($publicConfig['public_key']) . '" data-push-worker-url="../service-worker.js" data-push-worker-scope="../" data-push-subscribe-url="../web-push/subscribe.php" data-push-unsubscribe-url="../web-push/unsubscribe.php"' : '' ?>>
        <div class="push-test-admin__heading"><p class="eyebrow">Dispositivo actual</p><h2>Estado de Web Push</h2></div>
        <dl class="push-test-admin__status-grid">
            <div><dt>Service Worker</dt><dd data-push-support-worker><?= $publicConfig !== null ? 'Comprobando…' : 'Sin configurar' ?></dd></div>
            <div><dt>Push API</dt><dd data-push-support-api><?= $publicConfig !== null ? 'Comprobando…' : 'Sin configurar' ?></dd></div>
            <div><dt>Permiso</dt><dd data-push-permission><?= $publicConfig !== null ? 'Comprobando…' : 'Sin configurar' ?></dd></div>
            <div><dt>Suscripción</dt><dd data-push-subscription><?= $publicConfig !== null ? 'Comprobando…' : 'Sin configurar' ?></dd></div>
        </dl>
        <div class="push-test-admin__device-actions">
            <button type="button" class="button button-primary" data-push-action<?= $publicConfig === null ? ' disabled' : '' ?>>Activar notificaciones en este dispositivo</button>
            <button type="button" class="button compact-secondary-button" data-push-deactivate<?= $publicConfig === null ? ' disabled' : '' ?>>Desactivar notificaciones</button>
        </div>
        <p class="push-test-admin__device-message" role="status" aria-live="polite" data-push-status></p>
    </section>

    <section class="card push-test-admin__panel">
        <div class="push-test-admin__heading"><p class="eyebrow">Envío manual</p><h2>Enviar notificación de prueba</h2></div>
        <p class="push-test-admin__note">El envío se cifra en el hosting. La clave VAPID privada no se entrega al navegador.</p>
        <form method="post" class="push-test-admin__send-form">
            <input type="hidden" name="csrf_token" value="<?= pushAdminHtml(storeAdminCsrfToken()) ?>">
            <label class="push-test-admin__field">Destino<select name="destination" required><option value="all"<?= $selectedDestination === 'all' ? ' selected' : '' ?>>Todas las suscripciones activas</option><?php foreach ($activeRows as $row): $id = (int) $row['subscription_id']; ?><option value="<?= $id ?>"<?= $selectedDestination === (string) $id ? ' selected' : '' ?>><?= pushAdminHtml($row['device_name'] ?: 'Suscripción ' . $id) ?> · <?= pushAdminHtml($row['support_id'] ?: 'sin ID de soporte') ?> · ID interno <?= $id ?></option><?php endforeach; ?></select></label>
            <label class="push-test-admin__field">Título<input type="text" name="title" maxlength="120" value="<?= pushAdminHtml($title) ?>" required></label>
            <label class="push-test-admin__field push-test-admin__field--full">Mensaje<textarea name="body" maxlength="500" rows="3" required><?= pushAdminHtml($body) ?></textarea></label>
            <div class="push-test-admin__send-row">
                <label class="push-test-admin__field">URL a abrir<input type="text" name="target_url" maxlength="2048" value="<?= pushAdminHtml($targetUrl) ?>" required><small><code>./</code> abre la portada de Aquellas Lunas, también bajo <code>/astro/</code>.</small></label>
                <button type="submit" class="button button-primary push-test-admin__send-button"<?= !$serverConfigAvailable || $activeRows === [] ? ' disabled' : '' ?>>Enviar notificación</button>
            </div>
        </form>
    </section>
    </div>

    <section class="card push-test-admin__panel push-test-admin__subscriptions">
        <div class="push-test-admin__heading"><div><p class="eyebrow">WEB_DB</p><h2>Suscripciones guardadas</h2></div><div class="admin-live-status__controls"><button type="button" class="button compact-secondary-button" data-refresh-button>Actualizar</button><span data-refresh-feedback aria-live="polite">Datos cargados con la página</span></div></div>
        <form method="get" class="push-test-admin__support-search">
            <label class="push-test-admin__field">Buscar por ID de soporte o nombre<input type="search" name="q" maxlength="100" value="<?= pushAdminHtml($search) ?>" placeholder="AL-7K4P9M2D"></label>
            <button type="submit" class="button compact-secondary-button">Buscar dispositivo</button>
            <?php if ($search !== ''): ?><a class="button compact-secondary-button" href="notificaciones-prueba.php">Limpiar</a><?php endif; ?>
        </form>
        <?php if ($rows === []): ?><p class="store-admin-empty">No hay suscripciones guardadas.</p><?php endif; ?>
        <div class="push-test-admin__table-wrap"><table class="push-admin__table push-test-admin__table"><thead><tr><th>ID interno</th><th>ID de soporte</th><th>Salud</th><th>Dispositivo</th><th>Alta</th><th>Última actividad</th><th>Activa/válida</th><th>Último éxito</th><th>Último error</th><th>Agente</th></tr></thead><tbody data-subscriptions-body>
        <?php foreach ($rows as $row): $fullAgent = pushAdminFullUserAgent($row['user_agent']); $active = (int) $row['active'] === 1; $hasNewError = $row['last_error_at'] && (!$row['last_success_at'] || $row['last_error_at'] > $row['last_success_at']); $health = !$active ? 'Inactiva' : ($hasNewError ? 'Con errores' : ($row['last_success_at'] ? 'Saludable' : 'Sin actividad')); $healthClass = !$active ? 'is-inactive' : ($hasNewError ? 'is-error' : ($row['last_success_at'] ? 'is-active' : 'is-pending')); ?><tr><td><?= (int) $row['subscription_id'] ?></td><td><code><?= pushAdminHtml($row['support_id'] ?: '—') ?></code></td><td><span class="push-admin__state <?= $healthClass ?>"><?= $health ?></span></td><td><?= pushAdminHtml($row['device_name'] ?: 'Sin nombre') ?></td><td class="push-test-admin__date"><?= pushAdminHtml($row['created_at']) ?></td><td class="push-test-admin__date"><?= pushAdminHtml(max(array_filter([(string) $row['updated_at'], (string) $row['last_success_at'], (string) $row['last_error_at']]))) ?></td><td><?= $active ? 'Sí' : 'No' ?></td><td class="push-test-admin__date<?= $row['last_success_at'] ? '' : ' push-test-admin__empty-value' ?>"><?= pushAdminHtml($row['last_success_at'] ?: '—') ?></td><td class="push-test-admin__date"><?php if ($row['last_error_at']): ?><span><?= pushAdminHtml($row['last_error_at']) ?></span><?php if ($row['last_error_message']): ?><small><?= pushAdminHtml($row['last_error_message']) ?></small><?php endif; ?><?php else: ?><span class="push-test-admin__empty-value">—</span><?php endif; ?></td><td class="push-test-admin__device" title="<?= pushAdminHtml($fullAgent) ?>"><strong><?= pushAdminHtml(pushAdminDeviceSummary($row['user_agent'])) ?></strong><small><?= pushAdminHtml($fullAgent) ?></small></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <?php if (is_array($diagnosticDevice) && isset($rows[0])): $diagnosticRow = $rows[0]; $lastAttempt = $diagnosticLogs[0] ?? null; $lastOmission = null; $enabledTypeNames = array_map(static fn(array $preference): string => (string) $preference['display_name'], array_values(array_filter($diagnosticPreferences, static fn(array $preference): bool => (int) ($preference['enabled'] ?? 0) === 1))); foreach ($diagnosticLogs as $entry) { if (str_starts_with((string) $entry['status'], 'skipped')) { $lastOmission = $entry; break; } } ?>
    <section class="card push-test-admin__panel push-test-admin__diagnostic">
        <div class="push-test-admin__heading"><div><p class="eyebrow">Diagnóstico</p><h2><?= pushAdminHtml($diagnosticDevice['device_name'] ?: 'Dispositivo sin nombre') ?></h2></div><code class="push-test-admin__support-code"><?= pushAdminHtml($diagnosticRow['support_id']) ?></code></div>
        <dl class="push-admin__device-state">
            <div><dt>Suscripción</dt><dd><?= (int) $diagnosticDevice['active'] === 1 ? 'Activa' : 'Inactiva' ?> · ID interno <?= (int) $diagnosticDevice['subscription_id'] ?></dd></div>
            <div><dt>Alta / actualización</dt><dd><?= pushAdminHtml($diagnosticRow['created_at']) ?> · <?= pushAdminHtml($diagnosticRow['updated_at']) ?></dd></div>
            <div><dt>Ubicación</dt><dd><?= pushAdminHtml($diagnosticRow['location_name'] ?: 'Sin configurar') ?><?= $diagnosticRow['timezone'] ? ' · ' . pushAdminHtml($diagnosticRow['timezone']) : '' ?></dd></div>
            <div><dt>Notificaciones</dt><dd><?= (int) ($diagnosticRow['notifications_enabled'] ?? 0) === 1 ? 'Habilitadas' : 'Deshabilitadas' ?></dd></div>
            <div><dt>No molestar</dt><dd><?= (int) ($diagnosticRow['quiet_hours_enabled'] ?? 0) === 1 ? pushAdminHtml(substr((string) $diagnosticRow['quiet_start_local'], 0, 5) . ' → ' . substr((string) $diagnosticRow['quiet_end_local'], 0, 5)) : 'Desactivado' ?></dd></div>
            <div><dt>Tipos habilitados</dt><dd><?= pushAdminHtml($enabledTypeNames !== [] ? implode(', ', $enabledTypeNames) : 'Ninguno') ?></dd></div>
            <div><dt>Último éxito</dt><dd><?= pushAdminHtml($diagnosticRow['last_success_at'] ?: '—') ?></dd></div>
            <div><dt>Último intento</dt><dd><?= pushAdminHtml($lastAttempt['attempted_at'] ?? '—') ?><?= $lastAttempt ? ' · ' . pushAdminHtml((string) $lastAttempt['status']) : '' ?></dd></div>
            <?php if ($diagnosticRow['last_error_at']): ?><div><dt>Último error</dt><dd><?= pushAdminHtml($diagnosticRow['last_error_at']) ?> · <?= pushAdminHtml($diagnosticRow['last_error_message'] ?: 'Error de envío') ?></dd></div><?php endif; ?>
            <?php if ($lastOmission): ?><div><dt>Última omisión</dt><dd><?= pushAdminHtml($lastOmission['attempted_at']) ?> · <?= pushAdminHtml($lastOmission['decision_reason'] ?: $lastOmission['status']) ?></dd></div><?php endif; ?>
        </dl>
        <h3>Historial reciente</h3>
        <?php if ($diagnosticLogs === []): ?><p class="store-admin-empty">No hay decisiones o envíos registrados.</p><?php else: ?><div class="push-test-admin__table-wrap"><table class="push-admin__table"><thead><tr><th>Fecha/hora</th><th>Tipo</th><th>Evento</th><th>Estado</th><th>Decisión</th><th>Error</th></tr></thead><tbody><?php foreach ($diagnosticLogs as $entry): ?><tr><td><?= pushAdminHtml($entry['attempted_at']) ?></td><td><?= pushAdminHtml($entry['notification_type']) ?></td><td><?= pushAdminHtml($entry['event_key']) ?></td><td><?= pushAdminHtml($entry['status']) ?></td><td><?= pushAdminHtml($entry['decision_reason'] ?: '—') ?></td><td><?= pushAdminHtml($entry['error_message'] ? astronomyWebPushSanitizeError((string) $entry['error_message']) : '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <a class="button compact-secondary-button" href="notificaciones-astronomicas.php?subscription_id=<?= (int) $diagnosticDevice['subscription_id'] ?>">Abrir configuración completa</a>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
