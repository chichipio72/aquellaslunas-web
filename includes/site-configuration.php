<?php

declare(strict_types=1);

require_once __DIR__ . '/web-database.php';

function astronomySiteConfigCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $catalog = [
        'content.enabled' => [
            'group' => 'content',
            'label' => 'Contenidos públicos',
            'default' => true,
            'description' => 'Habilita el acceso público a contenidos editoriales.',
        ],

        'menu.home.enabled' => [
            'group' => 'menu',
            'label' => 'Inicio',
            'default' => true,
            'description' => 'Muestra la entrada Inicio en el menú principal.',
        ],
        'menu.today.enabled' => [
            'group' => 'menu',
            'label' => 'El cielo hoy',
            'default' => true,
            'description' => 'Muestra la entrada El cielo hoy en el menú principal.',
        ],
        'menu.tonight.enabled' => [
            'group' => 'menu',
            'label' => 'El cielo esta noche',
            'default' => true,
            'description' => 'Muestra la entrada El cielo esta noche en el menú principal.',
        ],
        'menu.sun_moon.enabled' => [
            'group' => 'menu',
            'label' => 'Calendario solar y lunar',
            'default' => true,
            'description' => 'Muestra la entrada Calendario solar y lunar en el menú principal.',
        ],
        'menu.events.enabled' => [
            'group' => 'menu',
            'label' => 'Eventos lunares',
            'default' => true,
            'description' => 'Muestra la entrada Eventos lunares en el menú principal.',
        ],
        'menu.eclipses.enabled' => [
            'group' => 'menu',
            'label' => 'Eclipses',
            'default' => true,
            'description' => 'Muestra la entrada Eclipses en el menú principal.',
        ],
        'menu.planner.enabled' => [
            'group' => 'menu',
            'label' => 'Planificador',
            'default' => true,
            'description' => 'Muestra la entrada Planificador en el menú principal.',
        ],
        'menu.gallery.enabled' => [
            'group' => 'menu',
            'label' => 'Galería',
            'default' => false,
            'description' => 'Muestra la entrada Galería en el menú principal.',
        ],
        'menu.content.enabled' => [
            'group' => 'menu',
            'label' => 'Contenidos',
            'default' => true,
            'description' => 'Muestra la entrada Contenidos en el menú principal.',
        ],
        'menu.location.enabled' => [
            'group' => 'menu',
            'label' => 'Ubicación',
            'default' => true,
            'description' => 'Muestra la entrada Ubicación en el menú principal.',
        ],
        'menu.capabilities.enabled' => [
            'group' => 'menu',
            'label' => 'Qué ofrece Aquellas Lunas',
            'default' => true,
            'description' => 'Muestra la entrada Qué ofrece Aquellas Lunas en el menú principal.',
        ],
        'menu.about.enabled' => [
            'group' => 'menu',
            'label' => 'Acerca del sitio',
            'default' => true,
            'description' => 'Muestra la entrada Acerca del sitio en el menú principal.',
        ],
        'menu.administration.enabled' => [
            'group' => 'menu',
            'label' => 'Administración',
            'default' => true,
            'description' => 'Muestra la entrada Administración en el menú principal.',
        ],

        'home.today.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta El cielo hoy',
            'default' => true,
            'description' => 'Muestra la tarjeta El cielo hoy en la portada.',
        ],
        'home.tonight.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta El cielo esta noche',
            'default' => true,
            'description' => 'Muestra la tarjeta El cielo esta noche en la portada.',
        ],
        'home.phases.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Próximas fases',
            'default' => true,
            'description' => 'Muestra la tarjeta Próximas fases en la portada.',
        ],
        'home.upcoming.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Lo próximo',
            'default' => true,
            'description' => 'Muestra la tarjeta Lo próximo en la portada.',
        ],
        'home.explore_sky.enabled' => [
            'group' => 'home',
            'label' => 'Bloque Explorá el cielo',
            'default' => true,
            'description' => 'Muestra el bloque Explorá el cielo en la portada.',
        ],
        'home.trivia.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Trivia',
            'default' => true,
            'description' => 'Muestra la tarjeta Trivia en la portada.',
        ],
        'home.sabias_que.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Sabías que',
            'default' => true,
            'description' => 'Muestra la tarjeta Sabías que en la portada.',
        ],
        'home.install.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Guardar / instalar',
            'default' => true,
            'description' => 'Muestra la tarjeta Guardar o instalar Aquellas Lunas en la portada.',
        ],
    ];

    return $catalog;
}

function astronomySiteConfigGroups(): array
{
    return [
        'content' => 'Contenido',
        'menu' => 'Menú principal',
        'home' => 'Portada',
    ];
}

function astronomySiteMenuConfigBySectionId(): array
{
    return [
        'home' => 'menu.home.enabled',
        'today' => 'menu.today.enabled',
        'tonight' => 'menu.tonight.enabled',
        'sun_moon' => 'menu.sun_moon.enabled',
        'events' => 'menu.events.enabled',
        'eclipses' => 'menu.eclipses.enabled',
        'planner' => 'menu.planner.enabled',
        'gallery' => 'menu.gallery.enabled',
        'content' => 'menu.content.enabled',
        'location' => 'menu.location.enabled',
        'capabilities' => 'menu.capabilities.enabled',
        'about' => 'menu.about.enabled',
        'administration' => 'menu.administration.enabled',
    ];
}

function astronomySiteHomeConfigByBlockId(): array
{
    return [
        'today' => 'home.today.enabled',
        'tonight' => 'home.tonight.enabled',
        'phases' => 'home.phases.enabled',
        'upcoming' => 'home.upcoming.enabled',
        'explore_sky' => 'home.explore_sky.enabled',
        'trivia' => 'home.trivia.enabled',
        'sabias_que' => 'home.sabias_que.enabled',
        'install' => 'home.install.enabled',
    ];
}

function astronomySiteConfigDefaults(): array
{
    $defaults = [];
    foreach (astronomySiteConfigCatalog() as $key => $definition) {
        $defaults[$key] = (($definition['default'] ?? false) === true);
    }
    return $defaults;
}

function astronomySiteConfigResetCache(): void
{
    $cache = &astronomySiteConfigRuntimeCache();
    $cache = [
        'loaded' => false,
        'values' => [],
        'error_reported' => false,
    ];
}

function &astronomySiteConfigRuntimeCache(): array
{
    static $cache = [
        'loaded' => false,
        'values' => [],
        'error_reported' => false,
    ];
    return $cache;
}

function astronomySiteConfigValidateKey(string $key): void
{
    if (!array_key_exists($key, astronomySiteConfigCatalog())) {
        throw new InvalidArgumentException('La clave de configuración no está permitida: ' . $key);
    }
}

function astronomySiteConfigValueToBool(mixed $value, bool $default): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }
    if (in_array($normalized, ['1', 'true', 'on', 'yes', 'si', 'sí'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
        return false;
    }
    return $default;
}

function astronomySiteConfigBoolString(bool $value): string
{
    return $value ? '1' : '0';
}

/**
 * @return array<string, bool>
 */
function astronomySiteConfigLoadAll(?callable $connectionFactory = null): array
{
    $defaults = astronomySiteConfigDefaults();
    $cache = &astronomySiteConfigRuntimeCache();

    if ($connectionFactory === null && $cache['loaded'] === true) {
        return $cache['values'];
    }

    $values = $defaults;

    try {
        $connection = $connectionFactory !== null
            ? $connectionFactory()
            : getWebDatabaseConnection();
        if (!$connection instanceof PDO) {
            throw new RuntimeException('La configuración del sitio requiere una conexión PDO válida.');
        }

        $keys = array_keys($defaults);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $statement = $connection->prepare(
            'SELECT clave, valor FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')'
        );
        $statement->execute($keys);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['clave'] ?? '');
            if (!array_key_exists($key, $defaults)) {
                continue;
            }
            $values[$key] = astronomySiteConfigValueToBool($row['valor'] ?? null, $defaults[$key]);
        }
    } catch (Throwable $exception) {
        if ($cache['error_reported'] !== true) {
            error_log('Aquellas Lunas site config load error: ' . $exception->getMessage());
            $cache['error_reported'] = true;
        }
    }

    if ($connectionFactory === null) {
        $cache['loaded'] = true;
        $cache['values'] = $values;
    }

    return $values;
}

function astronomySiteConfigBool(string $key, ?bool $fallback = null, ?callable $connectionFactory = null): bool
{
    astronomySiteConfigValidateKey($key);
    $defaults = astronomySiteConfigDefaults();
    $defaultValue = $fallback ?? ($defaults[$key] ?? false);
    $values = astronomySiteConfigLoadAll($connectionFactory);
    return astronomySiteConfigValueToBool($values[$key] ?? null, $defaultValue);
}

function astronomySiteConfigInitialize(PDO $connection): array
{
    $catalog = astronomySiteConfigCatalog();
    $statement = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave, valor, descripcion) '
        . 'VALUES (:clave, :valor, :descripcion) '
        . 'ON DUPLICATE KEY UPDATE clave = clave'
    );

    $inserted = 0;
    foreach ($catalog as $key => $definition) {
        $statement->execute([
            ':clave' => $key,
            ':valor' => astronomySiteConfigBoolString(($definition['default'] ?? false) === true),
            ':descripcion' => (string) ($definition['description'] ?? ''),
        ]);
        if ($statement->rowCount() === 1) {
            $inserted++;
        }
    }

    astronomySiteConfigResetCache();
    return [
        'inserted' => $inserted,
        'total' => count($catalog),
    ];
}

function astronomySiteConfigUpdate(PDO $connection, array $updates): void
{
    foreach ($updates as $key => $value) {
        astronomySiteConfigValidateKey((string) $key);
        if (!is_bool($value)) {
            throw new InvalidArgumentException('La actualización de configuración requiere booleanos por clave.');
        }
    }

    $connection->beginTransaction();
    try {
        astronomySiteConfigInitialize($connection);

        $updateStatement = $connection->prepare(
            'UPDATE admin_configuracion_sitio SET valor = :valor WHERE clave = :clave'
        );

        foreach ($updates as $key => $value) {
            $updateStatement->execute([
                ':clave' => (string) $key,
                ':valor' => astronomySiteConfigBoolString($value),
            ]);
        }

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }

    astronomySiteConfigResetCache();
}

function astronomySiteConfigGroupEntries(array $values): array
{
    $grouped = [];
    foreach (astronomySiteConfigGroups() as $groupId => $groupLabel) {
        $grouped[$groupId] = [
            'id' => $groupId,
            'label' => $groupLabel,
            'entries' => [],
        ];
    }

    foreach (astronomySiteConfigCatalog() as $key => $definition) {
        $groupId = (string) ($definition['group'] ?? 'content');
        if (!isset($grouped[$groupId])) {
            continue;
        }
        $grouped[$groupId]['entries'][] = [
            'key' => $key,
            'label' => (string) ($definition['label'] ?? $key),
            'description' => (string) ($definition['description'] ?? ''),
            'value' => astronomySiteConfigValueToBool($values[$key] ?? null, (bool) ($definition['default'] ?? false)),
        ];
    }

    return array_values($grouped);
}

function astronomySiteMenuEntryEnabled(string $sectionId): bool
{
    $map = astronomySiteMenuConfigBySectionId();
    if (!isset($map[$sectionId])) {
        return true;
    }
    return astronomySiteConfigBool($map[$sectionId]);
}

function astronomySiteHomeBlockEnabled(string $blockId): bool
{
    $map = astronomySiteHomeConfigByBlockId();
    if (!isset($map[$blockId])) {
        return true;
    }
    return astronomySiteConfigBool($map[$blockId]);
}
