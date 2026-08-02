<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$sql = file_get_contents(__DIR__ . '/web-push-subscriptions.sql');
if (!is_string($sql) || trim($sql) === '') {
    throw new RuntimeException('No se pudo leer la migración Web Push.');
}
getWebDatabaseConnection()->exec($sql);

echo "Tabla web_push_subscriptions disponible.\n";
