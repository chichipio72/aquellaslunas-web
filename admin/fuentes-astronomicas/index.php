<?php

declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/astronomy-events.php';
require_once __DIR__ . '/../../includes/astronomy-data.php';
require_once __DIR__ . '/../../includes/asset-url.php';
require_once __DIR__ . '/../../includes/favicon-links.php';

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
    'static' => 'Colección precalculada',
    'auto' => 'Automático',
    'compare' => 'Comparar',
];
$errors = [];
$notice = isset($_GET['saved']) ? 'Las fuentes astronómicas se guardaron correctamente.' : '';
$eventCatalog = astronomyEventSourceCatalog();
$generalCatalog = astronomyDataSourceCatalog();

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
            $eventUpdates = [];
            foreach ($eventCatalog as $eventGroup => $definition) {
                $eventUpdates[$eventGroup] = is_string($postedSources[$eventGroup] ?? null)
                    ? (string) $postedSources[$eventGroup]
                    : '';
            }
            $postedGeneralSources = is_array($_POST['general_sources'] ?? null) ? $_POST['general_sources'] : [];
            $generalUpdates = [];
            foreach ($generalCatalog as $functionality => $definition) {
                $generalUpdates[$functionality] = is_string($postedGeneralSources[$functionality] ?? null)
                    ? (string) $postedGeneralSources[$functionality]
                    : '';
            }
            astronomyDataSourceUpdate($connection, $generalUpdates);
            astronomyEventSourceUpdate($connection, $eventUpdates);
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
    $eventSettings = astronomyEventSourceSettings();
    $generalSettings = astronomyDataSourceSettings();
} catch (Throwable $exception) {
    $eventSettings = array_fill_keys(array_keys($eventCatalog), 'api');
    $generalSettings = [];
    foreach ($generalCatalog as $functionality => $definition) {
        $generalSettings[$functionality] = $definition['default'];
    }
    $errors[] = 'No se pudo resolver la configuración astronómica actual.';
}

$generalSources = [];
foreach ($generalCatalog as $functionality => $definition) {
    $generalSources[$functionality] = $definition + [
        'current' => $generalSettings[$functionality] ?? $definition['default'],
        'fallback' => $functionality === 'moon_image'
            ? 'Colección y API son seleccionables; PNG pequeño como fallback final.'
            : 'La fuente alternativa se usa sólo como fallback técnico.',
    ];
}

$eventSources = [];
foreach (['moon_phase', 'lunar_apsis', 'lunar_orbit', 'lunar_libration', 'lunar_conjunction'] as $eventGroup) {
    $eventSources[$eventGroup] = $eventCatalog[$eventGroup] + [
        'current' => $eventSettings[$eventGroup] ?? 'api',
        'configurable' => true,
        'fallback' => 'Respeta el modo y los fallbacks de la capa de eventos.',
    ];
}
$eventSources['earthshine'] = [
    'label' => 'Luz cenicienta', 'sources' => ['php'], 'current' => 'php', 'configurable' => false,
    'fallback' => 'PHP local; API como fallback técnico.',
];
$eventSources['full_moon_observation'] = [
    'label' => 'Observación de Luna llena', 'sources' => ['php'], 'current' => 'php', 'configurable' => false,
    'fallback' => 'PHP local; API como fallback técnico.',
];
$eventSources['eclipse'] = $eventCatalog['eclipse'] + [
    'current' => $eventSettings['eclipse'] ?? 'api',
    'configurable' => true,
    'fallback' => 'API, MariaDB y PHP disponibles; auto y compare respetan la cobertura de base de datos.',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Fuentes astronómicas · Aquellas Lunas</title>
    <?php renderFaviconLinks('../../'); ?>
    <link rel="stylesheet" href="<?= astronomySourcesHtml('../../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <script src="<?= astronomySourcesHtml('../../' . versionedAssetUrl('assets/js/astronomy-sources.js')) ?>" defer></script>
</head>
<body class="store-admin">
    <?php renderStoreAdminNavigation('astronomy_sources', 'Fuentes astronómicas'); ?>
    <main class="store-admin-main">
        <section class="card" style="max-width: 1100px; margin: 0 auto;">
            <h2>Fuentes astronómicas</h2>
            <p>Consultá la fuente primaria y elegí un modo sólo donde la arquitectura actual permite configurarlo.</p>

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

                <div class="astronomy-sources-global-actions" aria-labelledby="astronomy-global-actions-title">
                    <div><h3 id="astronomy-global-actions-title">Acciones globales</h3><p>Cambian los selectores compatibles. Los cambios se aplican recién al guardar.</p></div>
                    <div class="astronomy-sources-global-buttons">
                        <button type="button" class="button compact-secondary-button" data-astronomy-source-all="api">Todo a API</button>
                        <button type="button" class="button compact-secondary-button" data-astronomy-source-all="php">Todo a PHP</button>
                    </div>
                    <p class="astronomy-sources-global-status" data-astronomy-source-status aria-live="polite">Los cambios se aplican recién al guardar.</p>
                </div>

                <section class="astronomy-sources-section" aria-labelledby="general-sources-title">
                    <div class="astronomy-sources-section__heading"><h3 id="general-sources-title">Cálculos astronómicos generales</h3><p>Elegí API o PHP como fuente primaria. La otra queda como fallback técnico.</p></div>
                    <div class="astronomy-sources-grid">
                        <?php foreach ($generalSources as $sourceKey => $definition): ?>
                            <div class="astronomy-sources-row">
                                <div><label for="source-general-<?= astronomySourcesHtml($sourceKey) ?>"><?= astronomySourcesHtml($definition['label']) ?></label><small><?= astronomySourcesHtml($definition['fallback']) ?></small></div>
                                <select id="source-general-<?= astronomySourcesHtml($sourceKey) ?>" name="general_sources[<?= astronomySourcesHtml($sourceKey) ?>]" data-astronomy-source-select aria-label="Fuente para <?= astronomySourcesHtml($definition['label']) ?>">
                                    <?php foreach ($definition['sources'] as $source): ?><option value="<?= astronomySourcesHtml($source) ?>"<?= ($definition['current'] ?? '') === $source ? ' selected' : '' ?>><?= astronomySourcesHtml($sourceLabels[$source] ?? $source) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="astronomy-sources-section" aria-labelledby="event-sources-title">
                    <div class="astronomy-sources-section__heading"><h3 id="event-sources-title">Eventos astronómicos</h3><p>Los grupos configurables conservan los modos database, PHP, auto y compare existentes.</p></div>
                    <div class="astronomy-sources-grid">
                        <?php foreach ($eventSources as $eventGroup => $definition): $configurable = ($definition['configurable'] ?? false) === true; ?>
                            <div class="astronomy-sources-row">
                                <div><label for="source-event-<?= astronomySourcesHtml($eventGroup) ?>"><?= astronomySourcesHtml($eventGroup) ?></label><small><?= astronomySourcesHtml($definition['label']) ?> · <?= astronomySourcesHtml($definition['fallback']) ?></small></div>
                                <select id="source-event-<?= astronomySourcesHtml($eventGroup) ?>"<?= $configurable ? ' name="sources[' . astronomySourcesHtml($eventGroup) . ']" data-astronomy-source-select' : ' disabled' ?> aria-label="Fuente para <?= astronomySourcesHtml($eventGroup) ?>">
                                    <?php foreach ($definition['sources'] as $source): ?>
                                        <option value="<?= astronomySourcesHtml($source) ?>"<?= ($definition['current'] ?? '') === $source ? ' selected' : '' ?>><?= astronomySourcesHtml($sourceLabels[$source] ?? $source) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <aside class="astronomy-sources-help">
                    <h3>Qué significa cada modo</h3>
                    <dl>
                        <dt>API</dt><dd>Servicio Python existente, con respaldo automático en las fuentes disponibles si el servicio falla.</dd>
                        <dt>Base de datos</dt><dd>Eventos precalculados en MariaDB.</dd>
                        <dt>PHP</dt><dd>Motor astronómico local, sin llamadas operativas obligatorias a la API.</dd>
                        <dt>Colección precalculada</dt><dd>Biblioteca local de 404 imágenes lunares; no requiere GD en runtime.</dd>
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
