<?php

require_once __DIR__ . '/../includes/api-config.php';
require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-photo-sync.php';

function storeSyncAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$temporaryPath = sys_get_temp_dir() . '/store-photo-sync-' . bin2hex(random_bytes(8));
$nestedPath = $temporaryPath . '/ignored-directory';
$createdFiles = [];

try {
    $previousPrice = getenv('STORE_INITIAL_PRICE');
    putenv('STORE_INITIAL_PRICE=10.5');
    storeSyncAssert(loadStoreInitialPrice('/missing-production-config.php') === '10.50', 'El precio no se normalizó.');
    putenv('STORE_INITIAL_PRICE=0');
    try {
        loadStoreInitialPrice('/missing-production-config.php');
        throw new RuntimeException('El precio cero fue aceptado.');
    } catch (RuntimeException $exception) {
        storeSyncAssert(
            $exception->getMessage() === 'La configuración del precio inicial de la tienda no está disponible.',
            'El precio inválido no produjo un error genérico.'
        );
    }
    is_string($previousPrice)
        ? putenv('STORE_INITIAL_PRICE=' . $previousPrice)
        : putenv('STORE_INITIAL_PRICE');

    $storeConfig = loadStoreConfig();
    $sourceFiles = array_values(array_filter(
        iterator_to_array(new FilesystemIterator($storeConfig['originals_path'], FilesystemIterator::SKIP_DOTS)),
        static fn (SplFileInfo $file): bool => storePhotoSupportsFile($file)
    ));
    storeSyncAssert(isset($sourceFiles[0]), 'Falta un JPG original para ejecutar la prueba.');
    storeSyncAssert(mkdir($temporaryPath, 0700), 'No se pudo crear la carpeta temporal.');
    storeSyncAssert(mkdir($nestedPath, 0700), 'No se pudo crear la subcarpeta temporal.');

    $createdFiles = [
        $temporaryPath . '/valid.JPG',
        $temporaryPath . '/same-content.jpeg',
        $temporaryPath . '/new.JPG',
        $temporaryPath . '/historical.JPG',
        $temporaryPath . '/invalid.JPEG',
        $temporaryPath . '/ignored.txt',
    ];
    storeSyncAssert(copy($sourceFiles[0]->getPathname(), $createdFiles[0]), 'No se pudo copiar el JPG de prueba.');
    storeSyncAssert(file_put_contents($createdFiles[0], random_bytes(16), FILE_APPEND) !== false, 'No se pudo hacer único el JPG de prueba.');
    storeSyncAssert(copy($createdFiles[0], $createdFiles[1]), 'No se pudo copiar el JPG duplicado.');
    storeSyncAssert(copy($createdFiles[0], $createdFiles[2]), 'No se pudo copiar el JPG nuevo.');
    storeSyncAssert(file_put_contents($createdFiles[2], random_bytes(16), FILE_APPEND) !== false, 'No se pudo hacer único el segundo JPG.');
    storeSyncAssert(copy($createdFiles[0], $createdFiles[3]), 'No se pudo copiar el JPG histórico.');
    storeSyncAssert(file_put_contents($createdFiles[3], random_bytes(16), FILE_APPEND) !== false, 'No se pudo hacer único el JPG histórico.');
    storeSyncAssert(file_put_contents($createdFiles[4], 'not a jpeg') !== false, 'No se pudo crear el JPG inválido.');
    storeSyncAssert(file_put_contents($createdFiles[5], 'ignored') !== false, 'No se pudo crear el archivo omitido.');

    $connection = getStoreDatabaseConnection();
    $before = (int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn();
    $orderPhotosBefore = (int) $connection->query('SELECT COUNT(*) FROM pedido_fotos')->fetchColumn();
    $reportedErrors = 0;
    $reportedErrorDetails = [];
    $connection->beginTransaction();
    $historicalPhotoId = hash('sha256', 'historical-id-' . $temporaryPath);
    $historicalInsert = $connection->prepare(
        'INSERT INTO fotos (foto_id, nombre_archivo, archivo_original, ancho_px, alto_px, precio, disponible) '
        . 'VALUES (:foto_id, :nombre_archivo, :archivo_original, 1, 1, 123.45, 1)'
    );
    $historicalInsert->execute([
        'foto_id' => $historicalPhotoId,
        'nombre_archivo' => 'historical.JPG',
        'archivo_original' => 'historical.JPG',
    ]);
    $afterHistoricalInsert = (int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn();
    $insertSummary = synchronizeStorePhotos(
        $connection,
        $temporaryPath,
        '123.45',
        false,
        static function (string $detail) use (&$reportedErrors, &$reportedErrorDetails): void {
            $reportedErrors++;
            $reportedErrorDetails[] = $detail;
        }
    );
    storeSyncAssert($insertSummary === [
        'encontrados' => 7,
        'nuevos' => 2,
        'existentes' => 2,
        'omitidos' => 2,
        'errores' => 1,
    ], 'La sincronización transaccional no produjo el resumen esperado: ' . json_encode($insertSummary));
    $afterSynchronization = (int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn();
    storeSyncAssert(
        $afterSynchronization === $afterHistoricalInsert + 2,
        'La sincronización no omitió el histórico o no insertó las fotografías nuevas: antes=' . $afterHistoricalInsert . ' después=' . $afterSynchronization
    );
    $historicalSaved = $connection->prepare('SELECT foto_id FROM fotos WHERE archivo_original = :archivo_original LIMIT 1');
    $historicalSaved->execute(['archivo_original' => 'historical.JPG']);
    storeSyncAssert(
        $historicalSaved->fetchColumn() === $historicalPhotoId,
        'La sincronización modificó el foto_id histórico.'
    );
    storeSyncAssert(
        count($reportedErrorDetails) === 1
            && str_contains($reportedErrorDetails[0], 'invalid.JPEG')
            && str_contains($reportedErrorDetails[0], 'RuntimeException')
            && str_contains($reportedErrorDetails[0], 'formato permitido'),
        'El callback dejó de recibir el detalle de la excepción.'
    );
    $connection->rollBack();

    $reportedErrors = 0;
    $summary = synchronizeStorePhotos(
        $connection,
        $temporaryPath,
        '123.45',
        true,
        static function () use (&$reportedErrors): void {
            $reportedErrors++;
        }
    );
    $after = (int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn();

    storeSyncAssert($summary['encontrados'] === 7, 'El total encontrado no coincide.');
    storeSyncAssert($summary['nuevos'] === 3 && $summary['existentes'] === 1, 'El contenido duplicado no se detectó por foto_id.');
    storeSyncAssert($summary['omitidos'] === 2, 'La carpeta o el archivo no compatible no se omitieron.');
    storeSyncAssert($summary['errores'] === 1 && $reportedErrors === 1, 'La falla aislada no se contabilizó.');
    storeSyncAssert($before === $after, 'El modo dry-run modificó la tabla fotos.');
    storeSyncAssert(
        (int) $connection->query('SELECT COUNT(*) FROM pedido_fotos')->fetchColumn() === $orderPhotosBefore,
        'La prueba modificó pedido_fotos.'
    );
    storeSyncAssert(storePhotoSupportsFile(new SplFileInfo($createdFiles[0])), 'La extensión JPG mayúscula no fue aceptada.');
    storeSyncAssert(storePhotoSupportsFile(new SplFileInfo($createdFiles[1])), 'La extensión JPEG no fue aceptada.');
    $pngPath = $temporaryPath . '/supported.png';
    $webpPath = $temporaryPath . '/supported.webp';
    file_put_contents($pngPath, 'placeholder');
    file_put_contents($webpPath, 'placeholder');
    $createdFiles[] = $pngPath;
    $createdFiles[] = $webpPath;
    storeSyncAssert(storePhotoSupportsFile(new SplFileInfo($pngPath)), 'La extensión PNG no fue aceptada.');
    storeSyncAssert(storePhotoSupportsFile(new SplFileInfo($webpPath)), 'La extensión WEBP no fue aceptada.');

    fwrite(STDOUT, "store photo sync tests: ok\n");
} finally {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    foreach ($createdFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($nestedPath)) {
        rmdir($nestedPath);
    }
    if (is_dir($temporaryPath)) {
        rmdir($temporaryPath);
    }
}
