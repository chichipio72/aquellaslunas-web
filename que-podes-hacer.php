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
require_once __DIR__ . '/includes/contact.php';
require_once __DIR__ . '/includes/explore-sky.php';
require_once __DIR__ . '/includes/capabilities-content.php';

$capabilitiesCurrentDateTime = get_current_datetime('America/Argentina/Buenos_Aires');
$capabilitiesContent = astronomyCapabilitiesContentLoad();
$pageSeo = aquellasLunasSeoPage(
    'Qué podés hacer en Aquellas Lunas',
    'Conocé las herramientas de Aquellas Lunas para observar el cielo, consultar eventos, planificar fotografías y entender los datos astronómicos.',
    '/que-podes-hacer.php'
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
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/home-v2.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($capabilitiesCurrentDateTime); ?>
    <?php renderAstronomySiteHeader('capabilities'); ?>

    <main class="page capabilities-page">
        <div class="container capabilities-container">
            <?php if ($capabilitiesContent !== null): ?>
                <?php renderAstronomyCapabilitiesContent($capabilitiesContent); ?>
            <?php else: ?>
                <section class="card capabilities-closing" role="status">
                    <h1>Qué podés hacer en Aquellas Lunas</h1>
                    <p>El contenido de esta guía no está disponible temporalmente.</p>
                </section>
            <?php endif; ?>

            <?php renderAstronomyContactBlock('features_page'); ?>
            <?php renderAstronomyExploreSky('capabilities'); ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
