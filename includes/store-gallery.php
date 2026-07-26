<?php

require_once __DIR__ . '/asset-url.php';

function loadAvailableStorePhotos(PDO $connection): array
{
    return $connection->query(
        "SELECT foto_id, titulo, archivo_preview_tienda, precio, moneda, ancho_px, alto_px "
        . "FROM fotos "
        . "WHERE disponible = 1 AND archivo_preview_tienda IS NOT NULL AND TRIM(archivo_preview_tienda) <> '' "
        . "ORDER BY creado_en DESC, id DESC"
    )->fetchAll();
}

function storeGalleryPhotoPresentation(array $photo): ?array
{
    $previewFilename = trim((string) ($photo['archivo_preview_tienda'] ?? ''));
    if (preg_match('#^tienda/[a-f0-9]{64}\.jpg$#', $previewFilename) !== 1) {
        return null;
    }
    $title = isset($photo['titulo']) && is_string($photo['titulo'])
        ? trim($photo['titulo'])
        : '';
    $price = $photo['precio'] ?? null;
    $currency = strtoupper(trim((string) ($photo['moneda'] ?? '')));
    $publicId = strtolower(trim((string) ($photo['foto_id'] ?? '')));
    if (!is_numeric($price) || (float) $price <= 0 || preg_match('/^[A-Z]{3}$/', $currency) !== 1 || preg_match('/^[a-f0-9]{64}$/', $publicId) !== 1) {
        return null;
    }
    $normalizedPrice = number_format((float) $price, 2, '.', '');
    [$whole, $decimal] = explode('.', $normalizedPrice, 2);
    $width = filter_var($photo['ancho_px'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $height = filter_var($photo['alto_px'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return [
        'title' => $title !== '' ? $title : null,
        'public_id' => $publicId,
        'preview_url' => versionedAssetUrl('assets/images/tienda/previews/' . $previewFilename),
        'alt' => $title !== '' ? $title : 'Fotografía de la Luna',
        'price_label' => number_format((float) $price, 2, ',', '.') . ' ' . $currency,
        'price_cents' => ((int) $whole * 100) + (int) $decimal,
        'currency' => $currency,
        'width' => $width !== false ? (int) $width : null,
        'height' => $height !== false ? (int) $height : null,
    ];
}
