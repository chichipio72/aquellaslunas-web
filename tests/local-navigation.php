<?php

require_once __DIR__ . '/../includes/site-sections.php';

function localNavigationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

putenv('APP_ENV=local');
putenv('ASTRONOMY_SHOW_TIMINGS=false');
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
localNavigationAssert(($productionSections['gallery']['menu_enabled'] ?? true) === false, 'Galería aparece en el menú de producción.');
localNavigationAssert(($productionSections['visual_tests']['menu_enabled'] ?? true) === false, 'Pruebas visuales aparece en el menú de producción.');
localNavigationAssert(($productionSections['content']['menu_enabled'] ?? true) === false, 'Contenidos aparece en producción sin habilitación.');

putenv('CONTENT_ENABLED_IN_PRODUCTION=true');
$publishedContentSections = astronomySiteSections();
localNavigationAssert(($publishedContentSections['content']['menu_enabled'] ?? false) === true, 'Contenidos no aparece al habilitarlo explícitamente en producción.');
localNavigationAssert(($publishedContentSections['visual_tests']['menu_enabled'] ?? true) === false, 'La publicación de contenidos habilitó herramientas locales.');

putenv('APP_ENV');
putenv('ASTRONOMY_SHOW_TIMINGS');
putenv('CONTENT_ENABLED_IN_PRODUCTION');

echo "local-navigation: ok\n";
