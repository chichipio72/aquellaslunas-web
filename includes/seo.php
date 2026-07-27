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
        echo '    <meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
        echo '    <meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '    <meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;

        $schemaOrgType = $type === 'website' ? 'WebSite' : 'WebPage';
        $schemaOrgName = $siteName;
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
        ];

        if ($type !== 'website') {
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
