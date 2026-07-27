<?php
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/location-map.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/api-client.php';
sendDynamicNoCacheHeaders();

$message = '';
$messageIsError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = astronomySaveLocationRequest($_POST);
    if ($result['ok']) {
        $target = astronomyInternalUrl('ubicacion.php') . '?saved=1';
        header('Location: ' . $target, true, 303);
        exit;
    }
    $message = $result['message'];
    $messageIsError = true;
}
$location = astronomyLocationContext();
if (isset($_GET['saved'])) {
    $message = 'Ubicación guardada. Ya se usa en todo el sitio.';
}
$pageSeo = aquellasLunasSeoPage('Configurar ubicación | Aquellas Lunas', 'Elegí la ubicación del observador para todos los datos locales del sitio.', '/ubicacion.php', 'website');
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
</head>
<body>
    <?php renderAstronomySiteHeader('location', $location); ?>
    <main class="page location-page">
        <div class="container location-container">
            <header class="location-heading">
                <p class="eyebrow">Ubicación global</p>
                <h1><?= htmlspecialchars(astronomySiteSectionLabel('location')) ?></h1>
            </header>
            <?php if ($message !== ''): ?><p class="status-info<?= $messageIsError ? ' is-error' : '' ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <form id="location-search-form" class="location-search">
                <label class="control-field"><span>Buscar localidad</span><input type="search" name="place" autocomplete="address-level2" placeholder="Ej.: Boulogne Sur Mer"></label>
                <button class="button compact-secondary-button" type="submit">Buscar</button>
            </form>
            <?php renderAstronomyLocationMap($location); ?>
            <p id="location-map-status" class="card-note" role="status" aria-live="polite">Mové el marcador o mantené pulsado un punto del mapa.</p>
            <form id="global-location-form" class="card location-form" method="post">
                <label class="control-field location-name-field"><span><span class="location-label-long">Localidad actual</span><span class="location-label-short">Localidad</span></span><input type="text" name="location_name" maxlength="80" value="<?= htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                <div class="location-technical-fields">
                    <label class="control-field"><span><span class="location-label-long">Latitud</span><span class="location-label-short">Lat.</span></span><input type="number" name="latitude" min="-90" max="90" step="0.000001" value="<?= htmlspecialchars((string) $location['latitude'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label class="control-field"><span><span class="location-label-long">Longitud</span><span class="location-label-short">Long.</span></span><input type="number" name="longitude" min="-180" max="180" step="0.000001" value="<?= htmlspecialchars((string) $location['longitude'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label class="control-field"><span><span class="location-label-long">Zona horaria</span><span class="location-label-short">Zona</span></span><input type="text" name="timezone" value="<?= htmlspecialchars($location['timezone'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                </div>
                <input type="hidden" name="location_mode" value="<?= htmlspecialchars($location['mode'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="location-actions">
                    <button id="use-browser-location" class="button compact-secondary-button" type="button"><span class="location-action-long">Usar mi ubicación</span><span class="location-action-short">Mi ubicación</span></button>
                    <button id="use-default-location" class="button compact-secondary-button" type="button"><span class="location-action-long">Usar Buenos Aires</span><span class="location-action-short">Buenos Aires</span></button>
                    <button class="button button-primary location-save" type="submit" aria-label="Guardar ubicación"><span class="location-action-long">Guardar ubicación</span><span class="location-save-icon" aria-hidden="true">✓</span></button>
                </div>
            </form>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
