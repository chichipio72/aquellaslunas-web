<?php

require_once __DIR__ . '/../../includes/content-system.php';
require_once __DIR__ . '/../../includes/web-database.php';

const CONTENT_EDITOR_CSRF_KEY = 'content_editor_csrf';

/**
 * @return array<int, array{slug: string, titulo: string, resumen: string, visible: bool, actualizado_en: string, trivias_count: int, sabias_que_count: int}>
 */
function contentEditorDbListArticles(PDO $connection): array
{
    $statement = $connection->query(
        'SELECT '
        . 'a.slug, a.titulo, a.resumen, a.visible, '
        . 'DATE_FORMAT(a.actualizado_en, "%Y-%m-%d %H:%i:%s") AS actualizado_en, '
        . 'COALESCE(t.cantidad, 0) AS trivias_count, '
        . 'COALESCE(s.cantidad, 0) AS sabias_que_count '
        . 'FROM contenido_articulos a '
        . 'LEFT JOIN (SELECT articulo_id, COUNT(*) AS cantidad FROM contenido_trivias GROUP BY articulo_id) t ON t.articulo_id = a.id '
        . 'LEFT JOIN (SELECT articulo_id, COUNT(*) AS cantidad FROM contenido_sabias_que GROUP BY articulo_id) s ON s.articulo_id = a.id '
        . 'ORDER BY a.slug ASC'
    );
    $articles = [];
    foreach ($statement as $row) {
        $articles[] = [
            'slug' => (string) $row['slug'],
            'titulo' => (string) $row['titulo'],
            'resumen' => (string) $row['resumen'],
            'visible' => ((int) $row['visible']) === 1,
            'actualizado_en' => (string) $row['actualizado_en'],
            'trivias_count' => (int) $row['trivias_count'],
            'sabias_que_count' => (int) $row['sabias_que_count'],
        ];
    }
    return $articles;
}

/**
 * @return array<int, array{field: string, message: string}>
 */
function contentEditorDbGlobalWarnings(PDO $connection): array
{
    $warnings = [];
    $checks = [
        'contenido_trivias' => 'SELECT COUNT(*) FROM contenido_trivias t LEFT JOIN contenido_articulos a ON a.id = t.articulo_id WHERE a.id IS NULL',
        'contenido_sabias_que' => 'SELECT COUNT(*) FROM contenido_sabias_que s LEFT JOIN contenido_articulos a ON a.id = s.articulo_id WHERE a.id IS NULL',
        'contenido_trivia_opciones' => 'SELECT COUNT(*) FROM contenido_trivia_opciones o LEFT JOIN contenido_trivias t ON t.id = o.trivia_id WHERE t.id IS NULL',
    ];
    foreach ($checks as $field => $sql) {
        $count = (int) $connection->query($sql)->fetchColumn();
        if ($count > 0) {
            $warnings[] = astronomyContentError($field, 'Se detectaron ' . $count . ' registros huérfanos.');
        }
    }
    return $warnings;
}

/**
 * @return array{raw: array, metadata: array{slug: string, actualizado_en: string}, warnings: array<int, array{field: string, message: string}>}|null
 */
function contentEditorDbLoadArticleRaw(PDO $connection, string $slug): ?array
{
    $articleStatement = $connection->prepare(
        'SELECT id, slug, version, visible, titulo, resumen, markdown, imagen_principal, '
        . 'DATE_FORMAT(actualizado_en, "%Y-%m-%d %H:%i:%s") AS actualizado_en '
        . 'FROM contenido_articulos WHERE slug = :slug LIMIT 1'
    );
    $articleStatement->execute(['slug' => $slug]);
    $article = $articleStatement->fetch();
    if (!is_array($article)) {
        return null;
    }
    $articleId = (int) $article['id'];

    $wordsStatement = $connection->prepare(
        'SELECT palabra_clave FROM contenido_articulos_palabras_clave WHERE articulo_id = :articulo_id ORDER BY orden ASC, palabra_clave ASC'
    );
    $wordsStatement->execute(['articulo_id' => $articleId]);
    $palabrasClave = array_map(static fn(array $row): string => (string) $row['palabra_clave'], $wordsStatement->fetchAll());

    $relationsStatement = $connection->prepare(
        'SELECT slug_relacionado FROM contenido_articulos_relaciones WHERE articulo_id = :articulo_id ORDER BY orden ASC, slug_relacionado ASC'
    );
    $relationsStatement->execute(['articulo_id' => $articleId]);
    $relaciones = array_map(static fn(array $row): string => (string) $row['slug_relacionado'], $relationsStatement->fetchAll());

    $triviaStatement = $connection->prepare(
        'SELECT id, codigo, pregunta, imagen, visible, orden FROM contenido_trivias WHERE articulo_id = :articulo_id ORDER BY orden ASC, id ASC'
    );
    $triviaStatement->execute(['articulo_id' => $articleId]);
    $triviaRows = $triviaStatement->fetchAll();

    $optionStatement = $connection->prepare(
        'SELECT texto, correcta, explicacion, orden FROM contenido_trivia_opciones WHERE trivia_id = :trivia_id ORDER BY orden ASC, id ASC'
    );
    $trivias = [];
    foreach ($triviaRows as $triviaRow) {
        $triviaId = (int) $triviaRow['id'];
        $optionStatement->execute(['trivia_id' => $triviaId]);
        $optionRows = $optionStatement->fetchAll();
        $options = [];
        foreach ($optionRows as $optionRow) {
            $option = ['texto' => (string) $optionRow['texto']];
            if ((int) $optionRow['correcta'] === 1) {
                $option['explicacion'] = $optionRow['explicacion'] !== null ? (string) $optionRow['explicacion'] : '';
            }
            $options[] = $option;
        }
        $trivias[] = [
            'id' => (string) $triviaRow['codigo'],
            'visible' => ((int) $triviaRow['visible']) === 1,
            'pregunta' => (string) $triviaRow['pregunta'],
            'imagen' => contentEditorNullableText($triviaRow['imagen'] ?? null),
            'opciones' => $options,
            '_orden' => (int) $triviaRow['orden'],
        ];
    }

    $factStatement = $connection->prepare(
        'SELECT codigo, frase, detalle, imagen, visible, orden FROM contenido_sabias_que WHERE articulo_id = :articulo_id ORDER BY orden ASC, id ASC'
    );
    $factStatement->execute(['articulo_id' => $articleId]);
    $factRows = $factStatement->fetchAll();
    $sabiasQue = [];
    foreach ($factRows as $factRow) {
        $sabiasQue[] = [
            'id' => (string) $factRow['codigo'],
            'visible' => ((int) $factRow['visible']) === 1,
            'titulo' => (string) $factRow['frase'],
            'respuesta' => (string) $factRow['detalle'],
            'imagen' => contentEditorNullableText($factRow['imagen'] ?? null),
            '_orden' => (int) $factRow['orden'],
        ];
    }

    $raw = [
        'version' => (int) $article['version'],
        'visible' => ((int) $article['visible']) === 1,
        'imagen' => contentEditorNullableText($article['imagen_principal'] ?? null),
        'imagen_posicion_x' => 50,
        'imagen_posicion_y' => 50,
        'titulo' => (string) $article['titulo'],
        'resumen' => (string) $article['resumen'],
        'palabras_clave' => $palabrasClave,
        'relaciones' => $relaciones,
        'sabias_que' => $sabiasQue,
        'trivias' => $trivias,
        'articulo' => (string) $article['markdown'],
    ];

    $warnings = contentEditorDbValidateLoadedArticle($slug, $raw);

    return [
        'raw' => $raw,
        'metadata' => [
            'slug' => (string) $article['slug'],
            'actualizado_en' => (string) $article['actualizado_en'],
        ],
        'warnings' => $warnings,
    ];
}

/**
 * @return array<int, array{field: string, message: string}>
 */
function contentEditorDbValidateLoadedArticle(string $slug, array $raw): array
{
    $warnings = [];

    $article = astronomyContentValidateArticle($slug, $raw, $slug . '.php');
    foreach ($article['errors'] as $error) {
        $warnings[] = astronomyContentError((string) ($error['field'] ?? 'contenido'), (string) ($error['message'] ?? 'Error'));
    }

    $triviaIds = [];
    foreach (array_values(is_array($raw['trivias'] ?? null) ? $raw['trivias'] : []) as $index => $triviaRaw) {
        $trivia = astronomyContentValidateTrivia($triviaRaw, $slug, $index);
        foreach ($trivia['errors'] as $error) {
            $warnings[] = astronomyContentError((string) ($error['field'] ?? 'trivia'), (string) ($error['message'] ?? 'Error'));
        }

        $correctCount = 0;
        foreach (is_array($triviaRaw['opciones'] ?? null) ? $triviaRaw['opciones'] : [] as $option) {
            if (is_array($option) && array_key_exists('explicacion', $option)) {
                $correctCount++;
            }
        }
        if ($correctCount !== 1) {
            $warnings[] = astronomyContentError(
                'trivias.' . $index . '.opciones',
                'La trivia debe tener exactamente una opción correcta y actualmente tiene ' . $correctCount . '.'
            );
        }
        $triviaIds[(string) ($triviaRaw['id'] ?? '')] = true;

        if (array_key_exists('_orden', $triviaRaw) && (int) $triviaRaw['_orden'] !== $index) {
            $warnings[] = astronomyContentError(
                'trivias.' . $index . '._orden',
                'El orden guardado en base de datos no coincide con la posición actual (' . (int) $triviaRaw['_orden'] . ' vs ' . $index . ').'
            );
        }
        foreach (array_values(is_array($triviaRaw['opciones'] ?? null) ? $triviaRaw['opciones'] : []) as $optionIndex => $option) {
            if (is_array($option) && array_key_exists('_orden', $option) && (int) $option['_orden'] !== $optionIndex) {
                $warnings[] = astronomyContentError(
                    'trivias.' . $index . '.opciones.' . $optionIndex . '._orden',
                    'El orden de opciones no coincide con la posición actual.'
                );
            }
        }
    }

    foreach (array_values(is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : []) as $index => $factRaw) {
        $fact = astronomyContentValidateFact($factRaw, $slug, $index);
        foreach ($fact['errors'] as $error) {
            $warnings[] = astronomyContentError((string) ($error['field'] ?? 'sabias_que'), (string) ($error['message'] ?? 'Error'));
        }
        if (array_key_exists('_orden', $factRaw) && (int) $factRaw['_orden'] !== $index) {
            $warnings[] = astronomyContentError(
                'sabias_que.' . $index . '._orden',
                'El orden guardado en base de datos no coincide con la posición actual (' . (int) $factRaw['_orden'] . ' vs ' . $index . ').'
            );
        }
    }

    $components = astronomyContentMarkdownComponents((string) ($raw['articulo'] ?? ''));
    foreach ($components as $index => $component) {
        if (($component['type'] ?? '') !== 'trivia' || !is_array($component['attributes'] ?? null)) {
            continue;
        }
        $componentTriviaId = (string) ($component['attributes']['id'] ?? '');
        if (!isset($triviaIds[$componentTriviaId])) {
            $warnings[] = astronomyContentError(
                'articulo.componentes.' . $index,
                'El Markdown referencia la trivia “' . $componentTriviaId . '” pero no existe en la base para este artículo.'
            );
        }
    }

    array_walk_recursive($raw, static function ($value, $field) use (&$warnings): void {
        if (!is_string($value)) {
            return;
        }
        $isValidUtf8 = function_exists('mb_check_encoding')
            ? mb_check_encoding($value, 'UTF-8')
            : preg_match('//u', $value) === 1;
        if (!$isValidUtf8) {
            $warnings[] = astronomyContentError((string) $field, 'El valor contiene bytes fuera de UTF-8.');
        }
    });

    return $warnings;
}

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
    foreach (['trivias', 'sabias_que'] as $collection) {
        foreach (is_array($raw[$collection] ?? null) ? $raw[$collection] : [] as $index => $entry) {
            if (is_array($entry)) {
                unset($entry['referencia']);
                $raw[$collection][$index] = $entry;
            }
        }
    }
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

/**
 * @return array{saved: bool, slug: string, errors: array<int, array{field: string, message: string}>}
 */
function contentEditorDbSaveArticle(PDO $connection, string $originalSlug, string $slug, array $raw, bool $isNew): array
{
    $slug = trim($slug);
    $originalSlug = trim($originalSlug);
    $errors = contentEditorValidateDbCandidate($slug, $raw);
    if (!$isNew && $originalSlug === '') {
        $errors[] = astronomyContentError('original_slug', 'Falta el slug original para actualizar el artículo.');
    }

    if ($errors !== []) {
        return ['saved' => false, 'slug' => $slug, 'errors' => $errors];
    }

    $articleId = null;
    if ($isNew) {
        if (contentEditorDbSlugExists($connection, $slug)) {
            return ['saved' => false, 'slug' => $slug, 'errors' => [astronomyContentError('slug', 'Ya existe un artículo con ese slug.')]];
        }
    } else {
        $articleId = contentEditorDbFindArticleIdBySlug($connection, $originalSlug);
        if ($articleId === null) {
            return ['saved' => false, 'slug' => $slug, 'errors' => [astronomyContentError('slug', 'El artículo original no existe.')]];
        }
        if ($slug !== $originalSlug && contentEditorDbSlugExists($connection, $slug)) {
            return ['saved' => false, 'slug' => $slug, 'errors' => [astronomyContentError('slug', 'Ya existe un artículo con ese slug.')]];
        }
    }

    try {
        $connection->beginTransaction();

        if ($isNew) {
            $insert = $connection->prepare(
                'INSERT INTO contenido_articulos (slug, version, visible, titulo, resumen, markdown, imagen_principal) '
                . 'VALUES (:slug, :version, :visible, :titulo, :resumen, :markdown, :imagen_principal)'
            );
            $insert->execute([
                'slug' => $slug,
                'version' => (int) $raw['version'],
                'visible' => ($raw['visible'] ?? false) ? 1 : 0,
                'titulo' => (string) $raw['titulo'],
                'resumen' => (string) $raw['resumen'],
                'markdown' => (string) $raw['articulo'],
                'imagen_principal' => contentEditorNullableText($raw['imagen'] ?? null),
            ]);
            $articleId = (int) $connection->lastInsertId();
        } else {
            $update = $connection->prepare(
                'UPDATE contenido_articulos '
                . 'SET slug = :slug, version = :version, visible = :visible, titulo = :titulo, resumen = :resumen, markdown = :markdown, imagen_principal = :imagen_principal '
                . 'WHERE id = :id'
            );
            $update->execute([
                'id' => $articleId,
                'slug' => $slug,
                'version' => (int) $raw['version'],
                'visible' => ($raw['visible'] ?? false) ? 1 : 0,
                'titulo' => (string) $raw['titulo'],
                'resumen' => (string) $raw['resumen'],
                'markdown' => (string) $raw['articulo'],
                'imagen_principal' => contentEditorNullableText($raw['imagen'] ?? null),
            ]);
            contentEditorDbDeleteArticleCollections($connection, (int) $articleId);
        }

        contentEditorDbInsertWords($connection, (int) $articleId, is_array($raw['palabras_clave'] ?? null) ? $raw['palabras_clave'] : []);
        contentEditorDbInsertRelations($connection, (int) $articleId, is_array($raw['relaciones'] ?? null) ? $raw['relaciones'] : []);
        contentEditorDbInsertTrivias($connection, (int) $articleId, is_array($raw['trivias'] ?? null) ? $raw['trivias'] : []);
        contentEditorDbInsertFacts($connection, (int) $articleId, is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : []);

        $connection->commit();
        return ['saved' => true, 'slug' => $slug, 'errors' => []];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        return [
            'saved' => false,
            'slug' => $slug,
            'errors' => [astronomyContentError('mysql', 'No se pudo guardar en MySQL. Detalle: ' . $exception->getMessage())],
        ];
    }
}

/**
 * @return array<int, array{field: string, message: string}>
 */
function contentEditorValidateDbCandidate(string $slug, array $raw): array
{
    $errors = [];

    if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
        $errors[] = astronomyContentError('slug', 'El slug debe usar minúsculas, números y guiones.');
    }

    $version = filter_var($raw['version'] ?? null, FILTER_VALIDATE_INT);
    if ($version === false || (int) $version < 1) {
        $errors[] = astronomyContentError('version', 'La versión debe ser un entero mayor o igual a 1.');
    }

    $articleValidationRaw = $raw;
    $articleValidationRaw['version'] = 1;
    $article = astronomyContentValidateArticle($slug, $articleValidationRaw, $slug . '.php');
    foreach (($article['errors'] ?? []) as $error) {
        if (($error['field'] ?? '') === 'version') {
            continue;
        }
        $errors[] = astronomyContentError((string) ($error['field'] ?? 'contenido'), (string) ($error['message'] ?? 'Error de contenido.'));
    }

    $errors = array_merge($errors, contentEditorValidateSelectedImages($raw));

    foreach (array_values(is_array($raw['trivias'] ?? null) ? $raw['trivias'] : []) as $index => $triviaRaw) {
        $trivia = astronomyContentValidateTrivia($triviaRaw, $slug, $index);
        foreach (($trivia['errors'] ?? []) as $error) {
            $errors[] = astronomyContentError((string) ($error['field'] ?? 'trivias.' . $index), (string) ($error['message'] ?? 'Error en la trivia.'));
        }

        $correctCount = 0;
        foreach (is_array($triviaRaw['opciones'] ?? null) ? $triviaRaw['opciones'] : [] as $option) {
            if (is_array($option) && array_key_exists('explicacion', $option)) {
                $correctCount++;
            }
        }
        if ($correctCount !== 1) {
            $errors[] = astronomyContentError(
                'trivias.' . $index . '.opciones',
                'La trivia debe tener exactamente una opción correcta y actualmente tiene ' . $correctCount . '.'
            );
        }
    }

    foreach (array_values(is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : []) as $index => $factRaw) {
        $fact = astronomyContentValidateFact($factRaw, $slug, $index);
        foreach (($fact['errors'] ?? []) as $error) {
            $errors[] = astronomyContentError((string) ($error['field'] ?? 'sabias_que.' . $index), (string) ($error['message'] ?? 'Error en el bloque “Sabías que…”.'));
        }
    }

    array_walk_recursive($raw, static function ($value, $field) use (&$errors): void {
        if (!is_string($value)) {
            return;
        }
        $isValidUtf8 = function_exists('mb_check_encoding')
            ? mb_check_encoding($value, 'UTF-8')
            : preg_match('//u', $value) === 1;
        if (!$isValidUtf8) {
            $errors[] = astronomyContentError((string) $field, 'El valor contiene bytes fuera de UTF-8.');
        }
    });

    return $errors;
}

function contentEditorDbFindArticleIdBySlug(PDO $connection, string $slug): ?int
{
    $statement = $connection->prepare('SELECT id FROM contenido_articulos WHERE slug = :slug LIMIT 1');
    $statement->execute(['slug' => $slug]);
    $articleId = $statement->fetchColumn();
    return $articleId === false ? null : (int) $articleId;
}

function contentEditorDbSlugExists(PDO $connection, string $slug): bool
{
    return contentEditorDbFindArticleIdBySlug($connection, $slug) !== null;
}

function contentEditorDbDeleteArticleCollections(PDO $connection, int $articleId): void
{
    $listTrivias = $connection->prepare('SELECT id FROM contenido_trivias WHERE articulo_id = :articulo_id');
    $listTrivias->execute(['articulo_id' => $articleId]);

    $deleteOptions = $connection->prepare('DELETE FROM contenido_trivia_opciones WHERE trivia_id = :trivia_id');
    foreach ($listTrivias->fetchAll(PDO::FETCH_COLUMN) as $triviaId) {
        $deleteOptions->execute(['trivia_id' => (int) $triviaId]);
    }

    $connection->prepare('DELETE FROM contenido_trivias WHERE articulo_id = :articulo_id')->execute(['articulo_id' => $articleId]);
    $connection->prepare('DELETE FROM contenido_sabias_que WHERE articulo_id = :articulo_id')->execute(['articulo_id' => $articleId]);
    $connection->prepare('DELETE FROM contenido_articulos_palabras_clave WHERE articulo_id = :articulo_id')->execute(['articulo_id' => $articleId]);
    $connection->prepare('DELETE FROM contenido_articulos_relaciones WHERE articulo_id = :articulo_id')->execute(['articulo_id' => $articleId]);
}

function contentEditorDbInsertWords(PDO $connection, int $articleId, array $words): void
{
    if ($words === []) {
        return;
    }
    $insert = $connection->prepare(
        'INSERT INTO contenido_articulos_palabras_clave (articulo_id, palabra_clave, orden) '
        . 'VALUES (:articulo_id, :palabra_clave, :orden)'
    );
    foreach (array_values($words) as $index => $word) {
        $insert->execute([
            'articulo_id' => $articleId,
            'palabra_clave' => (string) $word,
            'orden' => $index,
        ]);
    }
}

function contentEditorDbInsertRelations(PDO $connection, int $articleId, array $relations): void
{
    if ($relations === []) {
        return;
    }
    $insert = $connection->prepare(
        'INSERT INTO contenido_articulos_relaciones (articulo_id, slug_relacionado, orden) '
        . 'VALUES (:articulo_id, :slug_relacionado, :orden)'
    );
    foreach (array_values($relations) as $index => $relatedSlug) {
        $insert->execute([
            'articulo_id' => $articleId,
            'slug_relacionado' => (string) $relatedSlug,
            'orden' => $index,
        ]);
    }
}

function contentEditorDbInsertTrivias(PDO $connection, int $articleId, array $trivias): void
{
    if ($trivias === []) {
        return;
    }

    $insertTrivia = $connection->prepare(
        'INSERT INTO contenido_trivias (articulo_id, codigo, pregunta, imagen, visible, orden) '
        . 'VALUES (:articulo_id, :codigo, :pregunta, :imagen, :visible, :orden)'
    );
    $insertOption = $connection->prepare(
        'INSERT INTO contenido_trivia_opciones (trivia_id, texto, correcta, explicacion, orden) '
        . 'VALUES (:trivia_id, :texto, :correcta, :explicacion, :orden)'
    );

    foreach (array_values($trivias) as $triviaIndex => $trivia) {
        $trivia = is_array($trivia) ? $trivia : [];
        $insertTrivia->execute([
            'articulo_id' => $articleId,
            'codigo' => (string) ($trivia['id'] ?? ''),
            'pregunta' => (string) ($trivia['pregunta'] ?? ''),
            'imagen' => contentEditorNullableText($trivia['imagen'] ?? null),
            'visible' => ($trivia['visible'] ?? false) ? 1 : 0,
            'orden' => $triviaIndex,
        ]);

        $triviaId = (int) $connection->lastInsertId();
        foreach (array_values(is_array($trivia['opciones'] ?? null) ? $trivia['opciones'] : []) as $optionIndex => $option) {
            $option = is_array($option) ? $option : [];
            $isCorrect = array_key_exists('explicacion', $option);
            $insertOption->execute([
                'trivia_id' => $triviaId,
                'texto' => (string) ($option['texto'] ?? ''),
                'correcta' => $isCorrect ? 1 : 0,
                'explicacion' => $isCorrect ? (string) ($option['explicacion'] ?? '') : null,
                'orden' => $optionIndex,
            ]);
        }
    }
}

function contentEditorDbInsertFacts(PDO $connection, int $articleId, array $facts): void
{
    if ($facts === []) {
        return;
    }

    $insert = $connection->prepare(
        'INSERT INTO contenido_sabias_que (articulo_id, codigo, frase, detalle, imagen, visible, orden) '
        . 'VALUES (:articulo_id, :codigo, :frase, :detalle, :imagen, :visible, :orden)'
    );

    foreach (array_values($facts) as $index => $fact) {
        $fact = is_array($fact) ? $fact : [];
        $insert->execute([
            'articulo_id' => $articleId,
            'codigo' => (string) ($fact['id'] ?? ''),
            'frase' => (string) ($fact['titulo'] ?? ''),
            'detalle' => (string) ($fact['respuesta'] ?? ''),
            'imagen' => contentEditorNullableText($fact['imagen'] ?? null),
            'visible' => ($fact['visible'] ?? false) ? 1 : 0,
            'orden' => $index,
        ]);
    }
}
