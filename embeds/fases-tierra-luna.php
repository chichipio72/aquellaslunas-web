<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$timezone = new DateTimeZone((string) $location['timezone']);
$selection = favoriteMoonSelection($_GET['fecha'] ?? null, $_GET['hora'] ?? null, get_current_datetime($timezone->getName()));
$options = earthMoonEmbedOptions($_GET);
$payload = null;
try {
    $payload = earthMoonEmbedPayload($selection['instant'], lunarEmbedObserver($location), $options);
    foreach (['albedo', 'relief', 'earth_albedo'] as $textureKey) {
        if (is_string($payload['textures'][$textureKey] ?? null)) $payload['textures'][$textureKey] = '../' . ltrim($payload['textures'][$textureKey], '/');
    }
} catch (Throwable $exception) {
    error_log('Aquellas Lunas Earth-Moon phases embed error: ' . $exception->getMessage());
}
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Fases Tierra–Luna · Widget</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/lunar-widgets.css')) ?>">
    <?php if ($payload !== null): ?><script type="module" src="<?= $html('../' . versionedAssetUrl('assets/js/earth-moon-phases-widget.js')) ?>"></script><?php endif; ?>
</head>
<body class="lunar-widget lunar-widget--earth-moon">
    <main class="lunar-widget__frame">
        <?php if ($payload !== null): ?>
            <div class="earth-moon-widget" data-earth-moon-widget data-earth-moon-state="loading">
                <script type="application/json" data-earth-moon-payload><?= json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                <p class="lunar-widget__loading" data-earth-moon-loading>Preparando el sistema Tierra–Luna…</p>
                <div class="earth-moon-widget__comparison" aria-label="Fases recíprocas de la Luna y la Tierra">
                    <figure class="earth-moon-widget__body earth-moon-widget__body--moon">
                        <div class="earth-moon-widget__canvas" data-earth-moon-canvas="moon"></div>
                        <figcaption><strong>Luna vista desde la Tierra</strong><span data-earth-moon-moon-phase></span></figcaption>
                    </figure>
                    <figure class="earth-moon-widget__body earth-moon-widget__body--earth">
                        <div class="earth-moon-widget__canvas" data-earth-moon-canvas="earth"></div>
                        <figcaption><strong>Tierra vista desde la Luna</strong><span data-earth-moon-earth-phase></span></figcaption>
                    </figure>
                </div>
                <div class="earth-moon-widget__readout"><strong data-earth-moon-date></strong><span>Diámetros aparentes a escala aproximada · Tierra 3,67×</span></div>
                <?php if ($options['controls']): ?>
                    <div class="earth-moon-widget__controls">
                        <button type="button" data-earth-moon-toggle><?= $options['autoplay'] ? 'Pausar' : 'Reproducir' ?></button>
                        <button type="button" data-earth-moon-reset>Volver al instante elegido</button>
                        <label><span>Recorrido de la lunación</span><input type="range" min="0" max="1000" step="1" data-earth-moon-progress></label>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="lunar-widget__error" role="alert">No se pudo preparar la comparación Tierra–Luna.</p>
        <?php endif; ?>
    </main>
</body>
</html>
