<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function webPushNotificationTypeColumnExists(PDO $connection, string $table, string $column): bool
{
    $statement = $connection->prepare(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() '
        . 'AND table_name = :table_name AND column_name = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    return $statement->fetchColumn() !== false;
}

function runWebPushNotificationTypesMigration(PDO $connection): void
{
    $sql = file_get_contents(__DIR__ . '/web-push-notification-types.sql');
    if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('No se pudo leer la migración del catálogo Web Push.');
    foreach (preg_split('/;\s*(?:\R|$)/', trim($sql)) ?: [] as $statement) {
        if (trim($statement) !== '') $connection->exec($statement);
    }
    foreach ([
        'title_sent' => 'VARCHAR(180) NULL AFTER error_message',
        'body_sent' => 'VARCHAR(500) NULL AFTER title_sent',
        'target_url_sent' => 'VARCHAR(500) NULL AFTER body_sent',
    ] as $column => $definition) {
        if (!webPushNotificationTypeColumnExists($connection, 'web_push_notification_log', $column)) {
            $connection->exec('ALTER TABLE web_push_notification_log ADD COLUMN `' . $column . '` ' . $definition);
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runWebPushNotificationTypesMigration(getWebDatabaseConnection());
    echo "Catálogo de tipos de notificación disponible.\n";
}
