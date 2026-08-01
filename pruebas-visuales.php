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

sendDynamicNoCacheHeaders();

if (!canUseSiteDebugTools()) {
    http_response_code(404);
    exit;
}

// Reemplazar o ampliar esta lista para probar otras publicaciones públicas.
$instagramPosts = [
    'https://www.instagram.com/aquellas_lunas/p/DOZhb0YDfHk/?hl=es-la',
    'https://www.instagram.com/aquellas_lunas/p/DNLbngkN5TB/?hl=es-la',
    'https://www.instagram.com/aquellas_lunas/p/DNbcVWyBLTZ/?hl=es-la',
    'https://www.instagram.com/aquellas_lunas/p/DaOqsVsFPVh/?hl=es-la',
    'https://www.instagram.com/aquellas_lunas/p/DR3UGfDDbpD/?hl=es-la',
    'https://www.instagram.com/aquellas_lunas/p/DPh6qNfjZiL/?hl=es-la',
];

function visualTestsInstagramUrl($value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $url = filter_var(trim($value), FILTER_VALIDATE_URL);
    if (!is_string($url)) {
        return null;
    }
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    if (!in_array($host, ['instagram.com', 'www.instagram.com'], true)) {
        return null;
    }
    if (preg_match('#^/(?:aquellas_lunas/)?(p|reel)/([A-Za-z0-9_-]+)/?$#', $path, $matches) !== 1) {
        return null;
    }
    return 'https://www.instagram.com/' . $matches[1] . '/' . $matches[2]
        . '/?utm_source=ig_embed&utm_campaign=loading';
}

function renderVisualTestsInstagramPost($value, string $className = ''): void
{
    $url = visualTestsInstagramUrl($value);
    $classes = trim('visual-tests-instagram ' . $className);
    if ($url === null) {
        ?>
        <div class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?> visual-tests-instagram--placeholder">
            <p>Pegá aquí la URL de una publicación pública de Instagram</p>
        </div>
        <?php
        return;
    }
    ?>
    <div class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>">
        <blockquote
            class="instagram-media"
            data-instgrm-captioned
            data-instgrm-permalink="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
            data-instgrm-version="14"
        >
            <p>Publicación pública de Aquellas Lunas en Instagram.</p>
            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Ver la publicación original en Instagram</a>
        </blockquote>
    </div>
    <?php
}

$location = astronomyLocationContext();
$currentDateTime = get_current_datetime($location['timezone']);
$pageSeo = aquellasLunasSeoPage(
    'Pruebas visuales | Aquellas Lunas',
    'Laboratorio local de componentes y contenidos experimentales de Aquellas Lunas.',
    '/pruebas-visuales.php',
    'article'
);
$pageSeo['robots'] = 'noindex, nofollow';
$featuredInstagramUrl = visualTestsInstagramUrl($instagramPosts[1] ?? null);
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
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/visual-tests.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/instagram-embeds.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($currentDateTime); ?>
    <?php renderAstronomySiteHeader('visual_tests', $location); ?>

    <main class="page visual-tests-page">
        <div class="container public-page-container visual-tests-container">
            <header class="hero visual-tests-hero atmosphere-card--night">
                <p class="eyebrow">LABORATORIO LOCAL</p>
                <h1>Pruebas visuales</h1>
                <p class="hero-subtitle">Componentes y contenidos experimentales dentro del diseño real del sitio.</p>
                <p class="visual-tests-local-note">Esta página solo está disponible en el entorno local.</p>
            </header>

            <section class="visual-tests-intro" aria-labelledby="instagram-tests-title">
                <h2 id="instagram-tests-title">Publicaciones de Aquellas Lunas</h2>
                <p>Prueba de integración de fotografías y publicaciones públicas de Instagram dentro del diseño de la web.</p>
            </section>

            <section class="card visual-tests-section" aria-labelledby="single-post-title">
                <header>
                    <p class="eyebrow">PRUEBA 1</p>
                    <h2 id="single-post-title">Publicación individual</h2>
                </header>
                <div class="visual-tests-single">
                    <?php renderVisualTestsInstagramPost($instagramPosts[0] ?? null); ?>
                </div>
            </section>

            <section class="card visual-tests-section" aria-labelledby="post-grid-title">
                <header>
                    <p class="eyebrow">PRUEBA 2</p>
                    <h2 id="post-grid-title">Grilla de publicaciones</h2>
                </header>
                <div class="visual-tests-grid">
                    <?php foreach ($instagramPosts as $post): ?>
                        <?php renderVisualTestsInstagramPost($post); ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card visual-tests-section" aria-labelledby="related-post-title">
                <header>
                    <p class="eyebrow">PRUEBA 3</p>
                    <h2 id="related-post-title">Publicación y contenido relacionado</h2>
                </header>
                <div class="visual-tests-featured">
                    <?php renderVisualTestsInstagramPost($instagramPosts[1] ?? null, 'visual-tests-instagram--featured'); ?>
                    <article class="visual-tests-related">
                        <p class="eyebrow">CONTENIDO DE EJEMPLO</p>
                        <h3>Una mirada más cercana a esta Luna</h3>
                        <p>Espacio de prueba para combinar una publicación con una futura explicación educativa breve.</p>
                        <?php if ($featuredInstagramUrl !== null): ?>
                            <a class="button button-primary" href="<?= htmlspecialchars($featuredInstagramUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Ver en Instagram</a>
                        <?php else: ?>
                            <span class="button button-primary is-disabled" aria-disabled="true">Ver en Instagram</span>
                        <?php endif; ?>
                    </article>
                </div>
            </section>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
