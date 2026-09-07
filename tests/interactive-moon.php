<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/interactive-moon.php';
require_once __DIR__ . '/../includes/site-menu.php';

function interactiveMoonAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$defaults = interactiveMoonOptions([]);
interactiveMoonAssert($defaults === ['craters' => true, 'maria' => true, 'other' => false, 'landings' => true, 'detail' => 'auto', 'illumination' => 'realistic', 'embed' => false], 'Las capas iniciales cambiaron.');
$custom = interactiveMoonOptions(['craters' => '0', 'maria' => '1', 'other' => 'true', 'landings' => 'off', 'detail' => 'more', 'illumination' => 'full', 'embed' => '1']);
interactiveMoonAssert($custom === ['craters' => false, 'maria' => true, 'other' => true, 'landings' => false, 'detail' => 'more', 'illumination' => 'full', 'embed' => true], 'El estado URL no se normaliza correctamente.');
interactiveMoonAssert(interactiveMoonOptions(['detail' => 'main'])['detail'] === 'main' && interactiveMoonOptions(['detail' => 'invalid'])['detail'] === 'auto', 'Los modos de etiquetas compatibles no se normalizan correctamente.');

$catalog = interactiveMoonFeatureCatalog();
$features = $catalog['features'];
interactiveMoonAssert(count($features) >= 45, 'El catálogo inicial es demasiado pequeño.');
interactiveMoonAssert(($catalog['metadata']['coordinate_system'] ?? '') === 'IAU Moon, planetocentric, positive east, -180..180', 'No se documentó el sistema de coordenadas.');
foreach ($features as $feature) {
    interactiveMoonAssert(in_array($feature['layer'] ?? '', ['craters', 'maria', 'other', 'landings'], true), 'Hay una capa lunar desconocida.');
    interactiveMoonAssert(is_numeric($feature['lat'] ?? null) && (float) $feature['lat'] >= -90 && (float) $feature['lat'] <= 90, 'Latitud lunar inválida.');
    interactiveMoonAssert(is_numeric($feature['lon'] ?? null) && (float) $feature['lon'] >= -180 && (float) $feature['lon'] <= 180, 'Longitud lunar inválida.');
    interactiveMoonAssert(in_array((int) ($feature['importance'] ?? 0), [1, 2], true), 'Nivel de importancia lunar inválido.');
}
$byName = array_column($features, null, 'name');
interactiveMoonAssert(abs((float) $byName['Tycho']['lat'] - -43.2958) < 1e-9 && abs((float) $byName['Tycho']['lon'] - -11.2153) < 1e-9, 'Tycho no coincide con el Gazetteer.');
interactiveMoonAssert(abs((float) $byName['Apolo 11']['lat'] - 0.67408) < 1e-9 && abs((float) $byName['Apolo 11']['lon'] - 23.47297) < 1e-9, 'Apolo 11 no coincide con la referencia NASA.');
interactiveMoonAssert((float) $byName['Chang’e 4']['lon'] > 170, 'Chang’e 4 no quedó en la cara oculta.');

$page = file_get_contents(__DIR__ . '/../luna-interactiva.php');
$module = file_get_contents(__DIR__ . '/../assets/js/interactive-moon.js');
$styles = file_get_contents(__DIR__ . '/../assets/css/interactive-moon.css');
$sitemap = file_get_contents(__DIR__ . '/../sitemap.php');
interactiveMoonAssert(is_string($page) && str_contains($page, 'data-interactive-moon') && str_contains($page, "moonThreeRenderPayload("), 'La página no reutiliza el render astronómico validado.');
interactiveMoonAssert(str_contains($page, "if (!\$options['embed'])") && str_contains($page, 'data-moon-layer="landings"'), 'La página no implementa embed o capas.');
interactiveMoonAssert(is_string($module) && str_contains($module, 'localToWorld') && str_contains($module, 'normal.dot(toCamera)'), 'Las etiquetas no se proyectan desde la superficie ni se ocultan detrás del limbo.');
interactiveMoonAssert(str_contains($module, 'occupied.some') && str_contains($module, "detail === 'more' && view.zoom"), 'No hay gestión de colisiones o detalle por zoom.');
interactiveMoonAssert(str_contains($module, 'MAXIMUM_LABEL_DETAIL_ZOOM = 1.62') && str_contains($module, 'maximumDetailFeatures'), 'El detalle máximo no incorpora candidatos del Gazetteer completo.');
interactiveMoonAssert(str_contains($module, 'labelCollisionRejects') && str_contains($module, 'diameter_km'), 'El detalle máximo no prioriza tamaño o no permite auditar colisiones.');
interactiveMoonAssert(str_contains($module, 'automaticLabelBand') && str_contains($module, 'fixedLabelBudget') && str_contains($module, 'is-marker-only'), 'El modo automático no controla progresivamente etiquetas y markers.');
interactiveMoonAssert(str_contains($module, "url.searchParams.set('yaw'") && str_contains($module, "url.searchParams.set('zoom'"), 'La vista no es reproducible mediante URL.');
interactiveMoonAssert(str_contains($module, "query.get('feature')") && str_contains($module, 'if (requested) choose(requested)'), 'La Luna interactiva no abre objetos indicados mediante feature=.');
interactiveMoonAssert(str_contains($module, "container.addEventListener('pointermove'") && (str_contains($module, 'inspectionGroup.rotation') || str_contains($module, 'inspectionGroup.quaternion')), 'La Luna ya no permite explorar la esfera mediante rotación controlada.');
interactiveMoonAssert(str_contains($page, 'data-moon-zoom-in') && str_contains($module, "querySelector('[data-moon-zoom-in]')"), 'El zoom no tiene controles accesibles para pantallas táctiles.');
interactiveMoonAssert(str_contains($page, 'Volver a vista desde la Tierra') && str_contains($page, 'conserva la fecha, hora e iluminación elegidas'), 'El restablecimiento de la vista no explica su alcance.');
interactiveMoonAssert(str_contains($page, 'data-moon-illumination') && str_contains($module, "illumination === 'full'") && str_contains($module, "sunlight.position.set(0, 0, 5)"), 'La iluminación completa no mantiene la luz junto a la cámara.');
interactiveMoonAssert(is_string($styles) && str_contains($styles, '.interactive-moon-page--embed'), 'El modo embed no tiene presentación propia.');
$siteCatalog = astronomySiteSectionCatalog();
$defaultMenuAssignments = astronomySiteMenuDefaultAssignments();
interactiveMoonAssert(
    ($siteCatalog['interactive_moon']['url'] ?? null) === 'luna-interactiva.php'
    && ($defaultMenuAssignments['interactive_moon']['group_id'] ?? null) === 'explore',
    'La herramienta no forma parte del catálogo o de la navegación inicial.',
);
interactiveMoonAssert(is_string($sitemap) && str_contains($sitemap, "'/luna-interactiva.php'"), 'La herramienta no está en el sitemap.');

echo "interactive-moon: OK\n";
