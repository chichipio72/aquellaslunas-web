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
require_once __DIR__ . '/includes/interactive-moon.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$timezone = new DateTimeZone((string) $location['timezone']);
$selection = favoriteMoonSelection($_GET['fecha'] ?? null, $_GET['hora'] ?? null, get_current_datetime($timezone->getName()));
$options = interactiveMoonOptions($_GET);
$instant = $selection['instant'];
$payload = null;
$renderError = null;
try {
    $interactiveConfiguration = interactiveMoonThreeRenderConfigurationLoad();
    $payload = moonThreeRenderPayload(
        $instant,
        new AstronomyEngine\Facade\AstronomyObserver(
            (float) $location['latitude'],
            (float) $location['longitude'],
            $timezone->getName(),
            (float) ($location['elevation_meters'] ?? 0.0),
        ),
        $interactiveConfiguration,
        'interactive.moon_three.',
    );
    $payload['features'] = interactiveMoonFeatureCatalog();
    $payload['landings'] = interactiveMoonLandingCatalog();
    $payload['catalogs'] = [
        'gazetteer' => versionedAssetUrl('assets/data/moon-gazetteer.json'),
        'geology' => versionedAssetUrl('assets/data/moon-geology-experiment.json'),
        'geology_auto' => versionedAssetUrl('assets/data/moon-geology-auto.json'),
        'geology_presentation' => versionedAssetUrl('assets/data/moon-geology-es.json'),
    ];
    $payload['interactive'] = $options;
} catch (Throwable $exception) {
    error_log('Aquellas Lunas interactive Moon error: ' . $exception->getMessage());
    $renderError = 'No pudimos preparar la Luna interactiva para ese momento.';
}
$phase = is_array($payload['geometry']['phase'] ?? null) ? $payload['geometry']['phase'] : [];
$pageSeo = aquellasLunasSeoPage(
    'Luna interactiva | Aquellas Lunas',
    'Explorá una Luna 3D con fase, orientación y libración reales, accidentes geográficos y sitios de alunizaje.',
    '/luna-interactiva.php',
    'website',
);
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php renderSeoHead($pageSeo); renderAnalyticsTracking(); renderFaviconLinks(); ?>
    <link rel="stylesheet" href="<?= $html(versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= $html(versionedAssetUrl('assets/css/interactive-moon.css')) ?>">
    <script src="<?= $html(versionedAssetUrl('assets/js/location.js')) ?>" defer></script>
    <?php if ($payload !== null): ?><script type="module" src="<?= $html(versionedAssetUrl('assets/js/interactive-moon.js')) ?>"></script><?php endif; ?>
</head>
<body class="interactive-moon-page<?= $options['embed'] ? ' interactive-moon-page--embed' : '' ?>">
    <?php if (!$options['embed']) renderAstronomySiteHeader('interactive_moon', $location); ?>
    <main class="page interactive-moon-main">
        <div class="container interactive-moon-container">
            <header class="interactive-moon-heading">
                <p class="eyebrow">Atlas lunar tridimensional</p>
                <h1>Luna interactiva</h1>
                <p>Fase, iluminación, orientación y libración calculadas para tu ubicación.</p>
            </header>

            <section class="interactive-moon-toolbar card" aria-label="Controles de la Luna interactiva">
                <form method="get" class="interactive-moon-time-form">
                    <label><span>Fecha</span><input type="date" name="fecha" value="<?= $html($selection['date']) ?>" min="<?= FAVORITE_MOON_MIN_DATE ?>" max="<?= FAVORITE_MOON_MAX_DATE ?>" required></label>
                    <label><span>Hora</span><input type="time" name="hora" value="<?= $html($selection['time']) ?>" required></label>
                    <?php foreach (['craters', 'maria', 'other', 'landings'] as $layer): ?><input type="hidden" name="<?= $layer ?>" value="<?= $options[$layer] ? '1' : '0' ?>" data-layer-state-input="<?= $layer ?>"><?php endforeach; ?>
                    <input type="hidden" name="detail" value="<?= $html($options['detail']) ?>" data-detail-state-input>
                    <input type="hidden" name="illumination" value="<?= $html($options['illumination']) ?>" data-illumination-state-input>
                    <?php if ($options['embed']): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                    <span class="interactive-moon-time-actions">
                        <button class="button button-primary" type="submit">Actualizar cielo</button>
                        <button type="button" class="button interactive-moon-reset" data-moon-reset-view title="Deshace el giro y el zoom; conserva la fecha, hora e iluminación elegidas"><span aria-hidden="true">↺</span> Volver a vista desde la Tierra</button>
                    </span>
                </form>
                <div class="interactive-moon-layers" aria-label="Capas de información">
                    <label><input type="checkbox" data-moon-layer="craters"<?= $options['craters'] ? ' checked' : '' ?>> Cráteres</label>
                    <label><input type="checkbox" data-moon-layer="maria"<?= $options['maria'] ? ' checked' : '' ?>> Mares</label>
                    <label><input type="checkbox" data-moon-layer="other"<?= $options['other'] ? ' checked' : '' ?>> Otros accidentes</label>
                    <label><input type="checkbox" data-moon-layer="landings"<?= $options['landings'] ? ' checked' : '' ?>> Alunizajes</label>
                    <label class="interactive-moon-detail"><span>Etiquetas</span><select data-moon-detail><option value="auto"<?= $options['detail'] === 'auto' ? ' selected' : '' ?>>Automáticas</option><option value="main"<?= $options['detail'] === 'main' ? ' selected' : '' ?>>Sólo principales</option><option value="more"<?= $options['detail'] === 'more' ? ' selected' : '' ?>>Máximo detalle</option></select></label>
                    <label class="interactive-moon-detail"><span>Iluminación</span><select data-moon-illumination><option value="realistic"<?= $options['illumination'] === 'realistic' ? ' selected' : '' ?>>Realista</option><option value="full"<?= $options['illumination'] === 'full' ? ' selected' : '' ?>>Todo iluminado</option></select></label>
                    <span class="interactive-moon-zoom" aria-label="Zoom lunar"><button type="button" data-moon-zoom-out aria-label="Alejar">−</button><button type="button" data-moon-zoom-in aria-label="Acercar">+</button></span>
                </div>
                <div class="interactive-moon-search" data-moon-search>
                    <label for="interactive-moon-search-input">Buscar accidente o alunizaje</label>
                    <div class="interactive-moon-search-box">
                        <input id="interactive-moon-search-input" type="search" autocomplete="off" placeholder="Ej.: Copernicus" data-moon-search-input aria-autocomplete="list" aria-controls="interactive-moon-search-results" aria-expanded="false">
                        <div id="interactive-moon-search-results" class="interactive-moon-search-results" data-moon-search-results role="listbox" hidden></div>
                    </div>
                    <small data-moon-search-status>Cargando catálogo lunar…</small>
                </div>
            </section>

            <section class="interactive-moon-stage card" aria-labelledby="interactive-moon-summary">
                <?php if ($payload !== null): ?>
                    <div id="interactive-moon-render" class="interactive-moon-render" data-interactive-moon data-moon-state="loading">
                        <script type="application/json" data-interactive-moon-payload><?= json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                        <div class="interactive-moon-labels" data-moon-labels aria-hidden="true"></div>
                        <p class="interactive-moon-loading" data-moon-loading>Cargando atlas lunar…</p>
                        <p class="interactive-moon-instructions">Arrastrá para explorar · rueda o controles para acercar</p>
                    </div>
                    <aside class="interactive-moon-summary" id="interactive-moon-summary">
                        <div data-moon-default-summary>
                        <h2><?= $html(favoriteMoonPhaseLabel((string) ($phase['name'] ?? ''))) ?></h2>
                        <dl>
                            <div><dt>Fase iluminada</dt><dd><?= number_format((float) ($phase['illumination_percent'] ?? 0), 1, ',', '.') ?>%</dd></div>
                            <div><dt>Luz del visor</dt><dd data-moon-light-label><?= $options['illumination'] === 'full' ? 'Todo iluminado' : 'Realista' ?></dd></div>
                            <div><dt>Momento</dt><dd><time datetime="<?= $html($instant->format(DateTimeInterface::ATOM)) ?>"><?= $html($instant->format('d/m/Y · H:i')) ?></time></dd></div>
                            <div><dt>Ubicación</dt><dd><?= $html($location['name']) ?></dd></div>
                            <div><dt>Libración</dt><dd><?= number_format((float) ($payload['geometry']['libration']['longitude_degrees'] ?? 0), 2, ',', '.') ?>° lon · <?= number_format((float) ($payload['geometry']['libration']['latitude_degrees'] ?? 0), 2, ',', '.') ?>° lat</dd></div>
                        </dl>
                        <p>Los nombres geográficos proceden de una selección del <a href="https://planetarynames.wr.usgs.gov/Page/MOON/target" rel="external">Gazetteer IAU/USGS</a>. Las etiquetas muestran coordenadas, no el tamaño completo de cada región.</p>
                        </div>
                        <article class="interactive-moon-object" data-moon-object-detail hidden></article>
                    </aside>
                <?php else: ?>
                    <p class="api-error-notice" role="alert"><?= $html($renderError) ?></p>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php if (!$options['embed']) renderAstronomySiteFooter(); ?>
</body>
</html>
