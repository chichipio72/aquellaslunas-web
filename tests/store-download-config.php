<?php

require_once __DIR__ . '/../includes/api-config.php';

function storeDownloadConfigAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$names = ['STORE_DOWNLOAD_EXPIRY_HOURS', 'STORE_DOWNLOAD_MAX_COUNT'];
$previous = [];
foreach ($names as $name) {
    $previous[$name] = getenv($name);
}
$temporaryConfigPath = tempnam(sys_get_temp_dir(), 'store-download-config-');

try {
    storeDownloadConfigAssert(is_string($temporaryConfigPath), 'No se pudo crear la configuración temporal.');
    file_put_contents($temporaryConfigPath, "<?php\nreturn ['store_download_expiry_hours' => 48, 'store_download_max_count' => 3];\n");
    foreach ($names as $name) {
        putenv($name);
    }
    storeDownloadConfigAssert(loadStoreDownloadExpiryHours('/missing-store-download-config.php') === 72, 'El vencimiento predeterminado cambió.');
    storeDownloadConfigAssert(loadStoreDownloadMaxCount('/missing-store-download-config.php') === 5, 'El máximo predeterminado cambió.');
    storeDownloadConfigAssert(loadStoreDownloadExpiryHours($temporaryConfigPath) === 48, 'No se cargó el vencimiento externo.');
    storeDownloadConfigAssert(loadStoreDownloadMaxCount($temporaryConfigPath) === 3, 'No se cargó el máximo externo.');
    putenv('STORE_DOWNLOAD_EXPIRY_HOURS=72');
    putenv('STORE_DOWNLOAD_MAX_COUNT=5');
    storeDownloadConfigAssert(loadStoreDownloadExpiryHours($temporaryConfigPath) === 72, 'El entorno no tuvo prioridad para vencimiento.');
    storeDownloadConfigAssert(loadStoreDownloadMaxCount($temporaryConfigPath) === 5, 'El entorno no tuvo prioridad para máximo.');
    foreach ([
        ['STORE_DOWNLOAD_EXPIRY_HOURS', '0'],
        ['STORE_DOWNLOAD_EXPIRY_HOURS', '8761'],
        ['STORE_DOWNLOAD_MAX_COUNT', '0'],
        ['STORE_DOWNLOAD_MAX_COUNT', '101'],
    ] as [$name, $value]) {
        $valid = getenv($name);
        putenv($name . '=' . $value);
        try {
            $name === 'STORE_DOWNLOAD_EXPIRY_HOURS'
                ? loadStoreDownloadExpiryHours($temporaryConfigPath)
                : loadStoreDownloadMaxCount($temporaryConfigPath);
            throw new RuntimeException('Se aceptó una configuración de descarga inválida.');
        } catch (RuntimeException $exception) {
            storeDownloadConfigAssert($exception->getMessage() === 'La configuración de descargas de la tienda no está disponible.', 'El error filtró detalles.');
        }
        putenv($name . '=' . $valid);
    }
    fwrite(STDOUT, "store download config tests: ok\n");
} finally {
    foreach ($previous as $name => $value) {
        is_string($value) ? putenv($name . '=' . $value) : putenv($name);
    }
    if (is_string($temporaryConfigPath) && is_file($temporaryConfigPath)) {
        unlink($temporaryConfigPath);
    }
}
