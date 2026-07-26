<?php

require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-gallery.php';

function storeGalleryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$connection = getStoreDatabaseConnection();
$photosBefore = (int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn();
$visibleBefore = count(loadAvailableStorePhotos($connection));

try {
    storeGalleryAssert($connection === getStoreDatabaseConnection(), 'La conexión PDO no se reutilizó.');
    $connection->beginTransaction();
    $insert = $connection->prepare(
        'INSERT INTO fotos '
        . '(foto_id, nombre_archivo, archivo_original, archivo_preview_tienda, archivo_preview_contenido, titulo, ancho_px, alto_px, precio, moneda, disponible, creado_en) '
        . 'VALUES '
        . '(:foto_id, :nombre_archivo, :archivo_original, :archivo_preview_tienda, :archivo_preview_contenido, :titulo, 1600, 900, :precio, :moneda, :disponible, :creado_en)'
    );
    $rows = [
        [str_repeat('1', 64), 'older.jpg', 'older-original.jpg', 'tienda/' . str_repeat('1', 64) . '.jpg', 'contenido/' . str_repeat('1', 64) . '.jpg', 'Luna antigua', '100.00', 'ARS', 1, '2037-01-01 00:00:00'],
        [str_repeat('2', 64), 'newer.jpg', 'newer-original.jpg', 'tienda/' . str_repeat('2', 64) . '.jpg', 'contenido/' . str_repeat('2', 64) . '.jpg', null, '1234.50', 'ARS', 1, '2037-02-01 00:00:00'],
        [str_repeat('3', 64), 'hidden.jpg', 'hidden-original.jpg', 'tienda/' . str_repeat('3', 64) . '.jpg', 'contenido/' . str_repeat('3', 64) . '.jpg', 'Oculta', '200.00', 'ARS', 0, '2037-03-01 00:00:00'],
        [str_repeat('4', 64), 'pending.jpg', 'pending-original.jpg', '', '', 'Pendiente', '300.00', 'ARS', 1, '2037-04-01 00:00:00'],
    ];
    foreach ($rows as $row) {
        $insert->execute([
            'foto_id' => $row[0],
            'nombre_archivo' => $row[1],
            'archivo_original' => $row[2],
            'archivo_preview_tienda' => $row[3],
            'archivo_preview_contenido' => $row[4],
            'titulo' => $row[5],
            'precio' => $row[6],
            'moneda' => $row[7],
            'disponible' => $row[8],
            'creado_en' => $row[9],
        ]);
    }

    $photos = loadAvailableStorePhotos($connection);
    storeGalleryAssert(count($photos) === $visibleBefore + 2, 'La consulta no filtró disponibilidad y preview.');
    storeGalleryAssert($photos[0]['archivo_preview_tienda'] === 'tienda/' . str_repeat('2', 64) . '.jpg', 'La consulta no ordenó por creado_en DESC.');
    storeGalleryAssert(
        array_intersect(['archivo_original', 'archivo_preview_contenido', 'nombre_archivo', 'metadatos_json'], array_keys($photos[0])) === [],
        'La consulta seleccionó campos internos.'
    );

    $presentation = storeGalleryPhotoPresentation($photos[0]);
    storeGalleryAssert(is_array($presentation), 'No se pudo presentar una foto válida.');
    storeGalleryAssert($presentation['title'] === null, 'Se inventó un título técnico.');
    storeGalleryAssert($presentation['alt'] === 'Fotografía de la Luna', 'El alt sin título no es genérico.');
    storeGalleryAssert($presentation['price_label'] === '1.234,50 ARS', 'Precio o moneda incorrectos.');
    storeGalleryAssert(
        storeGalleryPhotoPresentation(array_merge($photos[0], ['archivo_preview_tienda' => '../original.jpg'])) === null,
        'Se aceptó una ruta de preview insegura.'
    );

    $connection->rollBack();
    storeGalleryAssert((int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn() === $photosBefore, 'La prueba dejó filas en fotos.');
    fwrite(STDOUT, "store gallery tests: ok\n");
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
