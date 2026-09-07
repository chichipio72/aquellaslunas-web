<?php

declare(strict_types=1);

putenv('APP_ENV=local');
putenv('CONTENT_ENABLED_IN_PRODUCTION=false');

require_once __DIR__ . '/../admin/contenidos/editor-core.php';

function adminContentAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function adminContentDeleteArticleBySlug(PDO $connection, string $slug): void
{
    $find = $connection->prepare('SELECT id FROM contenido_articulos WHERE slug = :slug LIMIT 1');
    $find->execute(['slug' => $slug]);
    $articleId = $find->fetchColumn();
    if ($articleId === false) {
        return;
    }

    $listTrivias = $connection->prepare('SELECT id FROM contenido_trivias WHERE articulo_id = :id');
    $listTrivias->execute(['id' => (int) $articleId]);

    $deleteOption = $connection->prepare('DELETE FROM contenido_trivia_opciones WHERE trivia_id = :trivia_id');
    foreach ($listTrivias->fetchAll(PDO::FETCH_COLUMN) as $triviaId) {
        $deleteOption->execute(['trivia_id' => (int) $triviaId]);
    }

    $connection->prepare('DELETE FROM contenido_trivias WHERE articulo_id = :id')->execute(['id' => (int) $articleId]);
    $connection->prepare('DELETE FROM contenido_sabias_que WHERE articulo_id = :id')->execute(['id' => (int) $articleId]);
    $connection->prepare('DELETE FROM contenido_articulos_palabras_clave WHERE articulo_id = :id')->execute(['id' => (int) $articleId]);
    $connection->prepare('DELETE FROM contenido_articulos_relaciones WHERE articulo_id = :id')->execute(['id' => (int) $articleId]);
    $connection->prepare('DELETE FROM contenido_articulos WHERE id = :id')->execute(['id' => (int) $articleId]);
}

$connection = getWebDatabaseConnection();

$cleanup = $connection->query("SELECT slug FROM contenido_articulos WHERE slug LIKE 'test-admin-contenidos-%'");
foreach ($cleanup->fetchAll(PDO::FETCH_COLUMN) as $staleSlug) {
    adminContentDeleteArticleBySlug($connection, (string) $staleSlug);
}

$articles = contentEditorDbListArticles($connection);
$slugs = array_column($articles, 'slug');
$firstArticle = $articles[0] ?? null;
adminContentAssert(is_array($firstArticle) && is_bool($firstArticle['has_main_image'] ?? null), 'El listado no informa si existe imagen principal.');
$requiredSlugs = ['eclipses-lunares', 'fases-de-la-luna', 'pascua', 'semana', 'superluna'];
adminContentAssert(count($articles) >= count($requiredSlugs), 'El listado inicial no contiene los artículos base esperados.');
adminContentAssert(array_diff($requiredSlugs, $slugs) === [], 'Faltan artículos base en el catálogo actual.');
$imageGallery = contentEditorImageGallery();
foreach ($imageGallery as $image) {
    adminContentAssert(is_bool($image['hero_compatible'] ?? null), 'La galería no clasifica imágenes 16:9.');
    adminContentAssert(is_bool($image['vertical'] ?? null), 'La galería no clasifica imágenes verticales.');
    adminContentAssert(!($image['hero_compatible'] && $image['vertical']), 'Una imagen quedó clasificada simultáneamente como 16:9 y vertical.');
}

foreach ($slugs as $slug) {
    $loaded = contentEditorDbLoadArticleRaw($connection, $slug);
    adminContentAssert(is_array($loaded), 'No se pudo cargar el artículo: ' . $slug);
    adminContentAssert(astronomyContentNonEmptyString($loaded['raw']['titulo'] ?? null), 'Título vacío en ' . $slug);
}

$baseSlug = 'test-admin-contenidos-' . bin2hex(random_bytes(4));
$updatedSlug = $baseSlug . '-editado';

adminContentDeleteArticleBySlug($connection, $baseSlug);
adminContentDeleteArticleBySlug($connection, $updatedSlug);

$raw = contentEditorEmptyArticle();
$raw['version'] = 1;
$raw['visible'] = true;
$raw['titulo'] = 'Artículo administrativo ñandú';
$raw['resumen'] = 'Resumen con tildes: órbita, satélite, física.';
$raw['imagen'] = null;
$raw['palabras_clave'] = ['luna', 'administración', 'prueba'];
$raw['relaciones'] = ['pascua', 'semana'];
$raw['articulo'] = "# Artículo administrativo\n\nTexto con acentos y eñes.\n\n[[trivia id=\"tr-01\"]]\n";
$raw['trivias'] = [[
    'id' => 'tr-01',
    'visible' => true,
    'pregunta' => '¿Cuál opción es correcta?',
    'imagen' => null,
    'opciones' => [
        ['texto' => 'Incorrecta'],
        ['texto' => 'Correcta', 'explicacion' => 'Explicación válida con áéíóú.'],
    ],
]];
$raw['sabias_que'] = [[
    'id' => 'sq-01',
    'visible' => true,
    'titulo' => 'Dato curioso',
    'respuesta' => 'Respuesta curiosa.',
    'imagen' => null,
]];

$create = contentEditorDbSaveArticle($connection, $baseSlug, $baseSlug, $raw, true);
adminContentAssert($create['saved'], 'No se pudo crear artículo nuevo: ' . json_encode($create['errors']));

$created = contentEditorDbLoadArticleRaw($connection, $baseSlug);
adminContentAssert(is_array($created), 'No se pudo recargar el artículo recién creado.');
adminContentAssert(($created['raw']['titulo'] ?? '') === $raw['titulo'], 'El título creado no persistió.');
adminContentAssert(($created['raw']['resumen'] ?? '') === $raw['resumen'], 'El resumen creado no persistió UTF-8.');
$exportJson = contentEditorPackageJson($baseSlug, $created['raw']);
$exportValidation = contentEditorValidatePackageJson($exportJson);
adminContentAssert($exportValidation['valid'], 'El JSON exportado no es aceptado por el importador: ' . json_encode($exportValidation['errors']));
adminContentAssert(($exportValidation['slug'] ?? '') === $baseSlug, 'La exportación no preservó el slug.');
adminContentAssert(($exportValidation['raw']['articulo'] ?? '') === $raw['articulo'], 'La exportación no preservó el Markdown.');
adminContentAssert(count($exportValidation['raw']['trivias'] ?? []) === 1, 'La exportación no preservó las trivias.');
adminContentAssert(count($exportValidation['raw']['sabias_que'] ?? []) === 1, 'La exportación no preservó los bloques “Sabías que…”.');

$rawUpdate = $created['raw'];
$rawUpdate['visible'] = false;
$rawUpdate['version'] = 2;
$rawUpdate['palabras_clave'] = ['administración', 'orden-2', 'orden-3'];
$rawUpdate['relaciones'] = ['semana', 'pascua'];
$rawUpdate['trivias'][0]['pregunta'] = 'Pregunta actualizada';
$rawUpdate['trivias'][0]['opciones'] = [
    ['texto' => 'Nueva incorrecta'],
    ['texto' => 'Nueva correcta', 'explicacion' => 'Explicación actualizada'],
];
$rawUpdate['sabias_que'][0]['titulo'] = 'Dato actualizado';

$update = contentEditorDbSaveArticle($connection, $baseSlug, $updatedSlug, $rawUpdate, false);
adminContentAssert($update['saved'], 'No se pudo actualizar el artículo completo: ' . json_encode($update['errors']));

adminContentAssert(contentEditorDbLoadArticleRaw($connection, $baseSlug) === null, 'El slug anterior quedó activo tras la edición.');
$updated = contentEditorDbLoadArticleRaw($connection, $updatedSlug);
adminContentAssert(is_array($updated), 'El artículo actualizado no pudo recargarse por nuevo slug.');
adminContentAssert(($updated['raw']['version'] ?? 0) === 2, 'No se guardó la versión actualizada.');
adminContentAssert(($updated['raw']['visible'] ?? true) === false, 'No se guardó la visibilidad actualizada.');
adminContentAssert(contentEditorDbSetArticleVisibility($connection, $updatedSlug, true), 'No se pudo hacer visible el artículo desde el listado.');
$visibleFromList = contentEditorDbLoadArticleRaw($connection, $updatedSlug);
adminContentAssert(($visibleFromList['raw']['visible'] ?? false) === true, 'El cambio rápido no hizo visible el artículo.');
adminContentAssert(contentEditorDbSetArticleVisibility($connection, $updatedSlug, false), 'No se pudo volver a ocultar el artículo desde el listado.');
adminContentAssert(contentEditorDbSetArticleVisibility($connection, '../slug-invalido', true) === false, 'El cambio rápido aceptó un slug inválido.');
adminContentAssert(contentEditorDbSetArticleVisibility($connection, 'articulo-inexistente', true) === false, 'El cambio rápido informó éxito para un artículo inexistente.');
adminContentAssert(($updated['raw']['trivias'][0]['pregunta'] ?? '') === 'Pregunta actualizada', 'No se guardó la trivia actualizada.');
adminContentAssert(($updated['raw']['sabias_que'][0]['titulo'] ?? '') === 'Dato actualizado', 'No se guardó el bloque “Sabías que…” actualizado.');

$orderWords = $connection->prepare('SELECT palabra_clave FROM contenido_articulos_palabras_clave pk INNER JOIN contenido_articulos a ON a.id = pk.articulo_id WHERE a.slug = :slug ORDER BY pk.orden ASC');
$orderWords->execute(['slug' => $updatedSlug]);
adminContentAssert($orderWords->fetchAll(PDO::FETCH_COLUMN) === ['administración', 'orden-2', 'orden-3'], 'No se preservó el orden de palabras clave.');

$orderRelations = $connection->prepare('SELECT slug_relacionado FROM contenido_articulos_relaciones r INNER JOIN contenido_articulos a ON a.id = r.articulo_id WHERE a.slug = :slug ORDER BY r.orden ASC');
$orderRelations->execute(['slug' => $updatedSlug]);
adminContentAssert($orderRelations->fetchAll(PDO::FETCH_COLUMN) === ['semana', 'pascua'], 'No se preservó el orden de relaciones.');

$reviewExport = contentEditorDbReviewExport($connection);
adminContentAssert(($reviewExport['version'] ?? null) === 1, 'La revisión exportada no declara la versión esperada.');
$reviewSlugs = array_column($reviewExport['articulos'], 'slug');
adminContentAssert(count($reviewSlugs) === count(contentEditorDbListArticles($connection)), 'La revisión no exportó todos los artículos.');
adminContentAssert(in_array($updatedSlug, $reviewSlugs, true), 'La revisión omitió el artículo de prueba.');
$orderedTitles = $connection->query('SELECT titulo FROM contenido_articulos ORDER BY titulo ASC, slug ASC')->fetchAll(PDO::FETCH_COLUMN);
adminContentAssert(array_column($reviewExport['articulos'], 'titulo') === $orderedTitles, 'La revisión no está ordenada alfabéticamente por título.');
$reviewArticle = $reviewExport['articulos'][array_search($updatedSlug, $reviewSlugs, true)];
adminContentAssert($reviewArticle['palabras_clave'] === ['administración', 'orden-2', 'orden-3'], 'La revisión exportó palabras clave incorrectas.');
adminContentAssert($reviewArticle['relaciones'] === ['semana', 'pascua'], 'La revisión exportó relaciones incorrectas.');
adminContentAssert(array_diff(array_keys($reviewArticle), ['slug', 'titulo', 'visible', 'resumen', 'palabras_clave', 'relaciones']) === [], 'La revisión filtró campos no permitidos.');

$reviewPayload = $reviewExport;
$reviewIndex = array_search($updatedSlug, array_column($reviewPayload['articulos'], 'slug'), true);
$reviewPayload['articulos'][$reviewIndex]['titulo'] = 'Este título debe ignorarse';
$reviewPayload['articulos'][$reviewIndex]['visible'] = true;
$reviewPayload['articulos'][$reviewIndex]['resumen'] = 'Este resumen debe ignorarse';
$reviewPayload['articulos'][$reviewIndex]['palabras_clave'] = [' revisión ', 'revisión', '', 'nueva'];
$reviewPayload['articulos'][$reviewIndex]['relaciones'] = ['pascua', '', 'fases-de-la-luna'];
$reviewJson = json_encode($reviewPayload, JSON_UNESCAPED_UNICODE);
$preview = contentEditorDbPreviewReview($connection, $reviewJson);
adminContentAssert($preview['valid'], 'El preview de revisión válida falló: ' . json_encode($preview['errors']));
adminContentAssert($preview['summary']['modificados'] === 1, 'El preview no contó el artículo modificado.');
adminContentAssert($preview['summary']['slugs_inexistentes'] === [], 'El preview válido informó slugs de artículo inexistentes.');
$beforeApply = contentEditorDbLoadArticleRaw($connection, $updatedSlug);
adminContentAssert($beforeApply['raw']['palabras_clave'] === ['administración', 'orden-2', 'orden-3'], 'El preview modificó la base antes de confirmar.');

$applied = contentEditorDbApplyReview($connection, $reviewJson);
adminContentAssert($applied['applied'], 'No se pudo aplicar la revisión válida.');
$afterReview = contentEditorDbLoadArticleRaw($connection, $updatedSlug);
adminContentAssert($afterReview['raw']['palabras_clave'] === ['revisión', 'nueva'], 'La revisión no actualizó o deduplicó las palabras clave.');
adminContentAssert($afterReview['raw']['relaciones'] === ['pascua', 'fases-de-la-luna'], 'La revisión no actualizó o deduplicó las relaciones.');
adminContentAssert($afterReview['raw']['titulo'] === $rawUpdate['titulo'], 'La revisión cambió el título.');
adminContentAssert($afterReview['raw']['resumen'] === $rawUpdate['resumen'], 'La revisión cambió el resumen.');
adminContentAssert($afterReview['raw']['visible'] === false, 'La revisión cambió la visibilidad.');
adminContentAssert($afterReview['raw']['articulo'] === $rawUpdate['articulo'], 'La revisión cambió el Markdown.');
adminContentAssert(contentEditorDbLoadArticleRaw($connection, 'test-admin-contenidos-inexistente') === null, 'La revisión creó un slug inexistente.');

$invalidRelationPayload = $reviewPayload;
$invalidRelationPayload['articulos'][$reviewIndex]['relaciones'] = ['pascua', 'relacion-que-no-existe'];
$beforeInvalidRelation = contentEditorDbLoadArticleRaw($connection, $updatedSlug);
$invalidRelation = contentEditorDbApplyReview($connection, json_encode($invalidRelationPayload, JSON_UNESCAPED_UNICODE));
adminContentAssert(!$invalidRelation['applied'], 'La revisión aceptó una relación hacia un slug inexistente.');
$afterInvalidRelation = contentEditorDbLoadArticleRaw($connection, $updatedSlug);
adminContentAssert($afterInvalidRelation['raw']['relaciones'] === $beforeInvalidRelation['raw']['relaciones'], 'La relación inexistente modificó MySQL.');

$selfRelationPayload = $reviewPayload;
$selfRelationPayload['articulos'][$reviewIndex]['relaciones'] = [$updatedSlug];
adminContentAssert(!contentEditorDbPreviewReview($connection, json_encode($selfRelationPayload))['valid'], 'La revisión aceptó una autorrelación.');

$duplicateRelationPayload = $reviewPayload;
$duplicateRelationPayload['articulos'][$reviewIndex]['relaciones'] = ['pascua', 'pascua'];
adminContentAssert(!contentEditorDbPreviewReview($connection, json_encode($duplicateRelationPayload))['valid'], 'La revisión aceptó una relación duplicada.');

$missingArticlePayload = $reviewPayload;
$missingArticlePayload['articulos'][] = [
    'slug' => 'test-admin-contenidos-inexistente', 'titulo' => 'Inexistente', 'visible' => false,
    'resumen' => '', 'palabras_clave' => ['no-crear'], 'relaciones' => [],
];
$missingArticlePreview = contentEditorDbPreviewReview($connection, json_encode($missingArticlePayload));
adminContentAssert(!$missingArticlePreview['valid'], 'La revisión aceptó un artículo de origen inexistente.');
adminContentAssert($missingArticlePreview['summary']['slugs_inexistentes'] === ['test-admin-contenidos-inexistente'], 'El preview no informó el slug de artículo inexistente.');

$forbiddenPayload = $reviewPayload;
$forbiddenPayload['articulos'][$reviewIndex]['markdown'] = '# No permitido';
$forbidden = contentEditorDbApplyReview($connection, json_encode($forbiddenPayload));
adminContentAssert(!$forbidden['applied'], 'La revisión aceptó un campo fuera del esquema seguro.');
$invalid = contentEditorDbApplyReview($connection, '{json inválido');
adminContentAssert(!$invalid['applied'], 'La revisión aceptó JSON inválido.');
$afterInvalid = contentEditorDbLoadArticleRaw($connection, $updatedSlug);
adminContentAssert($afterInvalid['raw']['palabras_clave'] === ['revisión', 'nueva'], 'Una importación inválida modificó la base.');

$manualInvalidRelation = $rawUpdate;
$manualInvalidRelation['relaciones'] = ['relacion-manual-inexistente'];
adminContentAssert(!contentEditorDbSaveArticle($connection, $updatedSlug, $updatedSlug, $manualInvalidRelation, false)['saved'], 'El editor manual aceptó una relación inexistente.');
$manualSelfRelation = $rawUpdate;
$manualSelfRelation['relaciones'] = [$updatedSlug];
adminContentAssert(!contentEditorDbSaveArticle($connection, $updatedSlug, $updatedSlug, $manualSelfRelation, false)['saved'], 'El editor manual aceptó una autorrelación.');
$manualDuplicateRelation = $rawUpdate;
$manualDuplicateRelation['relaciones'] = ['pascua', 'pascua'];
adminContentAssert(!contentEditorDbSaveArticle($connection, $updatedSlug, $updatedSlug, $manualDuplicateRelation, false)['saved'], 'El editor manual aceptó una relación duplicada.');

$duplicateSlug = contentEditorDbSaveArticle($connection, $updatedSlug, 'pascua', $rawUpdate, false);
adminContentAssert(!$duplicateSlug['saved'], 'Se aceptó un slug duplicado.');

$withoutCorrect = $rawUpdate;
unset($withoutCorrect['trivias'][0]['opciones'][1]['explicacion']);
$withoutCorrectResult = contentEditorDbSaveArticle($connection, $updatedSlug, $updatedSlug, $withoutCorrect, false);
adminContentAssert(!$withoutCorrectResult['saved'], 'Se aceptó trivia sin respuesta correcta.');

$withManyCorrect = $rawUpdate;
$withManyCorrect['trivias'][0]['opciones'][0]['explicacion'] = 'No debería ser correcta';
$withManyCorrectResult = contentEditorDbSaveArticle($connection, $updatedSlug, $updatedSlug, $withManyCorrect, false);
adminContentAssert(!$withManyCorrectResult['saved'], 'Se aceptó trivia con más de una respuesta correcta.');

$afterFailed = contentEditorDbLoadArticleRaw($connection, $updatedSlug);
adminContentAssert(is_array($afterFailed), 'El artículo desapareció después de un guardado fallido.');
adminContentAssert(
    ($afterFailed['raw']['trivias'][0]['pregunta'] ?? '') === 'Pregunta actualizada',
    'Un guardado inválido alteró el estado persistido (rollback lógico falló).'
);

adminContentDeleteArticleBySlug($connection, $updatedSlug);

echo "admin-content-editor: ok\n";
