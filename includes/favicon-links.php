<?php

require_once __DIR__ . '/asset-url.php';

function renderFaviconLinks(): void
{
    $faviconSvg = htmlspecialchars(versionedAssetUrl('assets/images/favicon/favicon.svg'), ENT_QUOTES, 'UTF-8');
    $faviconIco = htmlspecialchars(versionedAssetUrl('assets/images/favicon/favicon.ico'), ENT_QUOTES, 'UTF-8');
    $faviconPng = htmlspecialchars(versionedAssetUrl('assets/images/favicon/favicon-32x32.png'), ENT_QUOTES, 'UTF-8');
    $appleTouchIcon = htmlspecialchars(versionedAssetUrl('assets/images/favicon/apple-touch-icon.png'), ENT_QUOTES, 'UTF-8');

    echo '    <link rel="icon" href="' . $faviconSvg . '" type="image/svg+xml">' . "\n";
    echo '    <link rel="icon" href="' . $faviconIco . '" sizes="any">' . "\n";
    echo '    <link rel="icon" href="' . $faviconPng . '" type="image/png" sizes="32x32">' . "\n";
    echo '    <link rel="apple-touch-icon" href="' . $appleTouchIcon . '" sizes="180x180">' . "\n";
}
