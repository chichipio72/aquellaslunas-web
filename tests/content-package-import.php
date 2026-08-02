<?php

declare(strict_types=1);

require_once __DIR__ . '/../admin/contenidos/editor-core.php';

function packageImportAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function packageImportJson(array $package): string
{
    return json_encode($package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function packageImportDelete(PDO $connection, string $slug): void
{
    $statement = $connection->prepare('DELETE FROM contenido_articulos WHERE slug = :slug');
    $statement->execute(['slug' => $slug]);
}

$exampleJson = contentEditorPackageExampleJson();
$exampleValidation = contentEditorValidatePackageJson($exampleJson);
packageImportAssert($exampleValidation['valid'], 'El ejemplo central no pasa el validador del importador.');
packageImportAssert(count($exampleValidation['raw']['trivias']) === 2, 'El ejemplo no contiene dos trivias.');
packageImportAssert(count($exampleValidation['raw']['sabias_que']) === 2, 'El ejemplo no contiene dos Sabías que.');
packageImportAssert(
    $exampleValidation['raw']['sabias_que'][0]['titulo'] === '¿Sabías que la Luna se aleja lentamente de la Tierra?',
    'La primera frase del ejemplo no es la pregunta esperada.'
);
packageImportAssert(
    $exampleValidation['raw']['sabias_que'][1]['titulo'] === '¿Sabías que siempre vemos casi la misma cara de la Luna?',
    'La segunda frase del ejemplo no es la pregunta esperada.'
);
foreach ($exampleValidation['raw']['sabias_que'] as $fact) {
    packageImportAssert(
        str_starts_with($fact['titulo'], '¿') && str_ends_with($fact['titulo'], '?'),
        'Cada frase de Sabías que del ejemplo debe ser una pregunta completa.'
    );
}

$invalidJson = contentEditorValidatePackageJson('{"version":');
packageImportAssert(!$invalidJson['valid'] && ($invalidJson['errors'][0]['field'] ?? '') === 'json', 'Se aceptó JSON inválido.');

$unknownVersion = contentEditorPackageExample();
$unknownVersion['version'] = 99;
packageImportAssert(!contentEditorValidatePackageJson(packageImportJson($unknownVersion))['valid'], 'Se aceptó una versión desconocida.');

$withoutCorrect = contentEditorPackageExample();
foreach ($withoutCorrect['trivias'][0]['opciones'] as &$option) {
    $option['correcta'] = false;
    $option['explicacion'] = null;
}
unset($option);
$withoutCorrectResult = contentEditorValidatePackageJson(packageImportJson($withoutCorrect));
packageImportAssert(!$withoutCorrectResult['valid'], 'Se aceptó una trivia sin respuesta correcta.');

$manyCorrect = contentEditorPackageExample();
$manyCorrect['trivias'][0]['opciones'][1]['correcta'] = true;
$manyCorrect['trivias'][0]['opciones'][1]['explicacion'] = 'También correcta';
packageImportAssert(!contentEditorValidatePackageJson(packageImportJson($manyCorrect))['valid'], 'Se aceptó una trivia con varias respuestas correctas.');

$duplicateCodes = contentEditorPackageExample();
$duplicateCodes['trivias'][1]['codigo'] = $duplicateCodes['trivias'][0]['codigo'];
$duplicateCodes['sabias_que'][1]['codigo'] = $duplicateCodes['sabias_que'][0]['codigo'];
$duplicateResult = contentEditorValidatePackageJson(packageImportJson($duplicateCodes));
packageImportAssert(!$duplicateResult['valid'], 'Se aceptaron códigos duplicados.');
packageImportAssert(count(array_filter($duplicateResult['errors'], static fn(array $error): bool => str_contains((string) ($error['message'] ?? ''), 'duplicado'))) >= 2, 'No se informaron ambos códigos duplicados.');

$invalidUtf8 = contentEditorValidatePackageJson("{\"version\":1,\"articulo\":\"\xC3\"}");
packageImportAssert(!$invalidUtf8['valid'] && ($invalidUtf8['errors'][0]['field'] ?? '') === 'json', 'Se aceptó texto fuera de UTF-8.');

$closedSchema = contentEditorPackageExample();
$closedSchema['tabla'] = 'contenido_articulos';
packageImportAssert(!contentEditorValidatePackageJson(packageImportJson($closedSchema))['valid'], 'El esquema cerrado aceptó una clave arbitraria.');

$connection = getWebDatabaseConnection();
$suffix = bin2hex(random_bytes(5));
$slug = 'test-paquete-' . $suffix;
$rollbackSlug = 'test-paquete-rollback-' . $suffix;
packageImportDelete($connection, $slug);
packageImportDelete($connection, $rollbackSlug);

try {
    $package = contentEditorPackageExample();
    $package['articulo']['slug'] = $slug;
    $package['articulo']['titulo'] = 'Importación completa ñandú';
    $package['trivias'][0]['codigo'] = 'trivia-' . $suffix . '-1';
    $package['trivias'][1]['codigo'] = 'trivia-' . $suffix . '-2';
    $package['sabias_que'][0]['codigo'] = 'sabias-' . $suffix . '-1';
    $package['sabias_que'][1]['codigo'] = 'sabias-' . $suffix . '-2';
    $json = packageImportJson($package);

    $import = contentEditorDbImportPackage($connection, $json);
    packageImportAssert($import['imported'], 'Falló la importación completa: ' . json_encode($import['errors'], JSON_UNESCAPED_UNICODE));
    $loaded = contentEditorDbLoadArticleRaw($connection, $slug);
    packageImportAssert(is_array($loaded), 'El artículo importado no existe.');
    packageImportAssert(($loaded['raw']['titulo'] ?? '') === 'Importación completa ñandú', 'El título UTF-8 no persistió.');
    packageImportAssert(count($loaded['raw']['palabras_clave'] ?? []) === 2, 'No se importaron las palabras clave.');
    packageImportAssert(count($loaded['raw']['relaciones'] ?? []) === 1, 'No se importaron las relaciones.');
    packageImportAssert(count($loaded['raw']['trivias'] ?? []) === 2, 'No se importaron todas las trivias.');
    packageImportAssert(count($loaded['raw']['sabias_que'] ?? []) === 2, 'No se importaron todos los Sabías que.');

    $replacement = $package;
    $replacement['articulo']['titulo'] = 'Título que no debe sobrescribir';
    $duplicateSlug = contentEditorDbImportPackage($connection, packageImportJson($replacement));
    packageImportAssert(!$duplicateSlug['imported'], 'La importación sobrescribió un slug existente.');
    $afterDuplicate = contentEditorDbLoadArticleRaw($connection, $slug);
    packageImportAssert(($afterDuplicate['raw']['titulo'] ?? '') === 'Importación completa ñandú', 'El rechazo por slug alteró el artículo existente.');

    $rollbackPackage = $package;
    $rollbackPackage['articulo']['slug'] = $rollbackSlug;
    $rollbackPackage['articulo']['relaciones'] = ['fases-de-la-luna', 'fases-de-la-luna'];
    $rollbackPackage['trivias'][0]['codigo'] = 'rollback-' . $suffix . '-1';
    $rollbackPackage['trivias'][1]['codigo'] = 'rollback-' . $suffix . '-2';
    $rollbackValidation = contentEditorValidatePackageJson(packageImportJson($rollbackPackage));
    packageImportAssert($rollbackValidation['valid'], 'El caso de rollback debe superar la validación previa.');
    $rollback = contentEditorDbImportPackage($connection, packageImportJson($rollbackPackage));
    packageImportAssert(!$rollback['imported'], 'La falla SQL deliberada no rechazó la importación.');
    packageImportAssert(!contentEditorDbSlugExists($connection, $rollbackSlug), 'El rollback dejó el artículo parcial.');
} finally {
    packageImportDelete($connection, $slug);
    packageImportDelete($connection, $rollbackSlug);
}

echo "content-package-import: ok\n";
