<?php

require_once __DIR__ . '/asset-url.php';

function loadStoreAdminPhotos(PDO $connection): array
{
    return $connection->query(
        'SELECT id, titulo, descripcion, palabras_clave, archivo_preview_tienda, disponible, precio, moneda, creado_en FROM fotos ORDER BY creado_en DESC, id DESC'
    )->fetchAll();
}

function storeAdminPhotoPresentation(array $photo): array
{
    $relative = trim((string) ($photo['archivo_preview_tienda'] ?? ''));
    $previewUrl = preg_match('#^tienda/[a-f0-9]{64}\.jpg$#', $relative) === 1
        ? '../' . versionedAssetUrl('assets/images/tienda/previews/' . $relative)
        : null;
    $title = is_string($photo['titulo'] ?? null) ? trim($photo['titulo']) : '';
    $price = normalizeStorePhotoPrice($photo['precio'] ?? null) ?? '0.00';
    return [
        'id' => (int) $photo['id'],
        'title' => $title !== '' ? $title : 'Foto sin título',
        'editorial_title' => $title,
        'description' => is_string($photo['descripcion'] ?? null) ? trim($photo['descripcion']) : '',
        'keywords' => is_string($photo['palabras_clave'] ?? null) ? trim($photo['palabras_clave']) : '',
        'preview_url' => $previewUrl,
        'available' => (int) ($photo['disponible'] ?? 0) === 1,
        'price' => $price,
        'currency' => strtoupper(trim((string) ($photo['moneda'] ?? ''))),
    ];
}

function normalizeStorePhotoEditorialText(mixed $value, int $maximumLength): ?string
{
    if ($value === null) {
        return null;
    }
    if (!is_string($value) || $maximumLength < 1) {
        throw new InvalidArgumentException('Los metadatos editoriales no son válidos.');
    }
    $normalized = trim($value);
    if ($normalized === '') {
        return null;
    }
    if (function_exists('mb_strlen')) {
        $length = mb_strlen($normalized, 'UTF-8');
    } else {
        $matched = preg_match_all('/./us', $normalized, $characters);
        $length = $matched === false ? $maximumLength + 1 : $matched;
    }
    if ($length > $maximumLength) {
        throw new InvalidArgumentException('Los metadatos editoriales no son válidos.');
    }
    return $normalized;
}

function setStorePhotoEditorialMetadata(
    PDO $connection,
    int $photoId,
    ?string $title,
    ?string $description,
    ?string $keywords
): bool {
    if ($photoId < 1) {
        return false;
    }
    $title = normalizeStorePhotoEditorialText($title, 255);
    $description = normalizeStorePhotoEditorialText($description, 5000);
    $keywords = normalizeStorePhotoEditorialText($keywords, 2000);
    $statement = $connection->prepare(
        'UPDATE fotos SET titulo = :titulo, descripcion = :descripcion, palabras_clave = :palabras_clave WHERE id = :id'
    );
    $statement->execute([
        'titulo' => $title,
        'descripcion' => $description,
        'palabras_clave' => $keywords,
        'id' => $photoId,
    ]);
    if ($statement->rowCount() === 1) {
        return true;
    }
    $exists = $connection->prepare('SELECT COUNT(*) FROM fotos WHERE id = :id');
    $exists->execute(['id' => $photoId]);
    return (int) $exists->fetchColumn() === 1;
}

function normalizeStorePhotoPrice(mixed $price): ?string
{
    if (!is_string($price) && !is_int($price) && !is_float($price)) {
        return null;
    }
    $normalized = str_replace(',', '.', trim((string) $price));
    if (preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $normalized) !== 1 || (float) $normalized <= 0) {
        return null;
    }
    [$integer, $decimals] = array_pad(explode('.', $normalized, 2), 2, '');
    return $integer . '.' . str_pad($decimals, 2, '0');
}

function setStorePhotoPrice(PDO $connection, int $photoId, string $price): bool
{
    if ($photoId < 1 || normalizeStorePhotoPrice($price) !== $price) {
        return false;
    }
    $exists = $connection->prepare('SELECT COUNT(*) FROM fotos WHERE id = :id');
    $exists->execute(['id' => $photoId]);
    if ((int) $exists->fetchColumn() !== 1) {
        return false;
    }
    $statement = $connection->prepare('UPDATE fotos SET precio = :precio WHERE id = :id');
    return $statement->execute(['precio' => $price, 'id' => $photoId]);
}

function setStorePhotoPrices(PDO $connection, array $photoIds, string $price): int
{
    if (normalizeStorePhotoPrice($price) !== $price || $photoIds === []) {
        throw new InvalidArgumentException('El lote de precios no es válido.');
    }
    $ids = [];
    foreach ($photoIds as $photoId) {
        $validated = filter_var($photoId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            throw new InvalidArgumentException('El lote de precios no es válido.');
        }
        $ids[(int) $validated] = (int) $validated;
    }
    $ids = array_values($ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $ownsTransaction = !$connection->inTransaction();
    if ($ownsTransaction) {
        $connection->beginTransaction();
    }
    try {
        $existing = $connection->prepare("SELECT COUNT(*) FROM fotos WHERE id IN ({$placeholders}) FOR UPDATE");
        $existing->execute($ids);
        if ((int) $existing->fetchColumn() !== count($ids)) {
            throw new RuntimeException('No se pudieron actualizar las fotos seleccionadas.');
        }
        $update = $connection->prepare("UPDATE fotos SET precio = ? WHERE id IN ({$placeholders})");
        $update->execute(array_merge([$price], $ids));
        if ($ownsTransaction) {
            $connection->commit();
        }
        return count($ids);
    } catch (Throwable $exception) {
        if ($ownsTransaction && $connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function setStorePhotoAvailability(PDO $connection, int $photoId, bool $available): bool
{
    if ($photoId < 1) {
        return false;
    }
    $statement = $connection->prepare('UPDATE fotos SET disponible = :disponible WHERE id = :id');
    $statement->execute(['disponible' => $available ? 1 : 0, 'id' => $photoId]);
    return $statement->rowCount() === 1;
}
