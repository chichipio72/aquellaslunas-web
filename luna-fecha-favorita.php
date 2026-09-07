<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/moon-three-render.php';
require_once __DIR__ . '/includes/favorite-moon.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$timezone = new DateTimeZone((string) $location['timezone']);
$selection = favoriteMoonSelection($_GET['fecha'] ?? null, $_GET['hora'] ?? null, get_current_datetime($timezone->getName()));
$instant = $selection['instant'];
$observer = new AstronomyEngine\Facade\AstronomyObserver(
    (float) $location['latitude'],
    (float) $location['longitude'],
    $timezone->getName(),
    (float) ($location['elevation_meters'] ?? 0.0),
);
$payload = null;
$renderError = null;
try {
    $payload = moonThreeRenderPayload(
        $instant,
        $observer,
        favoriteMoonThreeRenderConfigurationLoad(),
        'favorite.moon_three.',
    );
} catch (Throwable $exception) {
    error_log('Aquellas Lunas favorite Moon render error: ' . $exception->getMessage());
    $renderError = 'No pudimos recrear la Luna para ese momento.';
}
$geometry = is_array($payload['geometry'] ?? null) ? $payload['geometry'] : [];
$phase = is_array($geometry['phase'] ?? null) ? $geometry['phase'] : [];
$phaseLabel = favoriteMoonPhaseLabel((string) ($phase['name'] ?? ''));
$illumination = is_numeric($phase['illumination_percent'] ?? null) ? (int) round((float) $phase['illumination_percent']) : null;
$pagePath = '/luna-fecha-favorita.php';
$locationReturnPath = favoriteMoonReturnPath((string) (parse_url($_SERVER['SCRIPT_NAME'] ?? $pagePath, PHP_URL_PATH) ?: $pagePath), $selection['date'], $selection['time']);
$locationUrl = astronomyInternalUrl('ubicacion.php') . (str_contains(astronomyInternalUrl('ubicacion.php'), '?') ? '&' : '?') . 'return=' . rawurlencode($locationReturnPath);
$pageSeo = aquellasLunasSeoPage(
    'La Luna de tu fecha favorita | Aquellas Lunas',
    'Elegí una fecha y una hora para recrear la Luna desde tu ubicación.',
    $pagePath,
    'website',
);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php renderSeoHead($pageSeo); renderAnalyticsTracking(); renderFaviconLinks(); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/favorite-moon.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php if ($payload !== null): ?><script type="module" src="<?= htmlspecialchars(versionedAssetUrl('assets/js/moon-three-render.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php endif; ?>
</head>
<body>
    <?php renderAstronomySiteHeader('favorite_moon', $location); ?>
    <main class="page favorite-moon-page">
        <div class="container favorite-moon-container">
            <header class="favorite-moon-heading">
                <p class="eyebrow">Una fecha, una Luna</p>
                <h1>La Luna de tu fecha favorita</h1>
                <p>Elegí un día y una hora para descubrir cómo se veía la Luna.</p>
            </header>

            <?php if ($selection['corrected']): ?><p class="status-info is-error" role="status">La fecha o la hora no eran válidas. Mostramos el momento actual.</p><?php endif; ?>
            <section class="card favorite-moon-controls" aria-labelledby="favorite-moon-controls-title">
                <div>
                    <h2 id="favorite-moon-controls-title">Elegí el momento</h2>
                    <p>Ubicación activa: <strong><?= htmlspecialchars((string) $location['name'], ENT_QUOTES, 'UTF-8') ?></strong> · <a href="<?= htmlspecialchars($locationUrl, ENT_QUOTES, 'UTF-8') ?>">Cambiar ubicación</a></p>
                </div>
                <form method="get" class="favorite-moon-form">
                    <label class="control-field"><span>Fecha</span><input type="date" name="fecha" value="<?= htmlspecialchars($selection['date'], ENT_QUOTES, 'UTF-8') ?>" min="<?= FAVORITE_MOON_MIN_DATE ?>" max="<?= FAVORITE_MOON_MAX_DATE ?>" required></label>
                    <label class="control-field"><span>Hora</span><input type="time" name="hora" value="<?= htmlspecialchars($selection['time'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <button class="button button-primary" type="submit">Ver esta Luna</button>
                </form>
            </section>

            <section class="card favorite-moon-result" aria-labelledby="favorite-moon-result-title">
                <div class="favorite-moon-visual">
                    <?php if ($payload !== null): ?>
                        <div id="favorite-moon-render" class="favorite-moon-render" data-moon-three data-moon-three-state="loading" role="img" aria-label="<?= htmlspecialchars($phaseLabel . ', ' . ($illumination ?? 0) . ' % iluminada', ENT_QUOTES, 'UTF-8') ?>">
                            <script type="application/json" data-moon-three-payload><?= json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                            <p class="favorite-moon-render__loading" data-moon-three-loading>Cargando la Luna…</p>
                        </div>
                    <?php else: ?>
                        <p class="api-error-notice" role="alert"><?= htmlspecialchars((string) $renderError, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
                <div class="favorite-moon-copy">
                    <p class="eyebrow">Así se veía</p>
                    <h2 id="favorite-moon-result-title"><?= htmlspecialchars($phaseLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="favorite-moon-summary">
                        <?php if ($illumination !== null): ?><strong><?= $illumination ?>% iluminada</strong> · <?php endif; ?>
                        <time datetime="<?= htmlspecialchars($instant->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($instant->format('d/m/Y · H:i'), ENT_QUOTES, 'UTF-8') ?></time><br>
                        <?= htmlspecialchars((string) $location['name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($timezone->getName(), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="favorite-moon-note">Imagen recreada a partir de la fase, orientación y libración calculadas para esa fecha.</p>
                    <?php if ($payload !== null): ?>
                        <div class="favorite-moon-downloads">
                            <button class="button button-primary favorite-moon-download" type="button" data-moon-wallpaper-download data-moon-target="favorite-moon-render" data-wallpaper-width="1440" data-wallpaper-height="2560" data-filename="luna-<?= htmlspecialchars($selection['date'] . '-' . str_replace(':', '', $selection['time']), ENT_QUOTES, 'UTF-8') ?>-celular-1440x2560.png">Descargar para celular</button>
                            <button class="button compact-secondary-button favorite-moon-share" type="button" data-moon-wallpaper-share data-moon-target="favorite-moon-render" data-wallpaper-width="1440" data-wallpaper-height="2560" data-filename="luna-<?= htmlspecialchars($selection['date'] . '-' . str_replace(':', '', $selection['time']), ENT_QUOTES, 'UTF-8') ?>-celular-1440x2560.png" hidden>Compartir para celular</button>
                            <button class="button compact-secondary-button favorite-moon-download" type="button" data-moon-wallpaper-download data-moon-target="favorite-moon-render" data-wallpaper-width="2560" data-wallpaper-height="1440" data-filename="luna-<?= htmlspecialchars($selection['date'] . '-' . str_replace(':', '', $selection['time']), ENT_QUOTES, 'UTF-8') ?>-pc-2560x1440.png">Descargar para PC</button>
                        </div>
                        <label class="favorite-moon-text-option interactive-choice"><input type="checkbox" data-moon-wallpaper-text-toggle checked><span>Incluir texto en la imagen</span></label>
                        <script type="application/json" data-moon-wallpaper-copy><?= json_encode([
                            'eyebrow' => 'ASÍ SE VEÍA',
                            'phase' => $phaseLabel,
                            'illumination' => $illumination !== null ? $illumination . '% iluminada' : '',
                            'date' => $instant->format('d/m/Y'),
                            'time' => $instant->format('H:i'),
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                        <small class="favorite-moon-download-sizes">Celular: 1440 × 2560 · PC: 2560 × 1440</small>
                        <p class="favorite-moon-download-status" data-moon-wallpaper-status role="status" aria-live="polite"></p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
