<?php
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/location-map.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/featured-dates.php';
sendDynamicNoCacheHeaders();

$location = astronomyLocationContext();
$now = get_current_datetime($location['timezone']);
$featuredDates = ['upcoming' => [], 'recent' => []];
$featuredDatesAvailable = true;
try {
    $featuredDates = astronomyFeaturedDates(
        $now->format('Y-m-d'),
        $location['latitude'],
        $location['longitude'],
        $location['timezone']
    );
} catch (RuntimeException $exception) {
    $featuredDatesAvailable = false;
    error_log('Aquellas Lunas planner featured dates error: ' . $exception->getMessage());
}
$pageSeo = aquellasLunasSeoPage(
    'Planificador del Sol y la Luna | Aquellas Lunas',
    'Consultá desde qué dirección salen, se ponen y se encuentran el Sol y la Luna para una fecha, hora y ubicación.',
    '/planificador.php',
    'website'
);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php renderSeoHead($pageSeo); renderAnalyticsTracking(); renderFaviconLinks(); ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/astro-map.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/planner.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('planner'); ?>
</head>
<body<?= astronomyMobileSwipeNavigationAttributes('planner') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($now); ?>
    <?php renderAstronomySiteHeader('planner', $location); ?>
    <main class="page planner-page">
        <div class="container public-page-container planner-container">
            <header class="hero planner-heading atmosphere-card--mixed">
                <p class="eyebrow">PLANIFICACIÓN FOTOGRÁFICA</p>
                <h1><?= htmlspecialchars(astronomySiteSectionLabel('planner')) ?></h1>
                <p class="hero-subtitle">Prepará tus fotos con las direcciones del Sol y la Luna para el lugar y momento elegidos.</p>
            </header>
            <section class="card planner-controls" aria-labelledby="planner-controls-title">
                <h2 id="planner-controls-title" class="visually-hidden">Fecha y hora</h2>
                <form id="planner-form"
                    data-latitude="<?= htmlspecialchars((string) $location['latitude'], ENT_QUOTES, 'UTF-8') ?>"
                    data-longitude="<?= htmlspecialchars((string) $location['longitude'], ENT_QUOTES, 'UTF-8') ?>"
                    data-timezone="<?= htmlspecialchars($location['timezone'], ENT_QUOTES, 'UTF-8') ?>"
                    data-location-name="<?= htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8') ?>"
                    data-current-date="<?= $now->format('Y-m-d') ?>"
                    data-current-time="<?= $now->format('H:i') ?>">
                    <label class="control-field"><span>Fecha</span><input type="date" name="date" min="1900-01-01" max="2050-12-31" value="<?= $now->format('Y-m-d') ?>" required></label>
                    <label class="control-field"><span>Hora</span><input type="time" name="time" step="60" value="<?= $now->format('H:i') ?>" required></label>
                    <div class="control-field planner-featured-field">
                        <label for="planner-featured-date">Ir a una fecha destacada</label>
                        <select id="planner-featured-date"<?= $featuredDatesAvailable ? '' : ' disabled' ?>>
                            <option value=""><?= $featuredDatesAvailable ? 'Elegir fecha destacada…' : 'Fechas destacadas no disponibles' ?></option>
                            <?php foreach (['upcoming' => 'Próximos eventos', 'recent' => 'Eventos recientes'] as $groupKey => $groupLabel): ?>
                                <?php if (($featuredDates[$groupKey] ?? []) !== []): ?><optgroup label="<?= $groupLabel ?>">
                                    <?php foreach ($featuredDates[$groupKey] as $event): ?>
                                        <?php $dateParts = explode('-', $event['date']); $visibleDate = implode('/', array_reverse($dateParts)); ?>
                                        <option value="<?= htmlspecialchars($event['date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($event['label'] . ' — ' . $visibleDate, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </optgroup><?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <div id="planner-featured-enhancement" class="planner-featured-enhancement" hidden>
                            <button id="planner-featured-trigger" class="planner-featured-trigger" type="button" aria-expanded="false" aria-controls="planner-featured-panel">Elegir fecha destacada…</button>
                            <div id="planner-featured-backdrop" class="planner-featured-backdrop" hidden></div>
                            <section id="planner-featured-panel" class="planner-featured-panel" role="dialog" aria-modal="true" aria-labelledby="planner-featured-title" hidden>
                                <header><h3 id="planner-featured-title">Elegir fecha destacada</h3><button type="button" data-featured-close aria-label="Cerrar selector">×</button></header>
                                <div class="planner-featured-options"></div>
                            </section>
                        </div>
                    </div>
                    <div id="planner-quick-times" class="planner-quick-times" hidden>
                        <span>Horarios del día aplicado</span>
                        <div data-quick-time-buttons></div>
                    </div>
                    <button id="planner-use-now" class="button compact-secondary-button" type="button">Usar ahora</button>
                    <button id="planner-submit" class="button button-primary" type="submit">Mostrar direcciones</button>
                </form>
                <p id="planner-pending" class="planner-pending" role="status" hidden>Hay cambios sin aplicar.</p>
                <p id="planner-loading" class="planner-loading" role="status" hidden>Cargando direcciones…</p>
                <p id="planner-error" class="api-error-notice" role="alert" hidden></p>
            </section>
            <p id="planner-summary" class="planner-summary" aria-live="polite"></p>
            <noscript><p class="api-error-notice">El mapa y las direcciones requieren JavaScript.</p></noscript>
            <div id="planner-map-shell" class="planner-map-shell" data-swipe-navigation-ignore>
                <?php renderAstronomyLocationMap($location, 'Mapa de direcciones del Sol y la Luna', ['data-directions-map' => 'true']); ?>
                <div id="planner-map-overlay" class="planner-map-overlay" aria-live="polite" aria-atomic="true" hidden>
                    <div class="planner-map-overlay__content">
                        <span class="planner-map-spinner" aria-hidden="true" hidden></span>
                        <strong id="planner-map-overlay-title"></strong>
                        <p id="planner-map-overlay-detail"></p>
                        <button id="planner-map-update" class="button button-primary" type="button">Actualizar mapa</button>
                    </div>
                </div>
            </div>
            <p class="card-note planner-map-note">Las líneas indican azimut desde el observador. Su longitud es visual y no representa distancia a los astros.</p>
            <section id="planner-legend" class="card planner-legend" aria-labelledby="planner-legend-title">
                <h2 id="planner-legend-title">Capas del mapa</h2>
                <fieldset><legend>Sol</legend>
                    <label><input type="checkbox" data-layer-toggle="sun-rise" checked> <span class="direction-swatch sun-rise"></span>Salida</label>
                    <label><input type="checkbox" data-layer-toggle="sun-instant" checked> <span class="direction-swatch sun-instant"></span>Posición</label>
                    <label><input type="checkbox" data-layer-toggle="sun-set" checked> <span class="direction-swatch sun-set"></span>Puesta</label>
                </fieldset>
                <fieldset><legend>Luna</legend>
                    <label><input type="checkbox" data-layer-toggle="moon-rise" checked> <span class="direction-swatch moon-rise"></span>Salida</label>
                    <label><input type="checkbox" data-layer-toggle="moon-instant" checked> <span class="direction-swatch moon-instant"></span>Posición</label>
                    <label><input type="checkbox" data-layer-toggle="moon-set" checked> <span class="direction-swatch moon-set"></span>Puesta</label>
                </fieldset>
            </section>
            <section class="planner-results" aria-label="Datos de direcciones">
                <article id="planner-sun-card" class="card planner-body-card"><h2>Sol</h2><div data-body-results="sun">Esperando datos…</div></article>
                <article id="planner-moon-card" class="card planner-body-card"><h2>Luna</h2><div data-body-results="moon">Esperando datos…</div></article>
            </section>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
