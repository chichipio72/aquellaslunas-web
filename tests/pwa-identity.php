<?php

declare(strict_types=1);

function pwaIdentityAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$manifest = json_decode((string) file_get_contents(__DIR__ . '/../manifest.webmanifest'), true);
pwaIdentityAssert(is_array($manifest), 'El manifest no contiene JSON válido.');
pwaIdentityAssert(($manifest['id'] ?? null) === '/astro/', 'El ID de la PWA no es /astro/.');
pwaIdentityAssert(($manifest['start_url'] ?? null) === '/astro/', 'El start_url de la PWA no es /astro/.');
pwaIdentityAssert(($manifest['scope'] ?? null) === '/astro/', 'El scope de la PWA no es /astro/.');
pwaIdentityAssert(($manifest['display'] ?? null) === 'standalone', 'El display de la PWA dejó de ser standalone.');

ob_start();
require_once __DIR__ . '/../includes/favicon-links.php';
renderFaviconLinks('../../');
$links = (string) ob_get_clean();
pwaIdentityAssert(substr_count($links, 'rel="manifest"') === 1, 'No se genera exactamente un enlace al manifest.');
pwaIdentityAssert(str_contains($links, 'href="/astro/manifest.webmanifest"'), 'La URL del manifest no es estable.');
pwaIdentityAssert(!str_contains($links, 'manifest.webmanifest?'), 'El enlace al manifest conserva cache-busting.');

$htaccess = (string) file_get_contents(__DIR__ . '/../.htaccess');
pwaIdentityAssert(str_contains($htaccess, 'AddType application/manifest+json .webmanifest'), 'Falta el tipo MIME del manifest.');
pwaIdentityAssert(str_contains($htaccess, 'Header set Cache-Control "no-cache, max-age=0, must-revalidate"'),
    'Faltan los headers de revalidación del manifest.');
$dockerfile = (string) file_get_contents(__DIR__ . '/../Dockerfile');
pwaIdentityAssert(str_contains($dockerfile, 'a2enmod rewrite headers'), 'El Apache local no habilita los headers del manifest.');
$apacheConfig = (string) file_get_contents(__DIR__ . '/../apache/astro.conf');
pwaIdentityAssert(str_contains($apacheConfig, 'Alias /astro/ /var/www/html/'), 'El entorno local no replica el prefijo /astro/.');

echo "OK PWA identity\n";
