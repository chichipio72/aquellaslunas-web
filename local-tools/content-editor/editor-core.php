<?php

require_once __DIR__ . '/../../includes/content-system.php';

const CONTENT_EDITOR_BACKUP_DIRECTORY = __DIR__ . '/backups';
const CONTENT_EDITOR_CSRF_KEY = 'content_editor_csrf';

function contentEditorEmptyArticle(): array
{
    return [
        'version' => 1,
        'visible' => false,
        'imagen' => null,
        'imagen_posicion_x' => 50,
        'imagen_posicion_y' => 50,
        'titulo' => '',
        'resumen' => '',
        'palabras_clave' => [],
        'relaciones' => [],
        'sabias_que' => [],
        'trivias' => [],
        'articulo' => "# Título\n",
    ];
}

function contentEditorLoadRaw(string $slug, ?string $directory = null): array
{
    $directory ??= ASTRONOMY_CONTENT_DIRECTORY;
    if (astronomyContentSlugFromFilename($slug . '.php') !== $slug) {
        throw new InvalidArgumentException('El slug no es válido.');
    }
    $path = $directory . '/' . $slug . '.php';
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('El archivo no existe o no puede leerse.');
    }
    try {
        $raw = (static fn(string $filename) => require $filename)($path);
    } catch (Throwable $exception) {
        throw new RuntimeException('El archivo produjo un error al cargarse: ' . $exception->getMessage(), 0, $exception);
    }
    if (!is_array($raw)) {
        throw new RuntimeException('El archivo no devuelve un array PHP.');
    }
    return $raw;
}

function contentEditorLines($value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', $value), static fn(string $line): bool => $line !== ''));
    }
    $lines = preg_split('/\R/', (string) $value) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
}

function contentEditorNullableText($value): ?string
{
    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

function contentEditorImageGallery(?string $imageDirectory = null): array
{
    $imageDirectory ??= ASTRONOMY_CONTENT_IMAGE_DIRECTORY;
    $gallery = [];
    foreach (astronomyContentAvailableImages($imageDirectory) as $filename) {
        $size = @getimagesize($imageDirectory . '/' . $filename);
        $gallery[] = [
            'filename' => $filename,
            'url' => ASTRONOMY_CONTENT_IMAGE_URL_PREFIX . rawurlencode($filename),
            'width' => is_array($size) ? (int) $size[0] : 0,
            'height' => is_array($size) ? (int) $size[1] : 0,
            'format_label' => is_array($size) && astronomyContentImageHasHeroAspectRatio((int) $size[0], (int) $size[1])
                ? '16:9'
                : (is_array($size) && $size[0] > $size[1]
                    ? 'Horizontal'
                    : (is_array($size) && $size[0] === $size[1] ? 'Cuadrada' : 'Vertical')),
        ];
    }
    return $gallery;
}

function contentEditorImageRequiresFocalPoint(int $width, int $height): bool
{
    if ($width <= 0 || $height <= 0) {
        return false;
    }
    return !astronomyContentImageHasHeroAspectRatio($width, $height);
}

function contentEditorCatalogArticleIssues(array $catalog, string $slug, string $kind): array
{
    $key = $kind === 'warnings' ? 'warnings' : 'errors';
    $issues = $catalog['articles'][$slug][$key] ?? [];
    foreach (array_merge($catalog['trivias'] ?? [], $catalog['facts'] ?? []) as $entry) {
        if (($entry['source_slug'] ?? null) === $slug) {
            $issues = array_merge($issues, $entry[$key] ?? []);
        }
    }
    return $issues;
}

function contentEditorArticleStatus(array $catalog, string $slug): array
{
    $errors = contentEditorCatalogArticleIssues($catalog, $slug, 'errors');
    $warnings = contentEditorCatalogArticleIssues($catalog, $slug, 'warnings');
    return [
        'state' => $errors !== [] ? 'invalid' : ($warnings !== [] ? 'warning' : 'valid'),
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

function contentEditorArticleIsReadable(array $article): bool
{
    return ($article['valid'] ?? false) === true && ($article['visible'] ?? false) === true;
}

function contentEditorNormalizePost(array $post): array
{
    $raw = [
        'version' => filter_var($post['version'] ?? null, FILTER_VALIDATE_INT),
        'visible' => isset($post['visible']),
        'imagen' => contentEditorNullableText($post['imagen'] ?? null),
        'imagen_posicion_x' => astronomyContentImagePosition($post['imagen_posicion_x'] ?? 50),
        'imagen_posicion_y' => astronomyContentImagePosition($post['imagen_posicion_y'] ?? 50),
        'titulo' => trim((string) ($post['titulo'] ?? '')),
        'resumen' => trim((string) ($post['resumen'] ?? '')),
        'palabras_clave' => contentEditorLines($post['palabras_clave'] ?? ''),
        'relaciones' => contentEditorLines($post['relaciones'] ?? ''),
        'sabias_que' => [],
        'trivias' => [],
        'articulo' => rtrim((string) ($post['articulo'] ?? '')) . "\n",
    ];

    foreach (array_values(is_array($post['trivias'] ?? null) ? $post['trivias'] : []) as $trivia) {
        if (!is_array($trivia)) {
            continue;
        }
        $correctIndex = filter_var($trivia['correct_option'] ?? null, FILTER_VALIDATE_INT);
        $options = [];
        foreach ((is_array($trivia['opciones'] ?? null) ? $trivia['opciones'] : []) as $index => $option) {
            $option = is_array($option) ? $option : [];
            $normalized = ['texto' => trim((string) ($option['texto'] ?? ''))];
            if ($correctIndex !== false && $correctIndex === (int) $index) {
                $normalized['explicacion'] = trim((string) ($trivia['explicacion'] ?? ''));
            }
            $options[] = $normalized;
        }
        $raw['trivias'][] = [
            'id' => trim((string) ($trivia['id'] ?? '')),
            'visible' => isset($trivia['visible']),
            'pregunta' => trim((string) ($trivia['pregunta'] ?? '')),
            'imagen' => contentEditorNullableText($trivia['imagen'] ?? null),
            'opciones' => $options,
            'referencia' => [
                'articulo' => trim((string) ($trivia['referencia_articulo'] ?? '')),
                'ancla' => contentEditorNullableText($trivia['referencia_ancla'] ?? null),
            ],
        ];
    }

    foreach (array_values(is_array($post['sabias_que'] ?? null) ? $post['sabias_que'] : []) as $fact) {
        if (!is_array($fact)) {
            continue;
        }
        $raw['sabias_que'][] = [
            'id' => trim((string) ($fact['id'] ?? '')),
            'visible' => isset($fact['visible']),
            'titulo' => trim((string) ($fact['titulo'] ?? '')),
            'respuesta' => trim((string) ($fact['respuesta'] ?? '')),
            'imagen' => contentEditorNullableText($fact['imagen'] ?? null),
            'referencia' => [
                'articulo' => trim((string) ($fact['referencia_articulo'] ?? '')),
                'ancla' => contentEditorNullableText($fact['referencia_ancla'] ?? null),
            ],
        ];
    }
    return $raw;
}

function contentEditorExportValue($value, int $level = 1): string
{
    if (is_string($value)) {
        return var_export($value, true);
    }
    if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
        return var_export($value, true);
    }
    if (!is_array($value)) {
        throw new InvalidArgumentException('El contenido contiene un valor no serializable.');
    }
    if ($value === []) {
        return '[]';
    }
    $indent = str_repeat('    ', $level);
    $closing = str_repeat('    ', $level - 1);
    $isList = array_is_list($value);
    $lines = [];
    foreach ($value as $key => $entry) {
        $prefix = $isList ? '' : var_export((string) $key, true) . ' => ';
        $lines[] = $indent . $prefix . contentEditorExportValue($entry, $level + 1) . ',';
    }
    return "[\n" . implode("\n", $lines) . "\n" . $closing . ']';
}

function contentEditorSerialize(array $raw): string
{
    $markdown = rtrim((string) ($raw['articulo'] ?? ''));
    if (preg_match('/^MD;?$/m', $markdown) === 1) {
        throw new InvalidArgumentException('El artículo contiene una línea reservada “MD” que impide usar el heredoc.');
    }
    $orderedKeys = ['version', 'visible', 'imagen', 'imagen_posicion_x', 'imagen_posicion_y', 'titulo', 'resumen', 'palabras_clave', 'relaciones', 'sabias_que', 'trivias'];
    $lines = ["<?php", '', 'return ['];
    foreach ($orderedKeys as $key) {
        $lines[] = '    ' . var_export($key, true) . ' => ' . contentEditorExportValue($raw[$key] ?? null, 2) . ',';
        $lines[] = '';
    }
    $lines[] = "    'articulo' => <<<'MD'";
    $lines[] = $markdown;
    $lines[] = 'MD,';
    $lines[] = '';
    $lines[] = '];';
    return implode("\n", $lines) . "\n";
}

function contentEditorRemoveTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . '/' . $entry;
        is_dir($path) ? contentEditorRemoveTree($path) : @unlink($path);
    }
    @rmdir($directory);
}

function contentEditorValidateSelectedImages(array $raw, ?string $imageDirectory = null): array
{
    $available = array_fill_keys(astronomyContentAvailableImages($imageDirectory), true);
    $errors = [];
    $check = static function ($value, string $field) use (&$errors, $available): void {
        if ($value === null) {
            return;
        }
        if (!is_string($value) || !isset($available[$value])) {
            $errors[] = astronomyContentError($field, 'La imagen seleccionada no pertenece a la galería disponible.');
        }
    };
    $check($raw['imagen'] ?? null, 'imagen');
    foreach (is_array($raw['trivias'] ?? null) ? $raw['trivias'] : [] as $index => $trivia) {
        $check(is_array($trivia) ? ($trivia['imagen'] ?? null) : null, 'trivias.' . $index . '.imagen');
    }
    foreach (is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : [] as $index => $fact) {
        $check(is_array($fact) ? ($fact['imagen'] ?? null) : null, 'sabias_que.' . $index . '.imagen');
    }
    return $errors;
}

function contentEditorValidateCandidate(string $slug, array $raw, ?string $sourceDirectory = null, ?string $imageDirectory = null): array
{
    $sourceDirectory ??= ASTRONOMY_CONTENT_DIRECTORY;
    $ownErrors = [];
    if (astronomyContentSlugFromFilename($slug . '.php') !== $slug) {
        $ownErrors[] = astronomyContentError('slug', 'Usá únicamente minúsculas, números y guiones medios.');
    }
    $ownErrors = array_merge($ownErrors, contentEditorValidateSelectedImages($raw, $imageDirectory));
    try {
        $serialized = contentEditorSerialize($raw);
    } catch (Throwable $exception) {
        $ownErrors[] = astronomyContentError('articulo', $exception->getMessage());
        $serialized = null;
    }
    if ($ownErrors !== [] || $serialized === null) {
        return ['valid' => false, 'errors' => $ownErrors, 'warnings' => []];
    }

    $temporaryDirectory = sys_get_temp_dir() . '/aquellas-lunas-content-editor-' . bin2hex(random_bytes(8));
    if (!mkdir($temporaryDirectory, 0700, true)) {
        return ['valid' => false, 'errors' => [astronomyContentError('archivo', 'No se pudo crear el área temporal de validación.')], 'warnings' => []];
    }
    try {
        foreach (scandir($sourceDirectory) ?: [] as $filename) {
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'php' && is_file($sourceDirectory . '/' . $filename)) {
                copy($sourceDirectory . '/' . $filename, $temporaryDirectory . '/' . $filename);
            }
        }
        if (file_put_contents($temporaryDirectory . '/' . $slug . '.php', $serialized, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo preparar el archivo para validarlo.');
        }
        $catalog = astronomyLoadContentCatalog($temporaryDirectory);
        $article = $catalog['articles'][$slug] ?? null;
        if (!is_array($article)) {
            return ['valid' => false, 'errors' => [astronomyContentError('archivo', 'El cargador no pudo recuperar el contenido generado.')], 'warnings' => []];
        }
        $errors = $article['errors'];
        $warnings = $article['warnings'] ?? [];
        foreach (array_filter($catalog['trivias'], static fn(array $entry): bool => $entry['source_slug'] === $slug) as $trivia) {
            $errors = array_merge($errors, $trivia['errors']);
            $warnings = array_merge($warnings, $trivia['warnings']);
        }
        foreach (array_filter($catalog['facts'], static fn(array $entry): bool => $entry['source_slug'] === $slug) as $fact) {
            $errors = array_merge($errors, $fact['errors']);
            $warnings = array_merge($warnings, $fact['warnings']);
        }
        return ['valid' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    } catch (Throwable $exception) {
        return ['valid' => false, 'errors' => [astronomyContentError('archivo', $exception->getMessage())], 'warnings' => []];
    } finally {
        contentEditorRemoveTree($temporaryDirectory);
    }
}

function contentEditorSave(string $slug, array $raw, bool $isNew, ?string $contentDirectory = null, ?string $backupDirectory = null, ?string $imageDirectory = null): array
{
    $contentDirectory ??= ASTRONOMY_CONTENT_DIRECTORY;
    $backupDirectory ??= CONTENT_EDITOR_BACKUP_DIRECTORY;
    $validation = contentEditorValidateCandidate($slug, $raw, $contentDirectory, $imageDirectory);
    if (!$validation['valid']) {
        return ['saved' => false, 'errors' => $validation['errors'], 'backup' => null];
    }
    $target = $contentDirectory . '/' . $slug . '.php';
    if ($isNew && file_exists($target)) {
        return ['saved' => false, 'errors' => [astronomyContentError('slug', 'Ya existe un artículo con ese slug.')], 'backup' => null];
    }
    if (!$isNew && !is_file($target)) {
        return ['saved' => false, 'errors' => [astronomyContentError('archivo', 'El artículo original ya no existe.')], 'backup' => null];
    }
    $backup = null;
    if (is_file($target)) {
        $articleBackupDirectory = $backupDirectory . '/' . $slug;
        if (!is_dir($articleBackupDirectory) && !mkdir($articleBackupDirectory, 0770, true)) {
            return ['saved' => false, 'errors' => [astronomyContentError('archivo', 'No se pudo crear la carpeta de backups.')], 'backup' => null];
        }
        chmod($articleBackupDirectory, 0777);
        $backup = $articleBackupDirectory . '/' . $slug . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.php';
        if (!copy($target, $backup)) {
            return ['saved' => false, 'errors' => [astronomyContentError('archivo', 'No se pudo crear el backup previo.')], 'backup' => null];
        }
    }
    $temporary = tempnam($contentDirectory, '.' . $slug . '-');
    if ($temporary === false) {
        return ['saved' => false, 'errors' => [astronomyContentError('archivo', 'No se pudo crear el archivo temporal de guardado.')], 'backup' => $backup];
    }
    try {
        if (file_put_contents($temporary, contentEditorSerialize($raw), LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el archivo temporal.');
        }
        chmod($temporary, 0664);
        if (!rename($temporary, $target)) {
            throw new RuntimeException('No se pudo reemplazar el contenido de forma atómica.');
        }
        clearstatcache(true, $target);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($target, true);
        }
    } catch (Throwable $exception) {
        @unlink($temporary);
        return ['saved' => false, 'errors' => [astronomyContentError('archivo', $exception->getMessage())], 'backup' => $backup];
    }
    return ['saved' => true, 'errors' => [], 'backup' => $backup];
}

function contentEditorSession(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    return session_start();
}

function contentEditorCsrfToken(): string
{
    contentEditorSession();
    if (!isset($_SESSION[CONTENT_EDITOR_CSRF_KEY])) {
        $_SESSION[CONTENT_EDITOR_CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CONTENT_EDITOR_CSRF_KEY];
}

function contentEditorCsrfValid($token): bool
{
    contentEditorSession();
    return is_string($token)
        && isset($_SESSION[CONTENT_EDITOR_CSRF_KEY])
        && hash_equals($_SESSION[CONTENT_EDITOR_CSRF_KEY], $token);
}
