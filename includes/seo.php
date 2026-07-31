<?php

require_once __DIR__ . '/asset-url.php';

if (!function_exists('aquellasLunasSiteName')) {
    function aquellasLunasSiteName(): string
    {
        return 'Aquellas Lunas';
    }
}

if (!function_exists('aquellasLunasPublicBaseUrl')) {
    function aquellasLunasPublicBaseUrl(): string
    {
        return 'https://aquellaslunas.com.ar/astro';
    }
}

if (!function_exists('aquellasLunasCanonicalUrl')) {
    function aquellasLunasCanonicalUrl(string $path = '/'): string
    {
        $baseUrl = rtrim(aquellasLunasPublicBaseUrl(), '/');
        $normalizedPath = '/' . ltrim($path, '/');

        if ($normalizedPath === '/' || $normalizedPath === '') {
            return $baseUrl . '/';
        }

        return $baseUrl . $normalizedPath;
    }
}

if (!function_exists('aquellasLunasDefaultSocialImage')) {
    function aquellasLunasDefaultSocialImage(): array
    {
        return [
            'url' => aquellasLunasCanonicalUrl('/assets/images/social/aquellas-lunas-social.jpg'),
            'width' => 1200,
            'height' => 630,
            'type' => 'image/jpeg',
            'alt' => 'Fotografía de la Luna de Aquellas Lunas',
        ];
    }
}

if (!function_exists('aquellasLunasSeoPage')) {
    function aquellasLunasSeoPage(string $title, string $description, string $path, string $type = 'website'): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'path' => $path,
            'canonical_url' => aquellasLunasCanonicalUrl($path),
            'type' => $type,
        ];
    }
}

if (!function_exists('renderSeoHead')) {
    function renderSeoHead(array $page): void
    {
        $siteName = aquellasLunasSiteName();
        $canonicalUrl = $page['canonical_url'] ?? aquellasLunasCanonicalUrl($page['path'] ?? '/');
        $title = $page['title'] ?? $siteName;
        $description = $page['description'] ?? '';
        $type = $page['type'] ?? 'website';
        $path = $page['path'] ?? '/';
        $url = $canonicalUrl;
        $socialImage = array_replace(aquellasLunasDefaultSocialImage(), $page['image'] ?? []);

        echo '    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' . PHP_EOL;
        echo '    <meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        $robots = $page['robots'] ?? 'index, follow';
        echo '    <meta name="robots" content="' . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <link rel="canonical" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta property="og:type" content="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta property="og:url" content="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta property="og:site_name" content="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta property="og:image" content="' . htmlspecialchars($socialImage['url'], ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta property="og:image:width" content="' . (int) $socialImage['width'] . '">' . PHP_EOL;
        echo '    <meta property="og:image:height" content="' . (int) $socialImage['height'] . '">' . PHP_EOL;
        echo '    <meta property="og:image:type" content="' . htmlspecialchars($socialImage['type'], ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta property="og:image:alt" content="' . htmlspecialchars($socialImage['alt'], ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
        echo '    <meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta name="twitter:image" content="' . htmlspecialchars($socialImage['url'], ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta name="twitter:image:alt" content="' . htmlspecialchars($socialImage['alt'], ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;

        $schemaOrgType = match ($type) {
            'website' => 'WebSite',
            'article' => 'Article',
            default => 'WebPage',
        };
        $schemaOrgName = $type === 'website' ? $siteName : $title;
        $schemaOrgDescription = $description;
        $schemaOrgUrl = $url;
        $schemaOrgLanguage = 'es';
        $schemaOrgJson = [
            '@context' => 'https://schema.org',
            '@type' => $schemaOrgType,
            'name' => $schemaOrgName,
            'url' => $schemaOrgUrl,
            'inLanguage' => $schemaOrgLanguage,
            'description' => $schemaOrgDescription,
            'image' => $socialImage['url'],
        ];

        if ($type !== 'website') {
            $schemaOrgJson['isPartOf'] = [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => aquellasLunasCanonicalUrl('/'),
            ];
            $schemaOrgJson['about'] = [
                '@type' => 'Thing',
                'name' => $title,
            ];
        }

        echo '    <script type="application/ld+json">' . PHP_EOL;
        echo '        ' . json_encode($schemaOrgJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo '    </script>' . PHP_EOL;
    }
}
