<?php

const STORE_PHOTO_UPLOAD_MAX_FILES = 20;
const STORE_PHOTO_UPLOAD_MAX_BYTES = 25 * 1024 * 1024;

function storePhotoUploadFiles(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $normalized = [];
    foreach ($names as $index => $name) {
        $normalized[] = [
            'name' => (string) $name,
            'tmp_name' => (string) (is_array($files['tmp_name'] ?? null) ? ($files['tmp_name'][$index] ?? '') : ($files['tmp_name'] ?? '')),
            'error' => (int) (is_array($files['error'] ?? null) ? ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) : ($files['error'] ?? UPLOAD_ERR_NO_FILE)),
            'size' => (int) (is_array($files['size'] ?? null) ? ($files['size'][$index] ?? 0) : ($files['size'] ?? 0)),
        ];
    }
    return $normalized;
}

function storePhotoUploadValidate(array $file): array
{
    $name = trim((string) ($file['name'] ?? ''));
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('La transferencia del archivo no se completó.');
    }
    if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || in_array($name, ['.', '..'], true)) {
        throw new RuntimeException('El nombre del archivo no es válido.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > STORE_PHOTO_UPLOAD_MAX_BYTES || !is_file($temporaryPath) || filesize($temporaryPath) !== $size) {
        throw new RuntimeException('La imagen está vacía o supera el máximo de 25 MB.');
    }
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $image = storePhotoImageDetails($temporaryPath, $extension);
    $photoId = hash_file('sha256', $temporaryPath);
    if (!is_string($photoId) || preg_match('/^[a-f0-9]{64}$/', $photoId) !== 1) {
        throw new RuntimeException('No se pudo identificar la imagen.');
    }
    $canonicalExtension = match ($image[2]) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    };
    return ['photo_id' => $photoId, 'extension' => $canonicalExtension, 'width' => (int) $image[0], 'height' => (int) $image[1]];
}

function storePhotoRecordsByIds(PDO $connection, array $photoIds): array
{
    $photoIds = array_values(array_unique(array_filter($photoIds, static fn ($id): bool => is_string($id) && preg_match('/^[a-f0-9]{64}$/', $id) === 1)));
    if ($photoIds === []) {
        return [];
    }
    $statement = $connection->prepare(
        'SELECT id, foto_id, nombre_archivo, archivo_original, archivo_preview_tienda, archivo_preview_contenido'
        . ' FROM fotos WHERE foto_id IN (' . implode(',', array_fill(0, count($photoIds), '?')) . ')'
    );
    $statement->execute($photoIds);
    return $statement->fetchAll();
}
