<?php

require_once __DIR__ . '/api-config.php';

function getWebDatabaseConnection(?string $productionConfigPath = null): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    try {
        $config = loadWebDatabaseConfig($productionConfigPath);
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['name']
        );
        $connection = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $exception) {
        $technicalCode = strtoupper((string) $exception->getCode());
        $technicalCode = preg_match('/^[A-Z0-9_-]{1,32}$/', $technicalCode) === 1
            ? $technicalCode
            : 'UNAVAILABLE';
        error_log(sprintf(
            'Astronomy database connection failed [type=%s code=%s].',
            get_debug_type($exception),
            $technicalCode
        ));
        throw new RuntimeException('No se pudo establecer la conexión con la base de datos astronómica.');
    }
    return $connection;
}
