<?php

declare(strict_types=1);

putenv('APP_ENV=local');

require_once __DIR__ . '/../includes/site-configuration.php';
require_once __DIR__ . '/../includes/web-database.php';

function homeVisibilityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @return array<string, array{valor:string,descripcion:?string}>
 */
function homeVisibilitySnapshot(PDO $connection, array $keys): array
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

function homeVisibilityRestore(PDO $connection, array $keys, array $snapshot): void
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
$snapshot = homeVisibilitySnapshot($connection, $keys);

try {
    astronomySiteConfigInitialize($connection);

    $allEnabled = [];
    foreach ($keys as $key) {
        $allEnabled[$key] = true;
    }

    astronomySiteConfigUpdate($connection, array_merge($allEnabled, [
        'menu.today.enabled' => false,
        'home.today.enabled' => false,
        'home.install.enabled' => false,
    ]));

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $_GET = [];
    $_POST = [];

    ob_start();
    require __DIR__ . '/../index.php';
    $html = (string) ob_get_clean();

    homeVisibilityAssert(!str_contains($html, 'id="v2-today-title"'), 'La portada siguió renderizando la tarjeta El cielo hoy deshabilitada.');
    homeVisibilityAssert(!str_contains($html, 'data-install-card'), 'La portada siguió renderizando la tarjeta de instalación deshabilitada.');
    homeVisibilityAssert(!str_contains($html, '>El cielo hoy</a>'), 'La navegación del menú mantuvo una entrada deshabilitada.');
    homeVisibilityAssert(str_contains($html, 'id="v2-tonight-title"'), 'La portada ocultó tarjetas no deshabilitadas.');
} finally {
    homeVisibilityRestore($connection, $keys, $snapshot);
    putenv('APP_ENV');
}

echo "home-visibility: ok\n";
