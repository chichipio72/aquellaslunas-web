<?php

declare(strict_types=1);

putenv('APP_ENV=local');
putenv('CONTENT_ENABLED_IN_PRODUCTION=false');

require_once __DIR__ . '/../includes/content-system.php';
require_once __DIR__ . '/../includes/content-database.php';
require_once __DIR__ . '/../includes/web-database.php';
require_once __DIR__ . '/../includes/site-configuration.php';

function contentPublicMysqlAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function contentPublicMysqlConfigSnapshot(PDO $connection, array $keys): array
{
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));
    $statement = $connection->prepare('SELECT clave, valor, descripcion FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
    $statement->execute($keys);

    $snapshot = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $snapshot[(string) $row['clave']] = [
            'valor' => (string) $row['valor'],
            'descripcion' => isset($row['descripcion']) ? (string) $row['descripcion'] : null,
        ];
    }
    return $snapshot;
}

function contentPublicMysqlConfigRestore(PDO $connection, array $keys, array $snapshot): void
{
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));
    $delete = $connection->prepare('DELETE FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
    $delete->execute($keys);

    $insert = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave, valor, descripcion) VALUES (:clave, :valor, :descripcion)'
    );
    foreach ($snapshot as $key => $row) {
        $insert->execute([
            ':clave' => $key,
            ':valor' => $row['valor'],
            ':descripcion' => $row['descripcion'],
        ]);
    }

    astronomySiteConfigResetCache();
}

function contentPublicMysqlNormalizeTrivia(array $trivia): array
{
    return [
        'id' => (string) ($trivia['id'] ?? ''),
        'visible' => (bool) ($trivia['visible'] ?? false),
        'pregunta' => (string) ($trivia['pregunta'] ?? ''),
        'imagen' => astronomyContentDbNullableText($trivia['imagen'] ?? null),
        'opciones' => array_map(static function (array $option): array {
            $normalized = ['texto' => (string) ($option['texto'] ?? '')];
            if (array_key_exists('explicacion', $option)) {
                $normalized['explicacion'] = (string) $option['explicacion'];
            }
            return $normalized;
        }, array_values(is_array($trivia['opciones'] ?? null) ? $trivia['opciones'] : [])),
    ];
}

function contentPublicMysqlNormalizeFact(array $fact): array
{
    return [
        'id' => (string) ($fact['id'] ?? ''),
        'visible' => (bool) ($fact['visible'] ?? false),
        'titulo' => (string) ($fact['titulo'] ?? ''),
        'respuesta' => (string) ($fact['respuesta'] ?? ''),
        'imagen' => astronomyContentDbNullableText($fact['imagen'] ?? null),
    ];
}

function contentPublicMysqlComparable(array $raw): array
{
    return [
        'version' => (int) ($raw['version'] ?? 0),
        'visible' => (bool) ($raw['visible'] ?? false),
        'titulo' => (string) ($raw['titulo'] ?? ''),
        'resumen' => (string) ($raw['resumen'] ?? ''),
        'articulo' => (string) ($raw['articulo'] ?? ''),
        'articulo_renderizado' => astronomyContentRenderMarkdown(astronomyContentArticleBodyMarkdown((string) ($raw['articulo'] ?? ''))),
        'imagen' => astronomyContentDbNullableText($raw['imagen'] ?? null),
        'palabras_clave' => array_values(is_array($raw['palabras_clave'] ?? null) ? $raw['palabras_clave'] : []),
        'relaciones' => array_values(is_array($raw['relaciones'] ?? null) ? $raw['relaciones'] : []),
        'trivias' => array_map('contentPublicMysqlNormalizeTrivia', array_values(is_array($raw['trivias'] ?? null) ? $raw['trivias'] : [])),
        'sabias_que' => array_map('contentPublicMysqlNormalizeFact', array_values(is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : [])),
    ];
}

$configConnection = getWebDatabaseConnection();
$configKeys = ['content.enabled'];
$configSnapshot = contentPublicMysqlConfigSnapshot($configConnection, $configKeys);

try {
    astronomySiteConfigInitialize($configConnection);
    astronomySiteConfigUpdate($configConnection, [
        'content.enabled' => true,
    ]);

$catalog = astronomyLoadContentCatalog();
contentPublicMysqlAssert(is_array($catalog['articles'] ?? null), 'No se pudo cargar el catálogo desde MySQL.');
contentPublicMysqlAssert(count($catalog['articles']) >= 5, 'Se esperaban al menos cinco artículos en MySQL.');

$visibleArticles = astronomyContentVisibleArticles($catalog);
contentPublicMysqlAssert($visibleArticles !== [], 'No se detectaron artículos públicos visibles.');
foreach ($visibleArticles as $article) {
    contentPublicMysqlAssert(($article['visible'] ?? false) === true, 'El índice incluyó un artículo oculto.');
    contentPublicMysqlAssert(($article['valid'] ?? false) === true, 'El índice incluyó un artículo inválido.');
}

$selectedSlug = (string) ($visibleArticles[0]['slug'] ?? '');
contentPublicMysqlAssert($selectedSlug !== '', 'No se pudo seleccionar un slug visible para prueba.');
contentPublicMysqlAssert(isset($catalog['articles'][$selectedSlug]), 'No se pudo resolver el artículo por slug.');

$withoutTrivia = array_values(array_filter(
    $catalog['articles'],
    static fn(array $article): bool => is_array($article['raw'] ?? null) && ((count($article['raw']['trivias'] ?? []) === 0))
));

$withoutFacts = array_values(array_filter(
    $catalog['articles'],
    static fn(array $article): bool => is_array($article['raw'] ?? null) && ((count($article['raw']['sabias_que'] ?? []) === 0))
));


$utf8Html = astronomyContentRenderMarkdown('Título con acentos: órbita, física y marea.');
contentPublicMysqlAssert(str_contains($utf8Html, 'órbita') && str_contains($utf8Html, 'física'), 'El render Markdown no preservó UTF-8.');

$dbDownCatalog = astronomyLoadContentCatalog(static function (): PDO {
    throw new RuntimeException('db-offline-test');
});
contentPublicMysqlAssert(($dbDownCatalog['articles'] ?? []) === [], 'Con base caída no se devolvió un catálogo seguro vacío.');
contentPublicMysqlAssert(count($dbDownCatalog['diagnostics'] ?? []) > 0, 'Con base caída no se registró diagnóstico seguro.');

$syntheticCatalog = [
    'articles' => [
        'visible-parent' => ['valid' => true, 'visible' => true],
        'hidden-parent' => ['valid' => true, 'visible' => false],
    ],
    'trivias' => [[
        'valid' => true,
        'visible' => true,
        'source_slug' => 'hidden-parent',
        'raw' => ['opciones' => [['texto' => 'A'], ['texto' => 'B', 'explicacion' => 'ok']]],
    ]],
    'facts' => [[
        'valid' => true,
        'visible' => true,
        'source_slug' => 'hidden-parent',
        'raw' => ['titulo' => 'Dato', 'respuesta' => 'Respuesta'],
    ]],
];
contentPublicMysqlAssert(astronomyContentRandomTrivia($syntheticCatalog) === null, 'Se mostró una trivia con artículo padre oculto.');
contentPublicMysqlAssert(astronomyContentRandomFact($syntheticCatalog) === null, 'Se mostró un “Sabías que…” con artículo padre oculto.');

$syntheticVisibilityCatalog = [
    'articles' => [
        'visible-parent' => ['valid' => true, 'visible' => true],
    ],
    'trivias' => [
        [
            'id' => 'tr-visible',
            'valid' => true,
            'visible' => true,
            'source_slug' => 'visible-parent',
            'raw' => ['opciones' => [['texto' => 'A'], ['texto' => 'B', 'explicacion' => 'Correcta']]],
        ],
        [
            'id' => 'tr-hidden',
            'valid' => true,
            'visible' => false,
            'source_slug' => 'visible-parent',
            'raw' => ['opciones' => [['texto' => 'A'], ['texto' => 'B', 'explicacion' => 'Correcta']]],
        ],
    ],
    'facts' => [
        [
            'id' => 'sq-visible',
            'valid' => true,
            'visible' => true,
            'source_slug' => 'visible-parent',
            'raw' => ['titulo' => 'Dato visible', 'respuesta' => 'Respuesta visible'],
        ],
        [
            'id' => 'sq-hidden',
            'valid' => true,
            'visible' => false,
            'source_slug' => 'visible-parent',
            'raw' => ['titulo' => 'Dato oculto', 'respuesta' => 'Respuesta oculta'],
        ],
    ],
];

$visibleTrivia = astronomyContentRandomTrivia($syntheticVisibilityCatalog);
$visibleFact = astronomyContentRandomFact($syntheticVisibilityCatalog);
contentPublicMysqlAssert(is_array($visibleTrivia) && ($visibleTrivia['id'] ?? '') === 'tr-visible', 'La selección de trivia incluyó un elemento invisible.');
contentPublicMysqlAssert(is_array($visibleFact) && ($visibleFact['id'] ?? '') === 'sq-visible', 'La selección de “Sabías que…” incluyó un elemento invisible.');

$connection = getWebDatabaseConnection();
$slugs = ['eclipses-lunares', 'fases-de-la-luna', 'pascua', 'semana', 'superluna'];

foreach ($slugs as $slug) {
    $dbLoaded = astronomyContentDbLoadArticleRaw($connection, $slug);
    contentPublicMysqlAssert($dbLoaded !== null, 'No existe el artículo en MySQL: ' . $slug . '.');
    contentPublicMysqlAssert(
        contentPublicMysqlComparable($dbLoaded['raw']) === contentPublicMysqlComparable($catalog['articles'][$slug]['raw'] ?? []),
        'El artículo ' . $slug . ' no coincide entre la lectura directa de DB y el catálogo público.'
    );
}

} finally {
    contentPublicMysqlConfigRestore($configConnection, $configKeys, $configSnapshot);
}

echo "content-public-mysql: ok\n";
