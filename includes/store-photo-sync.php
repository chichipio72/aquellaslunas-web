<?php

function storePhotoSupportsFile(SplFileInfo $file): bool
{
    return $file->isFile()
        && !$file->isLink()
        && in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true);
}

function storePhotoImageDetails(string $path, string $extension): array
{
    $extension = strtolower($extension);
    $image = @getimagesize($path);
    $extensionsByType = [
        IMAGETYPE_JPEG => ['jpg', 'jpeg'],
        IMAGETYPE_PNG => ['png'],
        IMAGETYPE_WEBP => ['webp'],
    ];
    $imageType = is_array($image) ? ($image[2] ?? null) : null;
    if (!is_int($imageType) || !isset($extensionsByType[$imageType]) || !in_array($extension, $extensionsByType[$imageType], true)) {
        throw new RuntimeException('El contenido de la imagen no coincide con un formato permitido.');
    }
    return $image;
}

function storePhotoExifValue(array $exif, array $keys): mixed
{
    foreach ($keys as $key) {
        foreach ($exif as $section) {
            if (is_array($section) && array_key_exists($key, $section)) {
                return $section[$key];
            }
        }
    }
    return null;
}

function storePhotoReadableExifValue(mixed $value): string|int|float|null
{
    if (is_int($value) || is_float($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if ($value === '' || !mb_check_encoding($value, 'UTF-8')) {
        return null;
    }
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return is_string($value) && $value !== '' ? mb_substr($value, 0, 1000, 'UTF-8') : null;
}

function storePhotoCaptureDate(array $exif): ?string
{
    $rawValue = storePhotoExifValue($exif, ['DateTimeOriginal', 'DateTimeDigitized', 'DateTime']);
    if (!is_string($rawValue)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y:m:d H:i:s', trim($rawValue));
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }
    return $date->format('Y-m-d H:i:s');
}

function storePhotoBuildRecord(SplFileInfo $file, string $price): array
{
    $path = $file->getPathname();
    $filename = $file->getFilename();
    if (!mb_check_encoding($filename, 'UTF-8')) {
        throw new RuntimeException('El nombre del archivo no es UTF-8 válido.');
    }

    $image = storePhotoImageDetails($path, $file->getExtension());
    $photoId = hash_file('sha256', $path);
    if (!is_string($photoId) || preg_match('/^[a-f0-9]{64}$/', $photoId) !== 1) {
        throw new RuntimeException('No se pudo calcular la huella del archivo.');
    }

    $exif = [];
    if (($image[2] ?? null) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $readExif = @exif_read_data($path, null, true, false);
        $exif = is_array($readExif) ? $readExif : [];
    }
    $metadata = [
        'mime_type' => $image['mime'] ?? 'image/jpeg',
        'tamano_bytes' => $file->getSize(),
        'bits' => isset($image['bits']) ? (int) $image['bits'] : null,
        'canales' => isset($image['channels']) ? (int) $image['channels'] : null,
    ];
    $readableExifKeys = [
        'Make', 'Model', 'Software', 'Orientation', 'ExposureTime', 'FNumber',
        'ISOSpeedRatings', 'FocalLength', 'LensModel', 'DateTimeOriginal',
        'DateTimeDigitized', 'DateTime', 'GPSLatitudeRef', 'GPSLongitudeRef',
    ];
    $readableExif = [];
    foreach ($readableExifKeys as $key) {
        $value = storePhotoReadableExifValue(storePhotoExifValue($exif, [$key]));
        if ($value !== null) {
            $readableExif[$key] = $value;
        }
    }
    if ($readableExif !== []) {
        $metadata['exif'] = $readableExif;
    }

    $make = storePhotoReadableExifValue(storePhotoExifValue($exif, ['Make']));
    $model = storePhotoReadableExifValue(storePhotoExifValue($exif, ['Model']));
    $camera = trim(implode(' ', array_filter([$make, $model], static fn ($value): bool => is_string($value) && $value !== '')));
    $lens = storePhotoReadableExifValue(storePhotoExifValue($exif, ['LensModel']));

    return [
        'foto_id' => $photoId,
        'nombre_archivo' => $filename,
        'archivo_original' => $filename,
        'ancho_px' => (int) $image[0],
        'alto_px' => (int) $image[1],
        'fecha_captura' => storePhotoCaptureDate($exif),
        'camara' => $camera !== '' ? $camera : null,
        'lente' => is_string($lens) && $lens !== '' ? $lens : null,
        'metadatos_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'precio' => $price,
    ];
}

function synchronizeStorePhotos(
    PDO $connection,
    string $originalsPath,
    string $initialPrice,
    bool $dryRun = false,
    ?callable $reportError = null
): array {
    $summary = ['encontrados' => 0, 'nuevos' => 0, 'existentes' => 0, 'omitidos' => 0, 'errores' => 0];
    $seenPhotoIds = [];
    $existsByPhotoIdStatement = $connection->prepare('SELECT id FROM fotos WHERE foto_id = :foto_id LIMIT 1');
    $existsByOriginalStatement = $connection->prepare('SELECT id FROM fotos WHERE archivo_original = :archivo_original LIMIT 1');
    $insertStatement = $connection->prepare(
        'INSERT INTO fotos '
        . '(foto_id, nombre_archivo, archivo_original, ancho_px, alto_px, fecha_captura, camara, lente, metadatos_json, precio, disponible) '
        . 'VALUES '
        . '(:foto_id, :nombre_archivo, :archivo_original, :ancho_px, :alto_px, :fecha_captura, :camara, :lente, :metadatos_json, :precio, 1)'
    );

    foreach (new FilesystemIterator($originalsPath, FilesystemIterator::SKIP_DOTS) as $file) {
        $summary['encontrados']++;
        if (!storePhotoSupportsFile($file)) {
            $summary['omitidos']++;
            continue;
        }

        try {
            $record = storePhotoBuildRecord($file, $initialPrice);
            if (isset($seenPhotoIds[$record['foto_id']])) {
                $summary['existentes']++;
                continue;
            }
            $seenPhotoIds[$record['foto_id']] = true;
            $existsByPhotoIdStatement->execute(['foto_id' => $record['foto_id']]);
            if ($existsByPhotoIdStatement->fetchColumn() !== false) {
                $summary['existentes']++;
                continue;
            }
            $existsByOriginalStatement->execute(['archivo_original' => $record['archivo_original']]);
            if ($existsByOriginalStatement->fetchColumn() !== false) {
                $summary['existentes']++;
                continue;
            }
            if (!$dryRun) {
                $insertStatement->execute($record);
            }
            $summary['nuevos']++;
        } catch (Throwable $exception) {
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $existsByPhotoIdStatement->execute(['foto_id' => $record['foto_id'] ?? '']);
                $existsByOriginalStatement->execute(['archivo_original' => $record['archivo_original'] ?? '']);
                if ($existsByPhotoIdStatement->fetchColumn() !== false || $existsByOriginalStatement->fetchColumn() !== false) {
                    $summary['existentes']++;
                    continue;
                }
            }
            $summary['errores']++;
            if ($reportError !== null) {
                $reportError(
                    $file->getFilename()
                    . ' | '
                    . get_class($exception)
                    . ' | '
                    . $exception->getMessage()
                );
            }
        }
    }

    return $summary;
}
