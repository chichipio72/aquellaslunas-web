<?php

declare(strict_types=1);

require_once __DIR__ . '/web-push-astronomy.php';

const SCHEDULED_TASK_ORCHESTRATOR = 'scheduled_tasks';
const SCHEDULED_TASK_LOCK_STATUS = 'scheduled_tasks_lock';

function scheduledTaskTable(string $name): string
{
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $name) !== 1) {
        throw new InvalidArgumentException('El nombre interno de tabla no es válido.');
    }
    return '`' . $name . '`';
}

function scheduledTaskBootstrap(PDO $connection, string $migrationTable = 'application_schema_migrations',
    string $statusTable = 'scheduled_task_status'): void
{
    $migrations = scheduledTaskTable($migrationTable);
    $status = scheduledTaskTable($statusTable);
    $connection->exec(
        'CREATE TABLE IF NOT EXISTS ' . $migrations . ' ('
        . 'migration_id VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
        . 'applied_at DATETIME NOT NULL, execution_ms INT UNSIGNED NULL, checksum VARCHAR(64) NULL, '
        . 'PRIMARY KEY (migration_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $connection->exec(
        'CREATE TABLE IF NOT EXISTS ' . $status . ' ('
        . 'task_name VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
        . 'last_started_at DATETIME NULL, last_finished_at DATETIME NULL, last_success_at DATETIME NULL, '
        . 'last_status VARCHAR(30) NOT NULL, last_error VARCHAR(500) NULL, '
        . 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, '
        . 'PRIMARY KEY (task_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return array<string,array<string,mixed>> */
function scheduledTaskValidateRegistry(array $registry): array
{
    $validated = [];
    foreach ($registry as $entry) {
        if (!is_array($entry)) throw new InvalidArgumentException('El registro de migraciones no es válido.');
        $id = (string) ($entry['id'] ?? '');
        $mode = (string) ($entry['mode'] ?? '');
        if (preg_match('/^[0-9]{8}_[a-z0-9_]{1,160}$/', $id) !== 1 || isset($validated[$id])) {
            throw new InvalidArgumentException('El identificador u orden de migración no es válido.');
        }
        if (!in_array($mode, ['automatic', 'manual'], true)) {
            throw new InvalidArgumentException('El modo de migración no es válido.');
        }
        if ($mode === 'automatic' && !is_callable($entry['run'] ?? null)) {
            throw new InvalidArgumentException('La migración automática no tiene ejecutor.');
        }
        $validated[$id] = array_replace($entry, [
            'id' => $id,
            'mode' => $mode,
            'blocks_tasks' => (bool) ($entry['blocks_tasks'] ?? true),
        ]);
    }
    return $validated;
}

function scheduledTaskMigrationChecksum(array $entry): ?string
{
    $file = $entry['checksum_file'] ?? null;
    if (!is_string($file) || $file === '') return null;
    $checksum = hash_file('sha256', $file);
    if (!is_string($checksum)) throw new RuntimeException('No se pudo calcular la firma de una migración.');
    return $checksum;
}

/** @return array{applied:list<string>,manual_pending:list<string>,automatic_pending:list<string>,blocks_tasks:bool} */
function scheduledTaskRunMigrations(PDO $connection, array $registry,
    string $migrationTable = 'application_schema_migrations'): array
{
    $table = scheduledTaskTable($migrationTable);
    $registry = scheduledTaskValidateRegistry($registry);
    $rows = $connection->query('SELECT migration_id, checksum FROM ' . $table . ' ORDER BY migration_id')->fetchAll();
    $recorded = [];
    foreach ($rows as $row) $recorded[(string) $row['migration_id']] = $row['checksum'];
    $result = ['applied' => [], 'manual_pending' => [], 'automatic_pending' => [], 'blocks_tasks' => false];
    foreach ($registry as $id => $entry) {
        $checksum = scheduledTaskMigrationChecksum($entry);
        if (array_key_exists($id, $recorded)) {
            if ($checksum !== null && $recorded[$id] !== null && !hash_equals((string) $recorded[$id], $checksum)) {
                throw new RuntimeException('La firma de una migración ya aplicada no coincide.');
            }
            continue;
        }
        if ($entry['mode'] === 'manual') {
            $result['manual_pending'][] = $id;
            if ($entry['blocks_tasks']) $result['blocks_tasks'] = true;
            continue;
        }
        $result['automatic_pending'][] = $id;
        $started = hrtime(true);
        ($entry['run'])($connection);
        $elapsedMs = max(0, (int) round((hrtime(true) - $started) / 1000000));
        $insert = $connection->prepare(
            'INSERT INTO ' . $table . ' (migration_id, applied_at, execution_ms, checksum) '
            . 'VALUES (:migration_id, UTC_TIMESTAMP(), :execution_ms, :checksum)'
        );
        $insert->execute(['migration_id' => $id, 'execution_ms' => $elapsedMs, 'checksum' => $checksum]);
        $result['applied'][] = $id;
    }
    return $result;
}

function scheduledTaskStatusStart(PDO $connection, DateTimeImmutable $nowUtc,
    string $statusTable = 'scheduled_task_status'): void
{
    $table = scheduledTaskTable($statusTable);
    $statement = $connection->prepare(
        'INSERT INTO ' . $table . ' (task_name, last_started_at, last_finished_at, last_status, last_error) '
        . "VALUES (:task_name, :started_at, NULL, 'running', NULL) ON DUPLICATE KEY UPDATE "
        . "last_started_at = VALUES(last_started_at), last_finished_at = NULL, last_status = 'running', last_error = NULL"
    );
    $statement->execute(['task_name' => SCHEDULED_TASK_ORCHESTRATOR,
        'started_at' => astronomyPushUtc($nowUtc)->format('Y-m-d H:i:s')]);
}

function scheduledTaskStatusFinish(PDO $connection, DateTimeImmutable $nowUtc, bool $success,
    ?string $error = null, string $statusTable = 'scheduled_task_status'): void
{
    $table = scheduledTaskTable($statusTable);
    $statement = $connection->prepare(
        'UPDATE ' . $table . ' SET last_finished_at = :finished_at, '
        . 'last_success_at = CASE WHEN :successful = 1 THEN :success_at ELSE last_success_at END, '
        . 'last_status = :status, last_error = :last_error WHERE task_name = :task_name'
    );
    $statement->execute([
        'finished_at' => astronomyPushUtc($nowUtc)->format('Y-m-d H:i:s'),
        'success_at' => astronomyPushUtc($nowUtc)->format('Y-m-d H:i:s'),
        'successful' => $success ? 1 : 0,
        'status' => $success ? 'success' : 'failed',
        'last_error' => $success ? null : astronomyWebPushSanitizeError($error),
        'task_name' => SCHEDULED_TASK_ORCHESTRATOR,
    ]);
}

function scheduledTaskRecordLockSkip(PDO $connection, DateTimeImmutable $nowUtc,
    string $statusTable = 'scheduled_task_status'): void
{
    scheduledTaskBootstrap($connection, 'application_schema_migrations', $statusTable);
    $table = scheduledTaskTable($statusTable);
    $time = astronomyPushUtc($nowUtc)->format('Y-m-d H:i:s');
    $statement = $connection->prepare(
        'INSERT INTO ' . $table . ' (task_name, last_started_at, last_finished_at, last_status, last_error) '
        . "VALUES (:task_name, :started_at, :finished_at, 'skipped_lock', NULL) ON DUPLICATE KEY UPDATE "
        . "last_started_at = VALUES(last_started_at), last_finished_at = VALUES(last_finished_at), "
        . "last_status = 'skipped_lock', last_error = NULL"
    );
    $statement->execute(['task_name' => SCHEDULED_TASK_LOCK_STATUS, 'started_at' => $time, 'finished_at' => $time]);
}

/** @return resource|null */
function scheduledTaskAcquireLock(?string $path = null)
{
    $path ??= rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'aquellas-lunas-scheduled-tasks.lock';
    $newFile = !file_exists($path);
    $handle = @fopen($path, 'c');
    if (!is_resource($handle)) throw new RuntimeException('No se pudo abrir el lock del orquestador.');
    if ($newFile) @chmod($path, 0666);
    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        return null;
    }
    ftruncate($handle, 0);
    fwrite($handle, (string) getmypid());
    fflush($handle);
    return $handle;
}

/** @param resource $handle */
function scheduledTaskReleaseLock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

function scheduledTaskLockIsBusy(?string $path = null): ?bool
{
    try { $handle = scheduledTaskAcquireLock($path); }
    catch (Throwable $exception) { return null; }
    if ($handle === null) return true;
    scheduledTaskReleaseLock($handle);
    return false;
}

/** @return array<string,mixed> */
function scheduledTaskRun(PDO $connection, array $registry, callable $notificationRunner,
    DateTimeImmutable $nowUtc, string $migrationTable = 'application_schema_migrations',
    string $statusTable = 'scheduled_task_status', bool $bootstrap = true): array
{
    if ($bootstrap) scheduledTaskBootstrap($connection, $migrationTable, $statusTable);
    scheduledTaskStatusStart($connection, $nowUtc, $statusTable);
    try {
        $migrations = scheduledTaskRunMigrations($connection, $registry, $migrationTable);
        if ($migrations['blocks_tasks']) {
            throw new RuntimeException('Hay una migración manual pendiente que bloquea las tareas programadas.');
        }
        $notifications = $notificationRunner($connection, $nowUtc);
        if (!is_array($notifications)) throw new RuntimeException('El procesador de notificaciones no devolvió un resumen válido.');
        scheduledTaskStatusFinish($connection, new DateTimeImmutable('now', new DateTimeZone('UTC')), true, null, $statusTable);
        return ['migrations' => $migrations, 'notifications' => $notifications];
    } catch (Throwable $exception) {
        scheduledTaskStatusFinish($connection, new DateTimeImmutable('now', new DateTimeZone('UTC')), false,
            $exception->getMessage(), $statusTable);
        throw $exception;
    }
}

/** @return array<string,mixed> */
function scheduledTaskAdminState(PDO $connection, array $registry,
    string $migrationTable = 'application_schema_migrations', string $statusTable = 'scheduled_task_status'): array
{
    scheduledTaskBootstrap($connection, $migrationTable, $statusTable);
    $migrationTableSql = scheduledTaskTable($migrationTable);
    $statusTableSql = scheduledTaskTable($statusTable);
    $registry = scheduledTaskValidateRegistry($registry);
    $applied = $connection->query('SELECT migration_id FROM ' . $migrationTableSql)->fetchAll(PDO::FETCH_COLUMN);
    $appliedLookup = array_fill_keys(array_map('strval', $applied), true);
    $automaticPending = [];
    $manualPending = [];
    foreach ($registry as $id => $entry) {
        if (isset($appliedLookup[$id])) continue;
        if ($entry['mode'] === 'automatic') $automaticPending[] = $id;
        else $manualPending[] = $id;
    }
    $status = $connection->prepare('SELECT * FROM ' . $statusTableSql . ' WHERE task_name = :task_name');
    $status->execute(['task_name' => SCHEDULED_TASK_ORCHESTRATOR]);
    $orchestrator = $status->fetch() ?: null;
    $status->execute(['task_name' => SCHEDULED_TASK_LOCK_STATUS]);
    $lockSkip = $status->fetch() ?: null;
    $lastMigration = $connection->query(
        'SELECT migration_id, applied_at FROM ' . $migrationTableSql . ' ORDER BY applied_at DESC, migration_id DESC LIMIT 1'
    )->fetch() ?: null;
    return ['orchestrator' => $orchestrator, 'lock_skip' => $lockSkip, 'last_migration' => $lastMigration,
        'automatic_pending' => $automaticPending, 'manual_pending' => $manualPending];
}
