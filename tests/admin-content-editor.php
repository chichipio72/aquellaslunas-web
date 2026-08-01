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
adminContentAssert(count($articles) === 5, 'El listado inicial no tiene cinco artículos tras la limpieza de pruebas.');
adminContentAssert($slugs === ['eclipses-lunares', 'fases-de-la-luna', 'pascua', 'semana', 'superluna'], 'Los slugs iniciales no coinciden con el catálogo esperado.');

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
adminContentAssert(($updated['raw']['trivias'][0]['pregunta'] ?? '') === 'Pregunta actualizada', 'No se guardó la trivia actualizada.');
adminContentAssert(($updated['raw']['sabias_que'][0]['titulo'] ?? '') === 'Dato actualizado', 'No se guardó el bloque “Sabías que…” actualizado.');

$orderWords = $connection->prepare('SELECT palabra_clave FROM contenido_articulos_palabras_clave pk INNER JOIN contenido_articulos a ON a.id = pk.articulo_id WHERE a.slug = :slug ORDER BY pk.orden ASC');
$orderWords->execute(['slug' => $updatedSlug]);
adminContentAssert($orderWords->fetchAll(PDO::FETCH_COLUMN) === ['administración', 'orden-2', 'orden-3'], 'No se preservó el orden de palabras clave.');

$orderRelations = $connection->prepare('SELECT slug_relacionado FROM contenido_articulos_relaciones r INNER JOIN contenido_articulos a ON a.id = r.articulo_id WHERE a.slug = :slug ORDER BY r.orden ASC');
$orderRelations->execute(['slug' => $updatedSlug]);
adminContentAssert($orderRelations->fetchAll(PDO::FETCH_COLUMN) === ['semana', 'pascua'], 'No se preservó el orden de relaciones.');

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
