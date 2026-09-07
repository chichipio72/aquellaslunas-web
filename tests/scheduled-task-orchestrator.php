<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/scheduled-tasks.php';

function orchestratorAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$connection = getWebDatabaseConnection();
$suffix = bin2hex(random_bytes(4));
$migrationTable = 'test_schema_migrations_' . $suffix;
$statusTable = 'test_task_status_' . $suffix;
$markerTable = 'test_migration_marker_' . $suffix;
$lockPath = sys_get_temp_dir() . '/aquellas-lunas-lock-test-' . $suffix . '.lock';
$now = new DateTimeImmutable('2099-08-04 12:00:00', new DateTimeZone('UTC'));

try {
    $missingChecksumReported = false;
    try {
        scheduledTaskMigrationChecksum([
            'id' => '20990803_test_missing_checksum',
            'checksum_file' => __DIR__ . '/fixtures/missing-migration.sql',
        ]);
    } catch (RuntimeException $exception) {
        $missingChecksumReported = $exception->getMessage() ===
            'No se pudo calcular la firma de la migración 20990803_test_missing_checksum '
            . '(scripts/migrations/missing-migration.sql).';
    }
    orchestratorAssert($missingChecksumReported,
        'El error de checksum no identifica la migración y el archivo que fallaron.');

    $migrationCalls = 0;
    $notificationCalls = 0;
    $registry = [[
        'id' => '20990804_test_automatic', 'mode' => 'automatic', 'blocks_tasks' => true,
        'run' => static function (PDO $db) use (&$migrationCalls, $markerTable): void {
            $migrationCalls++;
            $db->exec('CREATE TABLE ' . scheduledTaskTable($markerTable) . ' (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
        },
    ]];
    $first = scheduledTaskRun($connection, $registry,
        static function () use (&$notificationCalls): array {
            $notificationCalls++;
            return ['summary' => ['tests_processed' => 0, 'evaluated' => 0, 'sent' => 0, 'skipped' => 0, 'errors' => 0]];
        }, $now, $migrationTable, $statusTable);
    orchestratorAssert($first['migrations']['applied'] === ['20990804_test_automatic'] && $migrationCalls === 1,
        'La primera ejecución no creó o aplicó la migración pendiente.');
    orchestratorAssert($notificationCalls === 1, 'No se ejecutaron notificaciones después de migrar.');

    scheduledTaskRun($connection, $registry,
        static function () use (&$notificationCalls): array {
            $notificationCalls++;
            return ['summary' => []];
        }, $now->modify('+1 minute'), $migrationTable, $statusTable);
    orchestratorAssert($migrationCalls === 1 && $notificationCalls === 2,
        'La segunda ejecución repitió la migración o no continuó con las tareas.');

    $notificationsAfterFailure = 0;
    $failed = false;
    try {
        scheduledTaskRun($connection, [[
            'id' => '20990805_test_failure', 'mode' => 'automatic', 'blocks_tasks' => true,
            'run' => static function (): void { throw new RuntimeException('Fallo sintético de migración'); },
        ]], static function () use (&$notificationsAfterFailure): array {
            $notificationsAfterFailure++;
            return [];
        }, $now, $migrationTable, $statusTable);
    } catch (RuntimeException) { $failed = true; }
    orchestratorAssert($failed && $notificationsAfterFailure === 0,
        'Una migración fallida no detuvo el procesamiento posterior.');
    $failedStatus = $connection->query('SELECT last_status, last_error FROM ' . scheduledTaskTable($statusTable)
        . " WHERE task_name = 'scheduled_tasks'")->fetch();
    orchestratorAssert($failedStatus['last_status'] === 'failed' && $failedStatus['last_error'] !== null,
        'El fallo general no quedó persistido.');

    $notificationsAfterManual = 0;
    $manualFailed = false;
    try {
        scheduledTaskRun($connection, [[
            'id' => '20990806_test_manual', 'mode' => 'manual', 'blocks_tasks' => true,
        ]], static function () use (&$notificationsAfterManual): array {
            $notificationsAfterManual++;
            return [];
        }, $now, $migrationTable, $statusTable);
    } catch (RuntimeException) { $manualFailed = true; }
    orchestratorAssert($manualFailed && $notificationsAfterManual === 0,
        'La migración manual bloqueante fue ejecutada o permitió tareas dependientes.');

    $firstLock = scheduledTaskAcquireLock($lockPath);
    orchestratorAssert(is_resource($firstLock), 'No se obtuvo el primer lock.');
    $secondLock = scheduledTaskAcquireLock($lockPath);
    orchestratorAssert($secondLock === null, 'Una segunda ejecución obtuvo el mismo lock.');
    scheduledTaskRecordLockSkip($connection, $now, $statusTable);
    $lockStatus = $connection->query('SELECT last_status FROM ' . scheduledTaskTable($statusTable)
        . " WHERE task_name = 'scheduled_tasks_lock'")->fetchColumn();
    orchestratorAssert($lockStatus === 'skipped_lock', 'La omisión por lock no quedó registrada.');
    scheduledTaskReleaseLock($firstLock);

    scheduledTaskBootstrap($connection, $migrationTable, $statusTable);
    $connection->beginTransaction();
    try {
        $endpoint = 'https://push.invalid.test/orchestrator-' . $suffix;
        $insert = $connection->prepare(
            'INSERT INTO web_push_subscriptions (endpoint, endpoint_hash, p256dh, auth, user_agent, active) '
            . 'VALUES (:endpoint, :endpoint_hash, :p256dh, :auth, :user_agent, 1)'
        );
        $insert->execute(['endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint, true),
            'p256dh' => 'test-' . $suffix, 'auth' => 'test-' . $suffix, 'user_agent' => 'Orchestrator Test']);
        $subscriptionId = (int) $connection->lastInsertId();
        astronomyPushSaveDevice($connection, $subscriptionId, [
            'device_name' => 'Cron único', 'notifications_enabled' => '1', 'location_name' => 'UTC',
            'latitude' => '0', 'longitude' => '0', 'timezone' => 'UTC', 'quiet_hours_enabled' => '0',
            'quiet_start_local' => '', 'quiet_end_local' => '', 'moonrise_enabled' => '0',
        ]);
        $test = astronomyPushScheduleTest($connection, $subscriptionId, 1, false, $now);
        $setDue = $connection->prepare('UPDATE web_push_scheduled_tests SET scheduled_at_utc = :due WHERE id = :id');
        $setDue->execute(['due' => $now->format('Y-m-d H:i:s'), 'id' => $test['id']]);
        $sendCalls = 0;
        $cycle = scheduledTaskRun($connection, [],
            static function (PDO $db, DateTimeImmutable $clock) use ($subscriptionId, &$sendCalls): array {
                $tests = astronomyPushProcessScheduledTests($db, $clock, false, $subscriptionId,
                    static function () use (&$sendCalls): array {
                        $sendCalls++;
                        return ['success' => 1, 'failed' => 0, 'errors' => []];
                    });
                return ['summary' => ['tests_processed' => $tests['sent'], 'evaluated' => $tests['pending_due'],
                    'sent' => $tests['sent'], 'skipped' => 0, 'errors' => $tests['failed']]];
            }, $now, $migrationTable, $statusTable, false);
        orchestratorAssert($sendCalls === 1 && $cycle['notifications']['summary']['tests_processed'] === 1,
            'El cron único no procesó la prueba Web Push programada.');
    } finally {
        $connection->rollBack();
    }

    $state = scheduledTaskAdminState($connection, [], $migrationTable, $statusTable);
    orchestratorAssert(is_array($state['orchestrator']) && $state['last_migration']['migration_id'] === '20990804_test_automatic',
        'El estado administrativo no refleja la ejecución y última migración.');
} finally {
    if (isset($firstLock) && is_resource($firstLock)) scheduledTaskReleaseLock($firstLock);
    if (is_file($lockPath)) unlink($lockPath);
    $connection->exec('DROP TABLE IF EXISTS ' . scheduledTaskTable($markerTable));
    $connection->exec('DROP TABLE IF EXISTS ' . scheduledTaskTable($statusTable));
    $connection->exec('DROP TABLE IF EXISTS ' . scheduledTaskTable($migrationTable));
}

echo "OK scheduled task orchestrator\n";
