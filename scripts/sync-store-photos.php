<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script sólo puede ejecutarse desde consola.\n");
    exit(2);
}

require_once __DIR__ . '/../includes/api-config.php';
require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-photo-sync.php';

$arguments = array_slice($argv, 1);
if (array_diff($arguments, ['--dry-run']) !== [] || count($arguments) !== count(array_unique($arguments))) {
    fwrite(STDERR, "Uso: php scripts/sync-store-photos.php [--dry-run]\n");
    exit(2);
}
$dryRun = in_array('--dry-run', $arguments, true);

try {
    $storeConfig = loadStoreConfig();
    $summary = synchronizeStorePhotos(
        getStoreDatabaseConnection(),
        $storeConfig['originals_path'],
        loadStoreInitialPrice(),
        $dryRun,
        static function (string $filename): void {
            $safeFilename = preg_replace('/[\x00-\x1F\x7F]/u', '?', $filename);
            fwrite(STDERR, "No se pudo procesar: " . ($safeFilename ?? 'archivo') . "\n");
        }
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo ejecutar la sincronización de fotos.\n");
    exit(1);
}

fwrite(STDOUT, $dryRun ? "Modo dry-run: no se realizaron inserciones.\n" : "Sincronización completada.\n");
fwrite(STDOUT, sprintf(
    "encontrados=%d nuevos=%d existentes=%d omitidos=%d errores=%d\n",
    $summary['encontrados'],
    $summary['nuevos'],
    $summary['existentes'],
    $summary['omitidos'],
    $summary['errores']
));
exit($summary['errores'] > 0 ? 1 : 0);
