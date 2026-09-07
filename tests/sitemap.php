<?php

declare(strict_types=1);

function sitemapAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

ob_start();
require __DIR__ . '/../sitemap.php';
$xml = (string) ob_get_clean();

$photographyCanonical = aquellasLunasCanonicalUrl('/fotografia.php');
$photographyEntry = '<loc>' . htmlspecialchars($photographyCanonical, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';

sitemapAssert(
    substr_count($xml, $photographyEntry) === 1,
    'Fotografía debe aparecer exactamente una vez con su URL canonical.'
);
sitemapAssert(
    !str_contains($xml, '/admin/')
        && !str_contains($xml, '/includes/')
        && !str_contains($xml, '/scripts/')
        && !str_contains($xml, '/storage/')
        && !str_contains($xml, '/tests/')
        && !str_contains($xml, '/sitemap.php'),
    'El sitemap publicó una ruta administrativa o técnica.'
);

echo "Sitemap público: OK\n";
