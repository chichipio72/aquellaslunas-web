<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script sólo puede ejecutarse desde consola.\n");
    exit(2);
}

require_once __DIR__ . '/../includes/api-config.php';
require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-preview-generator.php';

$arguments = array_slice($argv, 1);
$allowedArguments = ['--dry-run', '--force'];
if (array_diff($arguments, $allowedArguments) !== [] || count($arguments) !== count(array_unique($arguments))) {
    fwrite(STDERR, "Uso: php scripts/generate-store-previews.php [--dry-run] [--force]\n");
    exit(2);
}
$dryRun = in_array('--dry-run', $arguments, true);
$force = in_array('--force', $arguments, true);

try {
    $storeConfig = loadStoreConfig();
    $connection = getStoreDatabaseConnection();
    $variants = [
        'tienda' => [
            'column' => 'archivo_preview_tienda',
            'max_size' => loadStorePreviewTiendaMaxSize(),
            'quality' => loadStorePreviewTiendaJpegQuality(),
            'watermark' => true,
        ],
        'contenido' => [
            'column' => 'archivo_preview_contenido',
            'max_size' => loadStorePreviewContenidoMaxSize(),
            'quality' => loadStorePreviewContenidoJpegQuality(),
            'watermark' => false,
        ],
    ];
    $summary = generatePendingStorePreviews(
        $connection,
        storePendingPreviewPhotos($connection, $force),
        $storeConfig['originals_path'],
        $storeConfig['previews_path'],
        $variants,
        $dryRun,
        $force,
        static function (string $filename, string $variant): void {
            $safeFilename = preg_replace('/[\x00-\x1F\x7F]/u', '?', $filename);
            fwrite(STDERR, "No se pudo generar la preview {$variant} para: " . ($safeFilename ?? 'archivo') . "\n");
        }
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo ejecutar la generación de vistas previas.\n");
    exit(1);
}

fwrite(STDOUT, $dryRun ? "Modo dry-run: no se crearon archivos ni se actualizó la base.\n" : "Generación de vistas previas completada.\n");
fwrite(STDOUT, sprintf(
    "pendientes=%d generados=%d existentes=%d omitidos=%d errores=%d\n",
    $summary['pendientes'],
    $summary['generados'],
    $summary['existentes'],
    $summary['omitidos'],
    $summary['errores']
));
exit($summary['errores'] > 0 ? 1 : 0);
