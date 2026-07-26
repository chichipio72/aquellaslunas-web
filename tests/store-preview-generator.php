<?php

require_once __DIR__ . '/../includes/api-config.php';
require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-preview-generator.php';

function storePreviewAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function removeStorePreviewTestDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
        if ($item->isDir() && !$item->isLink()) {
            removeStorePreviewTestDirectory($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

$temporaryRoot = sys_get_temp_dir() . '/store-preview-generator-' . bin2hex(random_bytes(8));
$originalsPath = $temporaryRoot . '/originals';
$previewsPath = $temporaryRoot . '/previews';
$originalPath = $originalsPath . '/fixture.jpg';
$connection = null;
$variants = [
    'tienda' => ['column' => 'archivo_preview_tienda', 'max_size' => 800, 'quality' => 72, 'watermark' => true],
    'contenido' => ['column' => 'archivo_preview_contenido', 'max_size' => 400, 'quality' => 72, 'watermark' => false],
];

try {
    storePreviewAssert(loadStorePreviewTiendaMaxSize() === 800, 'El tamaño de tienda no coincide.');
    storePreviewAssert(loadStorePreviewTiendaJpegQuality() === 72, 'La calidad de tienda no coincide.');
    storePreviewAssert(loadStorePreviewContenidoMaxSize() === 400, 'El tamaño de contenido no coincide.');
    storePreviewAssert(loadStorePreviewContenidoJpegQuality() === 72, 'La calidad de contenido no coincide.');
    $previousQuality = getenv('STORE_PREVIEW_TIENDA_JPEG_QUALITY');
    putenv('STORE_PREVIEW_TIENDA_JPEG_QUALITY=100');
    try {
        loadStorePreviewTiendaJpegQuality('/missing-production-config.php');
        throw new RuntimeException('La calidad inválida fue aceptada.');
    } catch (RuntimeException $exception) {
        storePreviewAssert($exception->getMessage() === 'La configuración de vistas previas de la tienda no está disponible.', 'El error de configuración no es genérico.');
    }
    is_string($previousQuality) ? putenv('STORE_PREVIEW_TIENDA_JPEG_QUALITY=' . $previousQuality) : putenv('STORE_PREVIEW_TIENDA_JPEG_QUALITY');

    storePreviewAssert(mkdir($temporaryRoot, 0700), 'No se pudo crear la carpeta temporal.');
    storePreviewAssert(mkdir($originalsPath, 0700), 'No se pudo crear la carpeta de originales.');
    storePreviewAssert(mkdir($previewsPath, 0700), 'No se pudo crear la carpeta de previews.');

    $source = imagecreatetruecolor(2000, 1000);
    storePreviewAssert($source instanceof GdImage, 'No se pudo crear la imagen de prueba.');
    imagefill($source, 0, 0, imagecolorallocate($source, 30, 60, 90));
    storePreviewAssert(imagejpeg($source, $originalPath, 90), 'No se pudo escribir el JPEG de prueba.');
    imagedestroy($source);
    storePreviewAssert(file_put_contents($originalPath, random_bytes(16), FILE_APPEND) !== false, 'No se pudo hacer único el JPEG.');
    $photoId = hash_file('sha256', $originalPath);
    storePreviewAssert(is_string($photoId), 'No se pudo calcular el foto_id.');

    $connection = getStoreDatabaseConnection();
    $photosBefore = (int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn();
    $ordersBefore = (int) $connection->query('SELECT COUNT(*) FROM pedido_fotos')->fetchColumn();
    $connection->beginTransaction();
    $insert = $connection->prepare('INSERT INTO fotos (foto_id, nombre_archivo, archivo_original, ancho_px, alto_px, precio, disponible) VALUES (:foto_id, :nombre_archivo, :archivo_original, 2000, 1000, 123.45, 1)');
    $insert->execute(['foto_id' => $photoId, 'nombre_archivo' => 'fixture.jpg', 'archivo_original' => 'fixture.jpg']);
    $photo = ['id' => (int) $connection->lastInsertId(), 'foto_id' => $photoId, 'nombre_archivo' => 'fixture.jpg', 'archivo_original' => 'fixture.jpg', 'archivo_preview_tienda' => null, 'archivo_preview_contenido' => null];

    $dry = generatePendingStorePreviews($connection, [$photo], $originalsPath, $previewsPath, $variants, true, false);
    storePreviewAssert($dry['generados'] === 2, 'Dry-run no detectó los dos derivados.');
    storePreviewAssert(!is_dir($previewsPath . '/tienda') && !is_dir($previewsPath . '/contenido'), 'Dry-run creó carpetas.');
    $drySaved = $connection->query('SELECT archivo_preview_tienda, archivo_preview_contenido FROM fotos WHERE id = ' . $photo['id'])->fetch();
    storePreviewAssert($drySaved['archivo_preview_tienda'] === null && $drySaved['archivo_preview_contenido'] === null, 'Dry-run actualizó la base.');

    $generated = generatePendingStorePreviews($connection, [$photo], $originalsPath, $previewsPath, $variants, false, false);
    $storeRelative = 'tienda/' . $photoId . '.jpg';
    $contentRelative = 'contenido/' . $photoId . '.jpg';
    $storePath = $previewsPath . '/' . $storeRelative;
    $contentPath = $previewsPath . '/' . $contentRelative;
    storePreviewAssert($generated['generados'] === 2, 'No se generaron ambos derivados.');
    storePreviewAssert(getimagesize($storePath)[0] === 800 && getimagesize($storePath)[1] === 400, 'La preview de tienda no mide 800x400.');
    storePreviewAssert(getimagesize($contentPath)[0] === 400 && getimagesize($contentPath)[1] === 200, 'La preview de contenido no mide 400x200.');
    $saved = $connection->query('SELECT archivo_preview_tienda, archivo_preview_contenido FROM fotos WHERE id = ' . $photo['id'])->fetch();
    storePreviewAssert($saved['archivo_preview_tienda'] === $storeRelative && $saved['archivo_preview_contenido'] === $contentRelative, 'La base no guardó ambas rutas derivadas de foto_id.');
    storePreviewAssert(!in_array($photo['id'], array_column(storePendingPreviewPhotos($connection), 'id'), false), 'Una foto completa sigue apareciendo como pendiente.');
    storePreviewAssert(in_array($photo['id'], array_column(storePendingPreviewPhotos($connection, true), 'id'), false), '--force no selecciona fotos completas.');

    $plain = storePreviewCreateImage($originalPath, 800, false);
    $plainPath = $temporaryRoot . '/plain.jpg';
    imagejpeg($plain, $plainPath, 72);
    imagedestroy($plain);
    storePreviewAssert(hash_file('sha256', $plainPath) !== hash_file('sha256', $storePath), 'La marca de agua no alteró la preview comercial.');

    $storeHash = hash_file('sha256', $storePath);
    $contentHash = hash_file('sha256', $contentPath);
    $connection->exec('UPDATE fotos SET archivo_preview_tienda = NULL, archivo_preview_contenido = NULL WHERE id = ' . $photo['id']);
    $existing = generatePendingStorePreviews($connection, [$photo], $originalsPath, $previewsPath, $variants, false, false);
    storePreviewAssert($existing['existentes'] === 2, 'No se reutilizaron ambos previews válidos.');
    storePreviewAssert(hash_file('sha256', $storePath) === $storeHash && hash_file('sha256', $contentPath) === $contentHash, 'Se sobrescribió un derivado válido sin --force.');

    $forced = generatePendingStorePreviews($connection, [$photo], $originalsPath, $previewsPath, $variants, false, true);
    storePreviewAssert($forced['generados'] === 2, '--force no regeneró ambos derivados.');

    $badPhoto = $photo;
    $badPhoto['foto_id'] = str_repeat('0', 64);
    $mixed = generatePendingStorePreviews($connection, [$badPhoto, $photo], $originalsPath, $previewsPath, $variants, false, false);
    storePreviewAssert($mixed['errores'] === 1 && $mixed['existentes'] === 2, 'Una falla individual detuvo el lote.');

    $orientationImage = imagecreatetruecolor(10, 20);
    $orientationImage = storePreviewApplyOrientation($orientationImage, 6);
    storePreviewAssert(imagesx($orientationImage) === 20 && imagesy($orientationImage) === 10, 'La orientación EXIF 6 no rotó la imagen.');
    imagedestroy($orientationImage);
    $smallImage = storePreviewResize(imagecreatetruecolor(100, 50), 800);
    storePreviewAssert(imagesx($smallImage) === 100 && imagesy($smallImage) === 50, 'Se amplió una imagen pequeña.');
    imagedestroy($smallImage);

    $connection->rollBack();
    storePreviewAssert((int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn() === $photosBefore, 'La prueba dejó filas en fotos.');
    storePreviewAssert((int) $connection->query('SELECT COUNT(*) FROM pedido_fotos')->fetchColumn() === $ordersBefore, 'La prueba modificó pedido_fotos.');
    fwrite(STDOUT, "store preview generator tests: ok\n");
} finally {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    removeStorePreviewTestDirectory($temporaryRoot);
}
