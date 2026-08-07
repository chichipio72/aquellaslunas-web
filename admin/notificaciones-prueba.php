<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/web-push.php';
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

try {
    $connection = getWebDatabaseConnection();
    $rows = astronomyWebPushAdminRows($connection);
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
            $rows = astronomyWebPushAdminRows($connection);
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Web Push admin send failed [type=' . get_debug_type($exception) . '].');
            $errors[] = 'No se pudo completar el envío Web Push.';
        }
    }
}

$activeRows = array_values(array_filter($rows, static fn(array $row): bool => (int) $row['active'] === 1));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Notificaciones de prueba · Aquellas Lunas</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= pushAdminHtml('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
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
            .push-test-admin__send-row { grid-template-columns: minmax(0, 1fr); }
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
<?php renderStoreAdminNavigation('notifications', 'Notificaciones de prueba'); ?>
<main class="store-admin-main push-test-admin">
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
            <label class="push-test-admin__field">Destino<select name="destination" required><option value="all"<?= $selectedDestination === 'all' ? ' selected' : '' ?>>Todas las suscripciones activas</option><?php foreach ($activeRows as $row): $id = (int) $row['id']; ?><option value="<?= $id ?>"<?= $selectedDestination === (string) $id ? ' selected' : '' ?>>Suscripción <?= $id ?> · <?= pushAdminHtml(pushAdminUserAgent($row['user_agent'])) ?></option><?php endforeach; ?></select></label>
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
        <div class="push-test-admin__heading"><p class="eyebrow">WEB_DB</p><h2>Suscripciones guardadas</h2></div>
        <?php if ($rows === []): ?><p class="store-admin-empty">No hay suscripciones guardadas.</p><?php else: ?>
        <div class="push-test-admin__table-wrap"><table class="push-admin__table push-test-admin__table"><thead><tr><th>ID</th><th>Estado</th><th>Creada</th><th>Actualizada</th><th>Último éxito</th><th>Último error</th><th>Dispositivo</th></tr></thead><tbody>
        <?php foreach ($rows as $row): $fullAgent = pushAdminFullUserAgent($row['user_agent']); ?><tr><td><?= (int) $row['id'] ?></td><td><span class="push-admin__state <?= (int) $row['active'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int) $row['active'] === 1 ? 'Activa' : 'Inactiva' ?></span></td><td class="push-test-admin__date"><?= pushAdminHtml($row['created_at']) ?></td><td class="push-test-admin__date"><?= pushAdminHtml($row['updated_at']) ?></td><td class="push-test-admin__date<?= $row['last_success_at'] ? '' : ' push-test-admin__empty-value' ?>"><?= pushAdminHtml($row['last_success_at'] ?: '—') ?></td><td class="push-test-admin__date"><?php if ($row['last_error_at']): ?><span><?= pushAdminHtml($row['last_error_at']) ?></span><?php if ($row['last_error_message']): ?><small><?= pushAdminHtml($row['last_error_message']) ?></small><?php endif; ?><?php else: ?><span class="push-test-admin__empty-value">—</span><?php endif; ?></td><td class="push-test-admin__device" title="<?= pushAdminHtml($fullAgent) ?>"><strong><?= pushAdminHtml(pushAdminDeviceSummary($row['user_agent'])) ?></strong><small><?= pushAdminHtml($fullAgent) ?></small></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
