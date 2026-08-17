<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function runWebPushAstronomyEventTypesMigration(PDO $connection): void
{
    $sql = file_get_contents(__DIR__ . '/web-push-astronomy-event-types.sql');
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('No se pudo leer la migración de eventos Web Push.');
    }
    foreach (preg_split('/;\s*(?:\R|$)/', trim($sql)) ?: [] as $statement) {
        if (trim($statement) !== '') $connection->exec($statement);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runWebPushAstronomyEventTypesMigration(getWebDatabaseConnection());
    echo "Tipos astronómicos Web Push disponibles.\n";
}

