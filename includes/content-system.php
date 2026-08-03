<?php

require_once __DIR__ . '/api-config.php';
require_once __DIR__ . '/content-debug.php';
require_once __DIR__ . '/current-datetime.php';
require_once __DIR__ . '/asset-url.php';
require_once __DIR__ . '/web-database.php';
require_once __DIR__ . '/content-database.php';
require_once __DIR__ . '/store-admin-auth.php';
require_once __DIR__ . '/site-configuration.php';

const ASTRONOMY_CONTENT_IMAGE_DIRECTORY = __DIR__ . '/../assets/images/tienda/previews/contenido';
const ASTRONOMY_CONTENT_IMAGE_URL_PREFIX = 'assets/images/tienda/previews/contenido/';

function astronomyContentError(string $field, string $message): array
{
    return ['field' => $field, 'message' => $message];
}

function astronomyContentNonEmptyString($value): bool
{
    return is_string($value) && trim($value) !== '';
}

function astronomyContentStringList($value): bool
{
    return is_array($value) && array_is_list($value)
        && count(array_filter($value, 'astronomyContentNonEmptyString')) === count($value);
}

function astronomyContentImagePosition($value): float
{
    return is_numeric($value) && (float) $value >= 0 && (float) $value <= 100
        ? (float) $value
        : 50.0;
}

function astronomyContentImageHasHeroAspectRatio(?int $width, ?int $height): bool
{
    if (($width ?? 0) <= 0 || ($height ?? 0) <= 0) {
        return false;
    }
    $ratio = $width / $height;
    return $ratio >= 1.70 && $ratio <= 1.85;
}

function astronomyContentSlugFromFilename(string $filename): ?string
{
    $slug = pathinfo($filename, PATHINFO_FILENAME);
    return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1 ? $slug : null;
}

function astronomyContentMarkdownAnchors(string $markdown): array
{
    preg_match_all('/\{#([a-z0-9]+(?:-[a-z0-9]+)*)\}\s*$/mi', $markdown, $matches);
    return array_values(array_unique($matches[1] ?? []));
}

function astronomyContentComponentAttributes(string $source): ?array
{
    $attributes = [];
    $offset = 0;
    $length = strlen($source);
    while ($offset < $length) {
        if (preg_match('/\G\s+([a-z_]+)="([^"]*)"/A', $source, $match, 0, $offset) !== 1) {
            return trim(substr($source, $offset)) === '' ? $attributes : null;
        }
        $attributes[$match[1]] = $match[2];
        $offset += strlen($match[0]);
    }
    return $attributes;
}

function astronomyContentMarkdownComponents(string $markdown): array
{
    $components = [];
    preg_match_all('/\[\[(imagen|esquema|trivia)([^\]]*)\]\]/i', $markdown, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($matches as $match) {
        $offset = (int) $match[0][1];
        $components[] = [
            'type' => strtolower($match[1][0]),
            'attributes' => astronomyContentComponentAttributes($match[2][0]),
            'source' => $match[0][0],
            'line' => substr_count(substr($markdown, 0, $offset), "\n") + 1,
        ];
    }
    return $components;
}

function astronomyContentImageAlignment(mixed $alignment): string
{
    $normalized = is_string($alignment) ? strtolower(trim($alignment)) : '';
    return in_array($normalized, ['derecha', 'izquierda', 'centro'], true) ? $normalized : 'centro';
}

function astronomyContentImageBlockPosition(mixed $position): string
{
    $normalized = is_string($position) ? strtolower(trim($position)) : '';
    return in_array($normalized, ['derecha', 'izquierda'], true) ? $normalized : 'derecha';
}

function astronomyContentMarkdownImageBlocks(string $markdown): array
{
    preg_match_all(
        '/^[ \t]*\[\[(\/?bloque-imagen)([^\]]*)\]\][ \t]*\r?$/mi',
        $markdown,
        $matches,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );
    $blocks = [];
    $issues = [];
    $open = null;
    foreach ($matches as $match) {
        $marker = strtolower($match[1][0]);
        $offset = (int) $match[0][1];
        $line = substr_count(substr($markdown, 0, $offset), "\n") + 1;
        if ($marker === 'bloque-imagen') {
            if ($open !== null) {
                $issues[] = ['kind' => 'nested', 'line' => $line, 'message' => 'Los bloques con imagen no pueden anidarse.'];
                continue;
            }
            $open = [
                'offset' => $offset,
                'content_offset' => $offset + strlen($match[0][0]),
                'line' => $line,
                'attributes' => astronomyContentComponentAttributes($match[2][0]),
                'source' => $match[0][0],
            ];
            continue;
        }
        if ($open === null) {
            $issues[] = ['kind' => 'orphan_close', 'line' => $line, 'message' => 'Se encontró un cierre de bloque sin apertura.'];
            continue;
        }
        $content = substr($markdown, $open['content_offset'], $offset - $open['content_offset']);
        $blocks[] = array_merge($open, [
            'content' => trim($content),
            'end_offset' => $offset + strlen($match[0][0]),
            'closing_line' => $line,
        ]);
        $open = null;
    }
    if ($open !== null) {
        $issues[] = [
            'kind' => 'missing_close',
            'line' => $open['line'],
            'message' => 'Falta el marcador de cierre [[/bloque-imagen]].',
        ];
    }
    return ['blocks' => $blocks, 'issues' => $issues];
}

function astronomyContentContextualWarnings(array $warnings, string $label, array $context = []): array
{
    return array_map(
        static fn(array $warning): array => array_merge(
            $warning,
            $context,
            ['message' => $label . ': ' . lcfirst((string) ($warning['message'] ?? 'Se utilizó una alternativa.'))]
        ),
        $warnings
    );
}

function astronomyContentAvailableImages(?string $directory = null): array
{
    $directory ??= ASTRONOMY_CONTENT_IMAGE_DIRECTORY;
    $files = is_dir($directory) ? scandir($directory) : false;
    if (!is_array($files)) {
        return [];
    }
    $images = [];
    foreach ($files as $filename) {
        if (
            basename($filename) !== $filename
            || preg_match('/\.(?:jpe?g|png|webp)$/i', $filename) !== 1
        ) {
            continue;
        }
        $path = $directory . '/' . $filename;
        if (is_file($path) && is_readable($path) && is_array(@getimagesize($path))) {
            $images[] = $filename;
        }
    }
    sort($images, SORT_NATURAL | SORT_FLAG_CASE);
    return $images;
}

/**
 * Resuelve de manera centralizada una imagen solicitada o un reemplazo aleatorio.
 *
 * @return array{filename: ?string, url: ?string, width: ?int, height: ?int, orientation: ?string, fallback: bool, errors: array, warnings: array}
 */
function astronomyContentResolveImage($value, string $field, ?string $directory = null, ?string $urlPrefix = null): array
{
    static $cache = [];
    $directory ??= ASTRONOMY_CONTENT_IMAGE_DIRECTORY;
    $urlPrefix ??= ASTRONOMY_CONTENT_IMAGE_URL_PREFIX;
    $cacheKey = $directory . "\0" . $urlPrefix . "\0" . get_debug_type($value) . "\0" . (is_scalar($value) ? (string) $value : '');
    if (isset($cache[$cacheKey])) {
        $cached = $cache[$cacheKey];
        $cached['errors'] = array_map(
            static fn(array $error): array => astronomyContentError($field, $error['message']),
            $cached['errors']
        );
        $cached['warnings'] = array_map(
            static fn(array $warning): array => astronomyContentError($field, $warning['message']),
            $cached['warnings']
        );
        return $cached;
    }
    $result = ['filename' => null, 'url' => null, 'width' => null, 'height' => null, 'orientation' => null, 'fallback' => false, 'errors' => [], 'warnings' => []];
    if ($value === null) {
        return $cache[$cacheKey] = $result;
    }
    if (!astronomyContentNonEmptyString($value)) {
        $result['errors'][] = astronomyContentError($field, 'La imagen debe ser null o un nombre de archivo no vacío.');
        return $cache[$cacheKey] = $result;
    }
    $requested = trim($value);
    if (basename($requested) !== $requested || preg_match('/^[A-Za-z0-9._-]+\.(?:jpe?g|png|webp)$/i', $requested) !== 1) {
        $result['errors'][] = astronomyContentError($field, 'La imagen debe usar un nombre simple y una extensión JPG, JPEG, PNG o WEBP.');
        return $cache[$cacheKey] = $result;
    }
    $available = astronomyContentAvailableImages($directory);
    $selected = in_array($requested, $available, true)
        ? $requested
        : ($available !== [] ? $available[array_rand($available)] : null);
    if ($selected === null) {
        return $cache[$cacheKey] = $result;
    }
    $result['filename'] = $selected;
    $result['url'] = $urlPrefix . rawurlencode($selected);
    $dimensions = @getimagesize($directory . '/' . $selected);
    if (is_array($dimensions)) {
        $result['width'] = (int) $dimensions[0];
        $result['height'] = (int) $dimensions[1];
        $result['orientation'] = $result['height'] > $result['width'] ? 'portrait' : 'landscape';
    }
    if ($selected !== $requested) {
        $result['fallback'] = true;
        $result['warnings'][] = astronomyContentError(
            $field,
            'No se encontró “' . $requested . '”; se utilizó la imagen alternativa “' . $selected . '”.'
        );
    }
    return $cache[$cacheKey] = $result;
}

function astronomyContentProtectedImageHtml(array $image, string $alt = '', string $class = '', string $extraAttributes = ''): string
{
    $url = (string) ($image['url'] ?? '');
    if ($url === '' || !str_starts_with($url, ASTRONOMY_CONTENT_IMAGE_URL_PREFIX)) {
        return '';
    }
    $orientation = ($image['orientation'] ?? null) === 'landscape' ? 'landscape' : 'portrait';
    $classes = trim($class . ' protected-photo protected-photo--' . $orientation . ' js-protected-photo');
    $width = (int) ($image['width'] ?? 0);
    $height = (int) ($image['height'] ?? 0);
    $dimensionAttributes = $width > 0 && $height > 0
        ? ' width="' . $width . '" height="' . $height
            . '" style="--protected-photo-natural-width: ' . $width
            . 'px; --protected-photo-natural-height: ' . $height . 'px;'
            . htmlspecialchars($extraAttributes, ENT_QUOTES, 'UTF-8') . '"'
        : ($extraAttributes !== '' ? ' style="' . htmlspecialchars($extraAttributes, ENT_QUOTES, 'UTF-8') . '"' : '');
    return '<img class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" src="'
        . htmlspecialchars(versionedAssetUrl($url), ENT_QUOTES, 'UTF-8')
        . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')
        . '" loading="lazy" draggable="false"' . $dimensionAttributes . '>';
}

function astronomyContentValidateArticle(string $slug, $raw, string $filename): array
{
    $errors = [];
    $warnings = [];
    if (!is_array($raw)) {
        return [
            'slug' => $slug,
            'filename' => $filename,
            'raw' => null,
            'valid' => false,
            'visible' => false,
            'errors' => [astronomyContentError('archivo', 'El archivo debe devolver un array PHP.')],
            'warnings' => [],
            'anchors' => [],
            'trivias' => [],
            'facts' => [],
        ];
    }
    if (array_key_exists('slug', $raw)) {
        $errors[] = astronomyContentError('slug', 'El slug se obtiene del nombre del archivo y no debe declararse.');
    }
    if (($raw['version'] ?? null) !== 1) {
        $errors[] = astronomyContentError('version', 'La versión obligatoria es 1.');
    }
    if (!is_bool($raw['visible'] ?? null)) {
        $errors[] = astronomyContentError('visible', 'Debe ser un booleano.');
    }
    foreach (['titulo', 'resumen', 'articulo'] as $field) {
        if (!astronomyContentNonEmptyString($raw[$field] ?? null)) {
            $errors[] = astronomyContentError($field, 'El campo es obligatorio y no puede estar vacío.');
        }
    }
    foreach (['palabras_clave', 'relaciones'] as $field) {
        if (!astronomyContentStringList($raw[$field] ?? null)) {
            $errors[] = astronomyContentError($field, 'Debe ser una lista de textos no vacíos.');
        }
    }
    foreach (['trivias', 'sabias_que'] as $field) {
        if (!is_array($raw[$field] ?? null) || !array_is_list($raw[$field])) {
            $errors[] = astronomyContentError($field, 'Debe ser una lista, aunque esté vacía.');
        }
    }
    $image = astronomyContentResolveImage($raw['imagen'] ?? null, 'imagen');
    $errors = array_merge($errors, $image['errors']);
    $warnings = array_merge($warnings, astronomyContentContextualWarnings($image['warnings'], 'Imagen principal'));
    if (
        $image['url'] !== null
        && !astronomyContentImageHasHeroAspectRatio($image['width'], $image['height'])
    ) {
        $warnings[] = astronomyContentError(
            'imagen.proporcion',
            'La imagen principal no tiene proporción 16:9 y podría recortarse.'
        );
    }
    $imagePositionX = astronomyContentImagePosition($raw['imagen_posicion_x'] ?? 50);
    $imagePositionY = astronomyContentImagePosition($raw['imagen_posicion_y'] ?? 50);
    foreach (['imagen_posicion_x', 'imagen_posicion_y'] as $field) {
        if (array_key_exists($field, $raw) && (!is_numeric($raw[$field]) || (float) $raw[$field] < 0 || (float) $raw[$field] > 100)) {
            $warnings[] = astronomyContentError($field, 'Debe ser un porcentaje entre 0 y 100; se utilizó 50.');
        }
    }
    $markdown = is_string($raw['articulo'] ?? null) ? $raw['articulo'] : '';
    $anchors = astronomyContentMarkdownAnchors($markdown);
    $components = astronomyContentMarkdownComponents($markdown);
    preg_match_all('/\[\[[^\]]*\]\]/', $markdown, $allMarkers);
    foreach ($allMarkers[0] ?? [] as $marker) {
        if (
            preg_match('/^\[\[(?:imagen|esquema|trivia)(?:[^\]]*)\]\]$/i', $marker) !== 1
            && preg_match('/^\[\[\/?bloque-imagen(?:[^\]]*)\]\]$/i', $marker) !== 1
        ) {
            $errors[] = astronomyContentError('articulo.componentes', 'El Markdown contiene un marcador desconocido o mal formado.');
            break;
        }
    }
    foreach ($components as $index => $component) {
        if ($component['attributes'] === null) {
            $errors[] = astronomyContentError('articulo.componentes.' . $index, 'El marcador tiene atributos inválidos.');
            continue;
        }
        if ($component['type'] === 'imagen') {
            $requestedAlignment = $component['attributes']['alineacion'] ?? 'centro';
            $components[$index]['alignment'] = astronomyContentImageAlignment($requestedAlignment);
            $requestedAlignmentNormalized = strtolower(trim((string) $requestedAlignment));
            if (array_key_exists('alineacion', $component['attributes']) && astronomyContentDebugEnabled()) {
                $obsolete = in_array($requestedAlignmentNormalized, ['derecha', 'izquierda'], true);
                $invalid = $components[$index]['alignment'] !== $requestedAlignmentNormalized;
                $warnings[] = array_merge(
                    astronomyContentError(
                        'articulo.componentes.' . $index . '.alineacion',
                        $obsolete
                            ? 'La alineación lateral de [[imagen]] está obsoleta; se renderizó centrada. Migrá este contenido a [[bloque-imagen]].'
                            : ($invalid
                                ? 'La alineación “' . (string) $requestedAlignment . '” no es válida; se utilizó “centro”.'
                                : '')
                    ),
                    ['component' => $index + 1, 'line' => $component['line'], 'kind' => 'embedded_image_alignment']
                );
                if (!$obsolete && !$invalid) {
                    array_pop($warnings);
                }
            }
            $resolution = astronomyContentResolveImage(
                $component['attributes']['src'] ?? null,
                'articulo.componentes.' . $index . '.src'
            );
            $components[$index]['image'] = $resolution;
            $errors = array_merge($errors, $resolution['errors']);
            $warnings = array_merge(
                $warnings,
                astronomyContentContextualWarnings(
                    $resolution['warnings'],
                    'Imagen embebida en el artículo (componente ' . ($index + 1) . ', línea aproximada ' . $component['line'] . ')',
                    ['component' => $index + 1, 'line' => $component['line'], 'kind' => 'embedded_image']
                )
            );
        }
    }
    $imageBlockAnalysis = astronomyContentMarkdownImageBlocks($markdown);
    foreach ($imageBlockAnalysis['issues'] as $issueIndex => $issue) {
        $warnings[] = array_merge(
            astronomyContentError('articulo.bloques_imagen.' . $issueIndex, $issue['message']),
            ['line' => $issue['line'], 'kind' => 'image_block_' . $issue['kind']]
        );
    }
    foreach ($imageBlockAnalysis['blocks'] as $blockIndex => $block) {
        if (!is_array($block['attributes'])) {
            $warnings[] = array_merge(
                astronomyContentError('articulo.bloques_imagen.' . $blockIndex, 'El bloque tiene atributos inválidos y se mostrará como texto normal.'),
                ['line' => $block['line'], 'kind' => 'image_block_attributes']
            );
            continue;
        }
        $requestedPosition = $block['attributes']['posicion'] ?? 'derecha';
        $position = astronomyContentImageBlockPosition($requestedPosition);
        if ($position !== strtolower(trim((string) $requestedPosition))) {
            $warnings[] = array_merge(
                astronomyContentError(
                    'articulo.bloques_imagen.' . $blockIndex . '.posicion',
                    'La posición “' . (string) $requestedPosition . '” no es válida; se utilizó “derecha”.'
                ),
                ['line' => $block['line'], 'kind' => 'image_block_position']
            );
        }
        if ($block['content'] === '') {
            $warnings[] = array_merge(
                astronomyContentError('articulo.bloques_imagen.' . $blockIndex . '.contenido', 'El bloque no contiene texto asociado.'),
                ['line' => $block['line'], 'kind' => 'image_block_empty']
            );
        }
        $resolution = astronomyContentResolveImage(
            $block['attributes']['src'] ?? null,
            'articulo.bloques_imagen.' . $blockIndex . '.src'
        );
        $errors = array_merge($errors, $resolution['errors']);
        $warnings = array_merge(
            $warnings,
            astronomyContentContextualWarnings(
                $resolution['warnings'],
                'Imagen del bloque (línea aproximada ' . $block['line'] . ')',
                ['line' => $block['line'], 'kind' => 'image_block_image']
            )
        );
    }
    return [
        'slug' => $slug,
        'filename' => $filename,
        'raw' => $raw,
        'valid' => $errors === [],
        'visible' => ($raw['visible'] ?? false) === true,
        'errors' => $errors,
        'warnings' => $warnings,
        'anchors' => $anchors,
        'components' => $components,
        'image' => $image,
        'image_position_x' => $imagePositionX,
        'image_position_y' => $imagePositionY,
        'trivias' => [],
        'facts' => [],
    ];
}

function astronomyContentValidateTrivia($raw, string $slug, int $index): array
{
    $errors = [];
    $warnings = [];
    if (!is_array($raw)) {
        $errors[] = astronomyContentError('trivias.' . $index, 'La trivia debe ser un array.');
        $raw = [];
    }
    unset($raw['referencia']);
    if (!astronomyContentNonEmptyString($raw['id'] ?? null)) {
        $errors[] = astronomyContentError('trivias.' . $index . '.id', 'El identificador es obligatorio.');
    }
    if (!is_bool($raw['visible'] ?? null)) {
        $errors[] = astronomyContentError('trivias.' . $index . '.visible', 'Debe ser un booleano.');
    }
    if (!astronomyContentNonEmptyString($raw['pregunta'] ?? null)) {
        $errors[] = astronomyContentError('trivias.' . $index . '.pregunta', 'La pregunta es obligatoria.');
    }
    $image = astronomyContentResolveImage($raw['imagen'] ?? null, 'trivias.' . $index . '.imagen');
    $errors = array_merge($errors, $image['errors']);
    $triviaLabel = astronomyContentNonEmptyString($raw['id'] ?? null) ? ' “' . trim($raw['id']) . '”' : '';
    $warnings = array_merge($warnings, astronomyContentContextualWarnings($image['warnings'], 'Imagen de trivia' . $triviaLabel));
    $options = $raw['opciones'] ?? null;
    $correct = 0;
    if (!is_array($options) || !array_is_list($options) || count($options) < 2) {
        $errors[] = astronomyContentError('trivias.' . $index . '.opciones', 'Debe haber al menos dos opciones.');
    } else {
        foreach ($options as $optionIndex => $option) {
            if (!is_array($option) || !astronomyContentNonEmptyString($option['texto'] ?? null)) {
                $errors[] = astronomyContentError('trivias.' . $index . '.opciones.' . $optionIndex, 'Cada opción necesita texto.');
            }
            if (is_array($option) && array_key_exists('explicacion', $option)) {
                if (!astronomyContentNonEmptyString($option['explicacion'])) {
                    $errors[] = astronomyContentError('trivias.' . $index . '.opciones.' . $optionIndex . '.explicacion', 'La explicación no puede estar vacía.');
                } else {
                    $correct++;
                }
            }
        }
        if ($correct !== 1) {
            $errors[] = astronomyContentError('trivias.' . $index . '.opciones', 'Debe existir exactamente una opción correcta con explicación.');
        }
    }
    return [
        'type' => 'trivia',
        'source_slug' => $slug,
        'id' => is_string($raw['id'] ?? null) ? trim($raw['id']) : 'trivia-' . $index,
        'raw' => $raw,
        'visible' => ($raw['visible'] ?? false) === true,
        'valid' => $errors === [],
        'errors' => $errors,
        'warnings' => $warnings,
        'image' => $image,
    ];
}

function astronomyContentValidateFact($raw, string $slug, int $index): array
{
    $errors = [];
    $warnings = [];
    if (!is_array($raw)) {
        $errors[] = astronomyContentError('sabias_que.' . $index, 'La entrada debe ser un array.');
        $raw = [];
    }
    unset($raw['referencia']);
    foreach (['id', 'titulo', 'respuesta'] as $field) {
        if (!astronomyContentNonEmptyString($raw[$field] ?? null)) {
            $errors[] = astronomyContentError('sabias_que.' . $index . '.' . $field, 'El campo es obligatorio.');
        }
    }
    if (!is_bool($raw['visible'] ?? null)) {
        $errors[] = astronomyContentError('sabias_que.' . $index . '.visible', 'Debe ser un booleano.');
    }
    $image = astronomyContentResolveImage($raw['imagen'] ?? null, 'sabias_que.' . $index . '.imagen');
    $errors = array_merge($errors, $image['errors']);
    $factLabel = astronomyContentNonEmptyString($raw['id'] ?? null) ? ' “' . trim($raw['id']) . '”' : '';
    $warnings = array_merge($warnings, astronomyContentContextualWarnings($image['warnings'], 'Imagen de “Sabías que…”' . $factLabel));
    return [
        'type' => 'fact',
        'source_slug' => $slug,
        'id' => is_string($raw['id'] ?? null) ? trim($raw['id']) : 'fact-' . $index,
        'raw' => $raw,
        'visible' => ($raw['visible'] ?? false) === true,
        'valid' => $errors === [],
        'errors' => $errors,
        'warnings' => $warnings,
        'image' => $image,
    ];
}

function astronomyLoadContentCatalogFromDatabase(?callable $connectionFactory = null): array
{
    $homeProfileEnabled = ($GLOBALS['home_page_profile_content_detail_enabled'] ?? false) === true;
    $homeProfile = ['conexión MySQL' => 0.0, 'listado de artículos' => 0.0, 'carga SQL por artículo' => 0.0, 'validación de artículos' => 0.0, 'trivias/hechos y diagnósticos' => 0.0];
    $catalog = ['articles' => [], 'trivias' => [], 'facts' => [], 'diagnostics' => [], 'warnings' => []];
    if (!isContentEnabled() && !astronomyContentAdminPreviewEnabled()) {
        return $catalog;
    }

    $connectionFactory ??= static fn(): PDO => getWebDatabaseConnection();

    try {
        $profileStarted = hrtime(true);
        $connection = $connectionFactory();
        if ($homeProfileEnabled) {
            $homeProfile['conexión MySQL'] = (hrtime(true) - $profileStarted) / 1_000_000;
        }
        if (!$connection instanceof PDO) {
            throw new RuntimeException('La conexión de contenidos no devolvió un objeto PDO válido.');
        }

        $profileStarted = hrtime(true);
        $rows = astronomyContentDbListArticles($connection);
        if ($homeProfileEnabled) {
            $homeProfile['listado de artículos'] = (hrtime(true) - $profileStarted) / 1_000_000;
        }
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $profileStarted = hrtime(true);
            $loaded = astronomyContentDbLoadArticleRaw($connection, $slug);
            if ($homeProfileEnabled) {
                $homeProfile['carga SQL por artículo'] += (hrtime(true) - $profileStarted) / 1_000_000;
            }
            if ($loaded === null || !is_array($loaded['raw'] ?? null)) {
                $catalog['diagnostics'][] = [
                    'type' => 'article',
                    'slug' => $slug,
                    'errors' => [astronomyContentError('mysql', 'No se pudo cargar el artículo desde la base de datos.')],
                ];
                continue;
            }
            $profileStarted = hrtime(true);
            $article = astronomyContentValidateArticle($slug, $loaded['raw'], $slug . '.php');
            if ($homeProfileEnabled) {
                $homeProfile['validación de artículos'] += (hrtime(true) - $profileStarted) / 1_000_000;
            }
            $article['source'] = 'mysql';
            $catalog['articles'][$slug] = $article;
        }
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas content load error [mysql]: ' . $exception->getMessage());
        $catalog['diagnostics'][] = [
            'type' => 'catalog',
            'slug' => null,
            'errors' => [astronomyContentError('mysql', 'No se pudieron cargar los contenidos en este momento.')],
        ];
        return $catalog;
    }

    $profileStarted = hrtime(true);
    foreach ($catalog['articles'] as $slug => &$article) {
        $raw = is_array($article['raw']) ? $article['raw'] : [];
        $triviaIds = [];
        $sourceTrivias = is_array($raw['trivias'] ?? null) ? $raw['trivias'] : [];
        foreach ($sourceTrivias as $index => $triviaRaw) {
            $trivia = astronomyContentValidateTrivia($triviaRaw, $slug, $index);
            if (isset($triviaIds[$trivia['id']])) {
                $trivia['errors'][] = astronomyContentError('trivias.' . $index . '.id', 'El identificador está repetido dentro del artículo.');
            }
            $triviaIds[$trivia['id']] = true;
            $trivia['valid'] = $trivia['errors'] === [];
            $article['trivias'][$trivia['id']] = $trivia;
            $catalog['trivias'][] = $trivia;
        }
        $factIds = [];
        $sourceFacts = is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : [];
        foreach ($sourceFacts as $index => $factRaw) {
            $fact = astronomyContentValidateFact($factRaw, $slug, $index);
            if (isset($factIds[$fact['id']])) {
                $fact['errors'][] = astronomyContentError('sabias_que.' . $index . '.id', 'El identificador está repetido dentro del artículo.');
            }
            $factIds[$fact['id']] = true;
            $fact['valid'] = $fact['errors'] === [];
            $article['facts'][$fact['id']] = $fact;
            $catalog['facts'][] = $fact;
        }
        foreach ($article['components'] ?? [] as $index => $component) {
            if ($component['type'] === 'trivia' && is_array($component['attributes'])) {
                $id = $component['attributes']['id'] ?? '';
                if (!isset($article['trivias'][$id]) || $article['trivias'][$id]['valid'] !== true) {
                    $article['errors'][] = astronomyContentError('articulo.componentes.' . $index, 'La trivia incrustada no existe o tiene errores.');
                    $article['valid'] = false;
                }
            }
        }
        if (!$article['valid']) {
            $catalog['diagnostics'][] = ['type' => 'article', 'slug' => $slug, 'errors' => $article['errors']];
        }
        if (($article['warnings'] ?? []) !== []) {
            $catalog['warnings'][] = ['type' => 'article', 'slug' => $slug, 'warnings' => $article['warnings']];
        }
    }
    unset($article);

    foreach (array_merge($catalog['trivias'], $catalog['facts']) as $entry) {
        if (!$entry['valid']) {
            $catalog['diagnostics'][] = ['type' => $entry['type'], 'slug' => $entry['source_slug'] . '#' . $entry['id'], 'errors' => $entry['errors']];
        }
        if (($entry['warnings'] ?? []) !== []) {
            $catalog['warnings'][] = ['type' => $entry['type'], 'slug' => $entry['source_slug'] . '#' . $entry['id'], 'warnings' => $entry['warnings']];
        }
    }

    ksort($catalog['articles']);
    if ($homeProfileEnabled) {
        $homeProfile['trivias/hechos y diagnósticos'] = (hrtime(true) - $profileStarted) / 1_000_000;
        $GLOBALS['home_page_profile_content_detail'] = $homeProfile;
    }
    return $catalog;
}

function astronomyLoadContentCatalog(?callable $connectionFactory = null): array
{
    return astronomyLoadContentCatalogFromDatabase($connectionFactory);
}

function astronomyContentAdminPreviewEnabled(): bool
{
    if (function_exists('storeAdminHasValidSessionCookie')) {
        return storeAdminHasValidSessionCookie();
    }
    return false;
}

function astronomyContentVisibleArticles(array $catalog): array
{
    return array_values(array_filter($catalog['articles'], static fn(array $article): bool => $article['valid'] && $article['visible']));
}

function astronomyContentSearchNormalize(string $value): string
{
    $value = strtr(trim($value), [
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]);
    $value = strtolower($value);
    $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function astronomyContentSearchLimitQuery(string $query): string
{
    $query = trim($query);
    if (preg_match('/^.{0,120}/us', $query, $match) === 1) {
        return $match[0];
    }
    return substr($query, 0, 120);
}

function astronomyContentSearchArticles(array $articles, string $query): array
{
    $normalizedQuery = astronomyContentSearchNormalize(astronomyContentSearchLimitQuery($query));
    if ($normalizedQuery === '') {
        return array_values($articles);
    }
    $terms = array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $normalizedQuery) ?: [])));
    if ($terms === []) {
        return [];
    }

    $ranked = [];
    foreach (array_values($articles) as $position => $article) {
        if (($article['valid'] ?? false) !== true || ($article['visible'] ?? false) !== true) {
            continue;
        }
        $raw = is_array($article['raw'] ?? null) ? $article['raw'] : [];
        $fields = [
            'title' => astronomyContentSearchNormalize((string) ($raw['titulo'] ?? '')),
            'keywords' => astronomyContentSearchNormalize(implode(' ', is_array($raw['palabras_clave'] ?? null) ? $raw['palabras_clave'] : [])),
            'summary' => astronomyContentSearchNormalize((string) ($raw['resumen'] ?? '')),
            'markdown' => astronomyContentSearchNormalize((string) ($raw['articulo'] ?? '')),
        ];
        $combined = implode(' ', $fields);
        if (count(array_filter($terms, static fn(string $term): bool => str_contains($combined, $term))) !== count($terms)) {
            continue;
        }

        $weights = ['title' => 1000000, 'keywords' => 10000, 'summary' => 100, 'markdown' => 1];
        $score = 0;
        foreach ($fields as $field => $text) {
            if (str_contains($text, $normalizedQuery)) {
                $score += $weights[$field] * 2;
            }
            foreach ($terms as $term) {
                if (str_contains($text, $term)) {
                    $score += $weights[$field];
                }
            }
        }
        $ranked[] = ['article' => $article, 'score' => $score, 'position' => $position];
    }

    usort($ranked, static fn(array $left, array $right): int =>
        ($right['score'] <=> $left['score']) ?: ($left['position'] <=> $right['position'])
    );
    return array_column($ranked, 'article');
}

function astronomyContentRandomEntry(array $catalog, string $type): ?array
{
    if (!isContentEnabled()) {
        return null;
    }
    if ($type === 'trivia' && !astronomySiteHomeBlockEnabled('trivia')) {
        return null;
    }
    if ($type === 'fact' && !astronomySiteHomeBlockEnabled('sabias_que')) {
        return null;
    }

    $entries = $type === 'trivia' ? $catalog['trivias'] : $catalog['facts'];
    if (astronomyContentDebugEnabled()) {
        $invalid = array_values(array_filter($entries, static fn(array $entry): bool => !$entry['valid']));
        if ($invalid !== []) {
            return ['diagnostic' => $invalid[array_rand($invalid)]];
        }
    }
    $valid = array_values(array_filter($entries, static function (array $entry) use ($catalog): bool {
        if (!$entry['valid'] || !$entry['visible']) {
            return false;
        }
        $parent = $catalog['articles'][$entry['source_slug']] ?? null;
        return is_array($parent) && ($parent['valid'] ?? false) === true && ($parent['visible'] ?? false) === true;
    }));
    if ($valid === []) {
        return astronomyContentDebugEnabled()
            ? ['diagnostic' => [
                'errors' => [astronomyContentError($type, 'No quedó ningún elemento válido y visible para este tipo de tarjeta.')],
            ]]
            : null;
    }
    $selected = $valid[array_rand($valid)];
    if ($type === 'trivia') {
        $options = $selected['raw']['opciones'];
        shuffle($options);
        $selected['raw']['opciones'] = $options;
    }
    return $selected;
}

function astronomyContentRandomTrivia(array $catalog): ?array
{
    return astronomyContentRandomEntry($catalog, 'trivia');
}

function astronomyContentRandomFact(array $catalog): ?array
{
    return astronomyContentRandomEntry($catalog, 'fact');
}

function astronomyContentArticleUrl(string $slug, string $anchor = ''): string
{
    return astronomyInternalUrl(
        'contenido.php?slug=' . rawurlencode($slug)
        . ($anchor !== '' ? '#' . rawurlencode($anchor) : '')
    );
}

function astronomyContentEntryArticleUrl(array $catalog, array $entry): ?string
{
    $sourceSlug = (string) ($entry['source_slug'] ?? '');
    $article = $catalog['articles'][$sourceSlug] ?? null;
    if (
        preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $sourceSlug) !== 1
        || !is_array($article)
        || ($article['valid'] ?? false) !== true
        || ($article['visible'] ?? false) !== true
    ) {
        return null;
    }
    return astronomyContentArticleUrl($sourceSlug);
}

function renderAstronomyContentDiagnostic(array $diagnostic, string $heading = 'Contenido con errores'): void
{
    if (!astronomyContentDebugEnabled()) {
        return;
    }
    ?>
    <aside class="content-diagnostic" role="status">
        <strong><?= htmlspecialchars($heading) ?></strong>
        <?php if (isset($diagnostic['slug'])): ?><p><?= htmlspecialchars((string) $diagnostic['slug']) ?></p><?php endif; ?>
        <ul><?php foreach (($diagnostic['errors'] ?? []) as $error): ?><li><code><?= htmlspecialchars((string) ($error['field'] ?? 'contenido')) ?></code>: <?= htmlspecialchars((string) ($error['message'] ?? 'Error desconocido.')) ?></li><?php endforeach; ?></ul>
    </aside>
    <?php
}

function renderAstronomyContentWarning(array $warning): void
{
    if (!astronomyContentDebugEnabled()) {
        return;
    }
    ?>
    <aside class="content-diagnostic content-diagnostic--warning" role="status">
        <strong>Advertencia de contenido</strong>
        <?php if (isset($warning['slug'])): ?><p><?= htmlspecialchars((string) $warning['slug']) ?></p><?php endif; ?>
        <ul><?php foreach (($warning['warnings'] ?? []) as $entry): ?><li><code><?= htmlspecialchars((string) ($entry['field'] ?? 'imagen')) ?></code>: <?= htmlspecialchars((string) ($entry['message'] ?? 'Se utilizó una alternativa.')) ?></li><?php endforeach; ?></ul>
    </aside>
    <?php
}

function renderAstronomyHomeContentCards(array $catalog, bool $triviaEnabled = true, bool $factEnabled = true): void
{
    $trivia = $triviaEnabled ? astronomyContentRandomTrivia($catalog) : null;
    $fact = $factEnabled ? astronomyContentRandomFact($catalog) : null;
    $triviaArticleUrl = is_array($trivia) && !isset($trivia['diagnostic']) ? astronomyContentEntryArticleUrl($catalog, $trivia) : null;
    $factArticleUrl = is_array($fact) && !isset($fact['diagnostic']) ? astronomyContentEntryArticleUrl($catalog, $fact) : null;
    if ($trivia === null && $fact === null) {
        return;
    }
    ?>
    <section class="home-v2-content-cards" aria-label="Contenidos para descubrir">
        <?php if ($trivia !== null): ?>
            <article class="home-v2-card content-home-card">
                <?php if (isset($trivia['diagnostic'])): ?>
                    <?php renderAstronomyContentDiagnostic($trivia['diagnostic'], 'Trivia con errores'); ?>
                <?php else: ?>
                    <?php
                    $triviaDomId = 'content-trivia-' . substr(hash('sha256', (string) $trivia['source_slug'] . '|' . (string) $trivia['id']), 0, 12);
                    $correctExplanation = '';
                    foreach ($trivia['raw']['opciones'] as $option) {
                        if (trim((string) ($option['explicacion'] ?? '')) !== '') {
                            $correctExplanation = (string) $option['explicacion'];
                            break;
                        }
                    }
                    ?>
                    <?php if (($trivia['warnings'] ?? []) !== []): ?><?php renderAstronomyContentWarning(['slug' => $trivia['source_slug'] . '#' . $trivia['id'], 'warnings' => $trivia['warnings']]); ?><?php endif; ?>
                    <p class="eyebrow">TRIVIA</p>
                    <?= astronomyContentProtectedImageHtml($trivia['image'], '') ?>
                    <div class="content-trivia" data-content-trivia>
                        <h2 id="<?= htmlspecialchars($triviaDomId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $trivia['raw']['pregunta']) ?></h2>
                        <div class="content-trivia__options" role="group" aria-labelledby="<?= htmlspecialchars($triviaDomId, ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach ($trivia['raw']['opciones'] as $option): ?>
                                <?php $isCorrect = trim((string) ($option['explicacion'] ?? '')) !== ''; ?>
                                <button type="button" class="interactive-choice" data-trivia-option data-correct="<?= $isCorrect ? 'true' : 'false' ?>">
                                    <span><?= htmlspecialchars((string) ($option['texto'] ?? '')) ?></span>
                                    <span class="interactive-choice__status" data-trivia-option-status hidden></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="interactive-feedback" data-trivia-feedback aria-live="polite" aria-atomic="true" tabindex="-1" hidden>
                            <strong data-trivia-result></strong>
                            <p><?= htmlspecialchars($correctExplanation) ?></p>
                            <?php if ($triviaArticleUrl !== null): ?><a class="content-card__read-link" href="<?= htmlspecialchars($triviaArticleUrl, ENT_QUOTES, 'UTF-8') ?>">Leer más sobre este tema <span aria-hidden="true">→</span></a><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        <?php endif; ?>
        <?php if ($fact !== null): ?>
            <article class="home-v2-card content-home-card">
                <?php if (isset($fact['diagnostic'])): ?>
                    <?php renderAstronomyContentDiagnostic($fact['diagnostic'], '“Sabías que…” con errores'); ?>
                <?php else: ?>
                    <?php if (($fact['warnings'] ?? []) !== []): ?><?php renderAstronomyContentWarning(['slug' => $fact['source_slug'] . '#' . $fact['id'], 'warnings' => $fact['warnings']]); ?><?php endif; ?>
                    <p class="eyebrow">SABÍAS QUE…</p>
                    <?= astronomyContentProtectedImageHtml($fact['image'], '') ?>
                    <h2><?= htmlspecialchars((string) $fact['raw']['titulo']) ?></h2>
                    <p><?= htmlspecialchars((string) $fact['raw']['respuesta']) ?></p>
                    <?php if ($factArticleUrl !== null): ?><a class="content-card__read-link" href="<?= htmlspecialchars($factArticleUrl, ENT_QUOTES, 'UTF-8') ?>">Ver artículo <span aria-hidden="true">→</span></a><?php endif; ?>
                <?php endif; ?>
            </article>
        <?php endif; ?>
    </section>
    <?php
}

function astronomyContentInlineMarkdown(string $text): string
{
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $escaped = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)\s]+)\)/',
        static function (array $match): string {
            $decodedUrl = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
            $isSafe = preg_match('~^(?:https?://|/|#|[a-z0-9][a-z0-9._/-]*(?:\?[^\s]*)?)~i', $decodedUrl) === 1
                && preg_match('~^[a-z][a-z0-9+.-]*:~i', $decodedUrl) !== 1;
            if (preg_match('~^https?://~i', $decodedUrl) === 1) {
                $isSafe = true;
            }
            return $isSafe
                ? '<a href="' . htmlspecialchars($decodedUrl, ENT_QUOTES, 'UTF-8') . '">' . $match[1] . '</a>'
                : $match[0];
        },
        $escaped
    ) ?? $escaped;
    $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;
    return $escaped;
}

function astronomyContentRenderMarkdownFragment(string $markdown): string
{
    $lines = preg_split('/\R/', trim($markdown)) ?: [];
    $html = [];
    $paragraph = [];
    $inList = false;
    $flushParagraph = static function () use (&$paragraph, &$html): void {
        if ($paragraph !== []) {
            $html[] = '<p>' . astronomyContentInlineMarkdown(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        }
    };
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $flushParagraph();
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            continue;
        }
        if (preg_match('/^(#{1,6})\s+(.+?)(?:\s+\{#([a-z0-9-]+)\})?$/u', $trimmed, $match) === 1) {
            $flushParagraph();
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $level = strlen($match[1]);
            $id = ($match[3] ?? '') !== '' ? ' id="' . htmlspecialchars($match[3], ENT_QUOTES, 'UTF-8') . '"' : '';
            $html[] = '<h' . $level . $id . '>' . astronomyContentInlineMarkdown($match[2]) . '</h' . $level . '>';
            continue;
        }
        if (preg_match('/^\[\[(imagen|esquema|trivia)([^\]]*)\]\]$/i', $trimmed, $match) === 1) {
            $flushParagraph();
            $type = strtolower($match[1]);
            $attributes = astronomyContentComponentAttributes($match[2]);
            if ($type === 'imagen' && is_array($attributes)) {
                $image = astronomyContentResolveImage($attributes['src'] ?? null, 'articulo.imagen');
                if ($image['url'] !== null) {
                    $alt = htmlspecialchars((string) ($attributes['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $html[] = '<figure class="content-article-image content-article-image--center">'
                        . astronomyContentProtectedImageHtml($image, html_entity_decode($alt, ENT_QUOTES, 'UTF-8'))
                        . '</figure>';
                }
            } else {
                $html[] = '<div class="content-component-placeholder" data-content-component="' . $type . '"><code>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</code></div>';
            }
            continue;
        }
        if (preg_match('/^(?:-{3,}|\*{3,})$/', $trimmed) === 1) {
            $flushParagraph();
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $html[] = '<hr>';
            continue;
        }
        if (preg_match('/^-\s+(.+)$/u', $trimmed, $match) === 1) {
            $flushParagraph();
            if (!$inList) {
                $html[] = '<ul>';
                $inList = true;
            }
            $html[] = '<li>' . astronomyContentInlineMarkdown($match[1]) . '</li>';
            continue;
        }
        $paragraph[] = $trimmed;
    }
    $flushParagraph();
    if ($inList) {
        $html[] = '</ul>';
    }
    return implode("\n", $html);
}

function astronomyContentRenderMarkdown(string $markdown): string
{
    $analysis = astronomyContentMarkdownImageBlocks($markdown);
    $html = [];
    $offset = 0;
    foreach ($analysis['blocks'] as $block) {
        $before = substr($markdown, $offset, $block['offset'] - $offset);
        $before = preg_replace('/^[ \t]*\[\[\/?bloque-imagen[^\]]*\]\][ \t]*\r?$/mi', '', $before) ?? $before;
        $renderedBefore = astronomyContentRenderMarkdownFragment($before);
        if ($renderedBefore !== '') {
            $html[] = $renderedBefore;
        }
        if (!is_array($block['attributes'])) {
            $html[] = astronomyContentRenderMarkdownFragment($block['content']);
            $offset = $block['end_offset'];
            continue;
        }
        $position = astronomyContentImageBlockPosition($block['attributes']['posicion'] ?? 'derecha');
        $image = astronomyContentResolveImage($block['attributes']['src'] ?? null, 'articulo.bloque-imagen');
        $text = astronomyContentRenderMarkdownFragment($block['content']);
        if ($image['url'] === null) {
            $html[] = $text;
        } else {
            $alt = htmlspecialchars((string) ($block['attributes']['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html[] = '<section class="content-image-block content-image-block--'
                . ($position === 'izquierda' ? 'left' : 'right')
                . '"><div class="content-image-block__text">' . $text
                . '</div><figure class="content-image-block__media content-image-block__media--'
                . (($image['orientation'] ?? null) === 'landscape' ? 'landscape' : 'portrait') . '">'
                . astronomyContentProtectedImageHtml($image, html_entity_decode($alt, ENT_QUOTES, 'UTF-8'))
                . '</figure></section>';
        }
        $offset = $block['end_offset'];
    }
    $after = substr($markdown, $offset);
    $after = preg_replace('/^[ \t]*\[\[\/?bloque-imagen[^\]]*\]\][ \t]*\r?$/mi', '', $after) ?? $after;
    $renderedAfter = astronomyContentRenderMarkdownFragment($after);
    if ($renderedAfter !== '') {
        $html[] = $renderedAfter;
    }
    return implode("\n", $html);
}

function astronomyContentArticleBodyMarkdown(string $markdown): string
{
    return preg_replace('/\A(?:\x{FEFF})?[ \t]*#[ \t]+[^\r\n]*(?:\R|$)/u', '', $markdown, 1) ?? $markdown;
}
