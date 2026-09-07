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

$slug = trim((string) ($_GET['slug'] ?? ''));
$catalog = astronomyLoadContentCatalog();
$article = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1
    ? ($catalog['articles'][$slug] ?? null)
    : null;
$debug = astronomyContentDebugEnabled();
if ($article === null || (!$debug && (!$article['valid'] || (!$article['visible'] && !$adminPreview)))) {
    http_response_code(404);
    $article = null;
}

$location = astronomyLocationContext();
$now = get_current_datetime($location['timezone']);
$title = $article !== null && is_array($article['raw']) ? (string) ($article['raw']['titulo'] ?? 'Contenido con errores') : 'Contenido no encontrado';
$summary = $article !== null && is_array($article['raw']) ? (string) ($article['raw']['resumen'] ?? '') : '';
$articleIsPublic = $article !== null
    && ($article['valid'] ?? false) === true
    && ($article['visible'] ?? false) === true
    && !$adminPreview;
$articlePath = $articleIsPublic
    ? '/contenido.php?slug=' . rawurlencode((string) $article['slug'])
    : '/contenido.php';
$pageSeo = aquellasLunasSeoPage($title . ' | Aquellas Lunas', $summary, $articlePath, $articleIsPublic ? 'article' : 'webpage');
$pageSeo['robots'] = $articleIsPublic ? 'index, follow' : 'noindex, nofollow';
$relatedArticles = astronomyContentShouldShowRelatedArticles($article)
    ? astronomyContentRelatedArticles($catalog, $article, 3)
    : [];
if ($articleIsPublic) {
    $pageSeo['breadcrumbs'] = [
        ['name' => 'Inicio', 'url' => aquellasLunasCanonicalUrl('/')],
        ['name' => 'Contenidos', 'url' => aquellasLunasCanonicalUrl('/contenidos.php')],
        ['name' => $title, 'url' => (string) $pageSeo['canonical_url']],
    ];
    $updatedAt = (string) ($article['metadata']['actualizado_en'] ?? '');
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $updatedAt, $updatedMatch) === 1) {
        $pageSeo['date_modified'] = $updatedMatch[1];
    }
    $articleImagePath = (string) ($article['image']['url'] ?? '');
    if ($articleImagePath !== '') {
        $pageSeo['schema_image'] = aquellasLunasCanonicalUrl('/' . ltrim($articleImagePath, '/'));
    }
}
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
    <main class="page"><div class="container public-page-container content-container content-container--article-reading">
        <?php if ($article === null): ?>
            <section class="card"><h1>Contenido no encontrado</h1></section>
        <?php elseif (!$article['valid']): ?>
            <?php renderAstronomyContentDiagnostic(['slug' => $article['slug'], 'errors' => $article['errors']], 'Artículo con errores'); ?>
        <?php else: ?>
            <?php if (astronomyContentDebugEnabled() && ($article['warnings'] ?? []) !== []): ?><?php renderAstronomyContentWarning(['slug' => $article['slug'], 'warnings' => $article['warnings']]); ?><?php endif; ?>
            <article class="content-article">
                <?php if ($articleIsPublic): ?><?php renderAstronomyContentBreadcrumb((string) $article['raw']['titulo']); ?><?php endif; ?>
                <header class="content-article__header">
                    <h1><?= htmlspecialchars((string) $article['raw']['titulo']) ?></h1>
                    <p><?= htmlspecialchars((string) $article['raw']['resumen']) ?></p>
                </header>
                <div class="content-local-actions">
                    <?php if ($adminPreview): ?>
                        <?php if (!$contentEnabled): ?><p class="content-local-note">Sección pública deshabilitada</p><?php endif; ?>
                        <?php if (!$article['visible']): ?><p class="content-local-note">Contenido oculto</p><?php endif; ?>
                        <a class="content-local-edit-link" href="<?= htmlspecialchars('admin/contenidos/?action=edit&slug=' . rawurlencode($article['slug']), ENT_QUOTES, 'UTF-8') ?>">Editar artículo <span aria-hidden="true">→</span></a>
                    <?php else: ?>
                        <a class="content-local-edit-link" href="contenidos.php"><span aria-hidden="true">←</span> Ver otros temas</a>
                    <?php endif; ?>
                </div>
                <?php if (($article['image']['url'] ?? null) !== null): ?>
                    <figure class="article-hero"><?= astronomyContentProtectedImageHtml(
                        $article['image'],
                        (string) $article['raw']['titulo'],
                        'article-hero__image',
                        '--image-position-x: ' . $article['image_position_x'] . '%; --image-position-y: ' . $article['image_position_y'] . '%;'
                    ) ?></figure>
                <?php endif; ?>
                <div class="content-article__body"><?= astronomyContentRenderMarkdown(astronomyContentArticleBodyMarkdown((string) $article['raw']['articulo'])) ?></div>
                <?php if ($relatedArticles !== []): ?>
                    <section class="content-related" aria-labelledby="content-related-title">
                        <h2 id="content-related-title">Contenidos relacionados</h2>
                        <div class="content-index">
                            <?php foreach ($relatedArticles as $relatedArticle): ?>
                                <?php renderAstronomyContentIndexCard($relatedArticle); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
                <footer class="content-local-actions">
                    <a class="content-local-edit-link" href="contenidos.php"><span aria-hidden="true">←</span> Ver otros temas</a>
                </footer>
            </article>
        <?php endif; ?>
    </div></main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
