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
require_once __DIR__ . '/includes/content-system.php';

sendDynamicNoCacheHeaders();
$adminPreview = astronomyContentAdminPreviewEnabled();
$contentEnabled = isContentEnabled();
if (!$contentEnabled && !$adminPreview) {
    http_response_code(404);
    exit;
}

$catalog = astronomyLoadContentCatalog();
$articles = astronomyContentVisibleArticles($catalog);
$location = astronomyLocationContext();
$now = get_current_datetime($location['timezone']);
$pageSeo = aquellasLunasSeoPage(
    'Contenidos | Aquellas Lunas',
    'Artículos para comprender y disfrutar la Luna y el cielo.',
    '/contenidos.php',
    'article'
);
$pageSeo['robots'] = 'noindex, nofollow';
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
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/protected-photos.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($now); ?>
    <?php renderAstronomySiteHeader('content', $location); ?>
    <main class="page"><div class="container public-page-container content-container">
        <header class="hero">
            <p class="eyebrow">PARA SEGUIR DESCUBRIENDO</p>
            <h1>Contenidos</h1>
            <p>Artículos para comprender y disfrutar la Luna y el cielo.</p>
            <?php if (!$contentEnabled && $adminPreview): ?><p class="status-info">La sección pública de contenidos está deshabilitada en la configuración del sitio.</p><?php endif; ?>
        </header>
        <section class="content-index" aria-label="Artículos">
            <?php foreach ($articles as $article): ?>
                <?php $articleUrl = astronomyContentArticleUrl($article['slug']); ?>
                <article class="card content-index-card<?= ($article['image']['url'] ?? null) !== null ? ' content-index-card--with-image' : '' ?>">
                    <?php if (($article['image']['url'] ?? null) !== null): ?>
                        <div class="content-index-card__media"><?= astronomyContentProtectedImageHtml(
                            $article['image'],
                            (string) $article['raw']['titulo'],
                            'content-index-card__image',
                            '--image-position-x: ' . $article['image_position_x'] . '%; --image-position-y: ' . $article['image_position_y'] . '%;'
                        ) ?></div>
                    <?php endif; ?>
                    <div class="content-index-card__body">
                        <h2><a class="content-card__title-link" href="<?= htmlspecialchars($articleUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $article['raw']['titulo']) ?></a></h2>
                        <p><?= htmlspecialchars((string) $article['raw']['resumen']) ?></p>
                        <a class="content-card__read-link" href="<?= htmlspecialchars($articleUrl, ENT_QUOTES, 'UTF-8') ?>">Leer artículo <span aria-hidden="true">→</span></a>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($articles === [] && !astronomyContentDebugEnabled()): ?><p>No hay artículos disponibles por el momento.</p><?php endif; ?>
            <?php if (astronomyContentDebugEnabled()): ?><?php foreach ($catalog['diagnostics'] as $diagnostic): ?><?php renderAstronomyContentDiagnostic($diagnostic); ?><?php endforeach; ?><?php endif; ?>
            <?php if (astronomyContentDebugEnabled()): ?><?php foreach ($catalog['warnings'] as $warning): ?><?php renderAstronomyContentWarning($warning); ?><?php endforeach; ?><?php endif; ?>
        </section>
    </div></main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
