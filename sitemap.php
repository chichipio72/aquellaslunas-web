<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/content-system.php';

header('Content-Type: application/xml; charset=UTF-8');

/** @return list<array{loc:string,lastmod?:string}> */
function aquellasLunasSitemapEntries(array $catalog): array
{
    $paths = [
        '/',
        '/sol-y-luna.php',
        '/luna-fecha-favorita.php',
        '/luna-interactiva.php',
        '/cielo-de-hoy.php',
        '/cielo-de-esta-noche.php',
        '/eventos.php',
        '/planificador.php',
        '/fotografia.php',
        '/eclipses.php',
        '/ubicacion.php',
        '/canciones-a-la-luna.php',
        '/que-podes-hacer.php',
        '/fuentes-y-creditos.php',
        '/acerca-del-sitio.php',
        '/contenidos.php',
    ];
    $entries = [];
    foreach ($paths as $path) {
        $entries[] = ['loc' => aquellasLunasCanonicalUrl($path)];
    }

    foreach (astronomyContentVisibleArticles($catalog) as $article) {
        $slug = (string) ($article['slug'] ?? '');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            continue;
        }
        $entry = [
            'loc' => aquellasLunasCanonicalUrl('/contenido.php?slug=' . rawurlencode($slug)),
        ];
        $updatedAt = (string) ($article['metadata']['actualizado_en'] ?? '');
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $updatedAt, $updatedMatch) === 1) {
            $entry['lastmod'] = $updatedMatch[1];
        }
        $entries[] = $entry;
    }
    return $entries;
}

function aquellasLunasSitemapXml(array $entries): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $entry) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars((string) $entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        if (isset($entry['lastmod'])) {
            $xml .= '    <lastmod>' . htmlspecialchars((string) $entry['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
        }
        $xml .= "  </url>\n";
    }
    return $xml . "</urlset>\n";
}

echo aquellasLunasSitemapXml(aquellasLunasSitemapEntries(astronomyLoadContentCatalog()));
