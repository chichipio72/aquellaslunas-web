<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/web-push.php';
require_once __DIR__ . '/../includes/asset-url.php';

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
    <link rel="stylesheet" href="<?= pushAdminHtml('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <?php if ($publicConfig !== null): ?><script src="<?= pushAdminHtml('../' . versionedAssetUrl('assets/js/push-notifications.js')) ?>" defer></script><?php endif; ?>
</head>
<body class="store-admin">
<?php renderStoreAdminNavigation('notifications', 'Notificaciones de prueba'); ?>
<main class="store-admin-main push-admin">
    <?php if ($errors !== []): ?><section class="store-admin-alert" role="alert"><strong>No se pudo completar todo</strong><ul><?php foreach (array_unique($errors) as $error): ?><li><?= pushAdminHtml($error) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
    <?php if ($sendResult !== null): ?>
    <section class="store-admin-success push-admin__result" role="status">
        <strong>Envío finalizado</strong>
        <span><?= (int) $sendResult['sent'] ?> procesadas · <?= (int) $sendResult['success'] ?> exitosas · <?= (int) $sendResult['failed'] ?> fallidas</span>
        <?php if ($sendResult['errors'] !== []): ?><ul><?php foreach ($sendResult['errors'] as $failure): ?><li>Suscripción <?= (int) $failure['id'] ?>: <?= pushAdminHtml($failure['message']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card push-admin__panel"<?= $publicConfig !== null ? ' data-push-notifications data-push-public-key="' . pushAdminHtml($publicConfig['public_key']) . '" data-push-worker-url="../service-worker.js" data-push-worker-scope="../" data-push-subscribe-url="../web-push/subscribe.php" data-push-unsubscribe-url="../web-push/unsubscribe.php"' : '' ?>>
        <div><p class="eyebrow">Dispositivo actual</p><h2>Estado de Web Push</h2></div>
        <dl class="push-admin__device-state">
            <div><dt>Service Worker</dt><dd data-push-support-worker><?= $publicConfig !== null ? 'Comprobando…' : 'Sin configurar' ?></dd></div>
            <div><dt>Push API</dt><dd data-push-support-api><?= $publicConfig !== null ? 'Comprobando…' : 'Sin configurar' ?></dd></div>
            <div><dt>Permiso</dt><dd data-push-permission><?= $publicConfig !== null ? 'Comprobando…' : 'Sin configurar' ?></dd></div>
            <div><dt>Suscripción</dt><dd data-push-subscription><?= $publicConfig !== null ? 'Comprobando…' : 'Sin configurar' ?></dd></div>
        </dl>
        <div class="push-admin__actions">
            <button type="button" class="button button-primary" data-push-action<?= $publicConfig === null ? ' disabled' : '' ?>>Activar notificaciones en este dispositivo</button>
            <button type="button" class="button compact-secondary-button" data-push-deactivate<?= $publicConfig === null ? ' disabled' : '' ?>>Desactivar notificaciones</button>
        </div>
        <p class="push-admin__device-status" role="status" aria-live="polite" data-push-status></p>
    </section>

    <section class="card push-admin__panel">
        <div><p class="eyebrow">Envío manual</p><h2>Enviar notificación de prueba</h2></div>
        <p class="push-admin__note">El envío se cifra en el hosting. La clave VAPID privada no se entrega al navegador.</p>
        <form method="post" class="push-admin__send-form">
            <input type="hidden" name="csrf_token" value="<?= pushAdminHtml(storeAdminCsrfToken()) ?>">
            <label>Destino<select name="destination" required><option value="all"<?= $selectedDestination === 'all' ? ' selected' : '' ?>>Todas las suscripciones activas</option><?php foreach ($activeRows as $row): $id = (int) $row['id']; ?><option value="<?= $id ?>"<?= $selectedDestination === (string) $id ? ' selected' : '' ?>>Suscripción <?= $id ?> · <?= pushAdminHtml(pushAdminUserAgent($row['user_agent'])) ?></option><?php endforeach; ?></select></label>
            <label>Título<input type="text" name="title" maxlength="120" value="<?= pushAdminHtml($title) ?>" required></label>
            <label class="push-admin__send-form-message">Mensaje<textarea name="body" maxlength="500" rows="3" required><?= pushAdminHtml($body) ?></textarea></label>
            <label>URL a abrir<input type="text" name="target_url" maxlength="2048" value="<?= pushAdminHtml($targetUrl) ?>" required><small><code>./</code> abre la portada de Aquellas Lunas, también bajo <code>/astro/</code>.</small></label>
            <button type="submit" class="button button-primary"<?= !$serverConfigAvailable || $activeRows === [] ? ' disabled' : '' ?>>Enviar notificación</button>
        </form>
    </section>

    <section class="card push-admin__panel">
        <div><p class="eyebrow">WEB_DB</p><h2>Suscripciones guardadas</h2></div>
        <?php if ($rows === []): ?><p class="store-admin-empty">No hay suscripciones guardadas.</p><?php else: ?>
        <div class="push-admin__table-wrap"><table class="push-admin__table"><thead><tr><th>ID</th><th>Estado</th><th>Creada</th><th>Actualizada</th><th>Último éxito</th><th>Último error</th><th>Dispositivo</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><td><?= (int) $row['id'] ?></td><td><span class="push-admin__state <?= (int) $row['active'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int) $row['active'] === 1 ? 'Activa' : 'Inactiva' ?></span></td><td><?= pushAdminHtml($row['created_at']) ?></td><td><?= pushAdminHtml($row['updated_at']) ?></td><td><?= pushAdminHtml($row['last_success_at'] ?: '—') ?></td><td><?php if ($row['last_error_at']): ?><span><?= pushAdminHtml($row['last_error_at']) ?></span><?php if ($row['last_error_message']): ?><small><?= pushAdminHtml($row['last_error_message']) ?></small><?php endif; ?><?php else: ?>—<?php endif; ?></td><td class="push-admin__user-agent"><?= pushAdminHtml(pushAdminUserAgent($row['user_agent'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
