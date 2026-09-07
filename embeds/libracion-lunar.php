<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$timezone = new DateTimeZone((string) $location['timezone']);
$instant = get_current_datetime($timezone->getName());
$options = lunarLibrationEmbedOptions($_GET);
$payload = null;
try {
    $payload = lunarLibrationEmbedPayload($instant, lunarEmbedObserver($location), $options);
    foreach (['albedo', 'relief'] as $textureKey) {
        if (is_string($payload['textures'][$textureKey] ?? null)) $payload['textures'][$textureKey] = '../' . ltrim($payload['textures'][$textureKey], '/');
    }
} catch (Throwable $exception) {
    error_log('Aquellas Lunas lunar libration embed error: ' . $exception->getMessage());
}
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Libración lunar · Widget</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/lunar-widgets.css')) ?>">
    <?php if ($payload !== null): ?><script type="module" src="<?= $html('../' . versionedAssetUrl('assets/js/lunar-libration-widget.js')) ?>"></script><?php endif; ?>
</head>
<body class="lunar-widget lunar-widget--libration">
    <main class="lunar-widget__frame">
        <?php if ($payload !== null): ?>
            <div class="libration-widget" data-libration-widget data-libration-state="loading">
                <script type="application/json" data-libration-payload><?= json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                <p class="lunar-widget__loading" data-libration-loading>Preparando libración…</p>
                <div class="libration-widget__readout" aria-live="off">
                    <strong data-libration-date></strong>
                    <span data-libration-values></span>
                </div>
                <?php if ($options['controls']): ?>
                    <div class="libration-widget__controls">
                        <button type="button" data-libration-toggle><?= $options['autoplay'] ? 'Pausar' : 'Reproducir' ?></button>
                        <label><span>Recorrido</span><input type="range" min="0" max="1000" step="1" value="0" data-libration-progress></label>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="lunar-widget__error" role="alert">No se pudo preparar la animación de libración.</p>
        <?php endif; ?>
    </main>
</body>
</html>
