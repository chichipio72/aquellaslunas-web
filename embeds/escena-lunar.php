<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/lunar-scene-embed.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$timezone = new DateTimeZone((string) $location['timezone']);
$options = lunarSceneEmbedOptions($_GET, get_current_datetime($timezone->getName()));
$payload = null;
try {
    $payload = lunarSceneEmbedPayload($options['instant'], $location, $options);
    foreach (['albedo', 'relief'] as $textureKey) {
        if (is_string($payload['moon']['textures'][$textureKey] ?? null)) $payload['moon']['textures'][$textureKey] = '../' . ltrim($payload['moon']['textures'][$textureKey], '/');
    }
} catch (Throwable $exception) {
    error_log('Aquellas Lunas lunar scene embed error: ' . $exception->getMessage());
}
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Escena lunar · Widget interno</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/lunar-widgets.css')) ?>">
    <?php if ($payload !== null): ?><script type="module" src="<?= $html('../' . versionedAssetUrl('assets/js/moon-three-render.js')) ?>"></script><?php endif; ?>
</head>
<body class="lunar-widget lunar-widget--scene">
<main class="lunar-widget__frame lunar-scene" data-lunar-scene style="--lunar-scene-center: <?= $html($payload['scene']['sky_center_color'] ?? '#152033') ?>; --lunar-scene-edge: <?= $html($payload['scene']['sky_edge_color'] ?? '#01040c') ?>; --lunar-scene-radius: <?= $html(number_format((float) ($payload['scene']['sky_gradient_radius_percent'] ?? 64), 2, '.', '')) ?>%; --lunar-scene-halo: <?= $html(number_format((float) ($payload['scene']['halo_intensity'] ?? 0), 3, '.', '')) ?>">
    <?php if ($payload !== null): ?>
        <div class="lunar-scene__moon" data-moon-three data-moon-three-state="loading" role="img" aria-label="Luna para el instante y ubicación elegidos">
            <script type="application/json" data-moon-three-payload><?= json_encode($payload['moon'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
            <p class="lunar-widget__loading" data-moon-three-loading>Preparando la escena lunar…</p>
        </div>
        <p class="lunar-scene__context"><time datetime="<?= $html($payload['scene']['instant']) ?>"><?= $html($options['instant']->format('d/m/Y · H:i')) ?></time><span><?= $html($payload['scene']['location_name']) ?></span></p>
    <?php else: ?>
        <p class="lunar-widget__error" role="alert">No se pudo preparar la escena lunar.</p>
    <?php endif; ?>
</main>
</body>
</html>
