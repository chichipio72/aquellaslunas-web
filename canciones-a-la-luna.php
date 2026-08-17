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

$moonSongsCurrentDateTime = get_current_datetime('America/Argentina/Buenos_Aires');
$pageSeo = aquellasLunasSeoPage(
    'Canciones a la Luna | Aquellas Lunas',
    'Una playlist de canciones inspiradas en la Luna para acompañar tus noches bajo el cielo.',
    '/canciones-a-la-luna.php',
    'collection'
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
    <?php renderAstronomyDebugClock($moonSongsCurrentDateTime); ?>
    <?php renderAstronomySiteHeader('moon_songs'); ?>

    <main class="page moon-songs-page">
        <div class="container moon-songs-container">
            <article class="card moon-songs-card">
                <header class="moon-songs-heading">
                    <h1><?= htmlspecialchars(astronomySiteSectionLabel('moon_songs'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p>Una selección de canciones inspiradas en la Luna para acompañar tus noches bajo el cielo.</p>
                </header>
                <div class="moon-songs-player" data-swipe-navigation-ignore>
                    <iframe
                        data-testid="embed-iframe"
                        src="https://open.spotify.com/embed/playlist/2RgMG2nxWdfT51Gz1bZvKe?utm_source=generator&amp;si=81be99215b3b4ce2"
                        width="100%"
                        height="704"
                        frameborder="0"
                        allowfullscreen=""
                        allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                        loading="lazy"
                        title="Playlist Canciones a la Luna en Spotify">
                    </iframe>
                </div>
            </article>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
