<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/store-admin-auth.php';

if (!storeAdminHasValidSessionCookie()) {
    http_response_code(404);
    exit;
}
sendStoreAdminHeaders();

require_once __DIR__ . '/includes/astronomy-events.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/event-infographic.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/moon-three-render.php';
require_once __DIR__ . '/includes/eclipse-widget-embed.php';
require_once __DIR__ . '/includes/real-eclipse-embeds.php';

use AstronomyEngine\Facade\AstronomyObserver;

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$hasUsableLocation = astronomyEventInfographicHasUsableLocation($location);
$timezoneName = $hasUsableLocation ? trim((string) $location['timezone']) : 'UTC';
$request = $hasUsableLocation ? astronomyEventInfographicRequest($_GET, $timezoneName) : null;

$event = null;
$model = null;
$moonPayload = null;
$eclipsePayload = null;
$eclipsePayloads = [];
$infographicVariant = is_string($_GET['variant'] ?? null) && in_array($_GET['variant'], ['a', 'b', 'c'], true)
    ? (string) $_GET['variant'] : 'current';
if (!array_key_exists('variant', $_GET) && ($request['type'] ?? null) === 'lunar_conjunction') $infographicVariant = 'b';
$error = null;
if (!$hasUsableLocation) {
    $error = 'No hay una ubicación suficiente para personalizar esta pieza. Elegí una ubicación en el encabezado.';
} elseif ($request === null) {
    $error = 'El enlace de esta infografía no es válido.';
} else {
    try {
        $date = $request['date_value'];
        $isEclipse = in_array($request['type'], ['lunar_eclipse', 'solar_eclipse'], true);
        $result = astronomyEvents([
            'start_date' => $date->modify('-1 day')->format('Y-m-d'),
            'days' => 3,
            'latitude' => (float) $location['latitude'],
            'longitude' => (float) $location['longitude'],
            'elevation_meters' => is_numeric($location['elevation_meters'] ?? null) ? (float) $location['elevation_meters'] : 0.0,
            'timezone' => $timezoneName,
            'types' => $isEclipse ? 'eclipse' : 'conjunction',
        ], 'event infographic', 35);
        $items = is_array($result['items'] ?? null) ? $result['items'] : [];
        $event = $isEclipse
            ? astronomyEclipseInfographicFind($items, $request['date'], $request['type'], $timezoneName)
            : astronomyEventInfographicFind($items, $request['date'], $request['target'], $timezoneName);
        if ($event === null) $error = $isEclipse
            ? 'No encontramos ese eclipse para la fecha indicada.'
            : 'No encontramos ese acercamiento para la fecha indicada.';
        elseif (!astronomyEventInfographicIsEligible($event)) $error = astronomyEventInfographicUnavailableReason($event);
        else {
            $model = $isEclipse
                ? astronomyEclipseInfographicModel($event, $location)
                : astronomyConjunctionInfographicModel($event, $location);
            if ($model === null) $error = 'No hay suficiente información para preparar esta infografía.';
            else $model['variant'] = $infographicVariant;
            if ($model !== null && $isEclipse) {
                $widgetUrl = astronomyEclipseWidgetUrlForEvent($event, $timezoneName, $location);
                if ($widgetUrl === null) {
                    $model = null;
                    $error = 'No pudimos preparar el simulador del eclipse para esta ubicación.';
                } else {
                    $widgetQuery = [];
                    parse_str((string) (parse_url($widgetUrl, PHP_URL_QUERY) ?? ''), $widgetQuery);
                    $widgetQuery['controls'] = '0';
                    $widgetQuery['autoplay'] = '0';
                    $instance = realEclipseInstance($widgetQuery);
                    $eclipsePayload = $request['type'] === 'solar_eclipse'
                        ? realSolarEclipsePayload($instance, realSolarEclipseOptions($widgetQuery))
                        : realLunarEclipsePayload($instance, realLunarEclipseOptions($widgetQuery));
                    // El simulador conserva toda su geometría; sólo se fija su
                    // cursor inicial en el máximo efectivamente visible editorial.
                    $eclipsePayload['eclipse']['maximum'] = (string) $model['visual']['moment'];
                    $moments = is_array($model['moments'] ?? null) ? $model['moments'] : [];
                    $captureMoments = ['maximum' => (string) $model['visual']['moment']];
                    if ($infographicVariant === 'a' && count($moments) === 3) {
                        $captureMoments = [
                            'start' => (string) ($moments[0]['moment'] ?? ''),
                            'maximum' => (string) ($moments[1]['moment'] ?? ''),
                            'end' => (string) ($moments[2]['moment'] ?? ''),
                        ];
                    } elseif ($infographicVariant === 'b' && count($moments) === 3) {
                        $startMoment = new DateTimeImmutable((string) $moments[0]['moment']);
                        $maximumMoment = new DateTimeImmutable((string) $moments[1]['moment']);
                        $endMoment = new DateTimeImmutable((string) $moments[2]['moment']);
                        $between = static function (DateTimeImmutable $first, DateTimeImmutable $second): string {
                            $timestamp = (int) round(($first->getTimestamp() + $second->getTimestamp()) / 2);
                            return $first->setTimestamp($timestamp)->format(DateTimeInterface::ATOM);
                        };
                        $captureMoments = [
                            'start' => $startMoment->format(DateTimeInterface::ATOM),
                            'enter' => $between($startMoment, $maximumMoment),
                            'maximum' => $maximumMoment->format(DateTimeInterface::ATOM),
                            'exit' => $between($maximumMoment, $endMoment),
                            'end' => $endMoment->format(DateTimeInterface::ATOM),
                        ];
                    }
                    $eclipsePayload['eclipse']['capture_instants'] = $captureMoments;
                    $eclipsePayloads = ['sequence' => $eclipsePayload];
                    $model['visual']['capture_roles'] = array_keys($captureMoments);
                }
            } else {
                try {
                    $visualInstant = new DateTimeImmutable((string) $model['visual']['moment']);
                    $moonPayload = moonThreeRenderPayload(
                        $visualInstant,
                        new AstronomyObserver(
                            (float) $location['latitude'],
                            (float) $location['longitude'],
                            $timezoneName,
                            is_numeric($location['elevation_meters'] ?? null) ? (float) $location['elevation_meters'] : 0.0
                        ),
                        moonThreeRenderConfigurationLoad()
                    );
                } catch (Throwable $exception) {
                    error_log('Aquellas Lunas event infographic Moon render error: ' . $exception->getMessage());
                    $model = null;
                    $error = 'No pudimos preparar la apariencia real de la Luna para este momento.';
                }
            }
        }
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas event infographic error: ' . $exception->getMessage());
        $error = 'No pudimos cargar el evento en este momento. Probá nuevamente en unos minutos.';
    }
}

$pageSeo = aquellasLunasSeoPage('Crear infografía astronómica | Aquellas Lunas', 'Generá una imagen astronómica personalizada para tu ubicación.', '/infografia-evento.php', 'webpage');
$pageSeo['robots'] = 'noindex, follow';
$today = get_current_datetime($timezoneName);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php renderSeoHead($pageSeo); ?>
<?php renderAnalyticsTracking(); ?>
<?php renderFaviconLinks(); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/event-infographics.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php if ($moonPayload !== null): ?><script type="module" src="<?= htmlspecialchars(versionedAssetUrl('assets/js/moon-three-render.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php endif; ?>
    <?php if ($eclipsePayload !== null): ?><script type="module" src="<?= htmlspecialchars(versionedAssetUrl($request['type'] === 'solar_eclipse' ? 'assets/js/real-solar-eclipse-widget.js' : 'assets/js/real-lunar-eclipse-widget.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php endif; ?>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/event-infographic-renderer.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($today); ?>
    <?php renderAstronomySiteHeader('', $location); ?>
    <main class="page event-infographic-page">
        <div class="container public-page-container event-infographic-container">
            <section class="hero event-infographic-hero atmosphere-card--night" aria-labelledby="infographic-title">
                <p class="eyebrow">UNA ESCENA PARA COMPARTIR</p>
                <h1 id="infographic-title">Creá tu infografía</h1>
                <p class="hero-subtitle">Una imagen del evento, preparada para <?= htmlspecialchars((string) $location['name'], ENT_QUOTES, 'UTF-8') ?>.</p>
            </section>
            <?php if ($model === null): ?>
                <section class="card event-infographic-message" aria-labelledby="infographic-unavailable-title">
                    <h2 id="infographic-unavailable-title">No podemos generar esta pieza</h2>
                    <p><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
                    <a class="button compact-secondary-button" href="eventos.php">Volver a eventos</a>
                </section>
            <?php else: ?>
                <section class="event-infographic-workspace" data-event-infographic>
                    <div class="card event-infographic-controls">
                        <div>
                            <p class="eyebrow">FORMATO</p>
                            <h2><?= htmlspecialchars((string) $model['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p>Elegí el tamaño y descargá un PNG listo para publicar.</p>
                        </div>
                        <fieldset class="event-infographic-format" data-infographic-format>
                            <legend>Formato de la imagen</legend>
                            <label><input type="radio" name="infographic_format" value="story" checked><span>Historia <small>9:16</small></span></label>
                            <label><input type="radio" name="infographic_format" value="feed"><span>Feed <small>4:5</small></span></label>
                        </fieldset>
                        <div class="event-infographic-actions">
                            <button class="button button-primary event-infographic-download" type="button" data-infographic-download disabled>Descargar PNG</button>
                            <button class="button compact-secondary-button event-infographic-share" type="button" data-infographic-share hidden disabled>Compartir</button>
                        </div>
                        <p class="event-infographic-status" data-infographic-status role="status" aria-live="polite"></p>
                    </div>
                    <div class="card event-infographic-preview-card">
                        <p class="eyebrow">VISTA PREVIA</p>
                        <div class="event-infographic-preview" data-infographic-preview>
                            <canvas width="1080" height="1920" aria-label="Vista previa de la infografía de <?= htmlspecialchars((string) $model['title'], ENT_QUOTES, 'UTF-8') ?>"></canvas>
                        </div>
                    </div>
                    <script type="application/json" data-infographic-model><?= json_encode($model, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                    <?php if ($moonPayload !== null): ?>
                        <div class="event-infographic-moon-source" data-moon-three data-moon-capture data-infographic-moon-source data-moon-three-state="loading" aria-hidden="true">
                            <script type="application/json" data-moon-three-payload><?= json_encode($moonPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                        </div>
                    <?php elseif ($eclipsePayloads !== []): ?>
                        <div class="event-infographic-eclipse-sources" data-infographic-eclipse-sources aria-hidden="true">
                            <?php foreach ($eclipsePayloads as $captureRole => $capturePayload): ?>
                                <div class="event-infographic-eclipse-source" data-infographic-eclipse-source="<?= htmlspecialchars((string) $captureRole, ENT_QUOTES, 'UTF-8') ?>" data-eclipse-capture>
                                    <div class="real-eclipse-widget<?= $request['type'] === 'solar_eclipse' ? ' real-solar-eclipse' : '' ?>" <?= $request['type'] === 'solar_eclipse' ? 'data-real-solar-eclipse' : 'data-real-lunar-eclipse' ?>>
                                        <script type="application/json" data-real-eclipse-payload><?= json_encode($capturePayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                                        <?php if ($request['type'] === 'solar_eclipse'): ?>
                                            <canvas class="real-solar-eclipse__canvas" data-eclipse-stage></canvas>
                                        <?php else: ?>
                                            <div class="real-eclipse-widget__stage" data-eclipse-stage></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
