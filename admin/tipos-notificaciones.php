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

function notificationTypeAdminHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = '';
$types = [];
$selected = null;
$preview = null;
$selectedCode = trim((string) ($_GET['type'] ?? $_POST['notification_type'] ?? ''));
try {
    $connection = getWebDatabaseConnection();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
            throw new InvalidArgumentException('La sesión expiró o el token CSRF no es válido. Recargá la página.');
        }
        astronomyPushSaveNotificationType($connection, $selectedCode, $_POST);
        $success = 'Tipo de notificación actualizado.';
    }
    $types = astronomyPushNotificationTypes($connection);
    if ($selectedCode === '' && isset($types[0])) $selectedCode = (string) $types[0]['notification_type'];
    foreach ($types as $type) {
        if ($type['notification_type'] === $selectedCode) { $selected = $type; break; }
    }
    if ($selectedCode !== '' && $selected === null) throw new InvalidArgumentException('El tipo seleccionado no existe.');
    if ($selected !== null) $preview = astronomyPushNotificationPreview($selected);
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
    if (isset($_POST['notification_type'])) {
        $selected = array_replace($_POST, ['notification_type' => $selectedCode]);
    }
} catch (Throwable $exception) {
    error_log('Notification type admin failed [type=' . get_debug_type($exception) . '].');
    $errors[] = 'No se pudo cargar o guardar el catálogo de notificaciones.';
}

$allowed = $selected !== null ? astronomyPushAllowedPlaceholders((string) $selected['notification_type']) : [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Tipos de notificación · Aquellas Lunas</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= notificationTypeAdminHtml('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <style>
        .notification-types-admin { display: block; max-width: 92rem; }
        .notification-types-admin__layout { display: grid; grid-template-columns: minmax(18rem, 23rem) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        .notification-types-admin__catalog,
        .notification-types-admin__editor { min-width: 0; }
        .notification-types-admin__heading { margin-bottom: 1rem; }
        .notification-types-admin__heading :is(h2, p) { margin-bottom: 0; }
        .notification-types-admin__list { display: grid; gap: .65rem; }
        .notification-types-admin__type {
            position: relative; display: grid; gap: .45rem; min-width: 0; padding: .9rem 1rem;
            border: 1px solid rgba(151, 172, 213, .24); border-radius: .8rem;
            background: rgba(9, 16, 30, .58); color: #e9edf7; text-decoration: none;
            transition: border-color .18s ease, background .18s ease, transform .18s ease;
        }
        .notification-types-admin__type:hover { border-color: rgba(225, 188, 92, .58); background: rgba(225, 188, 92, .07); transform: translateY(-1px); }
        .notification-types-admin__type:focus-visible { outline: 2px solid #e2bd5f; outline-offset: 3px; }
        .notification-types-admin__type.is-active { border-color: rgba(226, 189, 95, .78); background: linear-gradient(115deg, rgba(226, 189, 95, .13), rgba(25, 36, 58, .62)); }
        .notification-types-admin__type.is-active::before {
            content: ''; position: absolute; inset: .75rem auto .75rem 0; width: 3px;
            border-radius: 0 999px 999px 0; background: #e2bd5f;
        }
        .notification-types-admin__type-head { display: flex; align-items: baseline; justify-content: space-between; gap: .75rem; min-width: 0; }
        .notification-types-admin__type-name { min-width: 0; font-weight: 750; line-height: 1.25; }
        .notification-types-admin__code { color: #9fb0ce; font-size: .75rem; overflow-wrap: anywhere; }
        .notification-types-admin__badges { display: flex; flex-wrap: wrap; gap: .4rem; }
        .notification-types-admin__badge {
            display: inline-flex; align-items: center; min-height: 1.45rem; padding: .18rem .48rem;
            border: 1px solid rgba(151, 172, 213, .26); border-radius: 999px;
            color: #bec9de; background: rgba(151, 172, 213, .07); font-size: .7rem; font-weight: 700;
        }
        .notification-types-admin__badge.is-available { border-color: rgba(147, 194, 137, .38); color: #b9d7ac; background: rgba(91, 135, 76, .12); }
        .notification-types-admin__badge.is-unavailable { border-color: rgba(212, 162, 94, .38); color: #dfbf82; background: rgba(154, 107, 42, .11); }
        .notification-types-admin__editor-head { display: flex; align-items: end; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
        .notification-types-admin__editor-head :is(h2, p) { margin-bottom: 0; }
        .notification-types-admin__immutable { margin: 0; color: #aeb9ce; font-size: .82rem; text-align: right; }
        .notification-types-admin__form { display: grid; gap: 1rem; }
        .notification-types-admin__section {
            min-width: 0; padding: 1.15rem; border: 1px solid rgba(151, 172, 213, .2);
            border-radius: .85rem; background: rgba(8, 15, 29, .4);
        }
        .notification-types-admin__section h3 { margin: 0 0 1rem; color: #f0d27d; font-size: 1rem; }
        .notification-types-admin__fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .95rem 1rem; align-items: start; }
        .notification-types-admin__fields--schedule { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .notification-types-admin__field { display: grid; gap: .42rem; min-width: 0; color: #cbd3e2; font-size: .85rem; font-weight: 650; }
        .notification-types-admin__field--full { grid-column: 1 / -1; }
        .notification-types-admin__field :is(input, textarea, select) {
            width: 100%; min-width: 0; margin: 0; border: 1px solid rgba(151, 172, 213, .34);
            border-radius: .65rem; background: #09111f; color: #eef2fa; font: inherit;
        }
        .notification-types-admin__field input,
        .notification-types-admin__field select { min-height: 2.75rem; padding: .62rem .72rem; }
        .notification-types-admin__field textarea { padding: .72rem; line-height: 1.5; resize: vertical; }
        .notification-types-admin__field :is(input, textarea, select):focus-visible { outline: 2px solid #e2bd5f; outline-offset: 2px; border-color: #e2bd5f; }
        .notification-types-admin__switches { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; grid-column: 1 / -1; }
        .notification-types-admin__switch {
            position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-width: 0;
            padding: .72rem .8rem; border: 1px solid rgba(151, 172, 213, .24); border-radius: .7rem;
            background: rgba(21, 31, 51, .52); color: #dce3f0; font-weight: 650; cursor: pointer;
        }
        .notification-types-admin__switch input { position: absolute; inline-size: 1px; block-size: 1px; opacity: 0; }
        .notification-types-admin__switch-control {
            position: relative; flex: 0 0 2.3rem; width: 2.3rem; height: 1.3rem; border: 1px solid #60708e;
            border-radius: 999px; background: #111b2c; transition: background .18s ease, border-color .18s ease;
        }
        .notification-types-admin__switch-control::after {
            content: ''; position: absolute; top: .16rem; left: .18rem; width: .82rem; height: .82rem;
            border-radius: 50%; background: #aeb8ca; transition: transform .18s ease, background .18s ease;
        }
        .notification-types-admin__switch input:checked + .notification-types-admin__switch-control { border-color: #e2bd5f; background: rgba(226, 189, 95, .3); }
        .notification-types-admin__switch input:checked + .notification-types-admin__switch-control::after { transform: translateX(.98rem); background: #f0cf70; }
        .notification-types-admin__switch input:focus-visible + .notification-types-admin__switch-control { outline: 2px solid #e2bd5f; outline-offset: 3px; }
        .notification-types-admin__markers { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: .9rem; }
        .notification-types-admin__marker {
            display: inline-block; padding: .3rem .55rem; border: 1px solid rgba(114, 159, 222, .34);
            border-radius: .45rem; background: rgba(50, 91, 149, .13); color: #bad1f3;
            font: 650 .76rem/1.25 ui-monospace, SFMono-Regular, Consolas, monospace; user-select: text;
        }
        .notification-types-admin__preview {
            display: grid; grid-template-columns: 2.75rem minmax(0, 1fr); gap: .85rem; align-items: start;
            padding: 1rem; border: 1px solid rgba(151, 172, 213, .27); border-radius: .8rem;
            background: linear-gradient(135deg, rgba(24, 35, 57, .9), rgba(8, 14, 27, .92));
        }
        .notification-types-admin__preview-icon {
            display: grid; place-items: center; width: 2.75rem; height: 2.75rem; border-radius: .7rem;
            background: rgba(226, 189, 95, .14); color: #f0cf70; font-size: 1.25rem;
        }
        .notification-types-admin__preview-copy { min-width: 0; }
        .notification-types-admin__preview-title { margin: 0 0 .35rem; color: #f5f7fb; font-weight: 800; }
        .notification-types-admin__preview-body { margin: 0 0 .6rem; color: #c8d1e2; line-height: 1.45; }
        .notification-types-admin__preview-url { margin: 0; color: #9eb3d5; font-size: .78rem; overflow-wrap: anywhere; }
        .notification-types-admin__actions { display: flex; justify-content: flex-end; padding-top: .1rem; }
        .notification-types-admin__actions .button-primary { min-width: 11rem; box-shadow: 0 .45rem 1.2rem rgba(0, 0, 0, .2); }
        @media (max-width: 68rem) {
            .notification-types-admin__layout { grid-template-columns: 1fr; }
            .notification-types-admin__list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .notification-types-admin__fields--schedule { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 42rem) {
            .notification-types-admin__list,
            .notification-types-admin__fields,
            .notification-types-admin__fields--schedule,
            .notification-types-admin__switches { grid-template-columns: minmax(0, 1fr); }
            .notification-types-admin__editor-head { display: grid; align-items: start; }
            .notification-types-admin__immutable { text-align: left; }
            .notification-types-admin__section { padding: .9rem; }
            .notification-types-admin__actions .button-primary { width: 100%; }
        }
    </style>
</head>
<body class="store-admin">
<?php renderStoreAdminNavigation('notification_types', 'Tipos de notificación'); ?>
<main class="store-admin-main notification-types-admin">
    <?php if ($errors !== []): ?><section class="store-admin-alert" role="alert"><strong>No se pudo completar la operación</strong><ul><?php foreach ($errors as $error): ?><li><?= notificationTypeAdminHtml($error) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
    <?php if ($success !== ''): ?><section class="store-admin-success" role="status"><strong><?= notificationTypeAdminHtml($success) ?></strong></section><?php endif; ?>

    <div class="notification-types-admin__layout">
    <section class="card notification-types-admin__catalog">
        <div class="notification-types-admin__heading"><p class="eyebrow">Catálogo global</p><h2>Tipos registrados</h2></div>
        <?php if ($types === []): ?><p class="store-admin-empty">No hay tipos registrados.</p><?php else: ?>
        <nav class="notification-types-admin__list" aria-label="Tipos de notificación">
        <?php foreach ($types as $type):
            $typeCode = (string) $type['notification_type'];
            $isActive = $typeCode === $selectedCode;
        ?><a class="notification-types-admin__type<?= $isActive ? ' is-active' : '' ?>" href="?type=<?= rawurlencode($typeCode) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
            <span class="notification-types-admin__type-head">
                <span class="notification-types-admin__type-name"><?= notificationTypeAdminHtml($type['display_name']) ?></span>
                <code class="notification-types-admin__code"><?= notificationTypeAdminHtml($typeCode) ?></code>
            </span>
            <span class="notification-types-admin__badges">
                <span class="notification-types-admin__badge <?= (int) $type['available'] === 1 ? 'is-available' : 'is-unavailable' ?>"><?= (int) $type['available'] === 1 ? 'Disponible' : 'No disponible' ?></span>
                <span class="notification-types-admin__badge"><?= (int) $type['admin_only'] === 1 ? 'Sólo administración' : 'Dispositivos' ?></span>
            </span>
        </a><?php endforeach; ?>
        </nav><?php endif; ?>
    </section>

    <?php if (is_array($selected)): ?>
    <section class="card notification-types-admin__editor">
        <div class="notification-types-admin__editor-head">
            <div><p class="eyebrow">Edición</p><h2><?= notificationTypeAdminHtml($selected['display_name'] ?? $selectedCode) ?></h2></div>
            <p class="notification-types-admin__immutable">Código interno inmutable<br><code><?= notificationTypeAdminHtml($selectedCode) ?></code></p>
        </div>
        <form method="post" class="notification-types-admin__form">
            <input type="hidden" name="csrf_token" value="<?= notificationTypeAdminHtml(storeAdminCsrfToken()) ?>">
            <input type="hidden" name="notification_type" value="<?= notificationTypeAdminHtml($selectedCode) ?>">
            <section class="notification-types-admin__section" aria-labelledby="notification-general-heading">
                <h3 id="notification-general-heading">Información general</h3>
                <div class="notification-types-admin__fields">
                    <label class="notification-types-admin__field">Nombre visible<input type="text" name="display_name" maxlength="100" required value="<?= notificationTypeAdminHtml($selected['display_name'] ?? '') ?>"></label>
                    <label class="notification-types-admin__field">Descripción<textarea name="description" maxlength="255" rows="2"><?= notificationTypeAdminHtml($selected['description'] ?? '') ?></textarea></label>
                    <div class="notification-types-admin__switches">
                        <label class="notification-types-admin__switch"><span>Disponible globalmente</span><input type="checkbox" name="available" value="1"<?= !empty($selected['available']) ? ' checked' : '' ?>><span class="notification-types-admin__switch-control" aria-hidden="true"></span></label>
                        <label class="notification-types-admin__switch"><span>Sólo uso administrativo</span><input type="checkbox" name="admin_only" value="1"<?= !empty($selected['admin_only']) ? ' checked' : '' ?>><span class="notification-types-admin__switch-control" aria-hidden="true"></span></label>
                    </div>
                </div>
            </section>

            <section class="notification-types-admin__section" aria-labelledby="notification-content-heading">
                <h3 id="notification-content-heading">Contenido de la notificación</h3>
                <div class="notification-types-admin__fields">
                    <label class="notification-types-admin__field notification-types-admin__field--full">Título<input type="text" name="title_template" maxlength="180" required value="<?= notificationTypeAdminHtml($selected['title_template'] ?? '') ?>"></label>
                    <label class="notification-types-admin__field notification-types-admin__field--full">Mensaje<textarea name="body_template" maxlength="500" rows="4" required><?= notificationTypeAdminHtml($selected['body_template'] ?? '') ?></textarea></label>
                    <label class="notification-types-admin__field notification-types-admin__field--full">URL de apertura<input type="text" name="target_url" maxlength="500" required value="<?= notificationTypeAdminHtml($selected['target_url'] ?? '') ?>"></label>
                </div>
                <div class="notification-types-admin__markers" aria-label="Marcadores permitidos">
                    <?php if ($allowed === []): ?><span class="push-admin__note">Este tipo no admite marcadores.</span><?php else: ?>
                    <?php foreach ($allowed as $placeholder): ?><code class="notification-types-admin__marker"><?= notificationTypeAdminHtml('{' . $placeholder . '}') ?></code><?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="notification-types-admin__section" aria-labelledby="notification-schedule-heading">
                <h3 id="notification-schedule-heading">Programación predeterminada</h3>
                <div class="notification-types-admin__fields notification-types-admin__fields--schedule">
                    <label class="notification-types-admin__field">Modo<select name="default_schedule_mode"><option value="before_event"<?= ($selected['default_schedule_mode'] ?? '') === 'before_event' ? ' selected' : '' ?>>Antes del evento</option><option value="fixed_time"<?= ($selected['default_schedule_mode'] ?? '') === 'fixed_time' ? ' selected' : '' ?>>Hora fija</option></select></label>
                    <label class="notification-types-admin__field">Anticipación<input type="number" name="default_lead_minutes" min="0" max="1440" value="<?= notificationTypeAdminHtml($selected['default_lead_minutes'] ?? '') ?>"></label>
                    <label class="notification-types-admin__field">Hora<input type="time" name="default_delivery_time" value="<?= notificationTypeAdminHtml(isset($selected['default_delivery_time']) ? substr((string) $selected['default_delivery_time'], 0, 5) : '') ?>"></label>
                    <label class="notification-types-admin__field">Desplazamiento de día<input type="number" name="default_delivery_day_offset" min="-366" max="366" required value="<?= notificationTypeAdminHtml($selected['default_delivery_day_offset'] ?? 0) ?>"></label>
                    <label class="notification-types-admin__field">Política de No molestar<select name="default_quiet_policy"><option value="omit"<?= ($selected['default_quiet_policy'] ?? '') === 'omit' ? ' selected' : '' ?>>Omitir</option><option value="ignore"<?= ($selected['default_quiet_policy'] ?? '') === 'ignore' ? ' selected' : '' ?>>Ignorar horario</option></select></label>
                    <label class="notification-types-admin__field">Orden<input type="number" name="sort_order" required value="<?= notificationTypeAdminHtml($selected['sort_order'] ?? 0) ?>"></label>
                </div>
            </section>

            <section class="notification-types-admin__section" aria-labelledby="notification-preview-heading">
                <h3 id="notification-preview-heading">Vista previa</h3>
                <div class="notification-types-admin__preview">
                    <span class="notification-types-admin__preview-icon" aria-hidden="true">☾</span>
                    <div class="notification-types-admin__preview-copy">
                        <p class="notification-types-admin__preview-title"><?= notificationTypeAdminHtml($preview['title'] ?? 'Vista previa no disponible') ?></p>
                        <?php if (is_array($preview)): ?><p class="notification-types-admin__preview-body"><?= notificationTypeAdminHtml($preview['body']) ?></p><p class="notification-types-admin__preview-url">Abrirá: <?= notificationTypeAdminHtml($preview['url']) ?></p><?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="notification-types-admin__actions"><button class="button button-primary" type="submit">Guardar tipo</button></div>
        </form>
    </section>
    <?php endif; ?>
    </div>
</main>
</body>
</html>
