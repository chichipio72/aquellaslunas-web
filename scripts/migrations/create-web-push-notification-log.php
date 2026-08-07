<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$connection = getWebDatabaseConnection();
$sql = file_get_contents(__DIR__ . '/web-push-notification-log.sql');
if (!is_string($sql) || trim($sql) === '') {
    throw new RuntimeException('No se pudo leer la migración del registro Web Push.');
}
$connection->exec($sql);

$index = $connection->prepare(
    'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() '
    . 'AND table_name = \'web_push_notification_log\' AND non_unique = 0 '
    . 'GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = '
    . '\'subscription_id,notification_type,event_time_utc\''
);
$index->execute();
if ($index->fetchColumn() === false) {
    $connection->exec(
        'ALTER TABLE web_push_notification_log ADD UNIQUE KEY uk_subscription_event '
        . '(subscription_id, notification_type, event_time_utc)'
    );
}

echo "Tabla web_push_notification_log disponible.\n";
