<?php

declare(strict_types=1);

putenv('APP_ENV=production');

require_once __DIR__ . '/../includes/site-sections.php';
require_once __DIR__ . '/../includes/content-system.php';
require_once __DIR__ . '/../includes/site-configuration.php';
require_once __DIR__ . '/../includes/web-database.php';

function siteVisibilityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @return array<string, array{valor:string,descripcion:?string}>
 */
function siteVisibilitySnapshot(PDO $connection, array $keys): array
{
    if ($keys === []) {
        return [];
    }
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

function siteVisibilityRestore(PDO $connection, array $keys, array $snapshot): void
{
    if ($keys !== []) {
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $delete = $connection->prepare('DELETE FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
        $delete->execute($keys);
    }

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

$connection = getWebDatabaseConnection();
$keys = array_keys(astronomySiteConfigCatalog());
$snapshot = siteVisibilitySnapshot($connection, $keys);

try {
    astronomySiteConfigInitialize($connection);

    $allEnabled = [];
    foreach ($keys as $key) {
        $allEnabled[$key] = true;
    }
    astronomySiteConfigUpdate($connection, $allEnabled);

    $sections = astronomySiteSections(false);
    siteVisibilityAssert(($sections['home']['menu_enabled'] ?? false) === true && array_key_first($sections) === 'home', 'Inicio no quedó siempre visible y primero.');

    astronomySiteConfigUpdate($connection, array_merge($allEnabled, [
        'home.today.enabled' => false,
        'home.install.enabled' => false,
    ]));

    siteVisibilityAssert(astronomySiteHomeBlockEnabled('today') === false, 'La tarjeta El cielo hoy no respetó su clave de portada.');
    siteVisibilityAssert(astronomySiteHomeBlockEnabled('install') === false, 'La tarjeta de instalación no respetó su clave de portada.');

    astronomySiteConfigUpdate($connection, array_merge($allEnabled, [
        'content.enabled' => false,
    ]));
    siteVisibilityAssert(!isContentEnabled(), 'El contenido global no quedó deshabilitado.');
    $catalogDisabled = astronomyLoadContentCatalog();
    siteVisibilityAssert(($catalogDisabled['articles'] ?? []) === [], 'Con contenido global deshabilitado se cargaron artículos públicos.');

    $syntheticCatalog = [
        'articles' => [
            'visible-parent' => ['valid' => true, 'visible' => true],
        ],
        'trivias' => [[
            'id' => 'tr-visible',
            'valid' => true,
            'visible' => true,
            'source_slug' => 'visible-parent',
            'raw' => ['opciones' => [['texto' => 'A'], ['texto' => 'B', 'explicacion' => 'Correcta']]],
        ]],
        'facts' => [[
            'id' => 'sq-visible',
            'valid' => true,
            'visible' => true,
            'source_slug' => 'visible-parent',
            'raw' => ['titulo' => 'Dato', 'respuesta' => 'Respuesta'],
        ]],
    ];

    astronomySiteConfigUpdate($connection, array_merge($allEnabled, [
        'content.enabled' => true,
        'home.trivia.enabled' => false,
    ]));
    siteVisibilityAssert(astronomyContentRandomTrivia($syntheticCatalog) === null, 'La trivia no respetó la deshabilitación global.');

    astronomySiteConfigUpdate($connection, array_merge($allEnabled, [
        'content.enabled' => true,
        'home.sabias_que.enabled' => false,
    ]));
    siteVisibilityAssert(astronomyContentRandomFact($syntheticCatalog) === null, 'Sabías que no respetó la deshabilitación global.');

    astronomySiteConfigUpdate($connection, $allEnabled);
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
    siteVisibilityAssert(is_array($visibleTrivia) && ($visibleTrivia['id'] ?? '') === 'tr-visible', 'La trivia ignoró la visibilidad individual.');
    siteVisibilityAssert(is_array($visibleFact) && ($visibleFact['id'] ?? '') === 'sq-visible', 'Sabías que ignoró la visibilidad individual.');

    $fallbackMenu = astronomySiteConfigBool(
        'menu.events.enabled',
        false,
        static function (): PDO {
            throw new RuntimeException('db-down');
        }
    );
    siteVisibilityAssert($fallbackMenu === true, 'El fallback de menú ante base caída no usó defaults seguros.');
} finally {
    siteVisibilityRestore($connection, $keys, $snapshot);
    putenv('APP_ENV');
}

echo "site-visibility: ok\n";
