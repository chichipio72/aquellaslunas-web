<?php
declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/web-database.php';
require_once __DIR__ . '/../../includes/photography-editorial.php';
require_once __DIR__ . '/../../includes/photography-editorial-json.php';
require_once __DIR__ . '/../../includes/photography-simulation.php';
require_once __DIR__ . '/../../includes/photography-reference-images.php';
require_once __DIR__ . '/../../includes/asset-url.php';
require_once __DIR__ . '/../../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

function photographyAdminHtml(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

function photographyAdminImageStoragePath(array $image): string
{
    return (string) $image['path'];
}

function photographyAdminSceneId(array $catalog, mixed $requestedId): ?int
{
    $sceneId = filter_var($requestedId, FILTER_VALIDATE_INT);
    foreach ($catalog as $scene) {
        if ($sceneId !== false && (int) $scene['id'] === $sceneId) return $sceneId;
    }
    return isset($catalog[0]['id']) ? (int) $catalog[0]['id'] : null;
}

function photographyAdminRenderImageSelector(string $name, ?string $selectedPath, string $label, array $gallery): void
{
    $selectedPath = trim((string) $selectedPath);
    $current = null;
    foreach ($gallery as $image) {
        if (photographyAdminImageStoragePath($image) === $selectedPath) { $current = $image; break; }
    }
    $hasSelection = $selectedPath !== '';
    $hasImage = $hasSelection && is_file(__DIR__ . '/../../' . $selectedPath);
    $previewUrl = $hasImage ? '../../' . versionedAssetUrl($selectedPath) : '';
    $cameraModel = is_array($current['exif'] ?? null) ? trim((string) ($current['exif']['camera_model'] ?? '')) : '';
    ?>
    <div class="editor-image-field photography-admin-image" data-image-selector>
        <span class="editor-image-field__label"><?= photographyAdminHtml($label) ?></span>
        <input type="hidden" name="<?= photographyAdminHtml($name) ?>" value="<?= photographyAdminHtml($selectedPath) ?>" data-image-value>
        <div class="editor-image-current" data-image-current<?= $hasImage ? '' : ' hidden' ?>>
            <img src="<?= photographyAdminHtml($previewUrl) ?>" alt="" loading="lazy" data-image-preview<?= $hasImage ? '' : ' hidden' ?>>
            <code data-image-name><?= photographyAdminHtml($current['filename'] ?? $selectedPath) ?></code>
            <small data-image-camera<?= $cameraModel !== '' ? '' : ' hidden' ?>>Cámara: <span><?= photographyAdminHtml($cameraModel) ?></span></small>
        </div>
        <div class="editor-image-actions">
            <button class="editor-button editor-button--quiet" type="button" data-image-choose><?= $hasSelection ? 'Cambiar imagen' : 'Elegir imagen' ?></button>
            <button class="editor-button editor-button--quiet" type="button" data-image-remove<?= $hasSelection ? '' : ' disabled' ?>>Quitar imagen</button>
        </div>
    </div>
    <?php
}

$errors = [];
$adminSection = ($_GET['section'] ?? '') === 'simulation' || isset($_GET['simulation_saved']) || ($_POST['mode'] ?? '') === 'photography_simulation' ? 'simulation' : 'editorial';
$replaceImportNotice = isset($_GET['imported']) && ($_GET['import_mode'] ?? '') === 'replace'
    ? 'Catálogo reemplazado: ' . (int) ($_GET['scenes'] ?? 0) . ' escenas y ' . (int) ($_GET['variants'] ?? 0) . ' variantes creadas.'
    : '';
$notice = isset($_GET['simulation_saved'])
    ? 'La calibración del modo Simulado se guardó correctamente.'
    : (isset($_GET['saved'])
    ? 'Los datos editoriales se guardaron correctamente.'
    : (isset($_GET['imported']) ? ($replaceImportNotice !== '' ? $replaceImportNotice : 'La configuración editorial se importó completamente.') : ''));
$importJson = '';
$importValidation = null;
$importMode = ($_POST['package_mode'] ?? '') === 'replace' ? 'replace' : 'merge';
try {
    $connection = getWebDatabaseConnection();
    photographyEditorialInitialize($connection);
} catch (Throwable $exception) {
    error_log('Photography admin init: ' . $exception->getMessage());
    $connection = null;
    $errors[] = 'No se pudo preparar el modelo editorial de Fotografía.';
}

$catalog = $connection instanceof PDO ? photographyEditorialCatalog($connection) : [];
if (($_GET['action'] ?? '') === 'export') {
    if (!$connection instanceof PDO) {
        http_response_code(503);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'No hay conexión disponible.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="fotografia-editorial.json"');
    echo photographyEditorialExportJson($catalog);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedSceneId = filter_var($_POST['selected_scene_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$connection instanceof PDO) $errors[] = 'No hay conexión disponible.';
    elseif (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) $errors[] = 'La sesión expiró. Recargá la página.';
    elseif (($_POST['mode'] ?? '') === 'photography_simulation') {
        try {
            $updates = [];
            foreach (photographySimulationDefinitions() as $key => $definition) {
                $shortKey = substr($key, strlen('photography.simulated.'));
                $updates[$key] = ($definition['type'] ?? '') === 'boolean'
                    ? ($_POST['simulation'][$shortKey] ?? '0')
                    : ($_POST['simulation'][$shortKey] ?? $definition['default']);
            }
            $normalized = [];
            foreach ($updates as $key => $value) $normalized[$key] = astronomySiteConfigNormalizeValue($key, $value);
            $nightThreshold = (float) $normalized['photography.simulated.night_transition_altitude'];
            $twilightThreshold = (float) $normalized['photography.simulated.twilight_center_altitude'];
            $dayThreshold = (float) $normalized['photography.simulated.day_transition_altitude'];
            if (!($nightThreshold < $twilightThreshold && $twilightThreshold < $dayThreshold)) {
                throw new InvalidArgumentException('Los umbrales solares deben mantener el orden: noche < máximo de crepúsculo < día.');
            }
            if ((float) $normalized['photography.simulated.moon_low_to_high_start_altitude'] >= (float) $normalized['photography.simulated.moon_low_to_high_end_altitude']) {
                throw new InvalidArgumentException('El inicio de transición lunar debe ser menor que el fin de transición a Luna alta.');
            }
            astronomySiteConfigUpdateValues($connection, $updates);
            $savedProfile = (string) ($_POST['simulation_profile'] ?? 'day_high');
            if (!in_array($savedProfile, ['day_high', 'day_low', 'twilight_high', 'twilight_low', 'twilight_low_antisolar', 'night_high', 'night_low'], true)) $savedProfile = 'day_high';
            header('Location: index.php?section=simulation&simulation_saved=1&profile=' . rawurlencode($savedProfile), true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Photography simulation admin save: ' . $exception->getMessage());
            $errors[] = 'No se pudo guardar la calibración del modo Simulado.';
        }
    }
    elseif (($_POST['mode'] ?? '') === 'photography_import') {
        $importJson = (string) ($_POST['package_json'] ?? '');
        $upload = $_FILES['package_file'] ?? null;
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'No se pudo leer el archivo JSON seleccionado.';
            } elseif ((int) ($upload['size'] ?? 0) > 2 * 1024 * 1024) {
                $errors[] = 'El archivo JSON supera el máximo de 2 MB.';
            } else {
                $uploadedJson = file_get_contents((string) ($upload['tmp_name'] ?? ''));
                if (!is_string($uploadedJson)) $errors[] = 'No se pudo leer el archivo JSON seleccionado.';
                else $importJson = $uploadedJson;
            }
        }
        if ($errors === []) {
            $importValidation = photographyEditorialValidateJson($importJson, $importMode);
            if ($importValidation['valid']) {
                $databaseErrors = $importMode === 'merge' ? photographyEditorialValidateImportAgainstDatabase($connection, $importValidation['data']) : [];
                if ($importMode === 'replace' && ($importValidation['data']['scenes'] ?? []) === []) $databaseErrors[] = photographyEditorialJsonError('scenes', 'Reemplazar todo requiere al menos una escena para dejar el catálogo utilizable.');
                if ($databaseErrors !== []) {
                    $importValidation['valid'] = false;
                    $importValidation['summary'] = null;
                    $importValidation['errors'] = array_merge($importValidation['errors'], $databaseErrors);
                }
            }
            if (!$importValidation['valid']) {
                $errors = array_merge($errors, $importValidation['errors']);
            } elseif (($_POST['package_action'] ?? '') === 'import') {
                try {
                    if ($importMode === 'replace') {
                        if (($_POST['confirm_replace'] ?? '') !== '1') throw new InvalidArgumentException('Confirmá explícitamente que querés reemplazar todo el catálogo editorial.');
                        $result = photographyEditorialReplacePackage($connection, $importValidation['data']);
                        header('Location: index.php?imported=1&import_mode=replace&scenes=' . $result['scenes'] . '&variants=' . $result['variants'] . '&scene=' . $result['selected_scene_id'], true, 303);
                    } else {
                        photographyEditorialImportPackage($connection, $importValidation['data']);
                        header('Location: index.php?imported=1', true, 303);
                    }
                    exit;
                } catch (Throwable $exception) {
                    error_log('Photography admin import: ' . $exception->getMessage());
                    $errors[] = 'No se pudo aplicar la importación. No se guardaron cambios parciales.';
                }
            }
        }
    }
    else {
        try {
            photographyEditorialUpdate($connection, $_POST);
            $location = 'index.php?saved=1' . ($postedSceneId !== false ? '&scene=' . $postedSceneId : '');
            header('Location: ' . $location, true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Photography admin save: ' . $exception->getMessage());
            $errors[] = 'No se pudieron guardar los cambios.';
        }
    }
}

$catalog = $connection instanceof PDO ? photographyEditorialCatalog($connection) : [];
$selectedSceneId = photographyAdminSceneId($catalog, $_POST['selected_scene_id'] ?? $_GET['scene'] ?? null);
$selectedScene = null;
foreach ($catalog as $scene) {
    if ((int) $scene['id'] === $selectedSceneId) { $selectedScene = $scene; break; }
}
$imageGallery = photographyReferenceImageCatalog();
$simulationDefinitions = photographySimulationDefinitions();
$simulationValues = photographySimulationConfig($connection instanceof PDO ? static fn(): PDO => $connection : null);
$simulationMoonDefinitions = array_filter($simulationDefinitions, static fn(array $definition): bool => isset($definition['moon_profile']));
$simulationHaloKeys = ['photography.simulated.moon_halo_enabled', 'photography.simulated.moon_halo_max_intensity', 'photography.simulated.moon_halo_radius', 'photography.simulated.moon_halo_phase_start_percent'];
$simulationSkyThresholdKeys = ['photography.simulated.night_transition_altitude', 'photography.simulated.twilight_center_altitude', 'photography.simulated.day_transition_altitude'];
$simulationMoonThresholdKeys = ['photography.simulated.moon_low_to_high_start_altitude', 'photography.simulated.moon_low_to_high_end_altitude'];
$simulationAtmosphereThresholdKeys = ['photography.simulated.atmosphere_horizon_altitude', 'photography.simulated.atmosphere_clear_altitude'];
$simulationSkyThresholdDefinitions = array_intersect_key($simulationDefinitions, array_flip($simulationSkyThresholdKeys));
$simulationMoonThresholdDefinitions = array_intersect_key($simulationDefinitions, array_flip($simulationMoonThresholdKeys));
$simulationAtmosphereThresholdDefinitions = array_intersect_key($simulationDefinitions, array_flip($simulationAtmosphereThresholdKeys));
$simulationHaloDefinitions = array_intersect_key($simulationDefinitions, array_flip($simulationHaloKeys));
$simulationAtmosphereDefinitions = array_filter($simulationDefinitions, static fn(array $definition, string $key): bool => str_contains($key, '.atmosphere_') && !in_array($key, $simulationAtmosphereThresholdKeys, true), ARRAY_FILTER_USE_BOTH);
$simulationSkyDefinitions = array_diff_key($simulationDefinitions, $simulationMoonDefinitions, $simulationHaloDefinitions, $simulationSkyThresholdDefinitions, $simulationMoonThresholdDefinitions, $simulationAtmosphereThresholdDefinitions, $simulationAtmosphereDefinitions);
$simulationMoonProfiles = [];
foreach ($simulationMoonDefinitions as $key => $definition) {
    $profile = (string) ($definition['moon_profile'] ?? '');
    if ($profile !== '') $simulationMoonProfiles[$profile][$key] = $definition;
}
$simulationProfilePreviewUrls = [
    'day_high' => '../../fotografia.php?mode=simulated&sensor=full_frame&focal=1200&aim=moon&date=2026-08-20&time=17:00&lat=-34.6037&lon=-58.3816',
    'day_low' => '../../fotografia.php?mode=simulated&sensor=full_frame&focal=1200&aim=moon&date=2026-08-20&time=13:00&lat=-34.6037&lon=-58.3816',
    'twilight_high' => '../../fotografia.php?mode=simulated&sensor=full_frame&focal=1200&aim=moon&date=2026-08-20&time=19:15&lat=-34.6037&lon=-58.3816',
    'twilight_low' => '../../fotografia.php?mode=simulated&sensor=full_frame&focal=1200&aim=moon&date=2026-08-13&time=18:45&lat=-34.6037&lon=-58.3816',
    'twilight_low_antisolar' => '../../fotografia.php?mode=simulated&sensor=full_frame&focal=1200&aim=moon&horizon=1&date=2026-08-28&time=07:25&lat=-34.6037&lon=-58.3816',
    'night_high' => '../../fotografia.php?mode=simulated&sensor=full_frame&focal=1200&aim=moon&date=2026-08-29&time=01:00&lat=-34.6037&lon=-58.3816',
    'night_low' => '../../fotografia.php?mode=simulated&sensor=full_frame&focal=1200&aim=moon&date=2026-08-28&time=20:00&lat=-34.6037&lon=-58.3816',
];
$requestedSimulationProfile = (string) ($_GET['profile'] ?? $_POST['simulation_profile'] ?? 'day_high');
$initialSimulationProfile = array_key_exists($requestedSimulationProfile, $simulationMoonProfiles) ? $requestedSimulationProfile : 'day_high';
$initialSimulationPreviewUrl = $simulationProfilePreviewUrls[$initialSimulationProfile];
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive">
<title>Fotografía · Administración</title><?php renderFaviconLinks('../../'); ?>
<link rel="stylesheet" href="<?= photographyAdminHtml('../../' . versionedAssetUrl('assets/css/styles.css')) ?>">
<link rel="stylesheet" href="<?= photographyAdminHtml('../../' . versionedAssetUrl('assets/css/photography.css')) ?>">
<link rel="stylesheet" href="<?= photographyAdminHtml('../../' . versionedAssetUrl('admin/contenidos/editor.css')) ?>">
<link rel="stylesheet" href="<?= photographyAdminHtml('../../' . versionedAssetUrl('admin/fotografia/admin.css')) ?>">
</head><body class="store-admin">
<?php renderStoreAdminNavigation('photography', 'Fotografía'); ?>
<main class="store-admin-main">
<section class="store-admin-dashboard__intro"><p class="eyebrow">Fotografía</p><h2>Escenas, variantes y simulación</h2><p>Las configuraciones editoriales son referencias; la calibración visual modifica únicamente el modo Simulado.</p></section>
<nav class="photography-admin-sections" aria-label="Secciones de Fotografía"><a href="?section=editorial"<?= $adminSection === 'editorial' ? ' class="is-active" aria-current="page"' : '' ?>>Modelo editorial</a><a href="?section=simulation"<?= $adminSection === 'simulation' ? ' class="is-active" aria-current="page"' : '' ?>>Simulación</a></nav>
<?php if ($notice !== ''): ?><p class="card photography-warning" role="status"><?= photographyAdminHtml($notice) ?></p><?php endif; ?>
<?php foreach ($errors as $error): ?><?php $errorText = is_array($error) ? (($error['field'] ?? 'json') . ': ' . ($error['message'] ?? 'Error de validación.')) : $error; ?><p class="card photography-warning" role="alert"><?= photographyAdminHtml($errorText) ?></p><?php endforeach; ?>

<?php if ($adminSection === 'simulation'): ?>
<section class="card photography-admin-simulation" id="simulado">
<header><p class="eyebrow">Simulación</p><h2>Calibración del cielo</h2><p>Estos parámetros modifican únicamente la apariencia del modo Simulado.</p></header>
<div class="photography-admin-simulation__body">
    <form method="post" class="photography-controls photography-admin-simulation__form">
        <input type="hidden" name="csrf_token" value="<?= photographyAdminHtml(storeAdminCsrfToken()) ?>"><input type="hidden" name="mode" value="photography_simulation">
        <fieldset class="photography-admin-simulation__group"><legend>Colores y apariencia del cielo</legend>
            <div class="photography-admin-simulation__colors">
            <?php foreach ($simulationSkyDefinitions as $key => $definition): ?><?php if (($definition['type'] ?? '') === 'color'): $shortKey = substr($key, strlen('photography.simulated.')); ?><label><span><?= photographyAdminHtml($definition['label']) ?></span><input type="color" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"></label><?php endif; ?><?php endforeach; ?>
            </div>
            <div class="photography-control-grid">
            <?php foreach ($simulationSkyDefinitions as $key => $definition): ?><?php if (($definition['type'] ?? '') === 'decimal'): $shortKey = substr($key, strlen('photography.simulated.')); ?><label><span><?= photographyAdminHtml($definition['label']) ?></span><input type="number" step="0.005" min="<?= photographyAdminHtml($definition['min']) ?>" max="<?= photographyAdminHtml($definition['max']) ?>" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"><small><?= photographyAdminHtml($definition['description']) ?></small></label><?php endif; ?><?php endforeach; ?>
            </div>
        </fieldset>
        <fieldset class="photography-admin-simulation__group"><legend>Umbrales de interpolación</legend>
            <p class="photography-admin-simulation__hint">Definen dónde empieza y termina cada mezcla continua. No agregan perfiles ni modifican la geometría astronómica.</p>
            <section class="photography-admin-simulation__thresholds"><h3>A. Umbrales del cielo</h3><div class="photography-control-grid">
            <?php foreach ($simulationSkyThresholdDefinitions as $key => $definition): ?><?php $shortKey = substr($key, strlen('photography.simulated.')); ?><label><span><?= photographyAdminHtml($definition['label']) ?></span><input type="number" step="0.005" min="<?= photographyAdminHtml($definition['min']) ?>" max="<?= photographyAdminHtml($definition['max']) ?>" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"><small><?= photographyAdminHtml($definition['description']) ?></small></label><?php endforeach; ?>
            </div></section>
            <section class="photography-admin-simulation__thresholds"><h3>B. Umbrales lunares</h3><div class="photography-control-grid">
            <?php foreach ($simulationMoonThresholdDefinitions as $key => $definition): ?><?php $shortKey = substr($key, strlen('photography.simulated.')); ?><label><span><?= photographyAdminHtml($definition['label']) ?></span><input type="number" step="0.005" min="<?= photographyAdminHtml($definition['min']) ?>" max="<?= photographyAdminHtml($definition['max']) ?>" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"><small><?= photographyAdminHtml($definition['description']) ?></small></label><?php endforeach; ?>
            </div></section>
            <section class="photography-admin-simulation__thresholds"><h3>C. Umbrales de atmósfera lunar</h3><div class="photography-control-grid">
            <?php foreach ($simulationAtmosphereThresholdDefinitions as $key => $definition): ?><?php $shortKey = substr($key, strlen('photography.simulated.')); ?><label><span><?= photographyAdminHtml($definition['label']) ?></span><input type="number" step="0.005" min="<?= photographyAdminHtml($definition['min']) ?>" max="<?= photographyAdminHtml($definition['max']) ?>" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"><small><?= photographyAdminHtml($definition['description']) ?></small></label><?php endforeach; ?>
            </div></section>
        </fieldset>
        <fieldset class="photography-admin-simulation__group"><legend>Atmósfera lunar · apariencia</legend>
            <p class="photography-admin-simulation__hint">Capa continua por altura lunar. Modula los perfiles existentes sin simular meteorología.</p>
            <div class="photography-admin-simulation__colors">
            <?php foreach ($simulationAtmosphereDefinitions as $key => $definition): ?><?php if (($definition['type'] ?? '') === 'color'): $shortKey = substr($key, strlen('photography.simulated.')); ?><label><span><?= photographyAdminHtml($definition['label']) ?></span><input type="color" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"></label><?php endif; ?><?php endforeach; ?>
            </div>
            <div class="photography-control-grid">
            <?php foreach ($simulationAtmosphereDefinitions as $key => $definition): ?><?php if (($definition['type'] ?? '') === 'decimal'): $shortKey = substr($key, strlen('photography.simulated.')); ?><label><span><?= photographyAdminHtml($definition['label']) ?></span><input type="number" step="0.005" min="<?= photographyAdminHtml($definition['min']) ?>" max="<?= photographyAdminHtml($definition['max']) ?>" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"><small><?= photographyAdminHtml($definition['description']) ?></small></label><?php endif; ?><?php endforeach; ?>
            </div>
        </fieldset>
        <fieldset class="photography-admin-simulation__group"><legend>Resplandor lunar</legend>
            <p class="photography-admin-simulation__hint">Capa difusa detrás de la Luna 3D. Su intensidad depende continuamente de fase y cielo, y su distribución sigue el lado realmente iluminado.</p>
            <input type="hidden" name="simulation[moon_halo_enabled]" value="0">
            <label class="photography-admin-simulation__switch"><span><strong>Resplandor lunar activo</strong><small>Al desactivarlo se omite completamente la capa y se recupera el render anterior.</small></span><input type="checkbox" name="simulation[moon_halo_enabled]" value="1"<?= ($simulationValues['moon_halo_enabled'] ?? true) === true ? ' checked' : '' ?>><span class="photography-admin-simulation__switch-control" aria-hidden="true"></span></label>
            <div class="photography-control-grid">
            <?php foreach ($simulationHaloDefinitions as $key => $definition): ?><?php if (($definition['type'] ?? '') === 'decimal'): $shortKey = substr($key, strlen('photography.simulated.')); ?><label><span><?= photographyAdminHtml($definition['label']) ?></span><input type="number" step="0.005" min="<?= photographyAdminHtml($definition['min']) ?>" max="<?= photographyAdminHtml($definition['max']) ?>" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"><small><?= photographyAdminHtml($definition['description']) ?></small></label><?php endif; ?><?php endforeach; ?>
            </div>
        </fieldset>
        <fieldset class="photography-admin-simulation__group"><legend>Luna 3D · perfiles</legend>
            <p class="photography-admin-simulation__hint">Cada bloque es un ancla. La apariencia pública interpola continuamente por altura del Sol y de la Luna.</p>
            <label class="photography-admin-moon-profile-selector"><span>Configuración lunar</span><select name="simulation_profile" data-moon-profile-selector><?php foreach ($simulationMoonProfiles as $profile => $definitions): ?><?php $firstDefinition = reset($definitions); $profileLabel = preg_replace('/ · [^·]+$/u', '', (string) ($firstDefinition['label'] ?? $profile)); ?><option value="<?= photographyAdminHtml($profile) ?>" data-preview-url="<?= photographyAdminHtml($simulationProfilePreviewUrls[$profile] ?? '') ?>"<?= $profile === $initialSimulationProfile ? ' selected' : '' ?>><?= photographyAdminHtml($profileLabel) ?></option><?php endforeach; ?></select></label>
            <div class="photography-admin-moon-profiles">
            <?php foreach ($simulationMoonProfiles as $profile => $definitions): ?><?php $firstDefinition = reset($definitions); $profileLabel = preg_replace('/ · [^·]+$/u', '', (string) ($firstDefinition['label'] ?? $profile)); ?>
                <section class="photography-admin-moon-profile" data-moon-profile="<?= photographyAdminHtml($profile) ?>"<?= $profile === $initialSimulationProfile ? '' : ' hidden' ?>><h3><?= photographyAdminHtml($profileLabel) ?></h3>
                <?php foreach ($definitions as $key => $definition): ?><?php $shortKey = substr($key, strlen('photography.simulated.')); $parameterLabel = preg_replace('/^.* · /u', '', (string) $definition['label']); ?>
                    <label><span><?= photographyAdminHtml($parameterLabel) ?></span><?php if (($definition['type'] ?? '') === 'color'): ?><input type="color" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"><?php else: ?><input type="number" step="0.005" min="<?= photographyAdminHtml($definition['min']) ?>" max="<?= photographyAdminHtml($definition['max']) ?>" name="simulation[<?= photographyAdminHtml($shortKey) ?>]" value="<?= photographyAdminHtml($simulationValues[$shortKey] ?? $definition['default']) ?>"><?php endif; ?></label>
                <?php endforeach; ?></section>
            <?php endforeach; ?>
            </div>
        </fieldset>
        <button class="button button-primary" type="submit">Guardar calibración</button>
    </form>
    <section class="photography-admin-simulation__preview"><div class="photography-admin-simulation__preview-controls"><h3>Vista previa astronómica</h3><p>La escena corresponde siempre a la configuración elegida en el desplegable. Los cambios visuales se aplican en tiempo real.</p><label><span>Focal de vista previa</span><input type="range" min="200" max="3000" step="10" value="1200" data-preview-focal><span><input type="number" min="200" max="3000" step="10" value="1200" inputmode="decimal" data-preview-focal-number> mm</span></label><small>Este zoom sólo facilita la inspección de la Luna; no se guarda ni modifica la configuración pública.</small></div><iframe name="photography-simulation-preview" title="Vista previa del modo Simulado" src="<?= photographyAdminHtml($initialSimulationPreviewUrl) ?>" loading="lazy" scrolling="no" tabindex="-1" data-simulation-preview></iframe></section>
</div>
</section>

<?php else: ?>
<section class="store-admin-dashboard__intro"><p class="eyebrow">Modelo editorial</p><h2>Escenas y variantes</h2><p>Las configuraciones son referencias editoriales: nunca recetas ni valores ideales.</p></section>

<details class="card photography-admin-transfer"<?= $importValidation !== null ? ' open' : '' ?>>
<summary>Importar / exportar JSON</summary>
<div class="photography-admin-transfer__body">
    <div class="photography-admin-transfer__intro">
        <div><h3>Configuración editorial</h3><p>Actualizar y agregar admite paquetes parciales: sólo modifica los campos presentes y conserva escenas, variantes y campos omitidos. Un valor <code>null</code> borra explícitamente ese campo. Reemplazar todo exige el catálogo completo.</p></div>
        <a class="editor-button editor-button--primary" href="?action=export">Exportar JSON</a>
    </div>
    <form class="editor-form editor-import-form photography-admin-import" method="post" enctype="multipart/form-data" data-photography-import>
        <input type="hidden" name="csrf_token" value="<?= photographyAdminHtml(storeAdminCsrfToken()) ?>">
        <input type="hidden" name="mode" value="photography_import">
        <fieldset class="photography-admin-import-mode"><legend>Modo de importación</legend><label><input type="radio" name="package_mode" value="merge"<?= $importMode === 'merge' ? ' checked' : '' ?>> <span><strong>Actualizar y agregar</strong><small>Conserva escenas y variantes que no estén en el JSON.</small></span></label><label><input type="radio" name="package_mode" value="replace"<?= $importMode === 'replace' ? ' checked' : '' ?>> <span><strong>Reemplazar todo</strong><small>Elimina el catálogo editorial actual antes de importar.</small></span></label></fieldset>
        <div class="photography-admin-replace-confirmation" data-replace-confirmation<?= $importMode === 'replace' ? '' : ' hidden' ?>><p>Esto eliminará todas las escenas y variantes actuales de Fotografía y las reemplazará por el contenido del JSON.</p><label><input type="checkbox" name="confirm_replace" value="1" data-confirm-replace> Confirmo que quiero reemplazar todo el catálogo editorial.</label></div>
        <label for="photography-package-file">Archivo JSON</label>
        <input id="photography-package-file" type="file" name="package_file" accept="application/json,.json" data-package-file>
        <label for="photography-package-json">Contenido JSON <small>También podés pegarlo o editarlo directamente.</small></label>
        <textarea id="photography-package-json" name="package_json" rows="14" spellcheck="false" data-package-json><?= photographyAdminHtml($importJson) ?></textarea>
        <div class="editor-import-actions">
            <button class="editor-button editor-button--quiet" type="submit" name="package_action" value="validate">Validar</button>
            <button class="editor-button editor-button--primary" type="submit" name="package_action" value="import" data-package-import-button<?= is_array($importValidation) && ($importValidation['valid'] ?? false) ? '' : ' disabled' ?>>Importar</button>
        </div>
    </form>
    <?php if (is_array($importValidation) && ($importValidation['valid'] ?? false) && is_array($importValidation['summary'] ?? null)): ?>
        <section class="editor-panel editor-import-summary"><h3>JSON válido, listo para importar</h3><dl><div><dt>Escenas</dt><dd><?= (int) $importValidation['summary']['scenes'] ?></dd></div><div><dt>Variantes</dt><dd><?= (int) $importValidation['summary']['variants'] ?></dd></div><div><dt>Modo</dt><dd><?= $importMode === 'replace' ? 'Reemplazar todo' : 'Actualizar y agregar' ?></dd></div></dl></section>
    <?php endif; ?>
</div>
</details>

<?php if ($catalog !== []): ?>
<form class="card photography-admin-selector" method="get" data-scene-selector-form>
    <label for="photography-admin-scene">Escena</label>
    <div><select id="photography-admin-scene" name="scene" data-scene-selector>
        <?php foreach ($catalog as $scene): ?><option value="<?= (int) $scene['id'] ?>"<?= (int) $scene['id'] === $selectedSceneId ? ' selected' : '' ?>><?= photographyAdminHtml($scene['title']) ?> · <?= photographyAdminHtml($scene['scene_key']) ?></option><?php endforeach; ?>
    </select><button class="editor-button editor-button--quiet" type="submit">Mostrar</button></div>
</form>
<?php endif; ?>

<?php if ($selectedScene !== null): ?>
<section class="card photography-admin-scene">
<form method="post" class="photography-controls photography-admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= photographyAdminHtml(storeAdminCsrfToken()) ?>"><input type="hidden" name="scene_id" value="<?= (int) $selectedScene['id'] ?>"><input type="hidden" name="selected_scene_id" value="<?= (int) $selectedScene['id'] ?>">
    <div class="photography-admin-heading photography-admin-full"><h3>Escena · <?= photographyAdminHtml($selectedScene['scene_key']) ?></h3><label><input type="checkbox" name="active" value="1"<?= $selectedScene['active'] ? ' checked' : '' ?>> Activa</label></div>
    <div class="photography-admin-column"><div class="photography-control-grid photography-admin-title-row"><label>Título<input name="title" value="<?= photographyAdminHtml($selectedScene['title']) ?>" required></label><label>Orden<input type="number" name="sort_order" value="<?= (int) $selectedScene['sort_order'] ?>"></label></div><label>Descripción<textarea name="description" rows="4" required><?= photographyAdminHtml($selectedScene['description']) ?></textarea></label></div>
    <div class="photography-admin-column"><?php photographyAdminRenderImageSelector('example_image_path', $selectedScene['example_image_path'], 'Imagen de ejemplo', $imageGallery); ?><label>Notas editoriales<textarea name="editorial_notes" rows="5"><?= photographyAdminHtml($selectedScene['editorial_notes']) ?></textarea></label></div>
    <div class="photography-admin-full"><button class="button button-primary" type="submit">Guardar escena</button></div>
</form>

<div class="photography-admin-variants">
<?php foreach ($selectedScene['variants'] as $variant): ?>
<form method="post" class="photography-controls photography-admin-variant photography-admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= photographyAdminHtml(storeAdminCsrfToken()) ?>"><input type="hidden" name="variant_id" value="<?= (int) $variant['id'] ?>"><input type="hidden" name="selected_scene_id" value="<?= (int) $selectedScene['id'] ?>">
    <div class="photography-admin-heading photography-admin-full"><h4>Variante · <?= photographyAdminHtml($variant['variant_key']) ?></h4><label><input type="checkbox" name="active" value="1"<?= $variant['active'] ? ' checked' : '' ?>> Activa</label></div>
    <div class="photography-admin-column"><div class="photography-control-grid photography-admin-title-row"><label>Título<input name="title" value="<?= photographyAdminHtml($variant['title']) ?>" required></label><label>Orden<input type="number" name="sort_order" value="<?= (int) $variant['sort_order'] ?>"></label></div><label>Descripción<textarea name="description" rows="4" required><?= photographyAdminHtml($variant['description']) ?></textarea></label><div class="photography-control-grid photography-admin-camera-fields"><label>Focal de referencia (mm)<input type="number" step="0.01" name="reference_focal_mm" value="<?= photographyAdminHtml($variant['reference_focal_mm']) ?>"></label><label>Apertura<input name="aperture" value="<?= photographyAdminHtml($variant['aperture']) ?>"></label><label>Velocidad<input name="shutter_speed" value="<?= photographyAdminHtml($variant['shutter_speed']) ?>"></label><label>ISO<input name="iso_value" value="<?= photographyAdminHtml($variant['iso_value']) ?>"></label></div></div>
    <div class="photography-admin-column"><?php photographyAdminRenderImageSelector('example_image_path', $variant['example_image_path'], 'Imagen de ejemplo', $imageGallery); ?><div class="photography-admin-phone-row"><label>Nota para celular<textarea name="phone_note" rows="4"><?= photographyAdminHtml($variant['phone_note']) ?></textarea></label><label>Viabilidad con celular<select name="phone_viability"><option value="no"<?= $variant['phone_viability'] === 'no' ? ' selected' : '' ?>>No</option><option value="limited"<?= $variant['phone_viability'] === 'limited' ? ' selected' : '' ?>>Limitada</option><option value="good"<?= $variant['phone_viability'] === 'good' ? ' selected' : '' ?>>Buena</option></select></label></div><label>Notas editoriales<textarea name="editorial_notes" rows="5"><?= photographyAdminHtml($variant['editorial_notes']) ?></textarea></label></div>
    <div class="photography-admin-full photography-admin-save-row"><p class="photography-note">Una posible configuración; no una configuración correcta o ideal.</p><button class="button button-primary" type="submit">Guardar variante</button></div>
</form>
<?php endforeach; ?>
</div></section>
<?php elseif ($catalog === [] && $errors === []): ?><p class="card photography-warning">No hay escenas disponibles.</p><?php endif; ?>

<dialog class="editor-image-dialog" data-image-dialog aria-labelledby="photography-image-dialog-title">
<div class="editor-image-dialog__heading"><h2 id="photography-image-dialog-title">Elegir imagen</h2><button class="editor-button editor-button--quiet" type="button" data-image-close>Cerrar</button></div>
<p<?= $imageGallery !== [] ? ' hidden' : '' ?>>No hay imágenes válidas disponibles.</p><div class="editor-image-gallery">
<?php foreach ($imageGallery as $image): ?><?php $imageExif = is_array($image['exif'] ?? null) ? $image['exif'] : []; ?><button class="editor-image-option" type="button" data-image-option="<?= photographyAdminHtml(photographyAdminImageStoragePath($image)) ?>" data-image-url="../../<?= photographyAdminHtml(versionedAssetUrl($image['url'])) ?>" data-image-name="<?= photographyAdminHtml($image['filename']) ?>" data-exif-focal="<?= photographyAdminHtml($imageExif['focal'] ?? '') ?>" data-exif-aperture="<?= photographyAdminHtml($imageExif['aperture'] ?? '') ?>" data-exif-shutter="<?= photographyAdminHtml($imageExif['shutter_speed'] ?? '') ?>" data-exif-iso="<?= photographyAdminHtml($imageExif['iso'] ?? '') ?>" data-exif-camera="<?= photographyAdminHtml($imageExif['camera_model'] ?? '') ?>" aria-pressed="false"><img src="../../<?= photographyAdminHtml(versionedAssetUrl($image['url'])) ?>" alt="" loading="lazy"><code><?= photographyAdminHtml($image['filename']) ?></code><span class="editor-image-option__format"><?= photographyAdminHtml($image['format_label']) ?></span><?php if (($imageExif['camera_model'] ?? '') !== ''): ?><small><?= photographyAdminHtml($imageExif['camera_model']) ?></small><?php endif; ?><span data-image-selected-label hidden>Seleccionada</span></button><?php endforeach; ?>
</div></dialog>
<?php endif; ?>
</main>
<script src="../../<?= photographyAdminHtml(versionedAssetUrl('admin/fotografia/admin.js')) ?>" defer></script>
</body></html>
