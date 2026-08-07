<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/web-push-astronomy.php';

function astronomyReminderOutput(string $message): void
{
    fwrite(STDOUT, '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message . PHP_EOL);
}

function astronomyReminderNow(?string $value): DateTimeImmutable
{
    $utc = new DateTimeZone('UTC');
    if ($value === null) return new DateTimeImmutable('now', $utc);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $utc);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        throw new InvalidArgumentException('--now debe usar YYYY-MM-DD HH:MM:SS y se interpreta en UTC.');
    }
    return $date;
}

try {
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument !== '--dry-run' && preg_match('/^--now=.+$/', $argument) !== 1
            && preg_match('/^--subscription-id=\d+$/', $argument) !== 1) {
            throw new InvalidArgumentException(
                'Opciones admitidas: --dry-run, --now="YYYY-MM-DD HH:MM:SS" y --subscription-id=N.'
            );
        }
    }
    $options = getopt('', ['dry-run', 'now:', 'subscription-id:']);
    $dryRun = isset($options['dry-run']);
    $nowUtc = astronomyReminderNow(array_key_exists('now', $options) ? (string) $options['now'] : null);
    $subscriptionId = null;
    if (array_key_exists('subscription-id', $options)) {
        $validated = filter_var($options['subscription-id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) throw new InvalidArgumentException('--subscription-id debe ser un entero positivo.');
        $subscriptionId = (int) $validated;
    }
    $connection = getWebDatabaseConnection();
    astronomyReminderOutput('Ejecución general para ' . $nowUtc->format('Y-m-d H:i:s T')
        . ($dryRun ? ' (dry-run)' : '') . '.');
    $cycle = astronomyPushRunReminderCycle(
        $connection, $nowUtc, $dryRun, $subscriptionId, 'astronomyReminderOutput'
    );
    $counts = $cycle['moonrise'];
    astronomyReminderOutput('Resumen: vencidos ' . $counts['due'] . ', enviados ' . $counts['sent']
        . ', omitidos ' . $counts['skipped'] . ', deduplicados ' . $counts['deduplicated']
        . ', fallidos ' . $counts['failed'] . '.');
    $testCounts = $cycle['tests'];
    astronomyReminderOutput('Resumen de pruebas: vencidas para procesar ' . $testCounts['pending_due']
        . ', enviadas ' . $testCounts['sent'] . ', omitidas ' . $testCounts['skipped']
        . ', expiradas ' . $testCounts['expired'] . ', deduplicadas ' . $testCounts['deduplicated']
        . ', fallidas ' . $testCounts['failed'] . '.');
} catch (Throwable $exception) {
    astronomyReminderOutput('ERROR: ' . astronomyWebPushSanitizeError($exception->getMessage()));
    exit(1);
}
