<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function runAstronomyRequestLogMigration(PDO $connection): void
{
    $sql = file_get_contents(__DIR__ . '/astronomy-request-log.sql');
    if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('No se pudo leer la migración de trazabilidad astronómica.');
    $connection->exec($sql);
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runAstronomyRequestLogMigration(getWebDatabaseConnection());
    echo "Trazabilidad astronómica disponible.\n";
}

