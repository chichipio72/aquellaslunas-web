<?php

declare(strict_types=1);

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");

$explorerEmbedMode = true;
$requestedHeight = filter_var($_GET['alto'] ?? null, FILTER_VALIDATE_INT);
$explorerEmbedHeight = $requestedHeight === false || $requestedHeight === null
    ? 520 : max(320, min(900, $requestedHeight));
$requestedTitle = isset($_GET['titulo']) && is_string($_GET['titulo']) ? trim($_GET['titulo']) : '';
$cleanTitle = preg_replace('/[\x00-\x1F\x7F]/', '', $requestedTitle) ?? '';
if ($cleanTitle !== '' && preg_match('/^.{0,100}/us', $cleanTitle, $titleMatch) === 1) {
    $cleanTitle = $titleMatch[0];
}
$explorerEmbedTitle = $cleanTitle === '' ? 'Explorador astronómico' : $cleanTitle;

require __DIR__ . '/interfaz-base.php';
