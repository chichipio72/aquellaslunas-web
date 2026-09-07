<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/event-type-configuration.php';
require_once __DIR__ . '/../scripts/migrations/publish-lunar-nodes.php';

function publishNodesAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$connection = getWebDatabaseConnection();
astronomyEventTypeInitialize($connection);
$rows = $connection->query(
    "SELECT * FROM admin_tipos_eventos WHERE scope='persisted' AND event_group='lunar_orbit' "
    . "AND event_type IN ('ascending_node','descending_node') ORDER BY event_type"
)->fetchAll(PDO::FETCH_ASSOC);
publishNodesAssert(count($rows) === 2, 'No se encontraron ambos nodos configurables.');
$ids = array_map(static fn(array $row): int => (int) $row['id'], $rows);
$idList = implode(',', $ids);
$surfaces = $connection->query("SELECT * FROM admin_tipos_eventos_superficies WHERE tipo_evento_id IN ({$idList})")->fetchAll(PDO::FETCH_ASSOC);

try {
    $connection->exec("DELETE FROM admin_tipos_eventos_superficies WHERE tipo_evento_id IN ({$idList})");
    $legacy = $connection->prepare('UPDATE admin_tipos_eventos SET nombre_amigable=:name,habilitado=0,relevante_esta_noche=0 WHERE id=:id');
    $legacy->execute(['id' => $ids[0], 'name' => 'Nodo lunar ascendente']);
    $legacy->execute(['id' => $ids[1], 'name' => 'Nodo personalizado']);

    runPublishLunarNodesMigration($connection);
    astronomyEventTypeConfigResetCache();

    publishNodesAssert(astronomyEventVisibleOnSurface(['type' => 'lunar_nodes', 'subtype' => 'ascending_node'], ASTRONOMY_EVENT_SURFACE_EVENTS), 'La migración no publicó el nodo legado en Eventos.');
    publishNodesAssert(astronomyEventVisibleOnSurface(['type' => 'lunar_nodes', 'subtype' => 'ascending_node'], ASTRONOMY_EVENT_SURFACE_TODAY), 'La migración no publicó el nodo legado en Hoy.');
    publishNodesAssert(astronomyEventVisibleOnSurface(['type' => 'lunar_nodes', 'subtype' => 'ascending_node'], ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING), 'La migración no publicó el nodo legado en portada.');
    publishNodesAssert(!astronomyEventVisibleOnSurface(['type' => 'lunar_nodes', 'subtype' => 'ascending_node'], ASTRONOMY_EVENT_SURFACE_TONIGHT), 'La migración publicó el nodo legado en Esta noche.');
    publishNodesAssert(!astronomyEventVisibleOnSurface(['type' => 'lunar_nodes', 'subtype' => 'descending_node'], ASTRONOMY_EVENT_SURFACE_EVENTS), 'La migración pisó un override persistido.');
} finally {
    $connection->exec("DELETE FROM admin_tipos_eventos_superficies WHERE tipo_evento_id IN ({$idList})");
    $restoreType = $connection->prepare(
        'UPDATE admin_tipos_eventos SET nombre_amigable=:name,habilitado=:enabled,relevante_esta_noche=:tonight WHERE id=:id'
    );
    foreach ($rows as $row) {
        $restoreType->execute([
            'id' => (int) $row['id'],
            'name' => $row['nombre_amigable'],
            'enabled' => (int) $row['habilitado'],
            'tonight' => $row['relevante_esta_noche'],
        ]);
    }
    $restoreSurface = $connection->prepare(
        'INSERT INTO admin_tipos_eventos_superficies (tipo_evento_id,superficie,posicion,creado_en,actualizado_en) '
        . 'VALUES (:id,:surface,:position,:created,:updated)'
    );
    foreach ($surfaces as $surface) {
        $restoreSurface->execute([
            'id' => (int) $surface['tipo_evento_id'],
            'surface' => $surface['superficie'],
            'position' => (int) $surface['posicion'],
            'created' => $surface['creado_en'],
            'updated' => $surface['actualizado_en'],
        ]);
    }
    astronomyEventTypeConfigResetCache();
}

echo "Publicación configurable de nodos lunares: OK\n";
