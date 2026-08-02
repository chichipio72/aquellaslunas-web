<?php

if (!defined('STORE_ADMIN_LOGIN_PATH')) {
    define('STORE_ADMIN_LOGIN_PATH', '../login.php');
}

require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/asset-url.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

require_once __DIR__ . '/editor-core.php';

function editorHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function editorFieldErrors(array $errors, string $prefix): void
{
    $matches = array_filter($errors, static function (array $error) use ($prefix): bool {
        $field = (string) ($error['field'] ?? '');
        return $field === $prefix || str_starts_with($field, $prefix . '.');
    });
    if ($matches === []) {
        return;
    }
    ?><ul class="editor-field-errors"><?php foreach ($matches as $error): ?><li><?= editorHtml($error['message'] ?? 'Error') ?></li><?php endforeach; ?></ul><?php
}

function editorHasErrors(array $errors, string $prefix): bool
{
    return count(array_filter(
        $errors,
        static function (array $error) use ($prefix): bool {
            $field = (string) ($error['field'] ?? '');
            return $field === $prefix || str_starts_with($field, $prefix . '.');
        }
    )) > 0;
}

function editorTabErrorCounts(array $errors): array
{
    $counts = ['content' => 0, 'facts' => 0, 'trivias' => 0];
    foreach ($errors as $error) {
        $field = (string) ($error['field'] ?? '');
        $tab = str_starts_with($field, 'sabias_que.') || $field === 'sabias_que'
            ? 'facts'
            : (str_starts_with($field, 'trivias.') || $field === 'trivias' ? 'trivias' : 'content');
        $counts[$tab]++;
    }
    return $counts;
}

function renderEditorImageSelector(string $name, ?string $selected, string $label, array $gallery): void
{
    $available = [];
    foreach ($gallery as $image) {
        $available[$image['filename']] = $image;
    }
    $current = $selected !== null && isset($available[$selected]) ? $available[$selected] : null;
    ?>
    <div class="editor-image-field" data-image-selector data-image-filter="vertical">
        <span class="editor-image-field__label"><?= editorHtml($label) ?></span>
        <input type="hidden" name="<?= editorHtml($name) ?>" value="<?= editorHtml($current['filename'] ?? '') ?>" data-image-value>
        <div class="editor-image-current">
            <img src="<?= $current !== null ? '../../' . editorHtml(versionedAssetUrl($current['url'])) : '' ?>" alt="" loading="lazy" data-image-preview<?= $current === null ? ' hidden' : '' ?>>
            <span data-image-empty<?= $current !== null ? ' hidden' : '' ?>>Sin imagen</span>
            <code data-image-name><?= editorHtml($current['filename'] ?? '') ?></code>
        </div>
        <div class="editor-image-actions">
            <button class="editor-button editor-button--quiet" type="button" data-image-choose>Elegir imagen</button>
            <button class="editor-button editor-button--quiet" type="button" data-image-remove<?= $current === null ? ' disabled' : '' ?>>Quitar imagen</button>
        </div>
    </div>
    <?php
}

function editorTriviaCorrectIndex(array $trivia): ?int
{
    foreach (($trivia['opciones'] ?? []) as $index => $option) {
        if (is_array($option) && array_key_exists('explicacion', $option)) {
            return $index;
        }
    }
    return null;
}

function editorTriviaExplanation(array $trivia): string
{
    $index = editorTriviaCorrectIndex($trivia);
    return $index !== null ? (string) ($trivia['opciones'][$index]['explicacion'] ?? '') : '';
}

function renderEditorTrivia(array $trivia, int $index, array $errors, array $gallery): void
{
    $correct = editorTriviaCorrectIndex($trivia);
    ?>
    <fieldset id="trivia-editor-<?= $index ?>" class="editor-block" data-trivia-block data-item-editor="<?= $index ?>"<?= $index === 0 ? '' : ' hidden' ?>>
        <legend>Trivia <span data-block-number><?= $index + 1 ?></span></legend>
        <div class="editor-grid">
            <label>ID<input name="trivias[<?= $index ?>][id]" value="<?= editorHtml($trivia['id'] ?? '') ?>" required><?php editorFieldErrors($errors, 'trivias.' . $index . '.id'); ?></label>
            <label class="editor-check"><input type="checkbox" name="trivias[<?= $index ?>][visible]"<?= ($trivia['visible'] ?? false) ? ' checked' : '' ?>> Visible<?php editorFieldErrors($errors, 'trivias.' . $index . '.visible'); ?></label>
            <label class="editor-wide">Pregunta<textarea name="trivias[<?= $index ?>][pregunta]" rows="2" required><?= editorHtml($trivia['pregunta'] ?? '') ?></textarea><?php editorFieldErrors($errors, 'trivias.' . $index . '.pregunta'); ?></label>
            <div><?php renderEditorImageSelector('trivias[' . $index . '][imagen]', contentEditorNullableText($trivia['imagen'] ?? null), 'Imagen opcional', $gallery); ?><?php editorFieldErrors($errors, 'trivias.' . $index . '.imagen'); ?></div>
        </div>
        <div class="editor-options" data-options>
            <?php foreach (array_values(is_array($trivia['opciones'] ?? null) ? $trivia['opciones'] : []) as $optionIndex => $option): ?>
            <div class="editor-option" data-option>
                <label class="editor-correct"><input type="radio" name="trivias[<?= $index ?>][correct_option]" value="<?= $optionIndex ?>"<?= $correct === $optionIndex ? ' checked' : '' ?>> Correcta</label>
                <label>Opción<input name="trivias[<?= $index ?>][opciones][<?= $optionIndex ?>][texto]" value="<?= editorHtml($option['texto'] ?? '') ?>" required></label>
                <button class="editor-button editor-button--quiet" type="button" data-remove-option>Quitar</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php editorFieldErrors($errors, 'trivias.' . $index . '.opciones'); ?>
        <button class="editor-button editor-button--quiet" type="button" data-add-option>Agregar opción</button>
        <label class="editor-explanation">Explicación de la respuesta correcta<textarea name="trivias[<?= $index ?>][explicacion]" rows="3" required><?= editorHtml(editorTriviaExplanation($trivia)) ?></textarea></label>
        <button class="editor-button editor-button--danger" type="button" data-remove-block>Quitar trivia</button>
    </fieldset>
    <?php
}

function renderEditorFact(array $fact, int $index, array $errors, array $gallery): void
{
    ?>
    <fieldset id="fact-editor-<?= $index ?>" class="editor-block" data-fact-block data-item-editor="<?= $index ?>"<?= $index === 0 ? '' : ' hidden' ?>>
        <legend>“Sabías que…” <span data-block-number><?= $index + 1 ?></span></legend>
        <div class="editor-grid">
            <label>ID<input name="sabias_que[<?= $index ?>][id]" value="<?= editorHtml($fact['id'] ?? '') ?>" required><?php editorFieldErrors($errors, 'sabias_que.' . $index . '.id'); ?></label>
            <label class="editor-check"><input type="checkbox" name="sabias_que[<?= $index ?>][visible]"<?= ($fact['visible'] ?? false) ? ' checked' : '' ?>> Visible<?php editorFieldErrors($errors, 'sabias_que.' . $index . '.visible'); ?></label>
            <label class="editor-wide">Título<textarea name="sabias_que[<?= $index ?>][titulo]" rows="2" required><?= editorHtml($fact['titulo'] ?? '') ?></textarea><?php editorFieldErrors($errors, 'sabias_que.' . $index . '.titulo'); ?></label>
            <label class="editor-wide">Respuesta<textarea name="sabias_que[<?= $index ?>][respuesta]" rows="3" required><?= editorHtml($fact['respuesta'] ?? '') ?></textarea><?php editorFieldErrors($errors, 'sabias_que.' . $index . '.respuesta'); ?></label>
            <div><?php renderEditorImageSelector('sabias_que[' . $index . '][imagen]', contentEditorNullableText($fact['imagen'] ?? null), 'Imagen opcional', $gallery); ?><?php editorFieldErrors($errors, 'sabias_que.' . $index . '.imagen'); ?></div>
        </div>
        <button class="editor-button editor-button--danger" type="button" data-remove-block>Quitar bloque</button>
    </fieldset>
    <?php
}

$action = (string) ($_GET['action'] ?? 'list');
$slug = trim((string) ($_GET['slug'] ?? ''));
$requestedTab = (string) ($_GET['tab'] ?? 'content');
$activeTab = in_array($requestedTab, ['content', 'facts', 'trivias'], true) ? $requestedTab : 'content';
$errors = [];
$warnings = [];
$notice = '';
$message = '';
$raw = contentEditorEmptyArticle();
$articleMetadata = null;
$importJson = '';
$importValidation = null;

$dbArticles = [];
$dbArticleDiagnostics = [];
$dbGlobalWarnings = [];
$dbConnection = null;

try {
    $dbConnection = getWebDatabaseConnection();
    $dbArticles = contentEditorDbListArticles($dbConnection);
    $dbGlobalWarnings = contentEditorDbGlobalWarnings($dbConnection);
    foreach ($dbArticles as $dbArticle) {
        $loaded = contentEditorDbLoadArticleRaw($dbConnection, $dbArticle['slug']);
        if ($loaded === null) {
            $dbArticleDiagnostics[$dbArticle['slug']] = [
                astronomyContentError('mysql', 'No se pudo cargar el artículo desde MySQL.'),
            ];
            continue;
        }
        $dbArticleDiagnostics[$dbArticle['slug']] = $loaded['warnings'];
    }
} catch (Throwable $exception) {
    $errors[] = astronomyContentError(
        'mysql',
        'No se pudo cargar el listado desde MySQL. Verificá la conexión. Detalle: '
        . $exception->getMessage()
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string) ($_POST['mode'] ?? 'edit');
    if ($mode === 'visibility_toggle') {
        $targetSlug = trim((string) ($_POST['slug'] ?? ''));
        $targetVisible = ($_POST['visible'] ?? '') === '1';
        if (!contentEditorCsrfValid($_POST['csrf_token'] ?? null)) {
            $errors[] = astronomyContentError('csrf', 'El token CSRF no es válido.');
        } elseif ($dbConnection === null) {
            $errors[] = astronomyContentError('mysql', 'No hay conexión disponible para cambiar la visibilidad.');
        } elseif (!contentEditorDbSetArticleVisibility($dbConnection, $targetSlug, $targetVisible)) {
            $errors[] = astronomyContentError('articulo', 'No se encontró el artículo solicitado para cambiar su visibilidad.');
        } else {
            header('Location: index.php?visibility=' . ($targetVisible ? 'visible' : 'hidden'), true, 303);
            exit;
        }
    }
    if ($mode === 'package_import') {
        $action = 'import';
        $importJson = (string) ($_POST['package_json'] ?? '');
        if (!contentEditorCsrfValid($_POST['csrf_token'] ?? null)) {
            $errors[] = astronomyContentError('csrf', 'El token CSRF no es válido.');
        } elseif ($dbConnection === null) {
            $errors[] = astronomyContentError('mysql', 'No hay conexión disponible para validar o importar el paquete.');
        } else {
            $importValidation = contentEditorValidatePackageJson($importJson);
            if ($importValidation['valid'] && contentEditorDbSlugExists($dbConnection, $importValidation['slug'])) {
                $importValidation['valid'] = false;
                $importValidation['summary'] = null;
                $importValidation['errors'][] = contentEditorPackageError(
                    'articulo.slug',
                    'Ya existe un artículo con ese slug. Editalo desde el editor normal; la importación nunca sobrescribe contenido.'
                );
            }
            if (!$importValidation['valid']) {
                $errors = array_merge($errors, $importValidation['errors']);
            } elseif (($_POST['package_action'] ?? '') === 'import') {
                $import = contentEditorDbImportPackage($dbConnection, $importJson);
                if ($import['imported']) {
                    header('Location: index.php?action=edit&slug=' . rawurlencode($import['slug']) . '&imported=1', true, 303);
                    exit;
                }
                $errors = array_merge($errors, $import['errors']);
                $importValidation['valid'] = false;
                $importValidation['summary'] = null;
            }
        }
    }
    if (in_array($mode, ['new', 'edit'], true)) {
        $action = $mode === 'new' ? 'new' : 'edit';
        $slug = trim((string) ($_POST['slug'] ?? $slug));
        $raw = contentEditorNormalizePost($_POST);
        $activeTab = in_array((string) ($_POST['active_tab'] ?? ''), ['content', 'facts', 'trivias'], true)
            ? (string) $_POST['active_tab']
            : $activeTab;

        if (!contentEditorCsrfValid($_POST['csrf_token'] ?? null)) {
            $errors[] = astronomyContentError('csrf', 'El token CSRF no es válido.');
        } elseif ($dbConnection === null) {
            $errors[] = astronomyContentError('mysql', 'No hay conexión disponible para guardar el artículo.');
        } else {
            $isNew = $action === 'new';
            $originalSlug = trim((string) ($_POST['original_slug'] ?? ''));
            $save = contentEditorDbSaveArticle($dbConnection, $originalSlug, $slug, $raw, $isNew);
            if ($save['saved']) {
                $targetSlug = trim((string) $save['slug']);
                header('Location: index.php?action=edit&slug=' . rawurlencode($targetSlug) . '&saved=1', true, 303);
                exit;
            }
            $errors = array_merge($errors, $save['errors']);
        }
    }
}

if ($action === 'edit' && $slug !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($dbConnection === null) {
        $errors[] = astronomyContentError('mysql', 'No hay conexión disponible para abrir el artículo solicitado.');
    } else {
        $loaded = contentEditorDbLoadArticleRaw($dbConnection, $slug);
        if ($loaded === null) {
            $errors[] = astronomyContentError('articulo', 'No existe un artículo con ese slug en MySQL.');
        } else {
            $raw = $loaded['raw'];
            $articleMetadata = $loaded['metadata'];
            $warnings = array_merge($warnings, $loaded['warnings']);
        }
    }
}

if ($action === 'new' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $slug = '';
    $raw = contentEditorEmptyArticle();
}

if (($_GET['saved'] ?? '') === '1') {
    $message = 'Cambios guardados correctamente en MySQL.';
} elseif (($_GET['imported'] ?? '') === '1') {
    $message = 'Paquete editorial importado completamente en MySQL.';
} elseif (($_GET['visibility'] ?? '') === 'visible') {
    $message = 'El artículo ahora es visible públicamente.';
} elseif (($_GET['visibility'] ?? '') === 'hidden') {
    $message = 'El artículo ahora está oculto públicamente.';
}

$editing = ($action === 'edit' && $slug !== '') || $action === 'new';
$importing = $action === 'import';
$isNewArticle = $action === 'new';
$editorImageGallery = contentEditorImageGallery();
$saveFailed = $_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== [];
$tabErrorCounts = editorTabErrorCounts($errors);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Contenidos · Área privada</title>
    <link rel="stylesheet" href="../../<?= editorHtml(versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="../../<?= editorHtml(versionedAssetUrl('admin/contenidos/editor.css')) ?>">
</head>
<body class="store-admin">
<?php renderStoreAdminNavigation('contents', 'Contenidos'); ?>
<main class="container editor-container">
<?php if ($notice !== ''): ?><p class="editor-notice" role="status"><?= editorHtml($notice) ?></p><?php endif; ?>
<?php if ($message !== ''): ?><section class="editor-notice" role="status"><strong><?= editorHtml($message) ?></strong></section><?php endif; ?>
<?php if ($errors !== []): ?><section class="editor-errors" role="alert"><h2>No se pudo completar la operación</h2><ul><?php foreach ($errors as $error): ?><li><code><?= editorHtml($error['field'] ?? 'mysql') ?></code>: <?= editorHtml($error['message'] ?? 'Error') ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php if ($dbGlobalWarnings !== []): ?><section class="editor-warnings" role="status"><h2>Advertencias globales de MySQL</h2><ul><?php foreach ($dbGlobalWarnings as $warning): ?><li><code><?= editorHtml($warning['field'] ?? 'mysql') ?></code>: <?= editorHtml($warning['message'] ?? 'Advertencia') ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php if ($editing && isLocalEnvironment()): ?>
    <section class="editor-photo-upload" data-photo-upload data-upload-url="upload-photos.php">
        <div class="editor-photo-upload__heading">
            <div><p class="eyebrow">HERRAMIENTA LOCAL</p><h2>Agregar fotografías</h2></div>
            <p>Los originales quedan en almacenamiento privado. La tienda y este selector usan solamente derivados.</p>
        </div>
        <div class="editor-photo-dropzone" data-photo-dropzone tabindex="0" role="button" aria-describedby="photo-upload-help">
            <strong>Arrastrá imágenes aquí</strong><span>o</span>
            <button class="editor-button editor-button--quiet" type="button" data-photo-select>Seleccionar archivos</button>
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input hidden>
        </div>
        <p id="photo-upload-help" class="editor-photo-upload__help">JPG, JPEG, PNG o WEBP · hasta 20 archivos de 25 MB cada uno.</p>
        <input type="hidden" value="<?= editorHtml(contentEditorCsrfToken()) ?>" data-photo-csrf>
        <progress class="editor-photo-progress" max="100" value="0" data-photo-progress hidden></progress>
        <p class="editor-photo-upload__status" data-photo-status role="status" aria-live="polite"></p>
        <ul class="editor-photo-results" data-photo-results aria-label="Resultado de la carga"></ul>
    </section>
<?php endif; ?>
<?php if (!$editing && !$importing): ?>
    <div class="editor-heading">
        <div><p class="eyebrow">GESTIÓN EDITORIAL</p><h2>Contenidos desde MySQL</h2></div>
        <div class="editor-heading__actions"><a class="editor-button editor-button--quiet" href="index.php?action=import">Importar contenido completo</a><a class="editor-button editor-button--primary" href="index.php?action=new">Nuevo artículo</a></div>
    </div>
    <?php if ($errors !== []): ?>
        <p class="editor-title-note">No se muestra el listado porque la consulta a MySQL falló.</p>
    <?php else: ?>
    <div class="editor-table-wrap">
        <table class="editor-table">
            <thead><tr><th>Slug</th><th>Título</th><th>Visibilidad</th><th>Imagen principal</th><th>Estado</th><th>Trivias</th><th>Sabías que</th><th>Actualizado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($dbArticles as $article): ?>
                <?php
                $articleWarnings = $dbArticleDiagnostics[$article['slug']] ?? [];
                $state = $articleWarnings === [] ? 'valid' : 'warning';
                $canReadArticle = true;
                $articleTitle = (string) ($article['titulo'] ?? 'No disponible');
                $articleReadUrl = '../../' . ltrim(astronomyContentArticleUrl((string) $article['slug']), '/');
                ?>
                <tr>
                    <td><code><?= editorHtml($article['slug']) ?></code></td>
                    <td>
                        <?php if ($canReadArticle): ?>
                            <a class="editor-title-link" href="<?= editorHtml($articleReadUrl) ?>"><?= editorHtml($articleTitle) ?></a>
                        <?php else: ?>
                            <span class="editor-title-unavailable"><?= editorHtml($articleTitle) ?></span>
                            <small class="editor-title-note">No disponible para lectura</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form class="editor-visibility-form" method="post">
                            <input type="hidden" name="csrf_token" value="<?= editorHtml(contentEditorCsrfToken()) ?>">
                            <input type="hidden" name="mode" value="visibility_toggle">
                            <input type="hidden" name="slug" value="<?= editorHtml($article['slug']) ?>">
                            <input type="hidden" name="visible" value="<?= $article['visible'] ? '0' : '1' ?>">
                            <button class="editor-visibility-toggle<?= $article['visible'] ? ' is-visible' : ' is-hidden' ?>" type="submit" aria-label="<?= $article['visible'] ? 'Ocultar' : 'Hacer visible' ?> <?= editorHtml($articleTitle) ?>"><?= $article['visible'] ? 'Visible' : 'Oculto' ?></button>
                        </form>
                    </td>
                    <td><span class="editor-image-status<?= $article['has_main_image'] ? ' has-image' : '' ?>"><?= $article['has_main_image'] ? 'Sí' : 'No' ?></span></td>
                    <td><span class="editor-status editor-status--<?= $state ?>"><?php if ($state === 'warning'): ?>Con advertencias · <?= count($articleWarnings) ?> advertencia<?= count($articleWarnings) === 1 ? '' : 's' ?><?php else: ?>Válido<?php endif; ?></span></td>
                    <td><?= (int) $article['trivias_count'] ?></td>
                    <td><?= (int) $article['sabias_que_count'] ?></td>
                    <td><?= editorHtml((string) $article['actualizado_en']) ?></td>
                    <td><a class="editor-link" href="index.php?action=edit&amp;slug=<?= rawurlencode($article['slug']) ?>">Editar</a></td>
                </tr>
                <?php if ($articleWarnings !== []): ?><tr class="editor-diagnostic-row"><td colspan="9"><ul><?php foreach ($articleWarnings as $warning): ?><li><code><?= editorHtml($warning['field']) ?></code>: <?= editorHtml($warning['message']) ?></li><?php endforeach; ?></ul></td></tr><?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php elseif ($importing): ?>
    <div class="editor-heading">
        <div><p class="eyebrow">IMPORTACIÓN JSON</p><h2>Importar contenido completo</h2></div>
        <a class="editor-link" href="index.php">Volver al listado</a>
    </div>
    <section class="editor-panel editor-import-help">
        <h3>Paquete editorial versionado</h3>
        <p>La operación crea un artículo nuevo junto con todas sus trivias y tarjetas “Sabías que…”. Nunca reemplaza contenido existente.</p>
    </section>
    <form class="editor-form editor-import-form" method="post" data-package-import>
        <input type="hidden" name="csrf_token" value="<?= editorHtml(contentEditorCsrfToken()) ?>">
        <input type="hidden" name="mode" value="package_import">
        <label for="package-json">JSON del paquete</label>
        <textarea id="package-json" name="package_json" rows="30" spellcheck="false" data-package-json required><?= editorHtml($importJson) ?></textarea>
        <div class="editor-import-actions">
            <button class="editor-button editor-button--quiet" type="button" data-copy-package-example>Copiar ejemplo</button>
            <button class="editor-button editor-button--quiet" type="submit" name="package_action" value="validate">Validar</button>
            <button class="editor-button editor-button--primary" type="submit" name="package_action" value="import"<?= is_array($importValidation) && ($importValidation['valid'] ?? false) ? '' : ' disabled' ?>>Importar</button>
        </div>
        <p class="editor-import-copy-status" data-package-copy-status role="status" aria-live="polite"></p>
    </form>
    <?php if (is_array($importValidation) && ($importValidation['valid'] ?? false) && is_array($importValidation['summary'] ?? null)): ?>
        <?php $summary = $importValidation['summary']; ?>
        <section class="editor-panel editor-import-summary" aria-labelledby="editor-import-summary-title">
            <h3 id="editor-import-summary-title">Paquete válido, listo para importar</h3>
            <dl>
                <div><dt>Slug</dt><dd><code><?= editorHtml($summary['slug']) ?></code></dd></div>
                <div><dt>Título</dt><dd><?= editorHtml($summary['titulo']) ?></dd></div>
                <div><dt>Palabras clave</dt><dd><?= (int) $summary['palabras_clave'] ?></dd></div>
                <div><dt>Relaciones</dt><dd><?= (int) $summary['relaciones'] ?></dd></div>
                <div><dt>Trivias</dt><dd><?= (int) $summary['trivias'] ?></dd></div>
                <div><dt>Sabías que</dt><dd><?= (int) $summary['sabias_que'] ?></dd></div>
            </dl>
        </section>
    <?php endif; ?>
    <script type="application/json" data-package-example><?= str_replace('</', '<\/', contentEditorPackageExampleJson()) ?></script>
<?php else: ?>
    <div class="editor-heading">
        <div><p class="eyebrow">EDICIÓN MYSQL</p><h2><?= $isNewArticle ? 'Nuevo artículo' : editorHtml($raw['titulo'] ?? $slug) ?></h2><?php if (is_array($articleMetadata) && isset($articleMetadata['actualizado_en'])): ?><p class="editor-title-note">Actualizado en MySQL: <?= editorHtml((string) $articleMetadata['actualizado_en']) ?></p><?php endif; ?></div>
        <a class="editor-link" href="index.php">Volver al listado</a>
    </div>
    <?php if ($warnings !== []): ?><section class="editor-warnings" role="status"><h2>Advertencias del contenido</h2><ul><?php foreach ($warnings as $warning): ?><li><code><?= editorHtml($warning['field'] ?? 'contenido') ?></code>: <?= editorHtml($warning['message'] ?? 'Advertencia') ?><?php if (($warning['kind'] ?? null) === 'embedded_image' && isset($warning['line'])): ?> <button class="editor-warning-action" type="button" data-goto-markdown-line="<?= (int) $warning['line'] ?>">Ir al componente</button><?php endif; ?></li><?php endforeach; ?></ul></section><?php endif; ?>
    <form class="editor-form" method="post" data-content-editor novalidate>
        <input type="hidden" name="csrf_token" value="<?= editorHtml(contentEditorCsrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="mode" value="<?= $isNewArticle ? 'new' : 'edit' ?>">
        <input type="hidden" name="original_slug" value="<?= editorHtml($isNewArticle ? '' : $slug) ?>">
        <input type="hidden" name="active_tab" value="<?= editorHtml($activeTab) ?>" data-active-tab>
        <div class="editor-tabs" role="tablist" aria-label="Secciones del artículo">
            <?php foreach (['content' => 'Contenido', 'facts' => 'Sabías que', 'trivias' => 'Trivias'] as $tabKey => $tabLabel): ?>
                <?php $tabIsActive = $activeTab === $tabKey; ?>
                <button id="editor-tab-<?= $tabKey ?>" class="editor-tab<?= $tabIsActive ? ' is-active' : '' ?><?= $tabErrorCounts[$tabKey] > 0 ? ' has-errors' : '' ?>" type="button" role="tab" aria-selected="<?= $tabIsActive ? 'true' : 'false' ?>" aria-controls="editor-panel-<?= $tabKey ?>" tabindex="<?= $tabIsActive ? '0' : '-1' ?>"><?= $tabLabel ?><?php if ($tabErrorCounts[$tabKey] > 0): ?> <span class="editor-tab__error-count" aria-label="<?= $tabErrorCounts[$tabKey] ?> error<?= $tabErrorCounts[$tabKey] === 1 ? '' : 'es' ?>"><?= $tabErrorCounts[$tabKey] ?></span><?php endif; ?></button>
            <?php endforeach; ?>
        </div>
        <div id="editor-panel-content" class="editor-tab-panel" role="tabpanel" aria-labelledby="editor-tab-content"<?= $activeTab === 'content' ? '' : ' hidden' ?>>
        <section class="editor-panel">
            <h2>Artículo</h2>
            <div class="editor-meta-row">
                <label>Slug<input name="slug" value="<?= editorHtml($slug) ?>" required></label>
                <label>Versión<input type="number" name="version" value="<?= editorHtml($raw['version'] ?? 1) ?>" min="1" required><?php editorFieldErrors($errors, 'version'); ?></label>
                <label class="editor-check"><input type="checkbox" name="visible"<?= ($raw['visible'] ?? false) ? ' checked' : '' ?>> Visible</label>
            </div>
            <?php
            $featuredImageName = contentEditorNullableText($raw['imagen'] ?? null);
            $featuredImageUrl = null;
            $featuredWidth = 0;
            $featuredHeight = 0;
            foreach ($editorImageGallery as $galleryImage) {
                if ($galleryImage['filename'] === $featuredImageName) {
                    $featuredImageUrl = '../../' . versionedAssetUrl($galleryImage['url']);
                    $featuredWidth = $galleryImage['width'];
                    $featuredHeight = $galleryImage['height'];
                    break;
                }
            }
            $requiresFocalPoint = contentEditorImageRequiresFocalPoint($featuredWidth, $featuredHeight);
            $hasHeroAspectRatio = astronomyContentImageHasHeroAspectRatio($featuredWidth, $featuredHeight);
            $positionX = astronomyContentImagePosition($raw['imagen_posicion_x'] ?? 50);
            $positionY = astronomyContentImagePosition($raw['imagen_posicion_y'] ?? 50);
            ?>
            <section class="editor-featured-image-panel" aria-labelledby="editor-featured-image-title">
                <h3 id="editor-featured-image-title">Imagen principal</h3>
                <div class="editor-featured-image-panel__layout" data-image-selector data-image-filter="hero">
                    <div class="editor-featured-original">
                        <span class="editor-image-field__label">Imagen original</span>
                        <div class="editor-image-current">
                            <img src="<?= $featuredImageUrl !== null ? editorHtml($featuredImageUrl) : '' ?>" alt="" loading="lazy" data-image-preview<?= $featuredImageUrl === null ? ' hidden' : '' ?>>
                            <span data-image-empty<?= $featuredImageUrl !== null ? ' hidden' : '' ?>>Sin imagen</span>
                        </div>
                    </div>
                    <div class="editor-featured-info">
                        <input type="hidden" name="imagen" value="<?= editorHtml($featuredImageName ?? '') ?>" data-image-value>
                        <span class="editor-image-field__label">Archivo seleccionado</span>
                        <code data-image-name><?= editorHtml($featuredImageName ?? '') ?></code>
                        <div class="editor-image-actions">
                            <button class="editor-button editor-button--quiet" type="button" data-image-choose>Elegir imagen</button>
                            <button class="editor-button editor-button--quiet" type="button" data-image-remove<?= $featuredImageUrl === null ? ' disabled' : '' ?>>Quitar imagen</button>
                        </div>
                        <p class="editor-featured-ratio-warning" data-featured-ratio-warning<?= $featuredImageUrl === null || $hasHeroAspectRatio ? ' hidden' : '' ?>>La imagen principal no tiene proporción 16:9 y podría recortarse.</p>
                <div class="editor-focal" data-focal-editor<?= $featuredImageUrl === null ? ' hidden' : '' ?>>
                    <span class="editor-image-field__label">Vista final 16:9</span>
                    <input type="hidden" name="imagen_posicion_x" value="<?= editorHtml($positionX) ?>" data-focal-x>
                    <input type="hidden" name="imagen_posicion_y" value="<?= editorHtml($positionY) ?>" data-focal-y>
                    <button class="editor-focal__surface<?= $requiresFocalPoint ? '' : ' is-disabled' ?>" type="button" data-focal-surface aria-label="<?= $requiresFocalPoint ? 'Elegir punto focal de la cabecera 16:9' : 'Vista previa de cabecera 16:9 sin recorte significativo' ?>" style="--focal-x: <?= editorHtml($positionX) ?>%; --focal-y: <?= editorHtml($positionY) ?>%;"<?= $requiresFocalPoint ? '' : ' disabled' ?>>
                        <img src="<?= editorHtml($featuredImageUrl ?? '') ?>" alt="" data-focal-image>
                        <span class="editor-focal__marker" aria-hidden="true"<?= $requiresFocalPoint ? '' : ' hidden' ?>></span>
                    </button>
                    <p class="editor-focal__status" data-focal-status><?= $requiresFocalPoint ? 'La cabecera 16:9 recorta la imagen. Elegí el motivo principal.' : 'La proporción coincide con 16:9; no se necesita ajustar el punto focal.' ?></p>
                    <div class="editor-focal__meta" data-focal-controls<?= $requiresFocalPoint ? '' : ' hidden' ?>><span>Posición: <strong data-focal-value><?= editorHtml(round($positionX)) ?>% / <?= editorHtml(round($positionY)) ?>%</strong></span><button class="editor-button editor-button--quiet" type="button" data-focal-center>Centrar</button></div>
                </div>
                    </div>
                </div>
            </section>
            <div class="editor-content-fields">
                <label>Título<input name="titulo" value="<?= editorHtml($raw['titulo'] ?? '') ?>" required><?php editorFieldErrors($errors, 'titulo'); ?></label>
                <label>Resumen<textarea name="resumen" rows="3" required><?= editorHtml($raw['resumen'] ?? '') ?></textarea><?php editorFieldErrors($errors, 'resumen'); ?></label>
                <label>Palabras clave <small>Una por línea</small><textarea name="palabras_clave" rows="6"><?= editorHtml(implode("\n", is_array($raw['palabras_clave'] ?? null) ? $raw['palabras_clave'] : [])) ?></textarea></label>
                <label>Relaciones <small>Una por línea</small><textarea name="relaciones" rows="6"><?= editorHtml(implode("\n", is_array($raw['relaciones'] ?? null) ? $raw['relaciones'] : [])) ?></textarea></label>
            </div>
        </section>
        <section class="editor-panel">
            <div class="editor-section-heading"><h2>Texto del artículo</h2><div class="editor-toolbar" aria-label="Insertar formato">
                <?php foreach ([
                    ['label' => '## Título', 'snippet' => '## Título {#ancla}'],
                    ['label' => 'Imagen independiente', 'snippet' => '[[imagen src="" alt=""]]'],
                    ['label' => 'Esquema', 'snippet' => '[[esquema tipo=""]]'],
                    ['label' => 'Trivia', 'snippet' => '[[trivia id=""]]'],
                    ['label' => 'Negrita', 'snippet' => '**negrita**'],
                    ['label' => 'Cursiva', 'snippet' => '*cursiva*'],
                    ['label' => 'Código', 'snippet' => '`código`'],
                    ['label' => 'Lista', 'snippet' => '- elemento de lista'],
                ] as $toolbarItem): ?>
                    <button class="editor-button editor-button--quiet" type="button" data-insert-snippet="<?= editorHtml($toolbarItem['snippet']) ?>" title="<?= editorHtml($toolbarItem['snippet']) ?>"><?= editorHtml($toolbarItem['label']) ?></button>
                <?php endforeach; ?>
            </div></div>
            <details class="editor-image-block-builder" data-image-block-builder>
                <summary>Insertar bloque con imagen</summary>
                <div class="editor-image-block-builder__fields">
                    <div class="editor-image-field" data-image-selector data-image-filter="vertical">
                        <span class="editor-image-field__label">Imagen</span>
                        <input type="hidden" value="" data-image-value>
                        <div class="editor-image-current"><img alt="" loading="lazy" data-image-preview hidden><span data-image-empty>Sin imagen</span><code data-image-name></code></div>
                        <div class="editor-image-actions">
                            <button class="editor-button editor-button--quiet" type="button" data-image-choose>Elegir imagen</button>
                            <button class="editor-button editor-button--quiet" type="button" data-image-remove disabled>Quitar imagen</button>
                        </div>
                    </div>
                    <label>Texto alternativo<input type="text" data-image-block-alt placeholder="Descripción de la imagen"></label>
                    <label>Posición<select data-image-block-position><option value="derecha">A la derecha</option><option value="izquierda">A la izquierda</option></select></label>
                </div>
                <div class="editor-image-block-builder__actions">
                    <p data-image-block-status aria-live="polite"></p>
                    <button class="editor-button editor-button--primary" type="button" data-insert-image-block>Insertar bloque</button>
                </div>
            </details>
            <textarea id="content-markdown-editor" class="editor-markdown" name="articulo" rows="28" data-markdown-editor required><?= editorHtml($raw['articulo'] ?? '') ?></textarea>
            <?php editorFieldErrors($errors, 'articulo'); ?>
        </section>
        </div>
        <div id="editor-panel-facts" class="editor-tab-panel" role="tabpanel" aria-labelledby="editor-tab-facts"<?= $activeTab === 'facts' ? '' : ' hidden' ?>>
            <section class="editor-panel editor-collection" data-collection="fact">
                <aside class="editor-collection__sidebar">
                    <div class="editor-collection__heading"><h2>“Sabías que…”</h2><button class="editor-button editor-button--quiet" type="button" data-add-fact>Nuevo Sabías que</button></div>
                    <div class="editor-item-list" data-item-list>
                    <?php foreach (array_values(is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : []) as $index => $fact): ?>
                        <?php $hasErrors = editorHasErrors($errors, 'sabias_que.' . $index); ?>
                        <button class="editor-item<?= $index === 0 ? ' is-selected' : '' ?><?= $hasErrors ? ' has-errors' : '' ?>" type="button" data-item-select="<?= $index ?>" aria-controls="fact-editor-<?= $index ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                            <strong data-item-label><?= editorHtml($fact['titulo'] ?? 'Sin título') ?></strong>
                            <span><span data-item-visibility><?= ($fact['visible'] ?? false) ? 'Visible' : 'Oculto' ?></span><?php if ($hasErrors): ?> · <span class="editor-item__error">Con errores</span><?php endif; ?></span>
                        </button>
                    <?php endforeach; ?>
                    </div>
                    <div class="editor-empty" data-empty-state<?= (is_array($raw['sabias_que'] ?? null) && $raw['sabias_que'] !== []) ? ' hidden' : '' ?>><p>Todavía no hay tarjetas “Sabías que…”.</p><button class="editor-button editor-button--primary" type="button" data-add-fact>Crear la primera</button></div>
                </aside>
                <div class="editor-collection__detail" data-fact-list><?php foreach (array_values(is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : []) as $index => $fact) renderEditorFact($fact, $index, $errors, $editorImageGallery); ?></div>
            </section>
        </div>
        <div id="editor-panel-trivias" class="editor-tab-panel" role="tabpanel" aria-labelledby="editor-tab-trivias"<?= $activeTab === 'trivias' ? '' : ' hidden' ?>>
            <section class="editor-panel editor-collection" data-collection="trivia">
                <aside class="editor-collection__sidebar">
                    <div class="editor-collection__heading"><h2>Trivias</h2><button class="editor-button editor-button--quiet" type="button" data-add-trivia>Nueva trivia</button></div>
                    <div class="editor-item-list" data-item-list>
                    <?php foreach (array_values(is_array($raw['trivias'] ?? null) ? $raw['trivias'] : []) as $index => $trivia): ?>
                        <?php $hasErrors = editorHasErrors($errors, 'trivias.' . $index); ?>
                        <button class="editor-item<?= $index === 0 ? ' is-selected' : '' ?><?= $hasErrors ? ' has-errors' : '' ?>" type="button" data-item-select="<?= $index ?>" aria-controls="trivia-editor-<?= $index ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                            <strong data-item-label><?= editorHtml($trivia['pregunta'] ?? 'Sin pregunta') ?></strong>
                            <span><span data-item-visibility><?= ($trivia['visible'] ?? false) ? 'Visible' : 'Oculta' ?></span><?php if ($hasErrors): ?> · <span class="editor-item__error">Con errores</span><?php endif; ?></span>
                        </button>
                    <?php endforeach; ?>
                    </div>
                    <div class="editor-empty" data-empty-state<?= (is_array($raw['trivias'] ?? null) && $raw['trivias'] !== []) ? ' hidden' : '' ?>><p>Todavía no hay trivias.</p><button class="editor-button editor-button--primary" type="button" data-add-trivia>Crear la primera</button></div>
                </aside>
                <div class="editor-collection__detail" data-trivia-list><?php foreach (array_values(is_array($raw['trivias'] ?? null) ? $raw['trivias'] : []) as $index => $trivia) renderEditorTrivia($trivia, $index, $errors, $editorImageGallery); ?></div>
            </section>
        </div>
        <div class="editor-actions"><a class="editor-button editor-button--quiet" href="index.php">Volver al listado</a><button class="editor-button editor-button--primary" type="submit">Guardar artículo</button></div>
    </form>
    <dialog class="editor-image-dialog" data-image-dialog aria-labelledby="editor-image-dialog-title">
        <div class="editor-image-dialog__heading">
            <h2 id="editor-image-dialog-title">Elegir imagen</h2>
            <button class="editor-button editor-button--quiet" type="button" data-image-close>Cerrar</button>
        </div>
        <p data-image-gallery-empty<?= $editorImageGallery !== [] ? ' hidden' : '' ?>>No hay imágenes válidas disponibles.</p>
        <p data-image-filter-empty hidden>No hay imágenes con una proporción adecuada para este uso.</p>
        <div class="editor-image-gallery" data-image-gallery>
            <?php foreach ($editorImageGallery as $image): ?>
                <button class="editor-image-option" type="button" data-image-option="<?= editorHtml($image['filename']) ?>" data-image-url="../../<?= editorHtml(versionedAssetUrl($image['url'])) ?>" data-image-width="<?= (int) $image['width'] ?>" data-image-height="<?= (int) $image['height'] ?>" data-image-hero="<?= $image['hero_compatible'] ? 'true' : 'false' ?>" data-image-vertical="<?= $image['vertical'] ? 'true' : 'false' ?>" aria-pressed="false">
                    <img src="../../<?= editorHtml(versionedAssetUrl($image['url'])) ?>" alt="" loading="lazy">
                    <code><?= editorHtml($image['filename']) ?></code>
                    <span class="editor-image-option__format"><?= editorHtml($image['format_label']) ?></span>
                    <span data-image-selected-label hidden>Seleccionada</span>
                </button>
            <?php endforeach; ?>
        </div>
    </dialog>
    <dialog class="editor-image-dialog editor-confirm-dialog" data-confirm-dialog aria-labelledby="editor-confirm-title">
        <h2 id="editor-confirm-title">Confirmar eliminación</h2>
        <p data-confirm-message></p>
        <div class="editor-confirm-dialog__actions">
            <button class="editor-button editor-button--quiet" type="button" data-confirm-cancel>Cancelar</button>
            <button class="editor-button editor-button--danger" type="button" data-confirm-accept>Eliminar</button>
        </div>
    </dialog>
<?php endif; ?>
</main>
<script src="../../<?= editorHtml(versionedAssetUrl('admin/contenidos/editor.js')) ?>" defer></script>
</body>
</html>
