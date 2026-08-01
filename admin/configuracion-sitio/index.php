<?php

declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/site-configuration.php';
require_once __DIR__ . '/../../includes/asset-url.php';

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
            $updates = [];
            foreach (array_keys(astronomySiteConfigCatalog()) as $key) {
                $updates[$key] = isset($_POST['settings'])
                    && is_array($_POST['settings'])
                    && array_key_exists($key, $_POST['settings']);
            }
            astronomySiteConfigUpdate($connection, $updates);
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
$groups = astronomySiteConfigGroupEntries($values);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Configuración del sitio · Aquellas Lunas</title>
    <link rel="stylesheet" href="<?= siteConfigHtml('../../' . versionedAssetUrl('assets/css/styles.css')) ?>">
</head>
<body class="store-admin">
    <?php renderStoreAdminNavigation('site_configuration', 'Configuración del sitio'); ?>
    <main class="store-admin-main">
        <section class="card" style="max-width: 1100px; margin: 0 auto;">
            <h2>Visibilidad pública</h2>
            <p>Controlá qué bloques del sitio público permanecen visibles. Estas opciones no afectan el acceso al área administrativa.</p>

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

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= siteConfigHtml(storeAdminCsrfToken()) ?>">

                <?php foreach ($groups as $group): ?>
                    <fieldset class="card" style="margin-top: 1rem;">
                        <legend><strong><?= siteConfigHtml($group['label'] ?? '') ?></strong></legend>
                        <?php foreach (($group['entries'] ?? []) as $entry): ?>
                            <label class="editor-check" style="display: block; margin: .75rem 0;">
                                <input
                                    type="checkbox"
                                    name="settings[<?= siteConfigHtml($entry['key'] ?? '') ?>]"
                                    value="1"
                                    <?= (($entry['value'] ?? false) === true) ? 'checked' : '' ?>
                                >
                                <?= siteConfigHtml($entry['label'] ?? '') ?>
                            </label>
                            <?php if (siteConfigHtml($entry['description'] ?? '') !== ''): ?>
                                <p style="margin: -.35rem 0 .75rem 1.65rem; color: #475569;"><?= siteConfigHtml($entry['description'] ?? '') ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>

                <div style="margin-top: 1rem; display: flex; gap: .75rem; flex-wrap: wrap;">
                    <button type="submit" class="button button-primary">Guardar configuración</button>
                    <a href="../index.php" class="button compact-secondary-button">Volver al panel</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
