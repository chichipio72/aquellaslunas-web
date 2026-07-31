<?php
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/contact.php';
$aboutCurrentDateTime = get_current_datetime('America/Argentina/Buenos_Aires');
$pageSeo = aquellasLunasSeoPage('Acerca de Aquellas Lunas', 'La historia y el propósito de Aquellas Lunas: acercar el cielo y sus fenómenos a todas las personas.', '/acerca-del-sitio.php', 'article');
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
    <?php renderAstronomyMobileSwipeNavigationScript('about'); ?>
</head>
<body<?= astronomyMobileSwipeNavigationAttributes('about') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($aboutCurrentDateTime); ?>
    <?php renderAstronomySiteHeader('about'); ?>

    <main class="page about-page">
        <div class="container about-container">
            <article class="card about-content">
                <header class="about-heading">
                    <h1><?= htmlspecialchars(astronomySiteSectionLabel('about')) ?></h1>
                </header>

                <p>Aquellas Lunas nació como una <a class="about-instagram-link" href="<?= htmlspecialchars(astronomyInstagramProfile()['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">cuenta de Instagram</a> dedicada a compartir fotografías de la Luna. Con el tiempo se fue formando una comunidad de personas que disfrutan mirar el cielo y que, además de las imágenes, empezaron a hacer siempre las mismas preguntas.</p>

                <ul class="about-questions" aria-label="Preguntas frecuentes sobre la Luna y el cielo">
                    <li>¿Cuándo es la próxima Luna llena?</li>
                    <li>¿A qué hora sale hoy?</li>
                    <li>¿Por qué ayer se veía tan roja?</li>
                    <li>¿Por qué hoy cuesta encontrarla?</li>
                    <li>¿Qué se puede ver esta noche?</li>
                </ul>

                <p>Encontrar esas respuestas no siempre es fácil. Suelen estar dispersas entre aplicaciones, calendarios o sitios pensados para quienes ya tienen conocimientos de astronomía.</p>
                <p>Este sitio nace para reunir esa información en un solo lugar y explicarla de una manera simple, clara y útil para cualquier persona.</p>
                <p>No busca reemplazar a las publicaciones de Instagram, sino complementarlas. La idea es que, cuando surja una duda sobre la Luna o sobre algún fenómeno del cielo, exista un lugar donde encontrar la respuesta de forma rápida y fácil de entender.</p>
                <p>Hoy el sitio ofrece información sobre las fases de la Luna, sus horarios de salida y puesta, eventos astronómicos y otros datos pensados para ayudarte a saber cuándo mirar el cielo, qué vas a poder ver y por qué sucede.</p>
                <p>Esperamos que Aquellas Lunas siga creciendo con el tiempo gracias a las preguntas, las sugerencias y los aportes de quienes forman parte de esta comunidad. Después de todo, muchas de las ideas para este sitio nacieron justamente de esas conversaciones.</p>
                <p class="about-closing">Si alguna vez este sitio consigue que levantes la vista en el momento justo para descubrir algo que de otro modo habría pasado desapercibido, entonces habrá cumplido su objetivo.</p>
            </article>
            <?php renderAstronomyContactBlock('about'); ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
