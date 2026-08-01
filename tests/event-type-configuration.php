<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/event-type-configuration.php';
require_once __DIR__ . '/../includes/moon-phase-presentation.php';

function eventTypeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$catalog = astronomyEventTypeCatalog();
eventTypeAssert(count($catalog) === 36, 'El catálogo no contiene los 36 tipos auditados.');
eventTypeAssert(count(astronomyEventSurfaceCatalog()) === 6, 'El catálogo de superficies no es cerrado.');

$unavailable = static function (): PDO {
    throw new RuntimeException('simulated unavailable database');
};
$fullMoon = ['type' => 'moon_phase', 'subtype' => 'full_moon'];
$node = ['type' => 'lunar_nodes', 'subtype' => 'ascending_node'];
eventTypeAssert(astronomyEventVisibleOnSurface($fullMoon, ASTRONOMY_EVENT_SURFACE_HOME_PHASES, $unavailable), 'El fallback ocultó una fase vigente.');
eventTypeAssert(astronomyEventVisibleOnSurface($fullMoon, ASTRONOMY_EVENT_SURFACE_TONIGHT, $unavailable), 'El fallback no reprodujo Luna llena esta noche.');
eventTypeAssert(astronomyEventVisibleOnSurface(['type' => 'moon_phase', 'subtype' => 'new_moon'], ASTRONOMY_EVENT_SURFACE_TONIGHT, $unavailable), 'El fallback alteró las fases solicitadas esta noche.');
eventTypeAssert(!astronomyEventVisibleOnSurface($node, ASTRONOMY_EVENT_SURFACE_EVENTS, $unavailable), 'El fallback publicó nodos que antes estaban ocultos.');
eventTypeAssert(astronomyEventRelevantTonight($fullMoon, $unavailable), 'El fallback no conservó la relevancia nocturna de Luna llena.');
eventTypeAssert(astronomyEventRelevantTonight(['type' => 'conjunction', 'subtype' => 'venus'], $unavailable), 'El fallback no conservó la relevancia nocturna de conjunciones.');
eventTypeAssert(!astronomyEventRelevantTonight(['type' => 'moon_phase', 'subtype' => 'new_moon'], $unavailable), 'Una fase ordinaria quedó destacada esta noche.');
eventTypeAssert(!astronomyEventVisibleOnSurface(['type' => 'invented', 'subtype' => 'x'], ASTRONOMY_EVENT_SURFACE_EVENTS, $unavailable), 'Un tipo desconocido quedó visible.');

$invalidSurfaceRejected = false;
try {
    astronomyEventVisibleOnSurface($fullMoon, 'superficie_libre', $unavailable);
} catch (InvalidArgumentException) {
    $invalidSurfaceRejected = true;
}
eventTypeAssert($invalidSurfaceRejected, 'Se aceptó una superficie fuera del catálogo.');

$connection = getWebDatabaseConnection();
astronomyEventTypeInitialize($connection);
$before = astronomyEventTypeAdminRows($connection);
$second = astronomyEventTypeInitialize($connection);
eventTypeAssert((int) $second['inserted'] === 0, 'La inicialización repetida dejó de ser idempotente.');
eventTypeAssert(count($before) === count($catalog), 'La tabla no refleja el catálogo completo.');

$target = null;
foreach ($before as $row) {
    if ($row['event_group'] === 'moon_phase' && $row['event_type'] === 'new_moon') {
        $target = $row;
        break;
    }
}
eventTypeAssert(is_array($target), 'No se encontró Luna nueva para probar la edición.');
$targetId = (int) $target['id'];

try {
    astronomyEventTypeUpdate($connection, [
        $targetId => ['name' => 'Luna nueva editorial', 'enabled' => false, 'relevant_tonight' => true, 'surfaces' => [ASTRONOMY_EVENT_SURFACE_TODAY]],
    ]);
    eventTypeAssert(!astronomyEventVisibleOnSurface(['type' => 'moon_phase', 'subtype' => 'new_moon'], ASTRONOMY_EVENT_SURFACE_TODAY), 'La deshabilitación global no prevaleció.');
    eventTypeAssert(astronomyEventFriendlyName(['type' => 'moon_phase', 'subtype' => 'new_moon']) === 'Luna nueva editorial', 'No se aplicó el nombre amigable editado.');
    eventTypeAssert(astronomyMajorMoonPhaseLabels()['new_moon'] === 'Luna nueva editorial', 'La fase exacta no reutilizó el nombre amigable central.');
    eventTypeAssert(astronomyEventRelevantTonight(['type' => 'moon_phase', 'subtype' => 'new_moon']), 'No se aplicó la relevancia nocturna editada.');

    astronomyEventTypeUpdate($connection, [
        $targetId => ['name' => 'Luna nueva editorial', 'enabled' => true, 'relevant_tonight' => false, 'surfaces' => [ASTRONOMY_EVENT_SURFACE_TODAY]],
    ]);
    eventTypeAssert(astronomyEventVisibleOnSurface(['type' => 'moon_phase', 'subtype' => 'new_moon'], ASTRONOMY_EVENT_SURFACE_TODAY), 'No se aplicó la visibilidad por superficie.');
    eventTypeAssert(!astronomyEventVisibleOnSurface(['type' => 'moon_phase', 'subtype' => 'new_moon'], ASTRONOMY_EVENT_SURFACE_HOME_PHASES), 'Una superficie no seleccionada quedó visible.');
} finally {
    astronomyEventTypeUpdate($connection, [
        $targetId => [
            'name' => (string) $target['nombre_amigable'],
            'enabled' => (int) $target['habilitado'] === 1,
            'relevant_tonight' => $target['relevante_esta_noche'] !== null ? (int) $target['relevante_esta_noche'] === 1 : (($target['relevantTonight'] ?? false) === true),
            'surfaces' => $target['surfaces'],
        ],
    ]);
}

echo "Event type configuration checks passed.\n";
