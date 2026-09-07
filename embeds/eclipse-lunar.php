<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$options = lunarEclipseEmbedOptions($_GET);
$payload = null;
try {
    $payload = lunarEclipseEmbedPayload(lunarEmbedObserver($location), $options);
    foreach (['albedo', 'relief'] as $textureKey) {
        if (is_string($payload['textures'][$textureKey] ?? null)) $payload['textures'][$textureKey] = '../' . ltrim($payload['textures'][$textureKey], '/');
    }
} catch (Throwable $exception) {
    error_log('Aquellas Lunas lunar eclipse embed error: ' . $exception->getMessage());
}
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Eclipse lunar · Widget</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/lunar-widgets.css')) ?>">
    <?php if ($payload !== null): ?><script type="module" src="<?= $html('../' . versionedAssetUrl('assets/js/lunar-eclipse-widget.js')) ?>"></script><?php endif; ?>
</head>
<body class="lunar-widget lunar-widget--eclipse">
<main class="lunar-widget__frame">
<?php if ($payload !== null): ?>
    <div class="lunar-eclipse-widget" data-lunar-eclipse-widget data-eclipse-state="loading">
        <script type="application/json" data-eclipse-payload><?= json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <p class="lunar-widget__loading" data-eclipse-loading>Preparando eclipse…</p>
        <div class="lunar-eclipse-widget__moon" data-eclipse-moon aria-label="Aspecto de la Luna durante el eclipse"></div>
        <div class="lunar-eclipse-widget__readout" aria-live="polite"><strong data-eclipse-state-label></strong><span data-eclipse-time></span></div>
        <?php if ($options['geometry']): ?>
            <figure class="lunar-eclipse-widget__geometry" aria-label="Geometría esquemática del eclipse lunar">
                <svg viewBox="0 0 720 120" role="img" aria-labelledby="eclipse-geometry-title"><title id="eclipse-geometry-title">La Luna atraviesa la penumbra y la umbra de la Tierra</title>
                    <defs><linearGradient id="eclipse-penumbra" x1="0" x2="1"><stop stop-color="#9aa8c6" stop-opacity=".2"/><stop offset="1" stop-color="#9aa8c6" stop-opacity=".04"/></linearGradient><linearGradient id="eclipse-umbra" x1="0" x2="1"><stop stop-color="#090b12" stop-opacity=".82"/><stop offset="1" stop-color="#15101a" stop-opacity=".68"/></linearGradient></defs>
                    <circle cx="55" cy="60" r="26" fill="#386da1" stroke="#86b8dc" stroke-width="2"/>
                    <path d="M81 24 L690 4 L690 116 L81 96 Z" fill="url(#eclipse-penumbra)"/><path d="M81 34 L650 43 L650 77 L81 86 Z" fill="url(#eclipse-umbra)"/>
                    <path d="M455 116 L485 4" fill="none" stroke="#d9e1f2" stroke-opacity=".28" stroke-width="1" stroke-dasharray="3 4"/>
                    <circle cx="470" cy="60" r="9" fill="#d8dbe1" stroke="#fff" data-eclipse-geometry-moon/>
                    <text x="55" y="109" text-anchor="middle">Tierra</text><text x="330" y="18">Penumbra</text><text x="330" y="79">Umbra</text>
                </svg>
                <figcaption>Esquema didáctico, no a escala.</figcaption>
            </figure>
        <?php endif; ?>
        <?php if ($options['controls']): ?><div class="lunar-eclipse-widget__controls"><button type="button" data-eclipse-toggle><?= $options['autoplay'] ? 'Pausar' : 'Reproducir' ?></button><label><span>Avance temporal</span><input type="range" min="0" max="1000" value="500" step="1" data-eclipse-progress></label></div><?php endif; ?>
    </div>
<?php else: ?><p class="lunar-widget__error" role="alert">No se pudo preparar el eclipse lunar.</p><?php endif; ?>
</main>
</body>
</html>
