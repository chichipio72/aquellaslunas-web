<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/site-sections.php';
require_once __DIR__ . '/../scripts/migrations/create-site-menu-configuration.php';

function siteMenuAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$connection = getWebDatabaseConnection();
runSiteMenuConfigurationMigration($connection);
$groupsSnapshot = $connection->query('SELECT id,label,sort_order FROM site_menu_groups')->fetchAll(PDO::FETCH_ASSOC);
$sectionsSnapshot = $connection->query('SELECT section_id,group_id,sort_order,public_visible,admin_visible FROM site_menu_sections')->fetchAll(PDO::FETCH_ASSOC);

$restore = static function () use ($connection, $groupsSnapshot, $sectionsSnapshot): void {
    $connection->beginTransaction();
    try {
        $connection->exec('DELETE FROM site_menu_sections');
        $connection->exec('DELETE FROM site_menu_groups');
        $groupInsert = $connection->prepare('INSERT INTO site_menu_groups (id,label,sort_order) VALUES (:id,:label,:sort_order)');
        foreach ($groupsSnapshot as $row) $groupInsert->execute($row);
        $sectionInsert = $connection->prepare('INSERT INTO site_menu_sections (section_id,group_id,sort_order,public_visible,admin_visible) VALUES (:section_id,:group_id,:sort_order,:public_visible,:admin_visible)');
        foreach ($sectionsSnapshot as $row) $sectionInsert->execute($row);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
    astronomySiteMenuResetCache();
};

try {
    $expectedMigration = astronomySiteMenuDefaultAssignments();
    $connection->exec('DELETE FROM site_menu_sections');
    $connection->exec('DELETE FROM site_menu_groups');
    astronomySiteMenuResetCache();
    runSiteMenuConfigurationMigration($connection);
    $menu = astronomySiteMenuLoad();
    siteMenuAssert(array_keys($menu['groups']) === ['events', 'explore', 'configuration', 'about'], 'Los grupos iniciales o su orden no coinciden.');
    siteMenuAssert(($menu['sections']['gallery']['admin_visible'] ?? false) === true, 'Galería no quedó disponible para administradores.');
    siteMenuAssert(($menu['sections']['visual_tests']['public_visible'] ?? true) === false && ($menu['sections']['visual_tests']['admin_visible'] ?? false) === true, 'Pruebas visuales no quedó inicialmente sólo para administración.');
    foreach ($expectedMigration as $sectionId => $expected) {
        siteMenuAssert(($menu['sections'][$sectionId]['public_visible'] ?? null) === $expected['public_visible'], 'La migración alteró la visibilidad pública previa de ' . $sectionId . '.');
    }

    $groups = $menu['groups'];
    $groups['events']['label'] = 'Agenda';
    $groups['events']['sort_order'] = 30;
    $groups['explore']['sort_order'] = 10;
    $sections = $menu['sections'];
    $sections['today'] = ['group_id' => 'events', 'sort_order' => 20, 'public_visible' => true, 'admin_visible' => true];
    $sections['tonight'] = ['group_id' => 'events', 'sort_order' => 10, 'public_visible' => false, 'admin_visible' => true];
    $sections['eclipses'] = ['group_id' => 'events', 'sort_order' => 30, 'public_visible' => true, 'admin_visible' => false];
    $sections['planner'] = ['group_id' => 'events', 'sort_order' => 40, 'public_visible' => false, 'admin_visible' => false];
    $sections['content']['group_id'] = 'about';
    $sections['content']['sort_order'] = 5;
    astronomySiteMenuUpdate($connection, $groups, $sections);
    astronomySiteMenuResetCache();
    $persisted = astronomySiteMenuLoad();
    siteMenuAssert($persisted['groups']['events']['label'] === 'Agenda', 'El nombre del grupo no persistió.');
    siteMenuAssert($persisted['sections']['content']['group_id'] === 'about', 'El movimiento de sección no persistió.');

    ob_start(); renderAstronomySiteNavigation('today', '', false); $publicHtml = (string) ob_get_clean();
    ob_start(); renderAstronomySiteNavigation('tonight', '', true); $adminHtml = (string) ob_get_clean();
    siteMenuAssert(strpos($publicHtml, '>Inicio<') < strpos($publicHtml, 'site-nav__group-title'), 'Inicio no quedó primero y fuera de grupos.');
    siteMenuAssert(strpos($publicHtml, '>Explorar<') < strpos($publicHtml, '>Agenda<'), 'No se respetó el orden de grupos.');
    siteMenuAssert(str_contains($publicHtml, '>El cielo hoy<') && !str_contains($publicHtml, '>El cielo esta noche<'), 'Falló el caso público sí/admin sí o público no/admin sí.');
    siteMenuAssert(str_contains($adminHtml, '>El cielo hoy<'), 'Falló el caso público sí/admin sí en contexto administrativo.');
    siteMenuAssert(strpos($publicHtml, '>El cielo hoy<') < strpos($publicHtml, '>Calendario solar y lunar<'), 'No se respetó el orden de secciones dentro del grupo.');
    siteMenuAssert(str_contains($publicHtml, '>Eclipses<') && !str_contains($adminHtml, '>Eclipses<'), 'Falló el caso público sí/admin no.');
    siteMenuAssert(!str_contains($publicHtml, '>Planificador<') && !str_contains($adminHtml, '>Planificador<'), 'Falló el caso público no/admin no.');
    siteMenuAssert(str_contains($adminHtml, '>El cielo esta noche<') && str_contains($adminHtml, 'aria-current="page"'), 'El menú administrador no muestra su sección propia o perdió el estado activo.');
    siteMenuAssert(str_contains($publicHtml, 'data-install-trigger data-install-source="menu"'), 'La instalación desde el menú cambió de contrato.');

    $emptyGroupId = astronomySiteMenuCreateGroup($connection, 'Vacío');
    ob_start(); renderAstronomySiteNavigation('home', '', false); $emptyHtml = (string) ob_get_clean();
    siteMenuAssert(!str_contains($emptyHtml, '>Vacío<'), 'Un grupo sin secciones visibles aparece en el menú.');
    astronomySiteMenuDeleteGroup($connection, $emptyGroupId);
    $deleteRejected = false;
    try { astronomySiteMenuDeleteGroup($connection, 'events'); } catch (InvalidArgumentException) { $deleteRejected = true; }
    siteMenuAssert($deleteRejected, 'Se eliminó un grupo que todavía contenía secciones.');

    $publicSections = astronomySiteSections(false);
    $adminSections = astronomySiteSections(true);
    siteMenuAssert(($publicSections['tonight']['menu_enabled'] ?? true) === false && ($adminSections['tonight']['menu_enabled'] ?? false) === true, 'La doble visibilidad no llega al catálogo de navegación.');
    siteMenuAssert(($publicSections['home']['menu_enabled'] ?? false) === true && ($adminSections['home']['menu_enabled'] ?? false) === true, 'Inicio dejó de ser incondicional.');
    $catalog = astronomySiteSectionCatalog();
    siteMenuAssert($catalog['today']['url'] === 'cielo-de-hoy.php' && $catalog['gallery']['url'] === 'galeria.php' && $catalog['administration']['url'] === 'admin/', 'La configuración modificó URLs existentes.');
} finally {
    $restore();
}

echo "site-menu: ok\n";
