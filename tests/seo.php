<?php

require_once __DIR__ . '/../includes/seo.php';

function seoAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$page = aquellasLunasSeoPage(
    'Artículo lunar | Aquellas Lunas',
    'Un artículo editorial sobre la Luna.',
    '/contenido.php?slug=articulo-lunar',
    'article'
);
$page['breadcrumbs'] = [
    ['name' => 'Inicio', 'url' => aquellasLunasCanonicalUrl('/')],
    ['name' => 'Contenidos', 'url' => aquellasLunasCanonicalUrl('/contenidos.php')],
    ['name' => 'Artículo lunar', 'url' => $page['canonical_url']],
];
ob_start();
renderSeoHead($page);
$head = ob_get_clean();

seoAssert(substr_count($head, '<title>') === 1, 'El helper duplicó title.');
seoAssert(substr_count($head, 'name="description"') === 1, 'El helper duplicó description.');
seoAssert(substr_count($head, 'rel="canonical"') === 1, 'El helper duplicó canonical.');
seoAssert(str_contains($head, 'https://aquellaslunas.com.ar/astro/contenido.php?slug=articulo-lunar'), 'Canonical individual fuera de /astro/.');
seoAssert(str_contains($head, 'name="twitter:card" content="summary_large_image"'), 'Twitter no usa la tarjeta social grande.');
seoAssert(substr_count($head, 'property="og:image"') === 1, 'og:image falta o está duplicada.');
seoAssert(str_contains($head, 'property="og:image" content="https://aquellaslunas.com.ar/astro/assets/images/social/aquellas-lunas-social.jpg"'), 'og:image no usa la URL absoluta esperada.');
seoAssert(str_contains($head, 'property="og:image:width" content="1200"'), 'Falta el ancho de la imagen social.');
seoAssert(str_contains($head, 'property="og:image:height" content="630"'), 'Falta el alto de la imagen social.');
seoAssert(str_contains($head, 'property="og:image:type" content="image/jpeg"'), 'Falta el tipo de la imagen social.');
seoAssert(str_contains($head, 'property="og:image:alt" content="Fotografía de la Luna de Aquellas Lunas"'), 'Falta el texto alternativo de Open Graph.');
seoAssert(substr_count($head, 'name="twitter:image"') === 1, 'twitter:image falta o está duplicada.');
seoAssert(str_contains($head, 'name="twitter:image:alt" content="Fotografía de la Luna de Aquellas Lunas"'), 'Falta el texto alternativo de Twitter.');

$customPage = $page;
$customPage['image'] = [
    'url' => 'https://aquellaslunas.com.ar/astro/assets/images/social/futura.jpg',
    'alt' => 'Imagen específica',
];
ob_start();
renderSeoHead($customPage);
$customHead = ob_get_clean();
seoAssert(str_contains($customHead, 'property="og:image" content="https://aquellaslunas.com.ar/astro/assets/images/social/futura.jpg"'), 'Una página no puede reemplazar la imagen predeterminada.');
seoAssert(str_contains($customHead, 'property="og:image:width" content="1200"'), 'El reemplazo parcial perdió valores predeterminados.');

preg_match('#<script type="application/ld\+json">\s*(.*?)\s*</script>#s', $head, $matches);
$schema = isset($matches[1]) ? json_decode($matches[1], true) : null;
seoAssert(is_array($schema), 'JSON-LD inválido.');
$graph = is_array($schema['@graph'] ?? null) ? $schema['@graph'] : [];
$articleSchema = $graph[0] ?? null;
$breadcrumbSchema = $graph[1] ?? null;
seoAssert(($articleSchema['@type'] ?? null) === 'Article', 'Una página editorial no conserva Article.');
seoAssert(($articleSchema['headline'] ?? null) === 'Artículo lunar | Aquellas Lunas', 'El titular estructurado no identifica el artículo.');
seoAssert(($articleSchema['url'] ?? null) === 'https://aquellaslunas.com.ar/astro/contenido.php?slug=articulo-lunar', 'El schema no usa la canonical individual.');
seoAssert(($articleSchema['isPartOf']['url'] ?? null) === 'https://aquellaslunas.com.ar/astro/', 'Falta pertenencia al sitio.');
seoAssert(($breadcrumbSchema['@type'] ?? null) === 'BreadcrumbList', 'Falta BreadcrumbList.');
$breadcrumbItems = $breadcrumbSchema['itemListElement'] ?? [];
seoAssert(count($breadcrumbItems) === 3, 'BreadcrumbList no tiene exactamente tres niveles.');
seoAssert(array_column($breadcrumbItems, 'name') === ['Inicio', 'Contenidos', 'Artículo lunar'], 'BreadcrumbList no conserva la jerarquía esperada.');
seoAssert(($breadcrumbItems[2]['item'] ?? null) === $page['canonical_url'], 'El último breadcrumb no coincide con la canonical.');

$robots = file_get_contents(__DIR__ . '/../robots.txt');
seoAssert(str_contains((string) $robots, 'Sitemap: https://aquellaslunas.com.ar/astro/sitemap.xml'), 'robots.txt no declara el sitemap.');
seoAssert(str_contains((string) $robots, 'Disallow: /astro/admin/'), 'robots.txt no excluye administración.');
seoAssert(str_contains((string) $robots, 'Disallow: /astro/pruebas-visuales.php'), 'robots.txt no excluye la página de pruebas.');

echo "SEO técnico: OK\n";
