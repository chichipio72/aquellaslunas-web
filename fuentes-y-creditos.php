<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/sources-credits-content.php';

$sourcesCreditsCurrentDateTime = get_current_datetime('America/Argentina/Buenos_Aires');
$sourcesCreditsContent = astronomySourcesCreditsContentLoad();
$pageSeo = aquellasLunasSeoPage(
    'Fuentes y créditos',
    'Conocé las fuentes científicas, datos, recursos visuales y créditos de Aquellas Lunas.',
    '/fuentes-y-creditos.php'
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
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($sourcesCreditsCurrentDateTime); ?>
    <?php renderAstronomySiteHeader('sources_credits'); ?>
    <main class="page sources-credits-page">
        <div class="container capabilities-container sources-credits__container">
            <?php if ($sourcesCreditsContent !== null): ?>
                <?php renderAstronomySourcesCreditsContent($sourcesCreditsContent); ?>
            <?php else: ?>
                <section class="card sources-credits__section" role="status">
                    <h1>Fuentes y créditos</h1>
                    <p>El contenido de esta página no está disponible temporalmente.</p>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
