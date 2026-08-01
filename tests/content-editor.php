<?php

putenv('APP_ENV=local');
putenv('CONTENT_ENABLED_IN_PRODUCTION=false');

require_once __DIR__ . '/../local-tools/content-editor/editor-core.php';

function contentEditorAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$statusCatalog = [
    'articles' => [
        'valid' => ['errors' => [], 'warnings' => []],
        'warning' => ['errors' => [], 'warnings' => [astronomyContentError('imagen', 'Alternativa.')]],
        'invalid' => ['errors' => [astronomyContentError('titulo', 'Obligatorio.')], 'warnings' => []],
    ],
    'trivias' => [],
    'facts' => [],
];
contentEditorAssert(contentEditorArticleStatus($statusCatalog, 'valid')['state'] === 'valid', 'El estado válido se calculó mal.');
contentEditorAssert(contentEditorArticleStatus($statusCatalog, 'warning')['state'] === 'warning', 'El estado con advertencias se calculó mal.');
contentEditorAssert(contentEditorArticleStatus($statusCatalog, 'invalid')['state'] === 'invalid', 'El estado con errores se calculó mal.');
contentEditorAssert(
    contentEditorArticleIsReadable(['valid' => true, 'visible' => true]),
    'Un artículo válido y visible no quedó disponible para lectura.'
);
contentEditorAssert(
    !contentEditorArticleIsReadable(['valid' => false, 'visible' => true])
        && !contentEditorArticleIsReadable(['valid' => true, 'visible' => false]),
    'El editor enlazó un artículo con errores bloqueantes u oculto.'
);

$canonicalImage = '3ffeca5edbdc335646cf6370c713441eedac5bbe6d6e1d11a0537b370d026148.jpg';
$canonicalHorizontalImage = '301b83e6b4a54232c828b70f983a22177173ad2ce2b606629d9155eb2fe335de.jpg';
$canonicalGallery = contentEditorImageGallery();
contentEditorAssert(in_array($canonicalImage, array_column($canonicalGallery, 'filename'), true), 'La galería no encontró el archivo real de previews/contenido.');
contentEditorAssert(
    ($canonicalGallery[array_search($canonicalImage, array_column($canonicalGallery, 'filename'), true)]['url'] ?? '')
        === 'assets/images/tienda/previews/contenido/' . $canonicalImage,
    'La galería no generó la URL pública canónica.'
);
$canonicalEntry = $canonicalGallery[array_search($canonicalImage, array_column($canonicalGallery, 'filename'), true)];
contentEditorAssert($canonicalEntry['width'] === 225 && $canonicalEntry['height'] === 400, 'La galería no conservó las dimensiones reales 9:16.');
contentEditorAssert(contentEditorImageRequiresFocalPoint(225, 400), 'Una imagen vertical no habilitó el punto focal para la cabecera 16:9.');
contentEditorAssert(!contentEditorImageRequiresFocalPoint(1600, 900), 'Una imagen 16:9 activó un punto focal innecesario.');
contentEditorAssert(astronomyContentImageHasHeroAspectRatio(1700, 1000), 'El límite inferior tolerado para la cabecera fue rechazado.');
contentEditorAssert(astronomyContentImageHasHeroAspectRatio(1850, 1000), 'El límite superior tolerado para la cabecera fue rechazado.');
contentEditorAssert(!astronomyContentImageHasHeroAspectRatio(225, 400), 'Una imagen vertical fue considerada apta para la cabecera.');

$work = sys_get_temp_dir() . '/aquellas-lunas-editor-test-' . bin2hex(random_bytes(6));
$contentDirectory = $work . '/contenido';
$backupDirectory = $work . '/backups';
$imageDirectory = $work . '/imagenes';
mkdir($contentDirectory, 0770, true);
mkdir($imageDirectory, 0770, true);
$validImage = 'seleccion.png';
$otherImage = 'otra.png';
file_put_contents(
    $imageDirectory . '/' . $validImage,
    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
);
copy($imageDirectory . '/' . $validImage, $imageDirectory . '/' . $otherImage);
file_put_contents($imageDirectory . '/falsa.jpg', 'no es una imagen');
mkdir($imageDirectory . '/anidada');
file_put_contents($imageDirectory . '/anidada/ignorada.png', file_get_contents($imageDirectory . '/' . $validImage));
contentEditorAssert(array_column(contentEditorImageGallery($imageDirectory), 'filename') === [$otherImage, $validImage], 'La galería no filtró imágenes reales o recorrió subdirectorios.');
contentEditorAssert(contentEditorImageGallery($work . '/vacia') === [], 'La galería ausente no quedó vacía.');

$raw = contentEditorEmptyArticle();
$raw['visible'] = true;
$raw['titulo'] = 'Artículo de prueba';
$raw['resumen'] = 'Resumen suficiente para probar el editor.';
$raw['palabras_clave'] = ['luna', 'prueba'];
$raw['relaciones'] = ['fase_lunar'];
$raw['imagen'] = $validImage;
$raw['imagen_posicion_x'] = 22;
$raw['imagen_posicion_y'] = 78;
$raw['articulo'] = "# Artículo de prueba\n\n## Sección {#seccion}\n\nTexto.";
$raw['trivias'] = [[
    'id' => 'tr-01',
    'visible' => true,
    'pregunta' => '¿Cuál es la correcta?',
    'imagen' => null,
    'opciones' => [
        ['texto' => 'Incorrecta'],
        ['texto' => 'Correcta', 'explicacion' => 'Esta es la explicación.'],
    ],
    'referencia' => ['articulo' => 'heredada', 'ancla' => 'ancla-antigua'],
]];
$raw['sabias_que'] = [[
    'id' => 'sq-01',
    'visible' => true,
    'titulo' => 'Un dato',
    'respuesta' => 'Una respuesta.',
    'imagen' => null,
    'referencia' => ['articulo' => 'heredada', 'ancla' => 'ancla-antigua'],
]];

$invalidSlug = contentEditorValidateCandidate('Slug inválido', $raw, $contentDirectory, $imageDirectory);
contentEditorAssert(!$invalidSlug['valid'], 'Se aceptó un slug inválido.');

$created = contentEditorSave('articulo-prueba', $raw, true, $contentDirectory, $backupDirectory, $imageDirectory);
contentEditorAssert($created['saved'], 'No se creó el artículo: ' . json_encode($created['errors']));
$path = $contentDirectory . '/articulo-prueba.php';
contentEditorAssert(is_file($path), 'No apareció el archivo creado.');
$source = file_get_contents($path);
contentEditorAssert(str_contains($source, "'articulo' => <<<'MD'"), 'El artículo no se serializó con heredoc.');
contentEditorAssert(!str_contains($source, "'slug' =>"), 'El serializador escribió un campo slug.');
contentEditorAssert(!str_contains($source, "'referencia' =>"), 'El serializador conservó referencias heredadas de trivias o “Sabías que…”.');
$reloadedRaw = (static fn(string $file) => require $file)($path);
contentEditorAssert($reloadedRaw['titulo'] === 'Artículo de prueba', 'El PHP generado no pudo recargarse.');
contentEditorAssert($reloadedRaw['imagen'] === $validImage, 'No persistió únicamente el nombre de la imagen principal.');
contentEditorAssert((float) $reloadedRaw['imagen_posicion_x'] === 22.0 && (float) $reloadedRaw['imagen_posicion_y'] === 78.0, 'No persistió el punto focal.');
$catalog = astronomyLoadContentCatalog($contentDirectory);
contentEditorAssert($catalog['articles']['articulo-prueba']['valid'], 'content-system.php rechazó el archivo generado.');
contentEditorAssert($catalog['trivias'][0]['valid'], 'La trivia generada no fue válida.');
contentEditorAssert($catalog['facts'][0]['valid'], 'El “Sabías que…” generado no fue válido.');

$canonicalRaw = $raw;
$canonicalRaw['imagen'] = $canonicalImage;
$canonicalSaved = contentEditorSave('imagen-real', $canonicalRaw, true, $contentDirectory, $backupDirectory);
contentEditorAssert($canonicalSaved['saved'], 'No se guardó una selección tomada de la galería canónica.');
contentEditorAssert(
    contentEditorLoadRaw('imagen-real', $contentDirectory)['imagen'] === $canonicalImage,
    'La selección real no permaneció al reabrir el artículo.'
);
$canonicalRaw['imagen'] = $canonicalHorizontalImage;
$canonicalResaved = contentEditorSave('imagen-real', $canonicalRaw, false, $contentDirectory, $backupDirectory);
contentEditorAssert($canonicalResaved['saved'], 'No se pudo reemplazar inmediatamente la imagen principal.');
$freshCatalog = astronomyLoadContentCatalog($contentDirectory);
$freshArticle = $freshCatalog['articles']['imagen-real'] ?? null;
contentEditorAssert(
    is_array($freshArticle) && ($freshArticle['raw']['imagen'] ?? null) === $canonicalHorizontalImage,
    'El catálogo posterior al guardado recuperó la imagen principal anterior.'
);
contentEditorAssert(
    count(array_filter(
        $freshArticle['warnings'] ?? [],
        static fn(array $warning): bool => ($warning['field'] ?? '') === 'imagen.proporcion'
    )) === 0,
    'El catálogo posterior al guardado conservó la advertencia de la imagen vertical anterior.'
);

$raw['titulo'] = 'Artículo editado';
$raw['imagen'] = $otherImage;
$edited = contentEditorSave('articulo-prueba', $raw, false, $contentDirectory, $backupDirectory, $imageDirectory);
contentEditorAssert($edited['saved'] && is_file((string) $edited['backup']), 'La edición no creó el backup previo.');
contentEditorAssert(contentEditorLoadRaw('articulo-prueba', $contentDirectory)['titulo'] === 'Artículo editado', 'La edición no se recargó.');
contentEditorAssert(contentEditorLoadRaw('articulo-prueba', $contentDirectory)['imagen'] === $otherImage, 'El cambio de imagen no persistió al reabrir.');

$duplicate = $raw;
$duplicate['trivias'][] = $duplicate['trivias'][0];
$duplicateResult = contentEditorValidateCandidate('articulo-prueba', $duplicate, $contentDirectory, $imageDirectory);
contentEditorAssert(!$duplicateResult['valid'], 'Se aceptaron IDs duplicados.');

$withoutCorrect = $raw;
unset($withoutCorrect['trivias'][0]['opciones'][1]['explicacion']);
$withoutCorrectResult = contentEditorValidateCandidate('articulo-prueba', $withoutCorrect, $contentDirectory, $imageDirectory);
contentEditorAssert(!$withoutCorrectResult['valid'], 'Se aceptó una trivia sin respuesta correcta.');

$manipulated = $raw;
$manipulated['trivias'][0]['imagen'] = '../privada.jpg';
$manipulatedResult = contentEditorValidateCandidate('articulo-prueba', $manipulated, $contentDirectory, $imageDirectory);
contentEditorAssert(!$manipulatedResult['valid'], 'Se aceptó una imagen manipulada fuera de la galería.');

$withoutImages = $raw;
$withoutImages['imagen'] = null;
$withoutImages['trivias'][0]['imagen'] = null;
$withoutImages['sabias_que'][0]['imagen'] = null;
$withoutImagesResult = contentEditorValidateCandidate('articulo-prueba', $withoutImages, $contentDirectory, $imageDirectory);
contentEditorAssert($withoutImagesResult['valid'], 'La ausencia de imágenes invalidó el contenido.');

$normalizedSparseOptions = contentEditorNormalizePost([
    'version' => '1',
    'titulo' => 'Prueba',
    'resumen' => 'Resumen',
    'articulo' => '# Prueba',
    'trivias' => [[
        'correct_option' => '2',
        'explicacion' => 'Correcta después de quitar otra opción.',
        'opciones' => [
            0 => ['texto' => 'Primera'],
            2 => ['texto' => 'Tercera'],
        ],
    ]],
]);
contentEditorAssert(
    ($normalizedSparseOptions['trivias'][0]['opciones'][1]['explicacion'] ?? '') === 'Correcta después de quitar otra opción.',
    'La respuesta correcta se perdió al normalizar opciones con índices discontinuos.'
);
$removedImage = contentEditorNormalizePost(['version' => '1', 'imagen' => '', 'articulo' => '# Prueba']);
contentEditorAssert($removedImage['imagen'] === null, 'Quitar imagen no normalizó la selección como null.');
$invalidPosition = contentEditorNormalizePost([
    'version' => '1',
    'imagen_posicion_x' => '-5',
    'imagen_posicion_y' => 'no-numérico',
    'articulo' => '# Prueba',
]);
contentEditorAssert(
    $invalidPosition['imagen_posicion_x'] === 50.0 && $invalidPosition['imagen_posicion_y'] === 50.0,
    'Las posiciones focales inválidas no volvieron al centro.'
);

contentEditorRemoveTree($work);
putenv('APP_ENV');
putenv('CONTENT_ENABLED_IN_PRODUCTION');
echo "content-editor: ok\n";
