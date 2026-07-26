<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script sólo puede ejecutarse desde consola.\n");
    exit(2);
}

require_once __DIR__ . '/../includes/store-database.php';

try {
    $statement = getStoreDatabaseConnection()->query('SELECT 1 AS ok');
    $result = $statement->fetch();
    if (!is_array($result) || (int) ($result['ok'] ?? 0) !== 1) {
        throw new RuntimeException('La verificación no devolvió el resultado esperado.');
    }
    fwrite(STDOUT, "store database connection: ok\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "store database connection: error\n");
    exit(1);
}
