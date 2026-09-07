<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';
require_once dirname(__DIR__, 2) . '/includes/event-type-configuration.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function runPublishLunarNodesMigration(PDO $connection): void
{
    $eligible = $connection->prepare(
        "SELECT t.id FROM admin_tipos_eventos t "
        . "LEFT JOIN admin_tipos_eventos_superficies s ON s.tipo_evento_id=t.id "
        . "WHERE t.scope='persisted' AND t.event_group='lunar_orbit' "
        . "AND t.event_type=:event_type AND t.nombre_amigable=:legacy_name "
        . "AND t.habilitado=0 AND (t.relevante_esta_noche IS NULL OR t.relevante_esta_noche=0) "
        . "GROUP BY t.id HAVING COUNT(s.tipo_evento_id)=0"
    );
    $enable = $connection->prepare('UPDATE admin_tipos_eventos SET habilitado=1,relevante_esta_noche=0 WHERE id=:id');
    $surface = $connection->prepare(
        'INSERT INTO admin_tipos_eventos_superficies (tipo_evento_id,superficie,posicion) VALUES (:id,:surface,:position) '
        . 'ON DUPLICATE KEY UPDATE tipo_evento_id=tipo_evento_id'
    );
    $legacyNames = [
        'ascending_node' => 'Nodo lunar ascendente',
        'descending_node' => 'Nodo lunar descendente',
    ];
    $surfaces = [
        ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING,
        ASTRONOMY_EVENT_SURFACE_TODAY,
        ASTRONOMY_EVENT_SURFACE_EVENTS,
    ];

    foreach ($legacyNames as $eventType => $legacyName) {
        $eligible->execute(['event_type' => $eventType, 'legacy_name' => $legacyName]);
        $id = $eligible->fetchColumn();
        if ($id === false) {
            continue;
        }
        $enable->execute(['id' => (int) $id]);
        foreach ($surfaces as $position => $surfaceName) {
            $surface->execute(['id' => (int) $id, 'surface' => $surfaceName, 'position' => $position]);
        }
    }
    astronomyEventTypeConfigResetCache();
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runPublishLunarNodesMigration(getWebDatabaseConnection());
    echo "Nodos lunares incorporados a las superficies públicas configuradas.\n";
}
