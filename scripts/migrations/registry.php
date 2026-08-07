<?php

declare(strict_types=1);

return [
    [
        'id' => '20260804_web_push_astronomy_config',
        'mode' => 'automatic',
        'blocks_tasks' => true,
        'checksum_file' => __DIR__ . '/web-push-astronomy-config.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-web-push-astronomy-config.php';
            runWebPushAstronomyConfigMigration($connection);
        },
    ],
    [
        'id' => '20260805_web_push_notification_types',
        'mode' => 'automatic',
        'blocks_tasks' => true,
        'checksum_file' => __DIR__ . '/web-push-notification-types.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-web-push-notification-types.php';
            runWebPushNotificationTypesMigration($connection);
        },
    ],
    [
        'id' => '20260805_astronomy_request_log',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/astronomy-request-log.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-astronomy-request-log.php';
            runAstronomyRequestLogMigration($connection);
        },
    ],
];
