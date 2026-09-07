<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/moon-three-render.php';

use AstronomyEngine\Facade\AstronomyObserver;

function moonRenderAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$catalog = moonThreeRenderConfigurationCatalog();
moonRenderAssert(count($catalog) === 13, 'El catálogo debe contener sólo los trece parámetros usados por el render.');
$interactiveCatalog = interactiveMoonThreeRenderConfigurationCatalog();
$interactiveRequiredKeys = [
    'interactive.moon_three.sun_intensity',
    'interactive.moon_three.size_percent',
    'interactive.moon_three.high_res_relief_enabled',
    'interactive.moon_three.high_res_mode',
    'interactive.moon_three.high_res_zoom_threshold',
    'interactive.moon_three.high_res_normal_scale_x',
    'interactive.moon_three.high_res_normal_scale_y',
    'interactive.moon_three.terminator_attenuation_enabled',
    'interactive.moon_three.terminator_attenuation_width_degrees',
    'interactive.moon_three.terminator_attenuation_minimum_percent',
    'interactive.moon_three.terminator_attenuation_curve',
];
moonRenderAssert(array_diff($interactiveRequiredKeys, array_keys($interactiveCatalog)) === [],
    'La Luna interactiva no tiene su catálogo independiente completo con relieve 8K.');
moonRenderAssert((float) moonThreeRenderValidatedValue('interactive.moon_three.size_percent', 105) === 105.0, 'No se validó el tamaño independiente de la Luna interactiva.');
moonRenderAssert(moonThreeRenderValidatedValue('interactive.moon_three.high_res_mode', 'auto') === 'auto', 'No se validó el modo automático 8K.');
moonRenderAssert((float) moonThreeRenderValidatedValue('home.moon_three.size_percent', 100) === 100.0, 'No se validó el tamaño nominal del render.');
moonRenderAssert(moonThreeRenderValidatedValue('home.moon_three.relief_mode', 'normal') === 'normal', 'No se validó el modo normal.');
try {
    moonThreeRenderValidatedValue('home.moon_three.sun_intensity', 20);
    throw new RuntimeException('Se aceptó una intensidad fuera de rango.');
} catch (InvalidArgumentException) {
}

$defaults = moonThreeRenderConfigurationDefaults();
$payload = moonThreeRenderPayload(
    new DateTimeImmutable('2026-08-16T22:01:00Z'),
    new AstronomyObserver(-34.532989356165835, -58.537805002828605, 'America/Argentina/Buenos_Aires'),
    $defaults,
);
moonRenderAssert(($payload['appearance']['relief_mode'] ?? null) === 'normal', 'El payload no aplicó el relieve configurado.');
moonRenderAssert((float) ($payload['appearance']['size_percent'] ?? 0) === 100.0, 'El payload no aplicó el tamaño nominal configurado.');
moonRenderAssert(str_contains((string) ($payload['textures']['albedo'] ?? ''), 'assets/images/moon-three/lroc_color_2k.jpg'), 'El payload no usa el albedo público.');
moonRenderAssert(str_contains((string) ($payload['textures']['relief'] ?? ''), 'ldem_4_normal.png'), 'El payload no seleccionó el normal map medio.');
moonRenderAssert(abs((float) ($payload['geometry']['phase']['illumination_percent'] ?? 0) - 19.83049) < 0.01, 'La geometría del componente no coincide con el caso auditado.');
$interactiveDefaults = moonThreeRenderCatalogDefaults($interactiveCatalog);
$interactiveDefaults['interactive.moon_three.sun_intensity'] = 4.1;
$interactivePayload = moonThreeRenderPayload(
    new DateTimeImmutable('2026-08-16T22:01:00Z'),
    new AstronomyObserver(-34.532989356165835, -58.537805002828605, 'America/Argentina/Buenos_Aires'),
    $interactiveDefaults,
    'interactive.moon_three.',
);
moonRenderAssert(abs((float) $interactivePayload['appearance']['sun_intensity'] - 4.1) < 1e-9, 'El payload no consume el namespace interactivo.');
moonRenderAssert(($interactivePayload['appearance']['high_resolution']['mode'] ?? null) === 'auto', 'El payload interactivo no contiene el control 8K.');
moonRenderAssert(str_contains((string) ($interactivePayload['textures']['relief_high'] ?? ''), 'lola_64ppd_normal_8k.png'), 'El payload no publica el normal map 8K progresivo.');
$favoriteDefaults = moonThreeRenderCatalogDefaults(favoriteMoonThreeRenderConfigurationCatalog());
$favoritePayload = moonThreeRenderPayload(
    new DateTimeImmutable('2026-08-16T22:01:00Z'),
    new AstronomyObserver(-34.532989356165835, -58.537805002828605, 'America/Argentina/Buenos_Aires'),
    $favoriteDefaults,
    'favorite.moon_three.',
);
moonRenderAssert(($favoritePayload['appearance']['high_resolution']['mode'] ?? null) === 'export', 'La fecha favorita no reserva el normal map 8K para exportación.');
moonRenderAssert(str_contains((string) ($favoritePayload['textures']['relief_high'] ?? ''), 'lola_64ppd_normal_8k.png'), 'La exportación favorita no reutiliza el normal map 8K.');

$homeSource = file_get_contents(__DIR__ . '/../index.php');
$todaySource = file_get_contents(__DIR__ . '/../cielo-de-hoy.php');
$tonightSource = file_get_contents(__DIR__ . '/../includes/home-tonight-scene.php');
$sharedStyles = file_get_contents(__DIR__ . '/../assets/css/styles.css');
moonRenderAssert(is_string($homeSource) && str_contains($homeSource, 'data-moon-three-payload'), 'La portada no monta el componente Three.js.');
moonRenderAssert(str_contains($homeSource, "home_moon_three_diagnostic"), 'La portada no mide la preparación server-side de la Luna.');
moonRenderAssert(is_string($todaySource) && str_contains($todaySource, 'todayMoonThreePayload') && str_contains($todaySource, 'data-moon-three-payload'), 'El cielo de hoy no monta el componente Three.js compartido.');
moonRenderAssert(str_contains($todaySource, 'moon-three-render__fallback') && str_contains($todaySource, 'moon-image.php?'), 'El cielo de hoy no conserva el PNG como fallback de WebGL.');
moonRenderAssert(str_contains($homeSource, 'data-fallback-src=') && str_contains($todaySource, 'data-fallback-src='), 'Portada o El cielo de hoy vuelven a cargar el PNG durante el estado inicial Three.js.');
moonRenderAssert(is_string($tonightSource) && str_contains($tonightSource, 'moonPhaseThumbnail'), 'La escena de esta noche dejó de usar miniaturas PNG.');
moonRenderAssert(is_string($sharedStyles) && !str_contains($sharedStyles, "\n.moonrise-notice::before"), 'El indicador de salida lunar volvió a quedar global y puede aparecer fuera de lugar.');
$apiClientSource = file_get_contents(__DIR__ . '/../includes/api-client.php');
$moonModuleSource = file_get_contents(__DIR__ . '/../assets/js/moon-three-render.js');
$interactivePageSource = file_get_contents(__DIR__ . '/../luna-interactiva.php');
$interactiveModuleSource = file_get_contents(__DIR__ . '/../assets/js/interactive-moon.js');
moonRenderAssert(is_string($apiClientSource) && str_contains($apiClientSource, 'data-moon-three-diagnostic-status'), 'El diagnóstico debug no expone los tiempos de la Luna.');
moonRenderAssert(is_string($moonModuleSource) && str_contains($moonModuleSource, 'resourcesMilliseconds') && str_contains($moonModuleSource, 'firstRenderMilliseconds'), 'El navegador no mide recursos y primer render lunar.');
moonRenderAssert(str_contains($moonModuleSource, 'const showFallback = () =>') && str_contains($moonModuleSource, 'fallbackImage.src = fallbackImage.dataset.fallbackSrc'), 'El PNG no queda reservado exclusivamente para un fallo real de Three.js.');
moonRenderAssert(str_contains($moonModuleSource, 'applyAppearance') && str_contains($moonModuleSource, 'appearanceUniforms.textureContrast.value'), 'La vista previa no actualiza la apariencia completa en vivo.');
moonRenderAssert(is_string($interactivePageSource) && str_contains($interactivePageSource, "'interactive.moon_three.'"), 'La Luna interactiva pública no usa su configuración independiente.');
moonRenderAssert(is_string($interactiveModuleSource) && str_contains($interactiveModuleSource, 'view.zoom * configuredSize'), 'El tamaño base configurado no impacta en el visor interactivo.');
moonRenderAssert(str_contains($interactiveModuleSource, 'maxTextureSize >= 8192') && str_contains($interactiveModuleSource, "moonHighResolution = 'ready'"), 'El visor no carga o no protege el normal map 8K.');
moonRenderAssert(str_contains($moonModuleSource, 'activateHighResolution(exportRenderer)') && str_contains($moonModuleSource, 'paintWallpaperText'), 'La descarga no activa 8K o no compone el texto opcional.');

if (extension_loaded('pdo_mysql')) {
    $connection = getWebDatabaseConnection();
    $keys = array_keys($catalog);
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $backupStatement = $connection->prepare('SELECT clave,valor,descripcion FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
    $backupStatement->execute($keys);
    $backup = $backupStatement->fetchAll(PDO::FETCH_ASSOC);

    try {
        $updated = $defaults;
        $updated['home.moon_three.sun_intensity'] = 4.4;
        $updated['home.moon_three.relief_mode'] = 'none';
        $updated['home.moon_three.size_percent'] = 108;
        moonThreeRenderConfigurationUpdate($connection, $updated);
        $loaded = moonThreeRenderConfigurationLoad(static fn(): PDO => $connection);
        moonRenderAssert(abs((float) $loaded['home.moon_three.sun_intensity'] - 4.4) < 1e-9, 'La intensidad no persistió.');
        moonRenderAssert($loaded['home.moon_three.relief_mode'] === 'none', 'El selector de relieve no persistió.');
        moonRenderAssert((float) $loaded['home.moon_three.size_percent'] === 108.0, 'El tamaño del render no persistió.');
        $storedPayload = moonThreeRenderPayload(
            new DateTimeImmutable('2026-08-16T22:01:00Z'),
            new AstronomyObserver(-34.532989356165835, -58.537805002828605, 'America/Argentina/Buenos_Aires'),
            $loaded,
        );
        moonRenderAssert(abs((float) $storedPayload['appearance']['sun_intensity'] - 4.4) < 1e-9, 'El payload público no recibió la intensidad persistida.');
        moonRenderAssert((float) $storedPayload['appearance']['size_percent'] === 108.0, 'El payload público no recibió el tamaño persistido.');
        moonRenderAssert($storedPayload['textures']['relief'] === null, 'El payload público no aplicó el relieve desactivado.');
    } finally {
        $connection->beginTransaction();
        try {
            $delete = $connection->prepare('DELETE FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')');
            $delete->execute($keys);
            $restore = $connection->prepare('INSERT INTO admin_configuracion_sitio (clave,valor,descripcion) VALUES (:key,:value,:description)');
            foreach ($backup as $row) $restore->execute([':key' => $row['clave'], ':value' => $row['valor'], ':description' => $row['descripcion']]);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $exception;
        }
    }
}

echo "moon-three-render: OK\n";
