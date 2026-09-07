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
    [
        'id' => '20260808_web_push_astronomy_event_types',
        'mode' => 'automatic',
        'blocks_tasks' => true,
        'checksum_file' => __DIR__ . '/web-push-astronomy-event-types.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-web-push-astronomy-event-types.php';
            runWebPushAstronomyEventTypesMigration($connection);
        },
    ],
    [
        'id' => '20260808_web_push_support_id',
        'mode' => 'automatic',
        'blocks_tasks' => true,
        'checksum_file' => __DIR__ . '/web-push-support-id.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-web-push-support-id.php';
            runWebPushSupportIdMigration($connection);
        },
    ],
    [
        'id' => '20260817_site_menu_configuration',
        'mode' => 'automatic',
        'blocks_tasks' => true,
        'checksum_file' => __DIR__ . '/site-menu-configuration.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-site-menu-configuration.php';
            runSiteMenuConfigurationMigration($connection);
        },
    ],
    [
        'id' => '20260819_eclipse_widget_template_storage',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/eclipse-widget-template-storage.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-eclipse-widget-template-storage.php';
            runEclipseWidgetTemplateStorageMigration($connection);
        },
    ],
    [
        'id' => '20260820_photography_editorial',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/photography-editorial.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-photography-editorial.php';
            runPhotographyEditorialMigration($connection);
        },
    ],
    [
        'id' => '20260820_photography_editorial_v1_seeds',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/photography-editorial-v1-seeds.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-photography-editorial-v1-seeds.php';
            runPhotographyEditorialV1SeedsMigration($connection);
        },
    ],
    [
        'id' => '20260821_photography_simulated_directional_twilight',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/photography-simulated-directional-twilight.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-photography-simulated-directional-twilight.php';
            runPhotographySimulatedDirectionalTwilightMigration($connection);
        },
    ],
    [
        'id' => '20260822_satellite_stations_menu',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/satellite-stations-menu.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/add-satellite-stations-menu.php';
            runSatelliteStationsMenuMigration($connection);
        },
    ],
    [
        'id' => '20260823_sources_credits_menu',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/sources-credits-menu.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/add-sources-credits-menu.php';
            runSourcesCreditsMenuMigration($connection);
        },
    ],
    [
        'id' => '20260906_lunar_scene_presets',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/lunar-scene-presets.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/create-lunar-scene-presets.php';
            runLunarScenePresetsMigration($connection);
        },
    ],
    [
        'id' => '20260906_publish_lunar_nodes',
        'mode' => 'automatic',
        'blocks_tasks' => false,
        'checksum_file' => __DIR__ . '/publish-lunar-nodes.sql',
        'run' => static function (PDO $connection): void {
            require_once __DIR__ . '/publish-lunar-nodes.php';
            runPublishLunarNodesMigration($connection);
        },
    ],
];
