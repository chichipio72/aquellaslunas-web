<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/site-sections.php';
require_once __DIR__ . '/../scripts/migrations/create-site-menu-configuration.php';

function localNavigationAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$connection = getWebDatabaseConnection();
runSiteMenuConfigurationMigration($connection);
astronomySiteMenuResetCache();
$publicSections = astronomySiteSections(false);
$adminSections = astronomySiteSections(true);

localNavigationAssert(($publicSections['home']['menu_enabled'] ?? false) === true, 'Inicio no permanece visible para público.');
localNavigationAssert(array_key_first($publicSections) === 'home', 'Inicio no permanece primero.');
localNavigationAssert(($adminSections['gallery']['menu_enabled'] ?? false) === true, 'Galería no aparece para administración.');
localNavigationAssert(($adminSections['visual_tests']['menu_enabled'] ?? false) === true, 'Pruebas visuales no aparece para administración.');
localNavigationAssert(($publicSections['visual_tests']['menu_enabled'] ?? true) === false, 'Pruebas visuales aparece para público.');
localNavigationAssert(($adminSections['gallery']['swipe_enabled'] ?? true) === false, 'Galería entró en navegación por deslizamiento.');
localNavigationAssert(($adminSections['visual_tests']['swipe_enabled'] ?? true) === false, 'Pruebas visuales entró en navegación por deslizamiento.');

$swipeSections = array_values(array_filter($publicSections, static fn(array $section): bool => $section['swipe_enabled'] === true));
usort($swipeSections, static fn(array $first, array $second): int => ($first['swipe_order'] ?? 0) <=> ($second['swipe_order'] ?? 0));
localNavigationAssert(array_column($swipeSections, 'id') === ['home', 'today', 'tonight', 'sun_moon', 'events', 'eclipses', 'planner'], 'La configuración del menú alteró el contrato independiente de swipe.');

echo "local-navigation: ok\n";
