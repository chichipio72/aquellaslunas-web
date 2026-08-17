<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';
require_once dirname(__DIR__, 2) . '/includes/web-push-support-id.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function webPushSupportIdColumnExists(PDO $connection): bool
{
    $statement = $connection->query(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() "
        . "AND table_name = 'web_push_device_config' AND column_name = 'support_id'"
    );
    return $statement->fetchColumn() !== false;
}

function webPushSupportIdUniqueIndexExists(PDO $connection): bool
{
    $statement = $connection->query(
        "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() "
        . "AND table_name = 'web_push_device_config' AND index_name = 'uk_web_push_device_support_id' "
        . 'AND non_unique = 0'
    );
    return $statement->fetchColumn() !== false;
}

function runWebPushSupportIdMigration(PDO $connection): void
{
    if (!webPushSupportIdColumnExists($connection)) {
        $connection->exec(
            'ALTER TABLE web_push_device_config ADD COLUMN support_id VARCHAR(11) '
            . 'CHARACTER SET ascii COLLATE ascii_bin NULL AFTER subscription_id'
        );
    }
    $rows = $connection->query(
        "SELECT subscription_id FROM web_push_device_config WHERE support_id IS NULL OR support_id = ''"
    )->fetchAll(PDO::FETCH_COLUMN);
    $update = $connection->prepare(
        'UPDATE web_push_device_config SET support_id = :support_id '
        . "WHERE subscription_id = :subscription_id AND (support_id IS NULL OR support_id = '')"
    );
    foreach ($rows as $subscriptionId) {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            try {
                $update->execute([
                    'support_id' => astronomyPushAllocateSupportId($connection),
                    'subscription_id' => (int) $subscriptionId,
                ]);
                break;
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() !== '23000' || $attempt === 19) throw $exception;
            }
        }
    }
    if (!webPushSupportIdUniqueIndexExists($connection)) {
        $connection->exec(
            'ALTER TABLE web_push_device_config ADD UNIQUE KEY uk_web_push_device_support_id (support_id)'
        );
    }
    $connection->exec(
        'ALTER TABLE web_push_device_config MODIFY support_id VARCHAR(11) '
        . 'CHARACTER SET ascii COLLATE ascii_bin NOT NULL'
    );
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runWebPushSupportIdMigration(getWebDatabaseConnection());
    echo "Identificadores de soporte Web Push disponibles.\n";
}
