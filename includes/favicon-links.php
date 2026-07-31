<?php

require_once __DIR__ . '/asset-url.php';

function renderFaviconLinks(): void
{
    $faviconSvg = htmlspecialchars(versionedAssetUrl('assets/images/favicon/favicon.svg'), ENT_QUOTES, 'UTF-8');
    $faviconIco = htmlspecialchars(versionedAssetUrl('assets/images/favicon/favicon.ico'), ENT_QUOTES, 'UTF-8');
    $faviconPng = htmlspecialchars(versionedAssetUrl('assets/images/favicon/favicon-32x32.png'), ENT_QUOTES, 'UTF-8');
    $appleTouchIcon = htmlspecialchars(versionedAssetUrl('assets/images/favicon/apple-touch-icon.png'), ENT_QUOTES, 'UTF-8');
    $manifestUrl = htmlspecialchars(versionedAssetUrl('manifest.webmanifest'), ENT_QUOTES, 'UTF-8');

    echo '    <link rel="icon" href="' . $faviconSvg . '" type="image/svg+xml">' . "\n";
    echo '    <link rel="icon" href="' . $faviconIco . '" sizes="any">' . "\n";
    echo '    <link rel="icon" href="' . $faviconPng . '" type="image/png" sizes="32x32">' . "\n";
    echo '    <link rel="apple-touch-icon" href="' . $appleTouchIcon . '" sizes="180x180">' . "\n";
    echo '    <link rel="manifest" href="' . $manifestUrl . '">' . "\n";
    echo '    <meta name="theme-color" content="#0b1020">' . "\n";
    echo '    <meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '    <meta name="apple-mobile-web-app-title" content="Aquellas Lunas">' . "\n";
    echo '    <script src="' . htmlspecialchars(versionedAssetUrl('assets/js/install-prompt.js'), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
