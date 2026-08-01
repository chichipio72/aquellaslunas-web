<?php

require_once __DIR__ . '/../includes/site-sections.php';
require_once __DIR__ . '/../includes/site-configuration.php';
require_once __DIR__ . '/../includes/web-database.php';

function localNavigationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

putenv('APP_ENV=local');
putenv('ASTRONOMY_SHOW_TIMINGS=false');
$connection = getWebDatabaseConnection();
astronomySiteConfigInitialize($connection);

$allEnabled = [];
foreach (array_keys(astronomySiteConfigCatalog()) as $key) {
    $allEnabled[$key] = true;
}
astronomySiteConfigUpdate($connection, $allEnabled);

$localSections = astronomySiteSections();
localNavigationAssert(($localSections['gallery']['menu_enabled'] ?? false) === true, 'Galería no aparece en el menú local.');
localNavigationAssert(($localSections['visual_tests']['menu_enabled'] ?? false) === true, 'Pruebas visuales no aparece en el menú local.');
localNavigationAssert(($localSections['content']['menu_enabled'] ?? false) === true, 'Contenidos no aparece en el menú local.');
localNavigationAssert(($localSections['gallery']['swipe_enabled'] ?? true) === false, 'Galería entró en la navegación por deslizamiento.');
localNavigationAssert(($localSections['visual_tests']['swipe_enabled'] ?? true) === false, 'Pruebas visuales entró en la navegación por deslizamiento.');

$orderedIds = array_keys($localSections);
$galleryIndex = array_search('gallery', $orderedIds, true);
$testsIndex = array_search('visual_tests', $orderedIds, true);
$locationIndex = array_search('location', $orderedIds, true);
localNavigationAssert(
    is_int($galleryIndex) && is_int($testsIndex) && is_int($locationIndex)
    && $galleryIndex < $testsIndex && $testsIndex < $locationIndex,
    'Las herramientas locales no quedaron antes de Ubicación.'
);

putenv('APP_ENV=production');
putenv('ASTRONOMY_SHOW_TIMINGS=true');
$productionSections = astronomySiteSections();
localNavigationAssert(($productionSections['gallery']['menu_enabled'] ?? false) === true, 'Galería dependió del entorno en vez de su configuración pública.');
localNavigationAssert(($productionSections['visual_tests']['menu_enabled'] ?? true) === false, 'Pruebas visuales aparece en el menú de producción.');
localNavigationAssert(($productionSections['content']['menu_enabled'] ?? false) === true, 'Contenidos no permanece visible por defecto en producción.');

astronomySiteConfigUpdate($connection, array_merge($allEnabled, [
    'menu.today.enabled' => false,
    'menu.content.enabled' => false,
]));
$restrictedSections = astronomySiteSections();
localNavigationAssert(($restrictedSections['today']['menu_enabled'] ?? true) === false, 'El menú no ocultó El cielo hoy al deshabilitar su clave.');
localNavigationAssert(($restrictedSections['content']['menu_enabled'] ?? true) === false, 'El menú no ocultó Contenidos al deshabilitar su clave.');
localNavigationAssert(($restrictedSections['visual_tests']['menu_enabled'] ?? true) === false, 'La configuración pública habilitó herramientas locales.');

astronomySiteConfigUpdate($connection, array_merge($allEnabled, [
    'menu.gallery.enabled' => false,
]));
$galleryRestrictedSections = astronomySiteSections();
localNavigationAssert(($galleryRestrictedSections['gallery']['menu_enabled'] ?? true) === false, 'Galería no respetó su clave de configuración pública.');

astronomySiteConfigUpdate($connection, $allEnabled);

putenv('APP_ENV');
putenv('ASTRONOMY_SHOW_TIMINGS');

echo "local-navigation: ok\n";
