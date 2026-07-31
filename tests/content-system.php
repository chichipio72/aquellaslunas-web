<?php

putenv('APP_ENV=local');
putenv('CONTENT_ENABLED_IN_PRODUCTION=false');

require_once __DIR__ . '/../includes/content-system.php';

function contentSystemAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$catalog = astronomyLoadContentCatalog(__DIR__ . '/fixtures/content');
contentSystemAssert(count($catalog['articles']) === 3, 'No se cargaron únicamente los tres archivos PHP.');
contentSystemAssert(isset($catalog['articles']['valid-content']), 'El slug no se obtuvo del nombre del archivo.');
contentSystemAssert($catalog['articles']['valid-content']['valid'] === true, 'El artículo válido fue rechazado.');
contentSystemAssert(array_key_exists('image', $catalog['articles']['valid-content']), 'El loader no preparó la imagen principal opcional.');
contentSystemAssert($catalog['articles']['valid-content']['image_position_x'] === 50.0, 'No se aplicó la posición X central por defecto.');
contentSystemAssert($catalog['articles']['valid-content']['image_position_y'] === 50.0, 'No se aplicó la posición Y central por defecto.');
contentSystemAssert($catalog['articles']['invalid-content']['valid'] === false, 'El retorno no-array fue aceptado.');
contentSystemAssert($catalog['articles']['throws-content']['valid'] === false, 'Una excepción de contenido escapó del aislamiento.');
contentSystemAssert(count(astronomyContentVisibleArticles($catalog)) === 1, 'El índice no filtró artículos inválidos.');

$trivia = astronomyContentRandomTrivia($catalog);
$fact = astronomyContentRandomFact($catalog);
contentSystemAssert(is_array($trivia) && ($trivia['valid'] ?? false), 'No se obtuvo una trivia válida.');
contentSystemAssert(is_array($fact) && ($fact['valid'] ?? false), 'No se obtuvo un “Sabías que” válido.');
contentSystemAssert(count($trivia['raw']['opciones'] ?? []) === 2, 'La selección aleatoria perdió opciones de trivia.');
contentSystemAssert(
    str_contains(astronomyContentRenderMarkdown($catalog['articles']['valid-content']['raw']['articulo']), 'id="seccion"'),
    'El Markdown no conservó el ancla explícita.'
);
contentSystemAssert(
    str_contains(astronomyContentRenderMarkdown('[[esquema tipo="prueba"]]'), 'content-component-placeholder'),
    'El marcador de componente futuro no fue preparado.'
);
$centeredImageHtml = astronomyContentRenderMarkdown('[[imagen src="LunaTotal.jpg" alt="Luna"]]');
$rightImageHtml = astronomyContentRenderMarkdown('[[imagen src="LunaTotal.jpg" alt="Luna" alineacion="derecha"]]');
$leftImageHtml = astronomyContentRenderMarkdown('[[imagen src="LunaTotal.jpg" alt="Luna" alineacion="izquierda"]]');
contentSystemAssert(
    str_contains($centeredImageHtml, 'content-article-image--center'),
    'Una imagen sin alineación no conservó el comportamiento centrado.'
);
contentSystemAssert(
    str_contains($rightImageHtml, 'content-article-image--center'),
    'La sintaxis lateral anterior no degradó a una imagen independiente segura.'
);
contentSystemAssert(
    str_contains($leftImageHtml, 'content-article-image--center'),
    'La sintaxis lateral izquierda anterior no degradó a una imagen independiente segura.'
);
contentSystemAssert(
    astronomyContentImageAlignment('desconocida') === 'centro',
    'Una alineación desconocida no volvió al centro.'
);
contentSystemAssert(
    substr_count(astronomyContentRenderMarkdown(
        "[[imagen src=\"LunaTotal.jpg\" alineacion=\"derecha\"]]\n"
        . "[[imagen src=\"LunaTotal.jpg\" alineacion=\"izquierda\"]]"
    ), '<figure class="content-article-image ') === 2,
    'Dos imágenes consecutivas no se renderizaron independientemente.'
);
$rightBlockMarkdown = <<<'MD'
[[bloque-imagen src="LunaTotal.jpg" alt="Luna" posicion="derecha"]]

Primer párrafo con [enlace](contenido.php?slug=prueba).

Segundo párrafo.

- Uno
- Dos

[[/bloque-imagen]]
MD;
$rightBlockHtml = astronomyContentRenderMarkdown($rightBlockMarkdown);
contentSystemAssert(
    str_contains($rightBlockHtml, 'content-image-block content-image-block--right')
        && str_contains($rightBlockHtml, 'content-image-block__text')
        && str_contains($rightBlockHtml, '<ul>')
        && str_contains($rightBlockHtml, '<a href="contenido.php?slug=prueba">'),
    'El bloque derecho no conservó párrafos, lista y enlace Markdown.'
);
contentSystemAssert(
    str_contains(
        astronomyContentRenderMarkdown(str_replace('posicion="derecha"', 'posicion="izquierda"', $rightBlockMarkdown)),
        'content-image-block--left'
    ),
    'No se renderizó el bloque con imagen a la izquierda.'
);
contentSystemAssert(
    str_contains(
        astronomyContentRenderMarkdown(str_replace('posicion="derecha"', 'posicion="diagonal"', $rightBlockMarkdown)),
        'content-image-block--right'
    ),
    'Una posición inválida no produjo el fallback seguro a la derecha.'
);
$unclosedBlock = "[[bloque-imagen src=\"LunaTotal.jpg\" posicion=\"derecha\"]]\n\nTexto recuperable.";
contentSystemAssert(
    !str_contains(astronomyContentRenderMarkdown($unclosedBlock), 'content-image-block')
        && str_contains(astronomyContentRenderMarkdown($unclosedBlock), 'Texto recuperable.'),
    'Un bloque sin cerrar no degradó de forma segura a texto normal.'
);
contentSystemAssert(
    str_contains(astronomyContentRenderMarkdown('[Referencia](contenido.php?slug=prueba)'), '<a href="contenido.php?slug=prueba">'),
    'El Markdown no generó enlaces seguros.'
);
contentSystemAssert(
    !str_contains(astronomyContentRenderMarkdown('[Peligro](javascript:alert(1))'), '<a href='),
    'El Markdown aceptó un enlace con esquema inseguro.'
);
contentSystemAssert(
    str_contains(astronomyContentRenderMarkdown("Texto\n\n---\n\n## Sección"), "<hr>\n<h2>"),
    'Un separador no interrumpió el flujo antes de la sección siguiente.'
);
contentSystemAssert(
    trim(astronomyContentArticleBodyMarkdown("# Título\n\nIntroducción\n\n## Desarrollo\n\nTexto")) === "## Desarrollo\n\nTexto",
    'La vista individual no separó el título principal del cuerpo Markdown.'
);
contentSystemAssert(astronomyContentImagePosition(15) === 15.0, 'Se perdió una posición focal válida.');
contentSystemAssert(astronomyContentImagePosition(120) === 50.0, 'Una posición focal fuera de rango no volvió al centro.');
$invalidPositionRaw = $catalog['articles']['valid-content']['raw'];
$invalidPositionRaw['imagen_posicion_x'] = -10;
$invalidPositionRaw['imagen_posicion_y'] = 'fuera-de-rango';
$invalidPositionArticle = astronomyContentValidateArticle('position-test', $invalidPositionRaw, 'position-test.php');
contentSystemAssert($invalidPositionArticle['valid'], 'Una posición focal inválida bloqueó el artículo.');
contentSystemAssert(
    $invalidPositionArticle['image_position_x'] === 50.0
        && $invalidPositionArticle['image_position_y'] === 50.0
        && count($invalidPositionArticle['warnings']) >= 2,
    'Las posiciones inválidas no produjeron fallback central y advertencias.'
);

$existingImage = astronomyContentResolveImage(
    'LunaTotal.jpg',
    'imagen',
    __DIR__ . '/../assets/images/contenido',
    'assets/images/contenido/'
);
contentSystemAssert($existingImage['filename'] === 'LunaTotal.jpg' && !$existingImage['fallback'], 'No se conservó una imagen solicitada existente.');
$landscapeImage = astronomyContentResolveImage(
    'SolParcial.jpg',
    'imagen',
    __DIR__ . '/../assets/images/contenido',
    'assets/images/contenido/'
);
contentSystemAssert(
    $landscapeImage['orientation'] === 'landscape' && $landscapeImage['width'] > $landscapeImage['height'],
    'La orientación horizontal no se obtuvo desde las dimensiones reales.'
);

$fallbackImage = astronomyContentResolveImage(
    'no-existe.jpg',
    'imagen',
    __DIR__ . '/../assets/images/contenido',
    'assets/images/contenido/'
);
contentSystemAssert($fallbackImage['filename'] !== null && $fallbackImage['fallback'], 'No se eligió una alternativa para una imagen ausente.');
contentSystemAssert($fallbackImage['errors'] === [] && $fallbackImage['warnings'] !== [], 'El fallback de imagen se trató como error bloqueante.');

$withoutImages = astronomyContentResolveImage('no-existe.jpg', 'imagen', __DIR__ . '/fixtures/sin-imagenes');
contentSystemAssert($withoutImages['filename'] === null && $withoutImages['errors'] === [], 'Una carpeta ausente invalidó el contenido.');

$unsafeImage = astronomyContentResolveImage('../privada.jpg', 'imagen');
contentSystemAssert($unsafeImage['errors'] !== [], 'Se aceptó una ruta de imagen insegura.');

$realContentImage = '3ffeca5edbdc335646cf6370c713441eedac5bbe6d6e1d11a0537b370d026148.jpg';
$realContentResolution = astronomyContentResolveImage($realContentImage, 'imagen');
contentSystemAssert(
    $realContentResolution['filename'] === $realContentImage
        && $realContentResolution['url'] === 'assets/images/tienda/previews/contenido/' . $realContentImage
        && $realContentResolution['width'] === 225
        && $realContentResolution['height'] === 400
        && $realContentResolution['orientation'] === 'portrait'
        && !$realContentResolution['fallback'],
    'La imagen canónica real no se resolvió desde previews/contenido.'
);
contentSystemAssert(
    astronomyContentImageHasHeroAspectRatio(1600, 900)
        && !astronomyContentImageHasHeroAspectRatio(225, 400),
    'La validación tolerante de proporción 16:9 no distingue cabeceras y fotografías verticales.'
);
contentSystemAssert(
    str_contains(astronomyContentProtectedImageHtml($realContentResolution, 'Luna'), 'class="protected-photo protected-photo--portrait js-protected-photo"')
        && str_contains(astronomyContentProtectedImageHtml($realContentResolution, 'Luna'), 'draggable="false"')
        && str_contains(astronomyContentProtectedImageHtml($realContentResolution, 'Luna'), '--protected-photo-natural-width: 225px')
        && str_contains(astronomyContentProtectedImageHtml($realContentResolution, 'Luna'), '--protected-photo-natural-height: 400px')
        && !str_contains(astronomyContentProtectedImageHtml($realContentResolution, 'Luna'), '<a '),
    'La fotografía pública no recibió protección y límites de tamaño natural reutilizables.'
);
contentSystemAssert(
    astronomyContentProtectedImageHtml(['url' => 'assets/images/original.jpg'], '') === '',
    'El helper público permitió una imagen fuera de la carpeta canónica de previews.'
);
$canonicalFallback = astronomyContentResolveImage('ausente-en-carpeta-canonica.jpg', 'imagen');
contentSystemAssert(
    $canonicalFallback['fallback']
        && str_starts_with((string) $canonicalFallback['url'], 'assets/images/tienda/previews/contenido/'),
    'El fallback aleatorio no utilizó la carpeta canónica.'
);
$mainImageRaw = $catalog['articles']['valid-content']['raw'];
$mainImageRaw['imagen'] = 'principal-ausente.jpg';
$mainImageWarning = astronomyContentValidateArticle('main-warning', $mainImageRaw, 'main-warning.php')['warnings'][0] ?? [];
contentSystemAssert(str_starts_with($mainImageWarning['message'] ?? '', 'Imagen principal:'), 'No se identificó una advertencia de imagen principal.');
$verticalMainRaw = $catalog['articles']['valid-content']['raw'];
$verticalMainRaw['imagen'] = $realContentImage;
$verticalMainWarnings = astronomyContentValidateArticle('vertical-main', $verticalMainRaw, 'vertical-main.php')['warnings'];
contentSystemAssert(
    count(array_filter(
        $verticalMainWarnings,
        static fn(array $warning): bool => ($warning['field'] ?? '') === 'imagen.proporcion'
    )) === 1,
    'Una imagen principal vertical no generó la advertencia no bloqueante de proporción.'
);
$horizontalMainRaw = $catalog['articles']['valid-content']['raw'];
$horizontalMainRaw['imagen'] = '301b83e6b4a54232c828b70f983a22177173ad2ce2b606629d9155eb2fe335de.jpg';
$horizontalMainWarnings = astronomyContentValidateArticle('horizontal-main', $horizontalMainRaw, 'horizontal-main.php')['warnings'];
contentSystemAssert(
    count(array_filter(
        $horizontalMainWarnings,
        static fn(array $warning): bool => ($warning['field'] ?? '') === 'imagen.proporcion'
    )) === 0,
    'Una imagen principal 16:9 válida generó una advertencia de proporción.'
);
$triviaWarning = astronomyContentValidateTrivia([
    'id' => 'tr-warning',
    'visible' => true,
    'pregunta' => 'Pregunta',
    'imagen' => 'trivia-ausente.jpg',
    'opciones' => [
        ['texto' => 'Incorrecta'],
        ['texto' => 'Correcta', 'explicacion' => 'Explicación'],
    ],
], 'warning-test', 0)['warnings'][0] ?? [];
contentSystemAssert(str_starts_with($triviaWarning['message'] ?? '', 'Imagen de trivia'), 'No se identificó una advertencia de trivia.');
$factWarning = astronomyContentValidateFact([
    'id' => 'sq-warning',
    'visible' => true,
    'titulo' => 'Título',
    'respuesta' => 'Respuesta',
    'imagen' => 'fact-ausente.jpg',
], 'warning-test', 0)['warnings'][0] ?? [];
contentSystemAssert(str_starts_with($factWarning['message'] ?? '', 'Imagen de “Sabías que…”'), 'No se identificó una advertencia de “Sabías que…”.');
$realArticleRaw = require __DIR__ . '/../includes/contenido/eclipses-lunares.php';
$realArticleRaw['articulo'] .= "\n\n[[imagen src=\"eclipse-lunar-total.jpg\" alt=\"Prueba\"]]";
$realArticleWithMissingComponent = astronomyContentValidateArticle('eclipses-lunares', $realArticleRaw, 'eclipses-lunares.php');
$embeddedWarning = array_values(array_filter(
    $realArticleWithMissingComponent['warnings'],
    static fn(array $warning): bool => ($warning['kind'] ?? null) === 'embedded_image'
))[0] ?? null;
contentSystemAssert(
    is_array($embeddedWarning)
        && str_contains($embeddedWarning['message'], 'Imagen embebida en el artículo')
        && str_contains($embeddedWarning['message'], 'eclipse-lunar-total.jpg')
        && isset($embeddedWarning['line'], $embeddedWarning['component']),
    'La imagen embebida faltante no recibió un diagnóstico contextual y localizable.'
);
$realArticleRaw['articulo'] = str_replace('eclipse-lunar-total.jpg', $realContentImage, $realArticleRaw['articulo']);
$realArticleWithFixedComponent = astronomyContentValidateArticle('eclipses-lunares', $realArticleRaw, 'eclipses-lunares.php');
contentSystemAssert(
    count(array_filter(
        $realArticleWithFixedComponent['warnings'],
        static fn(array $warning): bool => str_starts_with($warning['field'], 'articulo.componentes.')
    )) === 0,
    'La advertencia del componente no desapareció al elegir una imagen válida.'
);

astronomyContentDebugSession();
$_SESSION[ASTRONOMY_CONTENT_DEBUG_SESSION_KEY] = true;
$invalidAlignmentRaw = $catalog['articles']['valid-content']['raw'];
$invalidAlignmentRaw['articulo'] .= "\n\n[[imagen src=\"LunaTotal.jpg\" alineacion=\"diagonal\"]]";
$invalidAlignmentWarnings = astronomyContentValidateArticle(
    'alignment-warning',
    $invalidAlignmentRaw,
    'alignment-warning.php'
)['warnings'];
contentSystemAssert(
    count(array_filter(
        $invalidAlignmentWarnings,
        static fn(array $warning): bool => ($warning['kind'] ?? null) === 'embedded_image_alignment'
    )) === 1,
    'Debug no registró la advertencia por alineación inválida.'
);
$obsoleteAlignmentRaw = $catalog['articles']['valid-content']['raw'];
$obsoleteAlignmentRaw['articulo'] .= "\n\n[[imagen src=\"LunaTotal.jpg\" alineacion=\"derecha\"]]";
$obsoleteAlignmentWarnings = astronomyContentValidateArticle(
    'obsolete-alignment',
    $obsoleteAlignmentRaw,
    'obsolete-alignment.php'
)['warnings'];
contentSystemAssert(
    count(array_filter(
        $obsoleteAlignmentWarnings,
        static fn(array $warning): bool => str_contains($warning['message'] ?? '', 'obsoleta')
    )) === 1,
    'Debug no recomendó migrar la alineación lateral anterior a bloque-imagen.'
);
$blockValidationRaw = $catalog['articles']['valid-content']['raw'];
$blockValidationRaw['articulo'] .= "\n\n[[bloque-imagen src=\"ausente.jpg\" posicion=\"diagonal\"]]\n\n[[/bloque-imagen]]";
$blockValidationWarnings = astronomyContentValidateArticle(
    'block-validation',
    $blockValidationRaw,
    'block-validation.php'
)['warnings'];
contentSystemAssert(
    count(array_filter($blockValidationWarnings, static fn(array $warning): bool => ($warning['kind'] ?? '') === 'image_block_position')) === 1
        && count(array_filter($blockValidationWarnings, static fn(array $warning): bool => ($warning['kind'] ?? '') === 'image_block_empty')) === 1
        && count(array_filter($blockValidationWarnings, static fn(array $warning): bool => ($warning['kind'] ?? '') === 'image_block_image')) === 1,
    'El bloque no informó posición inválida, contenido vacío e imagen inexistente.'
);
$unclosedValidationRaw = $catalog['articles']['valid-content']['raw'];
$unclosedValidationRaw['articulo'] .= "\n\n" . $unclosedBlock;
$unclosedWarnings = astronomyContentValidateArticle('unclosed-block', $unclosedValidationRaw, 'unclosed-block.php')['warnings'];
contentSystemAssert(
    count(array_filter($unclosedWarnings, static fn(array $warning): bool => ($warning['kind'] ?? '') === 'image_block_missing_close')) === 1,
    'No se diagnosticó con precisión un bloque sin cierre.'
);
$nestedValidationRaw = $catalog['articles']['valid-content']['raw'];
$nestedValidationRaw['articulo'] .= "\n\n[[bloque-imagen src=\"LunaTotal.jpg\"]]\n[[bloque-imagen src=\"LunaTotal.jpg\"]]\nTexto\n[[/bloque-imagen]]";
$nestedWarnings = astronomyContentValidateArticle('nested-block', $nestedValidationRaw, 'nested-block.php')['warnings'];
contentSystemAssert(
    count(array_filter($nestedWarnings, static fn(array $warning): bool => ($warning['kind'] ?? '') === 'image_block_nested')) === 1,
    'No se rechazó el anidamiento de bloques con imagen.'
);
$emptyDebugTrivia = astronomyContentRandomTrivia(['trivias' => [], 'facts' => []]);
contentSystemAssert(
    is_array($emptyDebugTrivia['diagnostic']['errors'] ?? null),
    'Debug no explicó la ausencia total de trivias válidas.'
);
$_SESSION[ASTRONOMY_CONTENT_DEBUG_SESSION_KEY] = false;

ob_start();
renderAstronomyHomeContentCards($catalog);
$homeCards = (string) ob_get_clean();
contentSystemAssert(str_contains($homeCards, 'data-content-trivia'), 'La portada no renderizó la trivia interactiva.');
contentSystemAssert(substr_count($homeCards, 'data-correct="true"') === 1, 'La trivia no identificó exactamente una respuesta correcta después de mezclar.');
contentSystemAssert(!str_contains($homeCards, 'Conocer la respuesta'), 'Persistió el enlace anterior de la trivia.');
contentSystemAssert(str_contains($homeCards, 'class="home-v2-card__link"'), '“Sabías que…” no reutilizó el patrón visual de enlace.');

putenv('APP_ENV=production');
putenv('CONTENT_ENABLED_IN_PRODUCTION=false');
contentSystemAssert(astronomyLoadContentCatalog(__DIR__ . '/fixtures/content')['articles'] === [], 'El loader publicó contenido deshabilitado.');
contentSystemAssert(!astronomyContentDebugAvailable(), 'El modo debug quedó disponible en producción.');

putenv('APP_ENV');
putenv('CONTENT_ENABLED_IN_PRODUCTION');

echo "content-system: ok\n";
