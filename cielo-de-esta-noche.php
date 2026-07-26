<?php

require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/tonight.php';

sendDynamicNoCacheHeaders();

$location = astronomyLocationContext();
$timezoneName = $location['timezone'];
$now = get_current_datetime($timezoneName);
$date = $now->format('Y-m-d');
$tonightData = null;
$apiErrorMessage = null;

try {
    $apiConfig = loadAstronomyApiConfig();
    error_log('Aquellas Lunas API configuration source: ' . $apiConfig['source']);
    $tonightData = astronomyTonightRequest(
        $apiConfig['base_url'],
        $location,
        $date,
        'full',
        'tonight full',
        12
    );
    if ($tonightData === null) {
        $apiErrorMessage = 'No pudimos cargar el cielo de esta noche.';
    }
} catch (RuntimeException $exception) {
    $apiErrorMessage = 'No pudimos cargar el cielo de esta noche.';
    error_log('Aquellas Lunas API configuration error: ' . $exception->getMessage());
}

$sections = $tonightData !== null ? astronomyTonightSections($tonightData) : [];
$pageSeo = aquellasLunasSeoPage(
    'El cielo de esta noche | Aquellas Lunas',
    'Planetas, Luna, estrellas y otros objetos visibles esta noche desde tu ubicación.',
    '/cielo-de-esta-noche.php',
    'article'
);
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
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/cloud-cover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('tonight'); ?>
</head>
<body data-api-state="<?= $apiErrorMessage !== null ? 'error' : 'ok' ?>" data-cloud-cover-latitude="<?= htmlspecialchars((string) $location['latitude'], ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-longitude="<?= htmlspecialchars((string) $location['longitude'], ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-timezone="<?= htmlspecialchars($timezoneName, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-cache-scope="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>"<?= astronomyMobileSwipeNavigationAttributes('tonight') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($now); ?>
    <?php renderAstronomySiteHeader('tonight', $location); ?>

    <main class="page tonight-page">
        <div class="container tonight-container">
            <header class="hero tonight-hero">
                <p class="eyebrow">Esta noche</p>
                <h1>El cielo desde tu ubicación</h1>
                <?php if ($tonightData !== null): ?>
                    <p class="tonight-window"><?= htmlspecialchars(astronomyTonightWindowLabel($tonightData, $timezoneName)) ?></p>
                    <p class="tonight-state"><?= htmlspecialchars(astronomyTonightTemporalState($tonightData, $now, $timezoneName)) ?></p>
                    <p class="tonight-location">Horarios para <?= htmlspecialchars($location['name']) ?> · <?= htmlspecialchars($timezoneName) ?></p>
                <?php else: ?>
                    <p class="hero-subtitle">Planetas, Luna, estrellas y otros objetos para la noche local seleccionada.</p>
                <?php endif; ?>
            </header>

            <?php if ($tonightData !== null): ?>
                <section
                    class="tonight-section tonight-cloud-section"
                    data-cloud-cover-night
                    data-night-start="<?= htmlspecialchars((string) ($tonightData['night']['start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-night-end="<?= htmlspecialchars((string) ($tonightData['night']['end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    aria-labelledby="tonight-cloud-title"
                    hidden
                >
                    <h2 id="tonight-cloud-title">Nubosidad durante la noche</h2>
                    <figure class="night-cloud-chart" aria-labelledby="tonight-cloud-title">
                        <div data-night-cloud-chart></div>
                    </figure>
                </section>
            <?php endif; ?>

            <?php if ($apiErrorMessage !== null): ?>
                <div class="api-error-notice" data-api-error role="alert">
                    <p><?= htmlspecialchars($apiErrorMessage) ?> Reintentá en unos segundos.</p>
                    <button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button>
                </div>
            <?php else: ?>
                <?php if ($sections === []): ?>
                    <p class="tonight-empty" role="status">No hay objetos observables para mostrar durante esta noche.</p>
                <?php else: ?>
                    <div class="tonight-sections">
                        <?php foreach ($sections as $sectionTitle => $objects): ?>
                        <?php $sectionId = 'tonight-' . substr(md5($sectionTitle), 0, 10); ?>
                        <section class="tonight-section" aria-labelledby="<?= $sectionId ?>">
                            <h2 id="<?= $sectionId ?>"><?= htmlspecialchars($sectionTitle) ?></h2>
                            <div class="tonight-object-list">
                                <?php foreach ($objects as $object): ?>
                                    <?php
                                    $visibilityText = astronomyTonightObjectSentence($object, $timezoneName);
                                    $moonProximity = astronomyTonightMoonProximity($object);
                                    $constellationName = astronomyTonightConstellationName($object);
                                    ?>
                                    <article class="tonight-object">
                                        <h3>
                                            <span><?= htmlspecialchars((string) $object['name']) ?></span>
                                            <?php if ($constellationName !== null): ?><span class="tonight-object__constellation">(<?= htmlspecialchars($constellationName) ?>)</span><?php endif; ?>
                                        </h3>
                                        <?php if ($visibilityText !== ''): ?><p class="tonight-object__visibility"><?= htmlspecialchars($visibilityText) ?></p><?php endif; ?>
                                        <?php if ($moonProximity !== null): ?><p class="tonight-object__moon"><?= htmlspecialchars($moonProximity) ?></p><?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php renderAstronomyTimings(); ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
