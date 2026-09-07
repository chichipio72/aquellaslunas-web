<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/real-eclipse-embeds.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendDynamicNoCacheHeaders();
$payload = null;
$options = realSolarSpaceEclipseOptions($_GET);
try {
    $instance = realEclipseInstance($_GET);
    $payload = realSolarSpaceEclipsePayload($instance, $options);
    foreach ($payload['textures'] as $key => $path) $payload['textures'][$key] = '../' . ltrim((string) $path, '/');
} catch (Throwable $exception) {
    error_log('Aquellas Lunas solar eclipse space embed: ' . $exception->getMessage());
}
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Eclipse solar desde el espacio · Widget</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/lunar-widgets.css')) ?>">
    <?php if ($payload !== null): ?><script type="module" src="<?= $html('../' . versionedAssetUrl('assets/js/solar-eclipse-space-widget.js')) ?>"></script><?php endif; ?>
</head>
<body class="lunar-widget">
<main class="lunar-widget__frame">
<?php if ($payload !== null): ?>
    <div class="real-eclipse-widget solar-space-eclipse" data-solar-space-eclipse>
        <script type="application/json" data-real-eclipse-payload><?= json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <p class="lunar-widget__loading">Preparando la geometría de la sombra…</p>
        <div class="solar-space-eclipse__stage" data-eclipse-stage></div>
        <div class="real-eclipse-widget__readout"><strong data-eclipse-state></strong><span data-eclipse-time></span><small data-eclipse-visibility></small></div>
        <?php if ($options['controls']): ?>
        <div class="real-eclipse-widget__controls solar-space-eclipse__controls">
            <button type="button" data-eclipse-toggle><?= $options['autoplay'] ? 'Pausar' : 'Reproducir' ?></button>
            <button type="button" data-eclipse-maximum>Ir al máximo</button>
            <button type="button" data-space-follow aria-pressed="<?= $options['follow_shadow'] ? 'true' : 'false' ?>">Seguir sombra</button>
            <label><span>Tiempo</span><input type="range" min="0" max="1000" value="500" data-eclipse-progress></label>
            <label><span>Velocidad</span><select data-eclipse-speed><option value=".25">0,25×</option><option value=".5">0,5×</option><option value="1" selected>1×</option><option value="2">2×</option><option value="4">4×</option><option value="8">8×</option></select></label>
        </div>
        <div class="real-eclipse-widget__contacts" data-eclipse-contact-marks></div>
        <?php endif; ?>
        <p class="solar-space-eclipse__scale-note">Distancias no a escala</p>
    </div>
<?php else: ?>
    <p class="lunar-widget__error">No se encontró un eclipse solar en la fecha indicada.</p>
<?php endif; ?>
</main>
</body>
</html>
