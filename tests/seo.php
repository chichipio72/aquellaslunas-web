<?php

require_once __DIR__ . '/../includes/seo.php';

function seoAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$page = aquellasLunasSeoPage(
    'Eventos lunares | Aquellas Lunas',
    'Eventos lunares por fecha, ubicación y tipo.',
    '/eventos.php',
    'article'
);
ob_start();
renderSeoHead($page);
$head = ob_get_clean();

seoAssert(substr_count($head, '<title>') === 1, 'El helper duplicó title.');
seoAssert(substr_count($head, 'name="description"') === 1, 'El helper duplicó description.');
seoAssert(substr_count($head, 'rel="canonical"') === 1, 'El helper duplicó canonical.');
seoAssert(str_contains($head, 'https://aquellaslunas.com.ar/astro/eventos.php'), 'Canonical fuera de /astro/.');
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
seoAssert(($schema['@type'] ?? null) === 'Article', 'Una página editorial no usa Article.');
seoAssert(($schema['name'] ?? null) === 'Eventos lunares | Aquellas Lunas', 'El nombre estructurado no identifica la página.');
seoAssert(($schema['isPartOf']['url'] ?? null) === 'https://aquellaslunas.com.ar/astro/', 'Falta pertenencia al sitio.');

$sitemap = file_get_contents(__DIR__ . '/../sitemap.xml');
foreach (['cielo-de-hoy.php', 'que-podes-hacer.php'] as $path) {
    seoAssert(str_contains((string) $sitemap, '/astro/' . $path), 'Falta en sitemap: ' . $path);
}
seoAssert(!str_contains((string) $sitemap, '/astro/galeria.php'), 'Galería todavía figura en el sitemap.');
$robots = file_get_contents(__DIR__ . '/../robots.txt');
seoAssert(str_contains((string) $robots, 'Sitemap: https://aquellaslunas.com.ar/astro/sitemap.xml'), 'robots.txt no declara el sitemap.');
seoAssert(str_contains((string) $robots, 'Disallow: /astro/admin/'), 'robots.txt no excluye administración.');
seoAssert(str_contains((string) $robots, 'Disallow: /astro/pruebas-visuales.php'), 'robots.txt no excluye la página de pruebas.');

echo "SEO técnico: OK\n";
