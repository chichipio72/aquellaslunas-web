<?php

declare(strict_types=1);

require_once __DIR__ . '/web-database.php';
require_once __DIR__ . '/store-admin-auth.php';

/** @return array<string,array{id:string,label:string,url:?string,swipe_enabled:bool,swipe_order?:int,type?:string}> */
function astronomySiteSectionCatalog(): array
{
    return [
        'home' => ['id' => 'home', 'label' => 'Inicio', 'url' => 'index.php', 'swipe_enabled' => true, 'swipe_order' => 10],
        'today' => ['id' => 'today', 'label' => 'El cielo hoy', 'url' => 'cielo-de-hoy.php', 'swipe_enabled' => true, 'swipe_order' => 20],
        'tonight' => ['id' => 'tonight', 'label' => 'El cielo esta noche', 'url' => 'cielo-de-esta-noche.php', 'swipe_enabled' => true, 'swipe_order' => 30],
        'sun_moon' => ['id' => 'sun_moon', 'label' => 'Calendario solar y lunar', 'url' => 'sol-y-luna.php', 'swipe_enabled' => true, 'swipe_order' => 40],
        'events' => ['id' => 'events', 'label' => 'Eventos lunares', 'url' => 'eventos.php', 'swipe_enabled' => true, 'swipe_order' => 50],
        'eclipses' => ['id' => 'eclipses', 'label' => 'Eclipses', 'url' => 'eclipses.php', 'swipe_enabled' => true, 'swipe_order' => 60],
        'planner' => ['id' => 'planner', 'label' => 'Planificador', 'url' => 'planificador.php', 'swipe_enabled' => true, 'swipe_order' => 70],
        'photography' => ['id' => 'photography', 'label' => 'Fotografía', 'url' => 'fotografia.php', 'swipe_enabled' => false],
        'favorite_moon' => ['id' => 'favorite_moon', 'label' => 'La Luna de tu fecha favorita', 'url' => 'luna-fecha-favorita.php', 'swipe_enabled' => false],
        'interactive_moon' => ['id' => 'interactive_moon', 'label' => 'Luna interactiva', 'url' => 'luna-interactiva.php', 'swipe_enabled' => false],
        'satellite_stations' => ['id' => 'satellite_stations', 'label' => 'ISS y Tiangong', 'url' => 'iss-y-tiangong.php', 'swipe_enabled' => false],
        'explorer' => ['id' => 'explorer', 'label' => 'Explorador astronómico', 'url' => 'explorador/', 'swipe_enabled' => false],
        'content' => ['id' => 'content', 'label' => 'Contenidos', 'url' => 'contenidos.php', 'swipe_enabled' => false],
        'gallery' => ['id' => 'gallery', 'label' => 'Galería', 'url' => 'galeria.php', 'swipe_enabled' => false],
        'visual_tests' => ['id' => 'visual_tests', 'label' => 'Pruebas visuales', 'url' => 'pruebas-visuales.php', 'swipe_enabled' => false],
        'moon_songs' => ['id' => 'moon_songs', 'label' => 'Canciones a la Luna', 'url' => 'canciones-a-la-luna.php', 'swipe_enabled' => false],
        'location' => ['id' => 'location', 'label' => 'Ubicación', 'url' => 'ubicacion.php', 'swipe_enabled' => false],
        'notifications' => ['id' => 'notifications', 'label' => 'Configurar notificaciones', 'url' => 'notificaciones.php', 'swipe_enabled' => false],
        'install' => ['id' => 'install', 'label' => 'Instalar Aquellas Lunas', 'url' => null, 'type' => 'install', 'swipe_enabled' => false],
        'capabilities' => ['id' => 'capabilities', 'label' => 'Qué ofrece Aquellas Lunas', 'url' => 'que-podes-hacer.php', 'swipe_enabled' => false],
        'sources_credits' => ['id' => 'sources_credits', 'label' => 'Fuentes y créditos', 'url' => 'fuentes-y-creditos.php', 'swipe_enabled' => false],
        'about' => ['id' => 'about', 'label' => 'Acerca del sitio', 'url' => 'acerca-del-sitio.php', 'swipe_enabled' => false],
        'administration' => ['id' => 'administration', 'label' => 'Administración', 'url' => 'admin/', 'swipe_enabled' => false],
    ];
}

/** @return array<string,array{id:string,label:string,sort_order:int}> */
function astronomySiteMenuDefaultGroups(): array
{
    return [
        'events' => ['id' => 'events', 'label' => 'Eventos', 'sort_order' => 10],
        'explore' => ['id' => 'explore', 'label' => 'Explorar', 'sort_order' => 20],
        'configuration' => ['id' => 'configuration', 'label' => 'Configuración', 'sort_order' => 30],
        'about' => ['id' => 'about', 'label' => 'Sobre Aquellas Lunas', 'sort_order' => 40],
    ];
}

/** @return array<string,array{group_id:string,sort_order:int,public_visible:bool,admin_visible:bool}> */
function astronomySiteMenuDefaultAssignments(): array
{
    $rows = [];
    $groups = [
        'events' => ['today', 'tonight', 'sun_moon', 'events', 'eclipses', 'planner'],
        'explore' => ['photography', 'favorite_moon', 'interactive_moon', 'satellite_stations', 'explorer', 'content', 'gallery', 'visual_tests', 'moon_songs'],
        'configuration' => ['location', 'notifications', 'install', 'administration'],
        'about' => ['capabilities', 'sources_credits', 'about'],
    ];
    foreach ($groups as $groupId => $sectionIds) {
        foreach ($sectionIds as $index => $sectionId) {
            $publicVisible = $sectionId === 'visual_tests' ? false : true;
            if (!in_array($sectionId, ['install', 'visual_tests'], true) && function_exists('astronomySiteMenuEntryEnabled')) {
                $publicVisible = astronomySiteMenuEntryEnabled($sectionId);
            }
            if ($sectionId === 'content' && function_exists('isContentEnabled')) {
                $publicVisible = $publicVisible && isContentEnabled();
            }
            $rows[$sectionId] = [
                'group_id' => $groupId,
                'sort_order' => ($index + 1) * 10,
                'public_visible' => $publicVisible,
                'admin_visible' => true,
            ];
        }
    }
    return $rows;
}

function astronomySiteMenuResetCache(): void
{
    $cache = &astronomySiteMenuRuntimeCache();
    $cache = [];
}

function &astronomySiteMenuRuntimeCache(): array
{
    static $cache = [];
    return $cache;
}

/** @return array{groups:array<string,array<string,mixed>>,sections:array<string,array<string,mixed>>,source:string} */
function astronomySiteMenuLoad(?callable $connectionFactory = null): array
{
    $cache = &astronomySiteMenuRuntimeCache();
    if ($connectionFactory === null && isset($cache['menu'])) return $cache['menu'];
    $groups = astronomySiteMenuDefaultGroups();
    $sections = astronomySiteMenuDefaultAssignments();
    $source = 'fallback';
    try {
        $connection = $connectionFactory !== null ? $connectionFactory() : getWebDatabaseConnection();
        if (!$connection instanceof PDO) throw new RuntimeException('El menú requiere una conexión PDO válida.');
        $storedGroups = $connection->query('SELECT id,label,sort_order FROM site_menu_groups ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
        $storedSections = $connection->query('SELECT section_id,group_id,sort_order,public_visible,admin_visible FROM site_menu_sections ORDER BY group_id,sort_order,section_id')->fetchAll(PDO::FETCH_ASSOC);
        if ($storedGroups !== []) {
            $groups = [];
            foreach ($storedGroups as $row) $groups[(string) $row['id']] = ['id' => (string) $row['id'], 'label' => (string) $row['label'], 'sort_order' => (int) $row['sort_order']];
        }
        if ($storedSections !== []) {
            $sections = [];
            foreach ($storedSections as $row) $sections[(string) $row['section_id']] = [
                'group_id' => (string) $row['group_id'], 'sort_order' => (int) $row['sort_order'],
                'public_visible' => (bool) $row['public_visible'], 'admin_visible' => (bool) $row['admin_visible'],
            ];
        }
        $source = 'database';
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas site menu load error: ' . $exception->getMessage());
    }
    uasort($groups, static fn(array $a, array $b): int => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
    $result = ['groups' => $groups, 'sections' => $sections, 'source' => $source];
    if ($connectionFactory === null) $cache['menu'] = $result;
    return $result;
}

function astronomySiteMenuCreateGroup(PDO $connection, string $label): string
{
    $label = trim($label);
    if ($label === '' || strlen($label) > 120) throw new InvalidArgumentException('El nombre del grupo debe tener entre 1 y 120 caracteres.');
    $id = 'group_' . bin2hex(random_bytes(6));
    $nextOrder = (int) $connection->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM site_menu_groups')->fetchColumn();
    $statement = $connection->prepare('INSERT INTO site_menu_groups (id,label,sort_order) VALUES (:id,:label,:sort_order)');
    $statement->execute(['id' => $id, 'label' => $label, 'sort_order' => $nextOrder]);
    astronomySiteMenuResetCache();
    return $id;
}

function astronomySiteMenuDeleteGroup(PDO $connection, string $groupId): void
{
    $count = $connection->prepare('SELECT COUNT(*) FROM site_menu_sections WHERE group_id=:group_id');
    $count->execute(['group_id' => $groupId]);
    if ((int) $count->fetchColumn() > 0) throw new InvalidArgumentException('Mové primero las secciones del grupo antes de eliminarlo.');
    $delete = $connection->prepare('DELETE FROM site_menu_groups WHERE id=:id');
    $delete->execute(['id' => $groupId]);
    if ($delete->rowCount() !== 1) throw new InvalidArgumentException('El grupo indicado no existe.');
    astronomySiteMenuResetCache();
}

function astronomySiteMenuUpdate(PDO $connection, array $groupUpdates, array $sectionUpdates): void
{
    $current = astronomySiteMenuLoad(static fn(): PDO => $connection);
    $catalog = astronomySiteSectionCatalog();
    unset($catalog['home']);
    if (array_diff_key($current['groups'], $groupUpdates) !== [] || array_diff_key($groupUpdates, $current['groups']) !== []) throw new InvalidArgumentException('La lista de grupos no coincide con la configuración vigente.');
    if (array_diff_key($catalog, $sectionUpdates) !== [] || array_diff_key($sectionUpdates, $catalog) !== []) throw new InvalidArgumentException('La lista de secciones no coincide con el catálogo vigente.');
    foreach ($groupUpdates as $id => $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '' || strlen($label) > 120 || !is_numeric($row['sort_order'] ?? null)) throw new InvalidArgumentException('Los datos de grupo no son válidos.');
    }
    foreach ($sectionUpdates as $sectionId => $row) {
        if (!isset($groupUpdates[(string) ($row['group_id'] ?? '')]) || !is_numeric($row['sort_order'] ?? null)) throw new InvalidArgumentException('La asignación de sección no es válida.');
    }
    $connection->beginTransaction();
    try {
        $updateGroup = $connection->prepare('UPDATE site_menu_groups SET label=:label,sort_order=:sort_order WHERE id=:id');
        foreach ($groupUpdates as $id => $row) $updateGroup->execute(['id' => $id, 'label' => trim((string) $row['label']), 'sort_order' => (int) $row['sort_order']]);
        $updateSection = $connection->prepare('UPDATE site_menu_sections SET group_id=:group_id,sort_order=:sort_order,public_visible=:public_visible,admin_visible=:admin_visible WHERE section_id=:section_id');
        foreach ($sectionUpdates as $sectionId => $row) $updateSection->execute([
            'section_id' => $sectionId, 'group_id' => (string) $row['group_id'], 'sort_order' => (int) $row['sort_order'],
            'public_visible' => !empty($row['public_visible']) ? 1 : 0, 'admin_visible' => !empty($row['admin_visible']) ? 1 : 0,
        ]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
    astronomySiteMenuResetCache();
}
