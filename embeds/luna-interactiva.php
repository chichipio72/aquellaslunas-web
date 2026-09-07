<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$timezone = new DateTimeZone((string) $location['timezone']);
$selection = favoriteMoonSelection($_GET['fecha'] ?? null, $_GET['hora'] ?? null, get_current_datetime($timezone->getName()));
$options = lunarInteractiveEmbedOptions($_GET);
$payload = null;
try {
    $interactiveConfiguration = interactiveMoonThreeRenderConfigurationLoad();
    $payload = moonThreeRenderPayload($selection['instant'], lunarEmbedObserver($location), $interactiveConfiguration, 'interactive.moon_three.');
    $payload['features'] = interactiveMoonFeatureCatalog();
    $payload['catalogs'] = ['gazetteer' => '../' . ltrim(versionedAssetUrl('assets/data/moon-gazetteer.json'), '/')];
    $payload['interactive'] = $options;
    foreach (['albedo', 'relief'] as $textureKey) {
        if (is_string($payload['textures'][$textureKey] ?? null)) $payload['textures'][$textureKey] = '../' . ltrim($payload['textures'][$textureKey], '/');
    }
} catch (Throwable $exception) {
    error_log('Aquellas Lunas interactive Moon embed error: ' . $exception->getMessage());
}
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Luna interactiva · Widget</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/interactive-moon.css')) ?>">
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/lunar-widgets.css')) ?>">
    <?php if ($payload !== null): ?><script type="module" src="<?= $html('../' . versionedAssetUrl('assets/js/interactive-moon.js')) ?>"></script><?php endif; ?>
</head>
<body class="lunar-widget lunar-widget--interactive">
    <main class="lunar-widget__frame">
        <?php if ($payload !== null): ?>
            <?php if ($options['controls']): ?>
                <div class="lunar-widget__toolbar" aria-label="Capas de la Luna interactiva">
                    <label><input type="checkbox" data-moon-layer="craters"<?= $options['craters'] ? ' checked' : '' ?>> Cráteres</label>
                    <label><input type="checkbox" data-moon-layer="maria"<?= $options['maria'] ? ' checked' : '' ?>> Mares</label>
                    <label><input type="checkbox" data-moon-layer="other"<?= $options['other'] ? ' checked' : '' ?>> Otros</label>
                    <label><input type="checkbox" data-moon-layer="landings"<?= $options['landings'] ? ' checked' : '' ?>> Alunizajes</label>
                    <label><span class="visually-hidden">Etiquetas</span><select data-moon-detail><option value="auto"<?= $options['detail'] === 'auto' ? ' selected' : '' ?>>Automáticas</option><option value="main"<?= $options['detail'] === 'main' ? ' selected' : '' ?>>Principales</option><option value="more"<?= $options['detail'] === 'more' ? ' selected' : '' ?>>Máximo detalle</option></select></label>
                    <label><span class="visually-hidden">Iluminación</span><select data-moon-illumination><option value="realistic"<?= $options['illumination'] === 'realistic' ? ' selected' : '' ?>>Luz realista</option><option value="full"<?= $options['illumination'] === 'full' ? ' selected' : '' ?>>Todo iluminado</option></select></label>
                    <button type="button" data-moon-zoom-out aria-label="Alejar">−</button>
                    <button type="button" data-moon-zoom-in aria-label="Acercar">+</button>
                    <button type="button" data-moon-reset-view>Vista inicial</button>
                </div>
            <?php endif; ?>
            <div id="embedded-interactive-moon" class="interactive-moon-render lunar-widget__moon<?= $options['rotation'] === 'locked' ? ' is-rotation-locked' : '' ?>" data-interactive-moon data-moon-state="loading">
                <script type="application/json" data-interactive-moon-payload><?= json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                <div class="interactive-moon-labels" data-moon-labels aria-hidden="true"></div>
                <p class="interactive-moon-loading" data-moon-loading>Cargando Luna…</p>
            </div>
        <?php else: ?>
            <p class="lunar-widget__error" role="alert">No se pudo preparar la Luna interactiva.</p>
        <?php endif; ?>
    </main>
</body>
</html>
