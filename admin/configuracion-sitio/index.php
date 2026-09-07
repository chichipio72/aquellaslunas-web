<?php

declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/site-configuration.php';
require_once __DIR__ . '/../../includes/site-menu.php';
require_once __DIR__ . '/../../includes/asset-url.php';
require_once __DIR__ . '/../../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

function siteConfigHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$notice = '';

try {
    $connection = getWebDatabaseConnection();
    astronomySiteConfigInitialize($connection);
} catch (Throwable $exception) {
    $connection = null;
    $errors[] = 'No se pudo preparar la configuración del sitio.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($connection === null) {
        $errors[] = 'No hay conexión disponible para guardar cambios.';
    } elseif (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión expiró o el token CSRF no es válido. Recargá la página.';
    } else {
        try {
            $mode = isset($_POST['delete_group']) ? 'delete_group' : (string) ($_POST['mode'] ?? 'save_menu');
            if ($mode === 'create_group') {
                astronomySiteMenuCreateGroup($connection, (string) ($_POST['group_label'] ?? ''));
            } elseif ($mode === 'delete_group') {
                astronomySiteMenuDeleteGroup($connection, (string) ($_POST['delete_group'] ?? ''));
            } elseif ($mode === 'save_menu') {
                $groupUpdates = is_array($_POST['groups'] ?? null) ? $_POST['groups'] : [];
                $sectionInput = is_array($_POST['sections'] ?? null) ? $_POST['sections'] : [];
                $sectionUpdates = [];
                foreach ($sectionInput as $sectionId => $row) {
                    if (!is_array($row)) continue;
                    $sectionUpdates[(string) $sectionId] = [
                        'group_id' => (string) ($row['group_id'] ?? ''),
                        'sort_order' => $row['sort_order'] ?? null,
                        'public_visible' => isset($row['public_visible']),
                        'admin_visible' => isset($row['admin_visible']),
                    ];
                }
                astronomySiteMenuUpdate($connection, $groupUpdates, $sectionUpdates);
            } elseif ($mode === 'save_flags') {
                $updates = [];
                foreach (astronomySiteConfigCatalog() as $key => $definition) {
                    if (in_array(($definition['group'] ?? ''), ['menu', 'eclipses'], true) || ($definition['type'] ?? 'boolean') !== 'boolean') continue;
                    $updates[$key] = isset($_POST['settings']) && is_array($_POST['settings']) && array_key_exists($key, $_POST['settings']);
                }
                astronomySiteConfigUpdate($connection, $updates);
            } elseif ($mode === 'save_eclipses') {
                $settings = is_array($_POST['eclipse_settings'] ?? null) ? $_POST['eclipse_settings'] : [];
                astronomySiteConfigUpdateValues($connection, [
                    'eclipse.upcoming_notice.enabled' => array_key_exists('enabled', $settings),
                    'eclipse.upcoming_notice.days' => $settings['days'] ?? 10,
                    'eclipse.widget.solar_url' => $settings['solar_url'] ?? '',
                    'eclipse.widget.lunar_url' => $settings['lunar_url'] ?? '',
                ]);
            } else {
                throw new InvalidArgumentException('La operación solicitada no es válida.');
            }
            header('Location: index.php?saved=1', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $errors[] = 'Se recibió una clave de configuración inválida.';
        } catch (Throwable $exception) {
            error_log('Aquellas Lunas site config save error: ' . $exception->getMessage());
            $errors[] = 'No se pudo guardar la configuración del sitio.';
        }
    }
}

if (isset($_GET['saved'])) {
    $notice = 'La configuración del sitio se guardó correctamente.';
}

$values = astronomySiteConfigLoadAll();
$configurationGroups = array_values(array_filter(astronomySiteConfigGroupEntries($values), static fn(array $group): bool => !in_array(($group['id'] ?? ''), ['menu', 'eclipses'], true)));
$menu = astronomySiteMenuLoad();
$sectionCatalog = astronomySiteSectionCatalog();
unset($sectionCatalog['home']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Menú y secciones · Aquellas Lunas</title>
    <?php renderFaviconLinks('../../'); ?>
    <link rel="stylesheet" href="<?= siteConfigHtml('../../' . versionedAssetUrl('assets/css/styles.css')) ?>">
</head>
<body class="store-admin">
    <?php renderStoreAdminNavigation('site_configuration', 'Menú y secciones'); ?>
    <main class="store-admin-main">
        <section class="card site-menu-admin">
            <h2>Menú principal</h2>
            <p>Inicio permanece siempre primero. Para el resto podés definir grupo, orden y visibilidad independiente para público y administradores.</p>

            <?php if ($notice !== ''): ?>
                <p class="status-info" role="status"><?= siteConfigHtml($notice) ?></p>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="api-error-notice" role="alert">
                    <p>No se pudo guardar la configuración.</p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= siteConfigHtml($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="site-menu-admin__create">
                <input type="hidden" name="csrf_token" value="<?= siteConfigHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="mode" value="create_group">
                <label><span>Nuevo grupo</span><input type="text" name="group_label" maxlength="120" required placeholder="Nombre visible"></label>
                <button type="submit" class="button compact-secondary-button">Crear grupo</button>
            </form>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= siteConfigHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="mode" value="save_menu">
                <div class="site-menu-admin__groups">
                    <?php foreach ($menu['groups'] as $group): ?>
                        <?php $sectionCount = count(array_filter($menu['sections'], static fn(array $row): bool => ($row['group_id'] ?? '') === $group['id'])); ?>
                        <div class="site-menu-admin__group">
                            <code><?= siteConfigHtml($group['id']) ?></code>
                            <label><span>Nombre</span><input type="text" name="groups[<?= siteConfigHtml($group['id']) ?>][label]" maxlength="120" required value="<?= siteConfigHtml($group['label']) ?>"></label>
                            <label><span>Orden</span><input type="number" name="groups[<?= siteConfigHtml($group['id']) ?>][sort_order]" step="1" required value="<?= siteConfigHtml($group['sort_order']) ?>"></label>
                            <button type="submit" class="button compact-secondary-button" name="delete_group" value="<?= siteConfigHtml($group['id']) ?>" formmethod="post" formaction="index.php" <?= $sectionCount > 0 ? 'disabled title="Mové primero sus secciones"' : '' ?>>Eliminar</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="site-menu-admin__table-wrap">
                    <table class="site-menu-admin__table">
                        <thead><tr><th>Sección</th><th>Grupo</th><th>Orden</th><th>Público</th><th>Admin</th></tr></thead>
                        <tbody>
                        <?php foreach ($sectionCatalog as $sectionId => $section): ?>
                            <?php $settings = $menu['sections'][$sectionId] ?? ['group_id' => '', 'sort_order' => 0, 'public_visible' => false, 'admin_visible' => true]; ?>
                            <tr>
                                <th scope="row"><strong><?= siteConfigHtml($section['label']) ?></strong><code><?= siteConfigHtml($sectionId) ?></code></th>
                                <td><select name="sections[<?= siteConfigHtml($sectionId) ?>][group_id]" required><?php foreach ($menu['groups'] as $group): ?><option value="<?= siteConfigHtml($group['id']) ?>"<?= $settings['group_id'] === $group['id'] ? ' selected' : '' ?>><?= siteConfigHtml($group['label']) ?></option><?php endforeach; ?></select></td>
                                <td><input type="number" name="sections[<?= siteConfigHtml($sectionId) ?>][sort_order]" step="1" required value="<?= siteConfigHtml($settings['sort_order']) ?>" aria-label="Orden de <?= siteConfigHtml($section['label']) ?>"></td>
                                <td><label class="editor-check"><input type="checkbox" name="sections[<?= siteConfigHtml($sectionId) ?>][public_visible]" value="1"<?= $settings['public_visible'] ? ' checked' : '' ?>><span class="visually-hidden">Visible para público</span></label></td>
                                <td><label class="editor-check"><input type="checkbox" name="sections[<?= siteConfigHtml($sectionId) ?>][admin_visible]" value="1"<?= $settings['admin_visible'] ? ' checked' : '' ?>><span class="visually-hidden">Visible para administradores</span></label></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="site-menu-admin__actions">
                    <button type="submit" class="button button-primary">Guardar menú</button>
                    <a href="../index.php" class="button compact-secondary-button">Volver al panel</a>
                </div>
            </form>
        </section>

        <section class="card site-menu-admin site-menu-admin--secondary">
            <h2>Eclipses</h2>
            <p>Las URLs funcionan como plantillas. El sitio conserva sus ajustes y reemplaza únicamente fecha, latitud, longitud y elevación para cada observador.</p>
            <form method="post" class="site-config-eclipses-form">
                <input type="hidden" name="csrf_token" value="<?= siteConfigHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="mode" value="save_eclipses">
                <label class="editor-check site-menu-admin__flag"><input type="checkbox" name="eclipse_settings[enabled]" value="1"<?= (($values['eclipse.upcoming_notice.enabled'] ?? true) === true) ? ' checked' : '' ?>>Mostrar avisos de eclipses próximos</label>
                <label><span>Anticipación del aviso (días)</span><input type="number" name="eclipse_settings[days]" min="1" max="90" step="1" required value="<?= siteConfigHtml($values['eclipse.upcoming_notice.days'] ?? 10) ?>"></label>
                <label><span>URL base/configuración Eclipse solar real</span><textarea name="eclipse_settings[solar_url]" rows="5" spellcheck="false" required><?= siteConfigHtml($values['eclipse.widget.solar_url'] ?? '') ?></textarea></label>
                <label><span>URL base/configuración Eclipse lunar real</span><textarea name="eclipse_settings[lunar_url]" rows="5" spellcheck="false" required><?= siteConfigHtml($values['eclipse.widget.lunar_url'] ?? '') ?></textarea></label>
                <button type="submit" class="button button-primary">Guardar configuración de eclipses</button>
            </form>
        </section>

        <section class="card site-menu-admin site-menu-admin--secondary">
            <h2>Otras configuraciones de visibilidad</h2>
            <p>Estos controles corresponden a portada, contenidos y diagnóstico; no alteran la organización del menú.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= siteConfigHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="mode" value="save_flags">
                <?php foreach ($configurationGroups as $group): ?>
                    <fieldset class="card"><legend><strong><?= siteConfigHtml($group['label'] ?? '') ?></strong></legend>
                    <?php foreach (($group['entries'] ?? []) as $entry): ?><label class="editor-check site-menu-admin__flag"><input type="checkbox" name="settings[<?= siteConfigHtml($entry['key'] ?? '') ?>]" value="1"<?= (($entry['value'] ?? false) === true) ? ' checked' : '' ?>><?= siteConfigHtml($entry['label'] ?? '') ?></label><?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
                <button type="submit" class="button button-primary">Guardar otras opciones</button>
            </form>
        </section>
    </main>
</body>
</html>
