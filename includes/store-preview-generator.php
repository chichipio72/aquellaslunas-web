<?php

function storePendingPreviewPhotos(PDO $connection, bool $force = false): array
{
    $where = $force
        ? ''
        : " WHERE archivo_preview_tienda IS NULL OR TRIM(archivo_preview_tienda) = ''"
            . " OR archivo_preview_contenido IS NULL OR TRIM(archivo_preview_contenido) = ''";
    return $connection->query(
        "SELECT id, foto_id, nombre_archivo, archivo_original, archivo_preview_tienda, archivo_preview_contenido"
        . " FROM fotos" . $where . " ORDER BY id"
    )->fetchAll();
}

function storePreviewResolveOriginalPath(string $originalsPath, string $storedPath): string
{
    $storedPath = str_replace('\\', '/', trim($storedPath));
    if (
        $storedPath === ''
        || str_starts_with($storedPath, '/')
        || preg_match('/^[A-Za-z]:\//', $storedPath) === 1
        || in_array('..', explode('/', $storedPath), true)
    ) {
        throw new RuntimeException('La ruta del original no es válida.');
    }
    $root = realpath($originalsPath);
    $path = realpath($originalsPath . DIRECTORY_SEPARATOR . $storedPath);
    if ($root === false || $path === false || !is_file($path) || !is_readable($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('No se pudo leer el archivo original.');
    }
    return $path;
}

function storePreviewExifOrientation(string $path): int
{
    if (!function_exists('exif_read_data')) {
        return 1;
    }
    $exif = @exif_read_data($path, 'IFD0', true, false);
    $orientation = is_array($exif) ? ($exif['IFD0']['Orientation'] ?? 1) : 1;
    return is_numeric($orientation) && (int) $orientation >= 1 && (int) $orientation <= 8 ? (int) $orientation : 1;
}

function storePreviewRotate(GdImage $image, int $degrees): GdImage
{
    $rotated = imagerotate($image, $degrees, 0);
    if (!$rotated instanceof GdImage) {
        throw new RuntimeException('No se pudo aplicar la orientación de la imagen.');
    }
    imagedestroy($image);
    return $rotated;
}

function storePreviewApplyOrientation(GdImage $image, int $orientation): GdImage
{
    switch ($orientation) {
        case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
        case 3: $image = storePreviewRotate($image, 180); break;
        case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
        case 5: imageflip($image, IMG_FLIP_HORIZONTAL); $image = storePreviewRotate($image, -90); break;
        case 6: $image = storePreviewRotate($image, -90); break;
        case 7: imageflip($image, IMG_FLIP_HORIZONTAL); $image = storePreviewRotate($image, 90); break;
        case 8: $image = storePreviewRotate($image, 90); break;
    }
    return $image;
}

function storePreviewResize(GdImage $image, int $maxSize): GdImage
{
    $width = imagesx($image);
    $height = imagesy($image);
    $longestSide = max($width, $height);
    if ($longestSide <= $maxSize) {
        return $image;
    }
    $scale = $maxSize / $longestSide;
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    $resized = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$resized instanceof GdImage) {
        throw new RuntimeException('No se pudo crear la vista previa.');
    }
    imagefill($resized, 0, 0, imagecolorallocate($resized, 0, 0, 0));
    if (!imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
        imagedestroy($resized);
        throw new RuntimeException('No se pudo redimensionar la vista previa.');
    }
    imagedestroy($image);
    return $resized;
}

function storePreviewCreateWatermark(int $targetWidth): GdImage
{
    $base = imagecreatetruecolor(180, 34);
    if (!$base instanceof GdImage) {
        throw new RuntimeException('No se pudo crear la marca de agua.');
    }
    imagealphablending($base, false);
    imagesavealpha($base, true);
    imagefill($base, 0, 0, imagecolorallocatealpha($base, 0, 0, 0, 127));
    imagealphablending($base, true);
    $white = imagecolorallocatealpha($base, 255, 255, 255, 52);
    for ($offset = 0; $offset < 2; $offset++) {
        imagerectangle($base, 3 + $offset, 3 + $offset, 29 - $offset, 29 - $offset, $white);
    }
    imageellipse($base, 16, 16, 13, 13, $white);
    imagefilledellipse($base, 24, 9, 4, 4, $white);
    imagestring($base, 5, 37, 9, 'aquellas_lunas', $white);

    $width = max(90, min(300, $targetWidth));
    $height = max(17, (int) round(34 * ($width / 180)));
    $scaled = imagecreatetruecolor($width, $height);
    if (!$scaled instanceof GdImage) {
        imagedestroy($base);
        throw new RuntimeException('No se pudo escalar la marca de agua.');
    }
    imagealphablending($scaled, false);
    imagesavealpha($scaled, true);
    imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
    imagecopyresampled($scaled, $base, 0, 0, 0, 0, $width, $height, 180, 34);
    imagedestroy($base);
    $rotated = imagerotate($scaled, 18, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
    imagedestroy($scaled);
    if (!$rotated instanceof GdImage) {
        throw new RuntimeException('No se pudo rotar la marca de agua.');
    }
    imagesavealpha($rotated, true);
    return $rotated;
}

function storePreviewApplyStoreWatermark(GdImage $image): void
{
    $width = imagesx($image);
    $height = imagesy($image);
    $watermark = storePreviewCreateWatermark((int) round($width * 0.42));
    $markWidth = imagesx($watermark);
    $markHeight = imagesy($watermark);
    $positions = [
        [0.04, 0.08], [0.58, 0.18], [0.28, 0.43], [0.02, 0.70], [0.60, 0.78],
    ];
    imagealphablending($image, true);
    foreach ($positions as [$relativeX, $relativeY]) {
        $x = max(0, min($width - $markWidth, (int) round($width * $relativeX)));
        $y = max(0, min($height - $markHeight, (int) round($height * $relativeY)));
        imagecopy($image, $watermark, $x, $y, 0, 0, $markWidth, $markHeight);
    }
    imagedestroy($watermark);
}

function storePreviewIsValidJpeg(string $path): bool
{
    if (!is_file($path) || !is_readable($path) || is_link($path)) {
        return false;
    }
    $image = @getimagesize($path);
    return is_array($image) && ($image[2] ?? null) === IMAGETYPE_JPEG;
}

function storePreviewCreateImage(string $originalPath, int $maxSize, bool $watermark): GdImage
{
    $source = @imagecreatefromjpeg($originalPath);
    if (!$source instanceof GdImage) {
        throw new RuntimeException('No se pudo abrir el JPEG original.');
    }
    try {
        $source = storePreviewApplyOrientation($source, storePreviewExifOrientation($originalPath));
        $source = storePreviewResize($source, $maxSize);
        if ($watermark) {
            storePreviewApplyStoreWatermark($source);
        }
        return $source;
    } catch (Throwable $exception) {
        imagedestroy($source);
        throw $exception;
    }
}

function storePreviewEnsureVariantDirectory(string $root, string $variant, bool $dryRun): string
{
    $path = $root . DIRECTORY_SEPARATOR . $variant;
    if (is_link($path)) {
        throw new RuntimeException('La carpeta de vistas previas no es válida.');
    }
    if (!is_dir($path) && !$dryRun && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('No se pudo crear la carpeta de vistas previas.');
    }
    if (is_dir($path) && (!is_readable($path) || (!$dryRun && !is_writable($path)))) {
        throw new RuntimeException('La carpeta de vistas previas no está disponible.');
    }
    return $path;
}

function generatePendingStorePreviews(
    PDO $connection,
    array $photos,
    string $originalsPath,
    string $previewsPath,
    array $variants,
    bool $dryRun = false,
    bool $force = false,
    ?callable $reportError = null
): array {
    $summary = ['pendientes' => count($photos), 'generados' => 0, 'existentes' => 0, 'omitidos' => 0, 'errores' => 0];
    $variantDirectories = [];
    $updateStatements = [];
    foreach ($variants as $variant => $settings) {
        $column = $settings['column'] ?? '';
        if (!in_array($column, ['archivo_preview_tienda', 'archivo_preview_contenido'], true)) {
            throw new RuntimeException('La variante de vista previa no es válida.');
        }
        $variantDirectories[$variant] = storePreviewEnsureVariantDirectory($previewsPath, $variant, $dryRun);
        $updateStatements[$variant] = $connection->prepare("UPDATE fotos SET {$column} = :archivo_preview WHERE id = :id");
    }

    foreach ($photos as $photo) {
        try {
            $photoId = strtolower(trim((string) ($photo['foto_id'] ?? '')));
            if (preg_match('/^[a-f0-9]{64}$/', $photoId) !== 1 || !isset($photo['id'])) {
                throw new RuntimeException('El registro de la foto no es válido.');
            }
            $originalPath = storePreviewResolveOriginalPath($originalsPath, (string) ($photo['archivo_original'] ?? ''));
            $actualPhotoId = hash_file('sha256', $originalPath);
            if (!is_string($actualPhotoId) || !hash_equals($photoId, $actualPhotoId)) {
                throw new RuntimeException('La huella del original no coincide.');
            }
        } catch (Throwable $exception) {
            $summary['errores']++;
            if ($reportError !== null) {
                $reportError((string) ($photo['nombre_archivo'] ?? 'archivo'), 'original');
            }
            continue;
        }

        foreach ($variants as $variant => $settings) {
            $temporaryPath = null;
            $previewImage = null;
            try {
                $column = $settings['column'];
                $relativePath = $variant . '/' . $photoId . '.jpg';
                $previewPath = $variantDirectories[$variant] . DIRECTORY_SEPARATOR . $photoId . '.jpg';
                $storedPath = trim((string) ($photo[$column] ?? ''));
                $alreadyAssociated = $storedPath === $relativePath;
                if (is_link($previewPath)) {
                    throw new RuntimeException('La ruta de la vista previa no es válida.');
                }
                if (!$force && storePreviewIsValidJpeg($previewPath)) {
                    if (!$dryRun && !$alreadyAssociated) {
                        $updateStatements[$variant]->execute(['archivo_preview' => $relativePath, 'id' => $photo['id']]);
                    }
                    $summary['existentes']++;
                    continue;
                }

                $previewImage = storePreviewCreateImage($originalPath, $settings['max_size'], $settings['watermark']);
                if ($dryRun) {
                    imagedestroy($previewImage);
                    $previewImage = null;
                    $summary['generados']++;
                    continue;
                }
                $temporaryPath = $variantDirectories[$variant] . DIRECTORY_SEPARATOR . '.' . $photoId . '.' . bin2hex(random_bytes(8)) . '.tmp';
                if (!imagejpeg($previewImage, $temporaryPath, $settings['quality'])) {
                    throw new RuntimeException('No se pudo escribir la vista previa.');
                }
                imagedestroy($previewImage);
                $previewImage = null;
                if (!rename($temporaryPath, $previewPath)) {
                    throw new RuntimeException('No se pudo publicar la vista previa.');
                }
                $temporaryPath = null;
                @chmod($previewPath, 0664);
                $updateStatements[$variant]->execute(['archivo_preview' => $relativePath, 'id' => $photo['id']]);
                $summary['generados']++;
            } catch (Throwable $exception) {
                if ($previewImage instanceof GdImage) {
                    imagedestroy($previewImage);
                }
                if (is_string($temporaryPath) && is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
                $summary['errores']++;
                if ($reportError !== null) {
                    $reportError((string) ($photo['nombre_archivo'] ?? 'archivo'), $variant);
                }
            }
        }
    }
    return $summary;
}
