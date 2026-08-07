<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @return array<string,array<int,string>> */
function webPushAstronomyIndexes(PDO $connection, string $table): array
{
    $statement = $connection->query('SHOW INDEX FROM `' . $table . '`');
    $indexes = [];
    foreach ($statement as $row) {
        $indexes[(string) $row['Key_name']][(int) $row['Seq_in_index']] = (string) $row['Column_name'];
    }
    foreach ($indexes as &$columns) {
        ksort($columns);
        $columns = array_values($columns);
    }
    unset($columns);
    return $indexes;
}

function webPushAstronomyColumnExists(PDO $connection, string $table, string $column): bool
{
    $statement = $connection->prepare(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() '
        . 'AND table_name = :table_name AND column_name = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    return $statement->fetchColumn() !== false;
}

function runWebPushAstronomyConfigMigration(PDO $connection): void
{
    $sql = file_get_contents(__DIR__ . '/web-push-astronomy-config.sql');
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('No se pudo leer la migración de configuración Web Push.');
    }
    foreach (preg_split('/;\s*(?:\R|$)/', trim($sql)) ?: [] as $statement) {
        if (trim($statement) !== '') $connection->exec($statement);
    }

    if (!webPushAstronomyColumnExists($connection, 'web_push_notification_log', 'event_key')) {
        $connection->exec(
            "ALTER TABLE web_push_notification_log ADD COLUMN event_key VARCHAR(191) "
            . "CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'moonrise' AFTER notification_type"
        );
    }
    if (!webPushAstronomyColumnExists($connection, 'web_push_notification_log', 'decision_reason')) {
        $connection->exec(
            'ALTER TABLE web_push_notification_log ADD COLUMN decision_reason VARCHAR(50) NULL AFTER status'
        );
    }
    $connection->exec("UPDATE web_push_notification_log SET event_key = 'moonrise' WHERE event_key IS NULL OR event_key = ''");

    $wantedUnique = ['subscription_id', 'notification_type', 'event_key', 'event_time_utc'];
    $indexes = webPushAstronomyIndexes($connection, 'web_push_notification_log');
    foreach ($indexes as $name => $columns) {
        if ($name !== 'PRIMARY' && $columns === ['subscription_id', 'notification_type', 'event_time_utc']) {
            $connection->exec('ALTER TABLE web_push_notification_log DROP INDEX `' . str_replace('`', '``', $name) . '`');
        }
    }
    $indexes = webPushAstronomyIndexes($connection, 'web_push_notification_log');
    $hasWantedUnique = false;
    foreach ($indexes as $name => $columns) {
        if ($columns !== $wantedUnique) continue;
        $check = $connection->prepare(
            'SELECT non_unique FROM information_schema.statistics WHERE table_schema = DATABASE() '
            . 'AND table_name = \'web_push_notification_log\' AND index_name = :index_name LIMIT 1'
        );
        $check->execute(['index_name' => $name]);
        if ((int) $check->fetchColumn() === 0) $hasWantedUnique = true;
    }
    if (!$hasWantedUnique) {
        $connection->exec(
            'ALTER TABLE web_push_notification_log ADD UNIQUE KEY uk_subscription_event '
            . '(subscription_id, notification_type, event_key, event_time_utc)'
        );
    }
    $indexes = webPushAstronomyIndexes($connection, 'web_push_notification_log');
    if (!in_array(['subscription_id', 'attempted_at'], $indexes, true)) {
        $connection->exec(
            'ALTER TABLE web_push_notification_log ADD KEY idx_web_push_log_subscription_attempted '
            . '(subscription_id, attempted_at)'
        );
    }

    $subscription = $connection->prepare('SELECT 1 FROM web_push_subscriptions WHERE id = 11');
    $subscription->execute();
    if ($subscription->fetchColumn() === false) return;
    $device = $connection->prepare(
        'INSERT IGNORE INTO web_push_device_config '
        . '(subscription_id, device_name, notifications_enabled, location_name, latitude, longitude, timezone, '
        . 'quiet_hours_enabled, quiet_start_local, quiet_end_local) '
        . 'VALUES (11, :device_name, 1, :location_name, :latitude, :longitude, :timezone, 0, NULL, NULL)'
    );
    $device->execute([
        'device_name' => 'Celular Android', 'location_name' => 'Vicente López',
        'latitude' => '-34.52', 'longitude' => '-58.48',
        'timezone' => 'America/Argentina/Buenos_Aires',
    ]);
    $preference = $connection->prepare(
        'INSERT IGNORE INTO web_push_notification_preferences '
        . '(subscription_id, notification_type, enabled, schedule_mode, lead_minutes, delivery_local_time, '
        . 'delivery_day_offset, quiet_policy, parameters_json) '
        . "VALUES (11, 'moonrise', 1, 'before_event', 15, NULL, 0, 'omit', NULL)"
    );
    $preference->execute();
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runWebPushAstronomyConfigMigration(getWebDatabaseConnection());
    echo "Configuración de notificaciones astronómicas disponible.\n";
}
