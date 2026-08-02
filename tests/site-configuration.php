<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/site-configuration.php';
require_once __DIR__ . '/../includes/web-database.php';

function siteConfigurationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @return array<string, array{valor:string,descripcion:?string}>
 */
function siteConfigurationSnapshot(PDO $connection, array $keys): array
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

function siteConfigurationDeleteKeys(PDO $connection, array $keys): void
{
    if ($keys === []) {
        return;
    }
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));
    $statement = $connection->prepare('DELETE FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
    $statement->execute($keys);
}

function siteConfigurationRestore(PDO $connection, array $snapshot, array $keys): void
{
    siteConfigurationDeleteKeys($connection, $keys);

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
$catalog = astronomySiteConfigCatalog();
$keys = array_keys($catalog);
$snapshot = siteConfigurationSnapshot($connection, $keys);

try {
    siteConfigurationDeleteKeys($connection, $keys);
    astronomySiteConfigResetCache();

    $initialized = astronomySiteConfigInitialize($connection);
    siteConfigurationAssert(
        (int) ($initialized['inserted'] ?? 0) === count($keys),
        'La inicialización con tabla vacía no insertó todas las claves esperadas.'
    );

    $countStatement = $connection->prepare('SELECT COUNT(*) FROM admin_configuracion_sitio WHERE clave IN (' . implode(', ', array_fill(0, count($keys), '?')) . ')');
    $countStatement->execute($keys);
    siteConfigurationAssert((int) $countStatement->fetchColumn() === count($keys), 'No quedaron todas las claves iniciales después de inicializar.');

    $connection->prepare("UPDATE admin_configuracion_sitio SET valor = '0' WHERE clave = 'content.enabled'")->execute();
    astronomySiteConfigResetCache();

    $secondInitialization = astronomySiteConfigInitialize($connection);
    siteConfigurationAssert((int) ($secondInitialization['inserted'] ?? -1) === 0, 'La segunda inicialización sobreescribió claves existentes.');

    $storedContentEnabled = $connection->query("SELECT valor FROM admin_configuracion_sitio WHERE clave = 'content.enabled'")->fetchColumn();
    siteConfigurationAssert((string) $storedContentEnabled === '0', 'La inicialización idempotente modificó un valor existente.');

    $connection->prepare("DELETE FROM admin_configuracion_sitio WHERE clave = 'menu.about.enabled'")->execute();
    $connection->prepare("DELETE FROM admin_configuracion_sitio WHERE clave = 'menu.administration.enabled'")->execute();
    astronomySiteConfigResetCache();
    $loaded = astronomySiteConfigLoadAll();
    siteConfigurationAssert(($loaded['menu.about.enabled'] ?? false) === true, 'No se aplicó el valor por defecto al faltar una clave.');
    siteConfigurationAssert(($loaded['menu.administration.enabled'] ?? false) === true, 'No se aplicó el valor predeterminado de Administración al faltar su clave.');

    $threwUnknown = false;
    try {
        astronomySiteConfigUpdate($connection, ['menu.inexistente.enabled' => true]);
    } catch (InvalidArgumentException) {
        $threwUnknown = true;
    }
    siteConfigurationAssert($threwUnknown, 'Se aceptó una clave desconocida durante la actualización.');

    astronomySiteConfigUpdate($connection, [
        'menu.today.enabled' => false,
        'menu.administration.enabled' => false,
        'home.install.enabled' => false,
    ]);
    $reloaded = astronomySiteConfigLoadAll();
    siteConfigurationAssert(($reloaded['menu.today.enabled'] ?? true) === false, 'No se guardó la actualización de menú.');
    siteConfigurationAssert(($reloaded['menu.administration.enabled'] ?? true) === false, 'No se guardó la visibilidad de Administración.');
    siteConfigurationAssert(($reloaded['home.install.enabled'] ?? true) === false, 'No se guardó la actualización de portada.');

    $fallbackValue = astronomySiteConfigBool(
        'menu.events.enabled',
        false,
        static function (): PDO {
            throw new RuntimeException('db-down-test');
        }
    );
    siteConfigurationAssert($fallbackValue === true, 'El fallback ante base caída no usó los valores por defecto del catálogo.');
} finally {
    siteConfigurationRestore($connection, $snapshot, $keys);
}

echo "site-configuration: ok\n";
