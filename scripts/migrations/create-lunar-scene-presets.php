<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function runLunarScenePresetsMigration(PDO $connection): void
{
    $sql = file_get_contents(__DIR__ . '/lunar-scene-presets.sql');
    if (!is_string($sql)) throw new RuntimeException('No se pudo leer la migración de presets lunares.');
    $connection->exec($sql);
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runLunarScenePresetsMigration(getWebDatabaseConnection());
    echo "Presets de escenas lunares actualizados.\n";
}
