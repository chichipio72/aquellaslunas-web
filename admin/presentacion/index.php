<?php

declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/event-type-configuration.php';
require_once __DIR__ . '/../../includes/asset-url.php';
require_once __DIR__ . '/../../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

function eventAdminHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$notice = isset($_GET['saved']) ? 'La presentación de los tipos de eventos se guardó correctamente.' : '';
try {
    $connection = getWebDatabaseConnection();
    astronomyEventTypeInitialize($connection);
    $rows = astronomyEventTypeAdminRows($connection);
} catch (Throwable $exception) {
    error_log('Aquellas Lunas event presentation admin load error: ' . $exception->getMessage());
    $connection = null;
    $rows = [];
    $errors[] = 'No se pudo preparar la configuración de tipos de eventos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($connection === null) {
        $errors[] = 'No hay conexión disponible para guardar cambios.';
    } elseif (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión expiró o el token CSRF no es válido. Recargá la página.';
    } else {
        try {
            $posted = is_array($_POST['types'] ?? null) ? $_POST['types'] : [];
            $knownIds = array_map(static fn(array $row): int => (int) $row['id'], $rows);
            foreach (array_keys($posted) as $postedId) {
                if (filter_var($postedId, FILTER_VALIDATE_INT) === false || !in_array((int) $postedId, $knownIds, true)) {
                    throw new InvalidArgumentException('Se recibió un tipo de evento desconocido.');
                }
            }
            $updates = [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $input = is_array($posted[$id] ?? null) ? $posted[$id] : [];
                $updates[$id] = [
                    'name' => (string) ($input['name'] ?? ''),
                    'enabled' => isset($input['enabled']),
                    'relevant_tonight' => isset($input['relevant_tonight']),
                    'surfaces' => array_values(is_array($input['surfaces'] ?? null) ? $input['surfaces'] : []),
                ];
            }
            astronomyEventTypeUpdate($connection, $updates);
            header('Location: index.php?saved=1', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Aquellas Lunas event presentation admin save error: ' . $exception->getMessage());
            $errors[] = 'No se pudo guardar la configuración de tipos de eventos.';
        }
    }
}

$grouped = [];
foreach ($rows as $row) {
    $grouped[(string) $row['category_key']][] = $row;
}
$categories = astronomyEventCategoryCatalog();
$surfaces = astronomyEventSurfaceCatalog();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Presentación del sitio · Aquellas Lunas</title>
    <?php renderFaviconLinks('../../'); ?>
    <link rel="stylesheet" href="<?= eventAdminHtml('../../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= eventAdminHtml('../../' . versionedAssetUrl('assets/css/admin-presentation.css')) ?>">
</head>
<body class="store-admin">
<?php renderStoreAdminNavigation('presentation', 'Visibilidad de eventos'); ?>
<main class="store-admin-main presentation-admin">
    <section class="card presentation-admin__intro">
        <p class="eyebrow">PRESENTACIÓN EDITORIAL</p>
        <h2>Tipos de eventos</h2>
        <p>Elegí qué tipos de eventos puede publicar la web y el nombre base con el que se presentan. Los cálculos astronómicos y los datos de la API no se modifican.</p>
        <p class="presentation-admin__tip">Las listas públicas siguen ordenándose cronológicamente. Los eventos se consideran de arriba hacia abajo únicamente cuando una selección futura necesite elegir entre varios.</p>
        <nav class="presentation-admin__areas" aria-label="Áreas de presentación"><a aria-current="page" href="index.php">Tipos de eventos</a><a href="reglas.php">Reglas y mensajes</a></nav>
    </section>

    <?php if ($notice !== ''): ?><p class="status-info presentation-admin__status" role="status"><?= eventAdminHtml($notice) ?></p><?php endif; ?>
    <?php if ($errors !== []): ?><section class="api-error-notice presentation-admin__status" role="alert"><h2>No se pudo completar la operación</h2><ul><?php foreach ($errors as $error): ?><li><?= eventAdminHtml($error) ?></li><?php endforeach; ?></ul></section><?php endif; ?>

    <?php if ($rows !== []): ?>
    <form method="post" class="presentation-admin__form">
        <input type="hidden" name="csrf_token" value="<?= eventAdminHtml(storeAdminCsrfToken()) ?>">
        <?php foreach ($categories as $categoryKey => $categoryLabel): ?>
            <?php if (($grouped[$categoryKey] ?? []) === []) continue; ?>
            <details class="presentation-category">
                <summary id="category-<?= eventAdminHtml($categoryKey) ?>"><h3><?= eventAdminHtml($categoryLabel) ?></h3><span class="presentation-category__indicator" aria-hidden="true"></span></summary>
                <div class="presentation-category__content">
                <div class="presentation-category__items">
                <?php foreach ($grouped[$categoryKey] as $index => $row): ?>
                    <?php $id = (int) $row['id']; $enabled = (int) $row['habilitado'] === 1; $selectedSurfaces = is_array($row['surfaces'] ?? null) ? $row['surfaces'] : []; ?>
                    <details class="presentation-event">
                        <summary><span><?= eventAdminHtml($row['nombre_amigable']) ?></span><small><?= $enabled ? 'Se muestra' : 'Oculto' ?></small></summary>
                        <div class="presentation-event__body">
                            <label class="presentation-event__name">Nombre mostrado<input type="text" name="types[<?= $id ?>][name]" value="<?= eventAdminHtml($row['nombre_amigable']) ?>" maxlength="150" required></label>
                            <label class="presentation-event__enabled"><input type="checkbox" name="types[<?= $id ?>][enabled]" value="1"<?= $enabled ? ' checked' : '' ?>><span>Mostrar este evento</span></label>
                            <?php $relevantTonight = $row['relevante_esta_noche'] !== null ? (int) $row['relevante_esta_noche'] === 1 : (($row['relevantTonight'] ?? false) === true); ?>
                            <label class="presentation-event__enabled"><input type="checkbox" name="types[<?= $id ?>][relevant_tonight]" value="1"<?= $relevantTonight ? ' checked' : '' ?>><span>Destacar en El cielo esta noche</span></label>
                            <fieldset>
                                <legend>Dónde mostrarlo</legend>
                                <div class="presentation-event__surfaces">
                                <?php foreach ($surfaces as $surfaceKey => $surfaceLabel): ?>
                                    <label><input type="checkbox" name="types[<?= $id ?>][surfaces][]" value="<?= eventAdminHtml($surfaceKey) ?>"<?= in_array($surfaceKey, $selectedSurfaces, true) ? ' checked' : '' ?>><span><?= eventAdminHtml($surfaceLabel) ?></span></label>
                                <?php endforeach; ?>
                                </div>
                            </fieldset>
                        </div>
                    </details>
                <?php endforeach; ?>
                </div>
                </div>
            </details>
        <?php endforeach; ?>
        <div class="presentation-admin__actions"><button class="button button-primary" type="submit">Guardar presentación</button><a class="button compact-secondary-button" href="../index.php">Volver al panel</a></div>
    </form>
    <?php endif; ?>
</main>
</body>
</html>
