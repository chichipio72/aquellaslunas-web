<?php

declare(strict_types=1);

require_once __DIR__ . '/web-database.php';

const ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING = 'home_upcoming';
const ASTRONOMY_EVENT_SURFACE_HOME_PHASES = 'home_phases';
const ASTRONOMY_EVENT_SURFACE_TODAY = 'today';
const ASTRONOMY_EVENT_SURFACE_TONIGHT = 'tonight';
const ASTRONOMY_EVENT_SURFACE_EVENTS = 'events';
const ASTRONOMY_EVENT_SURFACE_ECLIPSES = 'eclipses';

function astronomyEventSurfaceCatalog(): array
{
    return [
        ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING => 'Lo próximo',
        ASTRONOMY_EVENT_SURFACE_HOME_PHASES => 'Próximas fases',
        ASTRONOMY_EVENT_SURFACE_TODAY => 'El cielo hoy',
        ASTRONOMY_EVENT_SURFACE_TONIGHT => 'El cielo esta noche',
        ASTRONOMY_EVENT_SURFACE_EVENTS => 'Eventos lunares',
        ASTRONOMY_EVENT_SURFACE_ECLIPSES => 'Eclipses',
    ];
}

function astronomyEventRendererCatalog(): array
{
    return ['phase', 'apsis', 'node', 'libration', 'conjunction', 'eclipse', 'earthshine', 'full_moon_observation'];
}

function astronomyEventPublicTypeLabels(): array
{
    return [
        'moon_phase' => 'Fases',
        'apsis' => 'Perigeo y apogeo',
        'lunar_nodes' => 'Nodos lunares',
        'conjunction' => 'Conjunciones',
        'earthshine' => 'Luz cenicienta',
        'full_moon_observation' => 'Cerca del amanecer o atardecer',
        'libration' => 'Libraciones',
        'eclipse' => 'Eclipses',
    ];
}

function astronomyEventTypeDefinition(
    string $scope,
    string $group,
    string $type,
    string $publicType,
    ?string $publicSubtype,
    string $category,
    string $name,
    string $renderer,
    bool $enabled,
    array $surfaces
): array {
    return compact('scope', 'group', 'type', 'publicType', 'publicSubtype', 'category', 'name', 'renderer', 'enabled', 'surfaces');
}

/** @return array<string,array> keyed by stable scope/group/type identity. */
function astronomyEventTypeCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }
    $allGeneral = [ASTRONOMY_EVENT_SURFACE_TODAY, ASTRONOMY_EVENT_SURFACE_EVENTS];
    $phaseSurfaces = [ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING, ASTRONOMY_EVENT_SURFACE_HOME_PHASES, ...$allGeneral, ASTRONOMY_EVENT_SURFACE_TONIGHT];
    $catalog = [];
    $add = static function (array $definition) use (&$catalog): void {
        $key = $definition['scope'] . '/' . $definition['group'] . '/' . $definition['type'];
        $catalog[$key] = $definition;
    };

    foreach ([
        'new_moon' => 'Luna nueva',
        'first_quarter' => 'Cuarto creciente',
        'full_moon' => 'Luna llena',
        'last_quarter' => 'Cuarto menguante',
    ] as $type => $name) {
        $add(astronomyEventTypeDefinition('persisted', 'moon_phase', $type, 'moon_phase', $type, 'phases', $name, 'phase', true, $phaseSurfaces));
    }
    foreach (['perigee' => 'Perigeo lunar', 'apogee' => 'Apogeo lunar'] as $type => $name) {
        $add(astronomyEventTypeDefinition('persisted', 'lunar_apsis', $type, 'apsis', $type, 'orbit', $name, 'apsis', true, [ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING, ASTRONOMY_EVENT_SURFACE_TODAY, ASTRONOMY_EVENT_SURFACE_TONIGHT, ASTRONOMY_EVENT_SURFACE_EVENTS]));
    }
    foreach (['ascending_node' => 'Nodo lunar ascendente', 'descending_node' => 'Nodo lunar descendente'] as $type => $name) {
        $add(astronomyEventTypeDefinition('persisted', 'lunar_orbit', $type, 'lunar_nodes', $type, 'orbit', $name, 'node', false, []));
    }
    foreach (['libration_east' => 'Libración hacia el este', 'libration_west' => 'Libración hacia el oeste', 'libration_north' => 'Libración hacia el norte', 'libration_south' => 'Libración hacia el sur'] as $type => $name) {
        $add(astronomyEventTypeDefinition('persisted', 'lunar_libration', $type, 'libration', $type, 'librations', $name, 'libration', true, [ASTRONOMY_EVENT_SURFACE_TODAY, ASTRONOMY_EVENT_SURFACE_TONIGHT, ASTRONOMY_EVENT_SURFACE_EVENTS]));
    }
    $conjunctions = [
        'mercury' => 'Mercurio', 'venus' => 'Venus', 'mars' => 'Marte', 'jupiter' => 'Júpiter', 'saturn' => 'Saturno',
        'aldebaran' => 'Aldebarán', 'alhena' => 'Alhena', 'antares' => 'Antares', 'deneb_algedi' => 'Deneb Algedi',
        'elnath' => 'Elnath', 'hyades' => 'Híades', 'm44' => 'M44/Pesebre', 'nunki' => 'Nunki',
        'pleiades' => 'Pléyades', 'pollux' => 'Pólux', 'regulus' => 'Régulo', 'spica' => 'Spica',
        'zubenelgenubi' => 'Zubenelgenubi',
    ];
    foreach ($conjunctions as $body => $label) {
        $type = 'moon_' . $body . '_conjunction';
        $add(astronomyEventTypeDefinition('persisted', 'lunar_conjunction', $type, 'conjunction', $body, 'conjunctions', 'Conjunción Luna–' . $label, 'conjunction', true, [ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING, ASTRONOMY_EVENT_SURFACE_TODAY, ASTRONOMY_EVENT_SURFACE_TONIGHT, ASTRONOMY_EVENT_SURFACE_EVENTS]));
    }
    foreach (['lunar_eclipse' => 'Eclipse lunar', 'solar_eclipse' => 'Eclipse solar'] as $type => $name) {
        $add(astronomyEventTypeDefinition('persisted', 'eclipse', $type, 'eclipse', $type, 'eclipses', $name, 'eclipse', true, [ASTRONOMY_EVENT_SURFACE_TODAY, ASTRONOMY_EVENT_SURFACE_TONIGHT, ASTRONOMY_EVENT_SURFACE_EVENTS, ASTRONOMY_EVENT_SURFACE_ECLIPSES]));
    }
    foreach (['morning' => 'Luz cenicienta matutina', 'evening' => 'Luz cenicienta vespertina'] as $subtype => $name) {
        $add(astronomyEventTypeDefinition('derived', 'earthshine', 'earthshine_' . $subtype, 'earthshine', $subtype, 'derived', $name, 'earthshine', true, [ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING, ASTRONOMY_EVENT_SURFACE_TODAY, ASTRONOMY_EVENT_SURFACE_TONIGHT, ASTRONOMY_EVENT_SURFACE_EVENTS]));
    }
    foreach (['morning' => 'Luna llena cerca de la salida del Sol', 'evening' => 'Luna llena cerca de la puesta del Sol'] as $subtype => $name) {
        $add(astronomyEventTypeDefinition('derived', 'local_full_moon', 'full_moon_' . $subtype, 'full_moon_observation', $subtype, 'derived', $name, 'full_moon_observation', true, [ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING, ASTRONOMY_EVENT_SURFACE_TODAY, ASTRONOMY_EVENT_SURFACE_TONIGHT, ASTRONOMY_EVENT_SURFACE_EVENTS]));
    }
    return $catalog;
}

function astronomyEventCategoryCatalog(): array
{
    return [
        'phases' => 'Fases de la Luna', 'orbit' => 'Órbita lunar', 'librations' => 'Libraciones',
        'conjunctions' => 'Conjunciones', 'eclipses' => 'Eclipses', 'derived' => 'Observaciones derivadas',
    ];
}

function astronomyEventTypeInitialize(PDO $connection): array
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS admin_tipos_eventos ("
        . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, "
        . "scope ENUM('persisted','derived') NOT NULL, event_group VARCHAR(50) NOT NULL, event_type VARCHAR(100) NOT NULL, "
        . "public_type VARCHAR(50) NOT NULL, public_subtype VARCHAR(100) NULL, category_key VARCHAR(50) NOT NULL, "
        . "nombre_amigable VARCHAR(150) NOT NULL, habilitado TINYINT(1) NOT NULL DEFAULT 1, renderer_key VARCHAR(50) NOT NULL, "
        . "creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, "
        . "UNIQUE KEY uq_admin_tipo_evento_identidad (scope,event_group,event_type), "
        . "KEY idx_admin_tipo_evento_publico (public_type,public_subtype)"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS admin_tipos_eventos_superficies ("
        . "tipo_evento_id BIGINT UNSIGNED NOT NULL, superficie VARCHAR(50) NOT NULL, posicion INT UNSIGNED NOT NULL DEFAULT 0, "
        . "creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, "
        . "PRIMARY KEY (tipo_evento_id,superficie), KEY idx_admin_superficie (superficie,posicion), "
        . "CONSTRAINT fk_admin_superficie_tipo FOREIGN KEY (tipo_evento_id) REFERENCES admin_tipos_eventos(id) ON DELETE CASCADE"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $insertType = $connection->prepare(
        'INSERT INTO admin_tipos_eventos (scope,event_group,event_type,public_type,public_subtype,category_key,nombre_amigable,habilitado,renderer_key) '
        . 'VALUES (:scope,:event_group,:event_type,:public_type,:public_subtype,:category_key,:nombre_amigable,:habilitado,:renderer_key) '
        . 'ON DUPLICATE KEY UPDATE id=id'
    );
    $findId = $connection->prepare('SELECT id FROM admin_tipos_eventos WHERE scope=:scope AND event_group=:event_group AND event_type=:event_type');
    $insertSurface = $connection->prepare(
        'INSERT INTO admin_tipos_eventos_superficies (tipo_evento_id,superficie,posicion) VALUES (:id,:surface,:position) '
        . 'ON DUPLICATE KEY UPDATE tipo_evento_id=tipo_evento_id'
    );
    $inserted = 0;
    foreach (astronomyEventTypeCatalog() as $definition) {
        $insertType->execute([
            'scope' => $definition['scope'], 'event_group' => $definition['group'], 'event_type' => $definition['type'],
            'public_type' => $definition['publicType'], 'public_subtype' => $definition['publicSubtype'],
            'category_key' => $definition['category'], 'nombre_amigable' => $definition['name'],
            'habilitado' => $definition['enabled'] ? 1 : 0, 'renderer_key' => $definition['renderer'],
        ]);
        $wasInserted = $insertType->rowCount() === 1;
        if ($wasInserted) {
            $inserted++;
        }
        $findId->execute(['scope' => $definition['scope'], 'event_group' => $definition['group'], 'event_type' => $definition['type']]);
        $id = (int) $findId->fetchColumn();
        if ($wasInserted) {
            foreach (array_values($definition['surfaces']) as $position => $surface) {
                $insertSurface->execute(['id' => $id, 'surface' => $surface, 'position' => $position]);
            }
        }
    }
    astronomyEventTypeConfigResetCache();
    return ['inserted' => $inserted, 'total' => count(astronomyEventTypeCatalog())];
}

function &astronomyEventTypeConfigCache(): array
{
    static $cache = ['loaded' => false, 'available' => false, 'rows' => [], 'error_reported' => false];
    return $cache;
}

function astronomyEventTypeConfigResetCache(): void
{
    $cache = &astronomyEventTypeConfigCache();
    $cache = ['loaded' => false, 'available' => false, 'rows' => [], 'error_reported' => false];
}

function astronomyEventTypeConfigLoad(?callable $connectionFactory = null): array
{
    $cache = &astronomyEventTypeConfigCache();
    if ($connectionFactory === null && $cache['loaded']) {
        return $cache;
    }
    $state = ['loaded' => true, 'available' => false, 'rows' => [], 'error_reported' => $cache['error_reported']];
    try {
        $connection = $connectionFactory !== null ? $connectionFactory() : getWebDatabaseConnection();
        $types = $connection->query(
            'SELECT id,scope,event_group,event_type,public_type,public_subtype,category_key,nombre_amigable,habilitado,renderer_key '
            . 'FROM admin_tipos_eventos ORDER BY id'
        )->fetchAll();
        $surfaceStatement = $connection->prepare('SELECT superficie,posicion FROM admin_tipos_eventos_superficies WHERE tipo_evento_id=:id ORDER BY posicion,superficie');
        foreach ($types as $row) {
            $surfaceStatement->execute(['id' => (int) $row['id']]);
            $row['surfaces'] = array_column($surfaceStatement->fetchAll(), 'superficie');
            $state['rows'][] = $row;
        }
        $state['available'] = true;
    } catch (Throwable $exception) {
        if (!$state['error_reported']) {
            error_log('Aquellas Lunas event type configuration unavailable.');
            $state['error_reported'] = true;
        }
    }
    if ($connectionFactory === null) {
        $cache = $state;
    }
    return $state;
}

function astronomyEventTypeIdentityFromPublic(array $event): ?array
{
    $publicType = is_string($event['type'] ?? null) ? trim($event['type']) : '';
    $subtype = is_string($event['subtype'] ?? null) ? trim($event['subtype']) : '';
    if ($publicType === '' || $subtype === '') {
        return null;
    }
    return match ($publicType) {
        'moon_phase' => ['persisted', 'moon_phase', $subtype],
        'apsis' => ['persisted', 'lunar_apsis', $subtype],
        'lunar_nodes' => ['persisted', 'lunar_orbit', $subtype],
        'libration' => ['persisted', 'lunar_libration', $subtype],
        'eclipse' => ['persisted', 'eclipse', $subtype],
        'conjunction' => ['persisted', 'lunar_conjunction', 'moon_' . $subtype . '_conjunction'],
        'earthshine' => ['derived', 'earthshine', 'earthshine_' . $subtype],
        'full_moon_observation' => ['derived', 'local_full_moon', 'full_moon_' . $subtype],
        default => null,
    };
}

function astronomyEventTypeFallbackDefinition(array $event): ?array
{
    $identity = astronomyEventTypeIdentityFromPublic($event);
    if ($identity === null) {
        return null;
    }
    return astronomyEventTypeCatalog()[implode('/', $identity)] ?? null;
}

function astronomyEventTypeConfiguredRow(array $event, ?callable $connectionFactory = null): ?array
{
    $identity = astronomyEventTypeIdentityFromPublic($event);
    if ($identity === null) {
        error_log('Aquellas Lunas unknown public event type received.');
        return null;
    }
    $state = astronomyEventTypeConfigLoad($connectionFactory);
    if (!$state['available']) {
        return astronomyEventTypeFallbackDefinition($event);
    }
    foreach ($state['rows'] as $row) {
        if ($row['scope'] === $identity[0] && $row['event_group'] === $identity[1] && $row['event_type'] === $identity[2]) {
            return $row;
        }
    }
    error_log('Aquellas Lunas unconfigured event type received: ' . implode('/', $identity));
    return null;
}

function astronomyEventVisibleOnSurface(array $event, string $surface, ?callable $connectionFactory = null): bool
{
    if (!array_key_exists($surface, astronomyEventSurfaceCatalog())) {
        throw new InvalidArgumentException('La superficie de eventos no está permitida.');
    }
    $row = astronomyEventTypeConfiguredRow($event, $connectionFactory);
    if ($row === null) {
        return false;
    }
    $enabled = array_key_exists('habilitado', $row) ? ((int) $row['habilitado'] === 1) : (($row['enabled'] ?? false) === true);
    $surfaces = $row['surfaces'] ?? [];
    return $enabled && in_array($surface, is_array($surfaces) ? $surfaces : [], true);
}

function astronomyFilterEventsForSurface(array $events, string $surface, ?callable $connectionFactory = null): array
{
    return array_values(array_filter($events, static fn($event): bool => is_array($event) && astronomyEventVisibleOnSurface($event, $surface, $connectionFactory)));
}

function astronomyEventFriendlyName(array $event, ?callable $connectionFactory = null): ?string
{
    $row = astronomyEventTypeConfiguredRow($event, $connectionFactory);
    $name = is_array($row) ? ($row['nombre_amigable'] ?? $row['name'] ?? null) : null;
    return is_string($name) && trim($name) !== '' ? trim($name) : null;
}

function astronomyEventFriendlyNameIsCustomized(array $event, ?callable $connectionFactory = null): bool
{
    $configured = astronomyEventFriendlyName($event, $connectionFactory);
    $fallback = astronomyEventTypeFallbackDefinition($event);
    return $configured !== null && is_array($fallback) && $configured !== $fallback['name'];
}

function astronomyEventPublicTypesForSurface(string $surface, ?callable $connectionFactory = null): array
{
    if (!array_key_exists($surface, astronomyEventSurfaceCatalog())) {
        throw new InvalidArgumentException('La superficie de eventos no está permitida.');
    }
    $state = astronomyEventTypeConfigLoad($connectionFactory);
    $types = [];
    if ($state['available']) {
        foreach ($state['rows'] as $row) {
            if ((int) $row['habilitado'] === 1 && in_array($surface, $row['surfaces'] ?? [], true)) {
                $types[] = (string) $row['public_type'];
            }
        }
    } else {
        foreach (astronomyEventTypeCatalog() as $definition) {
            if ($definition['enabled'] && in_array($surface, $definition['surfaces'], true)) {
                $types[] = $definition['publicType'];
            }
        }
    }
    return array_values(array_unique($types));
}

function astronomyEventTypeAdminRows(PDO $connection): array
{
    $state = astronomyEventTypeConfigLoad(static fn(): PDO => $connection);
    return $state['rows'];
}

function astronomyEventTypeUpdate(PDO $connection, array $updates): void
{
    $surfaces = astronomyEventSurfaceCatalog();
    $renderers = astronomyEventRendererCatalog();
    $rows = astronomyEventTypeAdminRows($connection);
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }
    foreach ($updates as $id => $update) {
        if (!is_int($id) || !isset($byId[$id]) || !is_array($update)) {
            throw new InvalidArgumentException('El tipo de evento no está permitido.');
        }
        $name = trim((string) ($update['name'] ?? ''));
        if ($name === '' || strlen($name) > 150 || preg_match('//u', $name) !== 1) {
            throw new InvalidArgumentException('El nombre amigable no es válido.');
        }
        foreach ($update['surfaces'] ?? [] as $surface) {
            if (!is_string($surface) || !array_key_exists($surface, $surfaces)) {
                throw new InvalidArgumentException('La superficie no está permitida.');
            }
        }
        if (!in_array($byId[$id]['renderer_key'], $renderers, true)) {
            throw new RuntimeException('El renderer configurado no pertenece al catálogo cerrado.');
        }
    }
    $connection->beginTransaction();
    try {
        $updateType = $connection->prepare('UPDATE admin_tipos_eventos SET nombre_amigable=:name,habilitado=:enabled WHERE id=:id');
        $deleteSurfaces = $connection->prepare('DELETE FROM admin_tipos_eventos_superficies WHERE tipo_evento_id=:id');
        $insertSurface = $connection->prepare('INSERT INTO admin_tipos_eventos_superficies (tipo_evento_id,superficie,posicion) VALUES (:id,:surface,:position)');
        foreach ($updates as $id => $update) {
            $updateType->execute(['id' => $id, 'name' => trim((string) $update['name']), 'enabled' => ($update['enabled'] ?? false) ? 1 : 0]);
            $deleteSurfaces->execute(['id' => $id]);
            foreach (array_values($update['surfaces'] ?? []) as $position => $surface) {
                $insertSurface->execute(['id' => $id, 'surface' => $surface, 'position' => $position]);
            }
        }
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
    astronomyEventTypeConfigResetCache();
}
