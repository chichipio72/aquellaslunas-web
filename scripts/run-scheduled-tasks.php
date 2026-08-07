<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/scheduled-tasks.php';

function scheduledTasksOutput(string $message): void
{
    fwrite(STDOUT, '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message . PHP_EOL);
}

$startedAt = hrtime(true);
$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$lock = null;
try {
    $lock = scheduledTaskAcquireLock();
    if ($lock === null) {
        try { scheduledTaskRecordLockSkip(getWebDatabaseConnection(), $nowUtc); }
        catch (Throwable $exception) { /* El lock sigue siendo una salida normal aunque el estado no pueda persistirse. */ }
        scheduledTasksOutput('Ejecución omitida: otra instancia conserva el lock.');
        exit(0);
    }
    $registry = require __DIR__ . '/migrations/registry.php';
    if (!is_array($registry)) throw new RuntimeException('El registro de migraciones no es válido.');
    $result = scheduledTaskRun(
        getWebDatabaseConnection(),
        $registry,
        static fn(PDO $connection, DateTimeImmutable $clock): array =>
            astronomyPushRunReminderCycle($connection, $clock, false),
        $nowUtc
    );
    if ($result['migrations']['applied'] !== []) {
        scheduledTasksOutput('Migraciones aplicadas: ' . implode(', ', $result['migrations']['applied']) . '.');
    }
    if ($result['migrations']['manual_pending'] !== []) {
        scheduledTasksOutput('Migraciones manuales pendientes: '
            . implode(', ', $result['migrations']['manual_pending']) . '.');
    }
    $summary = $result['notifications']['summary'];
    $elapsedMs = max(0, (int) round((hrtime(true) - $startedAt) / 1000000));
    scheduledTasksOutput('OK: pruebas ' . $summary['tests_processed'] . ', avisos evaluados '
        . $summary['evaluated'] . ', envíos ' . $summary['sent'] . ', omisiones ' . $summary['skipped']
        . ', errores ' . $summary['errors'] . ', duración ' . $elapsedMs . ' ms.');
} catch (Throwable $exception) {
    $elapsedMs = max(0, (int) round((hrtime(true) - $startedAt) / 1000000));
    scheduledTasksOutput('ERROR: ' . astronomyWebPushSanitizeError($exception->getMessage())
        . ' Duración ' . $elapsedMs . ' ms.');
    exit(1);
} finally {
    if (is_resource($lock)) scheduledTaskReleaseLock($lock);
}
