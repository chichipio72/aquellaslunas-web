<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/favorite-moon.php';
require_once __DIR__ . '/../includes/moon-three-render.php';

function favoriteMoonAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$fallback = new DateTimeImmutable('2026-08-17 09:42:37', new DateTimeZone('America/Argentina/Buenos_Aires'));
$selection = favoriteMoonSelection('2024-04-08', '15:17', $fallback);
favoriteMoonAssert($selection['instant']->format(DateTimeInterface::ATOM) === '2024-04-08T15:17:00-03:00', 'La selección no conserva la hora civil local.');
favoriteMoonAssert($selection['corrected'] === false, 'Una selección válida fue marcada como corregida.');

$invalid = favoriteMoonSelection('2024-02-31', '28:00', $fallback);
favoriteMoonAssert($invalid['date'] === '2026-08-17' && $invalid['time'] === '09:42', 'La selección inválida no vuelve al instante de respaldo.');
favoriteMoonAssert($invalid['corrected'] === true, 'La selección inválida no fue informada.');
favoriteMoonAssert(favoriteMoonReturnPath('/astro/luna-fecha-favorita.php', '2024-04-08', '15:17') === '/astro/luna-fecha-favorita.php?fecha=2024-04-08&hora=15%3A17', 'El retorno no conserva fecha y hora.');

$page = file_get_contents(__DIR__ . '/../luna-fecha-favorita.php');
$module = file_get_contents(__DIR__ . '/../assets/js/moon-three-render.js');
$sections = file_get_contents(__DIR__ . '/../includes/site-sections.php');
favoriteMoonAssert(is_string($page) && str_contains($page, 'data-moon-wallpaper-download'), 'La página no ofrece descarga del wallpaper.');
favoriteMoonAssert(str_contains($page, 'moonThreeRenderPayload(') && str_contains($page, '$instant') && str_contains($page, '$observer'), 'La página no reutiliza el render astronómico validado.');
favoriteMoonAssert(str_contains($page, "'favorite.moon_three.'"), 'La página no usa su configuración visual independiente.');
favoriteMoonAssert(substr_count($page, 'data-moon-wallpaper-download') === 2, 'La página no ofrece ambas descargas.');
favoriteMoonAssert(substr_count($page, 'data-moon-wallpaper-share') === 1, 'La página no ofrece compartir el wallpaper para celular.');
favoriteMoonAssert(is_string($module) && str_contains($module, 'wallpaper.width = width') && str_contains($module, 'data-wallpaper-width') === false && str_contains($module, 'wallpaperWidth'), 'El módulo no compone wallpapers configurables.');
favoriteMoonAssert(str_contains($module, 'const drawSize = renderSize * 0.84 * scale'), 'La exportación no deja el margen lunar acordado.');
favoriteMoonAssert(str_contains($module, 'createLinearGradient(0, 0, width, 0)'), 'El fondo no usa el degradado horizontal configurable.');
favoriteMoonAssert(str_contains($module, 'illumination_fraction') && str_contains($module, 'bright_limb_angle_degrees'), 'El resplandor no depende de fase y orientación iluminada.');
favoriteMoonAssert(str_contains($module, 'paintMoonGlow(context') && str_contains($module, 'paintMoonGlow(previewContext'), 'El resplandor no se aplica tanto a exportación como a vista previa.');
favoriteMoonAssert(str_contains($module, "mode === 'sky_diffusion'") && str_contains($module, 'paintSkyDiffusion'), 'El módulo no conserva los dos métodos de resplandor.');
favoriteMoonAssert(str_contains($module, "wallpaper.glow_enabled === 'on' ? 'halo'"), 'El payload heredado no conserva compatibilidad con Halo.');
favoriteMoonAssert(str_contains($module, 'const wallpaperBlobCache = new Map()') && str_contains($module, 'navigator.canShare') && str_contains($module, 'navigator.share({ files: [file]'), 'El wallpaper no reutiliza su PNG al compartir mediante Web Share.');
favoriteMoonAssert(is_string($sections) && str_contains($sections, "'favorite_moon'"), 'La sección no está en la navegación pública.');

if (extension_loaded('pdo_mysql')) {
    $connection = getWebDatabaseConnection();
    $favoriteCatalog = favoriteMoonThreeRenderConfigurationCatalog();
    $favoriteKeys = array_keys($favoriteCatalog);
    $placeholders = implode(',', array_fill(0, count($favoriteKeys), '?'));
    $backupStatement = $connection->prepare('SELECT clave,valor,descripcion FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
    $backupStatement->execute($favoriteKeys);
    $backup = $backupStatement->fetchAll(PDO::FETCH_ASSOC);
    try {
        $delete = $connection->prepare('DELETE FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
        $delete->execute($favoriteKeys);
        $homeValues = moonThreeRenderConfigurationLoad(static fn(): PDO => $connection);
        favoriteMoonThreeRenderConfigurationInitialize($connection);
        $copied = favoriteMoonThreeRenderConfigurationLoad(static fn(): PDO => $connection);
        favoriteMoonAssert((string) $copied['favorite.moon_three.sun_intensity'] === (string) $homeValues['home.moon_three.sun_intensity'], 'La copia inicial no tomó los valores vigentes de portada.');
        favoriteMoonAssert($copied['favorite.moon_three.background_color'] === '#000000', 'El fondo inicial no es negro.');
        favoriteMoonAssert($copied['favorite.moon_three.glow_mode'] === 'sky_diffusion', 'La difusión de cielo no quedó como resplandor inicial.');
        favoriteMoonAssert(moonThreeRenderValidatedValue('favorite.moon_three.glow_mode', 'halo') === 'halo', 'El modo Halo dejó de ser seleccionable.');
        favoriteMoonAssert(abs((float) $copied['favorite.moon_three.glow_intensity'] - 0.18) < 1e-9, 'La intensidad inicial del resplandor cambió.');
        $updated = $copied;
        $updated['favorite.moon_three.sun_intensity'] = 4.7;
        favoriteMoonThreeRenderConfigurationUpdate($connection, $updated);
        favoriteMoonAssert((float) moonThreeRenderConfigurationLoad(static fn(): PDO => $connection)['home.moon_three.sun_intensity'] === (float) $homeValues['home.moon_three.sun_intensity'], 'La configuración favorita modificó la portada.');
    } finally {
        $delete = $connection->prepare('DELETE FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
        $delete->execute($favoriteKeys);
        $restore = $connection->prepare('INSERT INTO admin_configuracion_sitio (clave,valor,descripcion) VALUES (:key,:value,:description)');
        foreach ($backup as $row) $restore->execute([':key' => $row['clave'], ':value' => $row['valor'], ':description' => $row['descripcion']]);
    }
}

echo "favorite moon: OK\n";
