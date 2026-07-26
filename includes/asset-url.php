<?php

function versionedAssetUrl(string $relativePath): string
{
    $normalizedPath = ltrim($relativePath, '/');
    $filePath = dirname(__DIR__) . '/' . $normalizedPath;

    if (!is_file($filePath)) {
        return $relativePath;
    }

    $modifiedAt = filemtime($filePath);
    if ($modifiedAt === false) {
        return $relativePath;
    }

    $separator = strpos($relativePath, '?') === false ? '?' : '&';
    return $relativePath . $separator . 'v=' . $modifiedAt;
}
