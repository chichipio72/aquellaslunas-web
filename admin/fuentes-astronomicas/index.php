<?php

declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/astronomy-events.php';
require_once __DIR__ . '/../../includes/asset-url.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

function astronomySourcesHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$sourceLabels = [
    'api' => 'API',
    'database' => 'Base de datos',
    'php' => 'PHP',
    'auto' => 'Automático',
    'compare' => 'Comparar',
];
$errors = [];
$notice = isset($_GET['saved']) ? 'Las fuentes astronómicas se guardaron correctamente.' : '';
$catalog = astronomyEventSourceCatalog();

try {
    $connection = getWebDatabaseConnection();
} catch (Throwable $exception) {
    $connection = null;
    $errors[] = 'No se pudo cargar la configuración astronómica.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$connection instanceof PDO) {
        $errors[] = 'No hay conexión disponible para guardar cambios.';
    } elseif (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión expiró o el token CSRF no es válido. Recargá la página.';
    } else {
        try {
            $postedSources = is_array($_POST['sources'] ?? null) ? $_POST['sources'] : [];
            $updates = [];
            foreach ($catalog as $eventGroup => $definition) {
                $updates[$eventGroup] = is_string($postedSources[$eventGroup] ?? null)
                    ? (string) $postedSources[$eventGroup]
                    : '';
            }
            astronomyEventSourceUpdate($connection, $updates);
            header('Location: index.php?saved=1', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $errors[] = 'Se recibió una fuente no permitida para uno de los fenómenos.';
        } catch (Throwable $exception) {
            error_log('Aquellas Lunas astronomy source save error: ' . $exception->getMessage());
            $errors[] = 'No se pudo guardar la configuración astronómica.';
        }
    }
}

try {
    $settings = astronomyEventSourceSettings();
} catch (Throwable $exception) {
    $settings = array_fill_keys(array_keys($catalog), 'api');
    $errors[] = 'No se pudo resolver la configuración astronómica actual.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Fuentes astronómicas · Aquellas Lunas</title>
    <link rel="stylesheet" href="<?= astronomySourcesHtml('../../' . versionedAssetUrl('assets/css/styles.css')) ?>">
</head>
<body class="store-admin">
    <?php renderStoreAdminNavigation('astronomy_sources', 'Fuentes astronómicas'); ?>
    <main class="store-admin-main">
        <section class="card" style="max-width: 1100px; margin: 0 auto;">
            <h2>Fuente por fenómeno</h2>
            <p>Elegí qué origen debe usar la web para cada grupo. Sólo se muestran opciones actualmente disponibles.</p>

            <?php if ($notice !== ''): ?>
                <p class="status-info" role="status"><?= astronomySourcesHtml($notice) ?></p>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <div class="api-error-notice" role="alert">
                    <p>No se pudo completar la operación.</p>
                    <ul><?php foreach ($errors as $error): ?><li><?= astronomySourcesHtml($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="post" class="astronomy-sources-form">
                <input type="hidden" name="csrf_token" value="<?= astronomySourcesHtml(storeAdminCsrfToken()) ?>">
                <div class="astronomy-sources-grid">
                    <?php foreach ($catalog as $eventGroup => $definition): ?>
                        <div class="astronomy-sources-row">
                            <label for="source-<?= astronomySourcesHtml($eventGroup) ?>"><?= astronomySourcesHtml($definition['label']) ?></label>
                            <select id="source-<?= astronomySourcesHtml($eventGroup) ?>" name="sources[<?= astronomySourcesHtml($eventGroup) ?>]">
                                <?php foreach ($definition['sources'] as $source): ?>
                                    <option value="<?= astronomySourcesHtml($source) ?>"<?= ($settings[$eventGroup] ?? 'api') === $source ? ' selected' : '' ?>><?= astronomySourcesHtml($sourceLabels[$source] ?? $source) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>

                <aside class="astronomy-sources-help">
                    <h3>Qué significa cada modo</h3>
                    <dl>
                        <dt>API</dt><dd>Servicio Python existente, con respaldo automático en las fuentes disponibles si el servicio falla.</dd>
                        <dt>Base de datos</dt><dd>Eventos precalculados en MariaDB.</dd>
                        <dt>PHP</dt><dd>Motor astronómico local.</dd>
                        <dt>Automático</dt><dd>Selección y alternativa definidas por la capa astronómica.</dd>
                        <dt>Comparar</dt><dd>Usa más de una fuente para diagnóstico sin mezclar resultados.</dd>
                    </dl>
                </aside>

                <div class="astronomy-sources-actions">
                    <button type="submit" class="button button-primary">Guardar fuentes</button>
                    <a href="../index.php" class="button compact-secondary-button">Volver al panel</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
