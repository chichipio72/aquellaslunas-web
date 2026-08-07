<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/web-push-moonrise.php';

function moonriseReminderOutput(string $message): void
{
    fwrite(STDOUT, '[' . gmdate('Y-m-d H:i:s') . " UTC] " . $message . PHP_EOL);
}

function moonriseReminderParseNow(?string $value): DateTimeImmutable
{
    $timezone = new DateTimeZone(MOONRISE_PUSH_TIMEZONE);
    if ($value === null) {
        return new DateTimeImmutable('now', $timezone);
    }
    $now = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$now || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException('--now debe usar YYYY-MM-DD HH:MM:SS en ' . MOONRISE_PUSH_TIMEZONE . '.');
    }
    return $now;
}

try {
    $options = getopt('', ['dry-run', 'now:']);
    $allowedArguments = 1 + (isset($options['dry-run']) ? 1 : 0) + (array_key_exists('now', $options) ? 1 : 0);
    if ($argc !== $allowedArguments) {
        throw new InvalidArgumentException('Opciones admitidas: --dry-run y --now="YYYY-MM-DD HH:MM:SS".');
    }
    $dryRun = isset($options['dry-run']);
    $nowLocal = moonriseReminderParseNow(array_key_exists('now', $options) ? (string) $options['now'] : null);
    $nowUtc = astronomyMoonrisePushUtc($nowLocal);
    $connection = getWebDatabaseConnection();
    if (!astronomyMoonrisePushSubscriptionIsActive($connection)) {
        throw new RuntimeException('La suscripción piloto 11 no existe o no está activa.');
    }

    moonriseReminderOutput('Ejecución para ' . $nowLocal->format('Y-m-d H:i:s T') . ($dryRun ? ' (dry-run).' : '.'));
    $events = astronomyMoonrisePushEvents($nowLocal);
    foreach ($events as $event) {
        $eventDescription = 'Salida ' . $event['event_local']->format('Y-m-d H:i:s T')
            . '; aviso nominal ' . $event['notification_utc']->setTimezone($nowLocal->getTimezone())->format('Y-m-d H:i:s T') . '.';
        moonriseReminderOutput($eventDescription);
        if (!astronomyMoonrisePushIsDue($event['notification_utc'], $nowUtc)) {
            moonriseReminderOutput('Decisión: omitida por estar fuera de la ventana retrospectiva de ' . MOONRISE_PUSH_LOOKBACK_MINUTES . ' minutos.');
            continue;
        }
        if ($dryRun) {
            moonriseReminderOutput('Decisión: enviaría el recordatorio; dry-run sin escritura ni envío.');
            continue;
        }

        $claim = astronomyMoonrisePushClaim($connection, $event['event_utc'], $event['notification_utc'], new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if (!$claim['claimed']) {
            moonriseReminderOutput('Decisión: omitida por deduplicación (en proceso o ya enviada).');
            continue;
        }
        $message = astronomyMoonrisePushMessage($event['event_local']);
        try {
            $result = astronomyWebPushSend(
                $connection,
                loadWebPushServerConfig(),
                MOONRISE_PUSH_SUBSCRIPTION_ID,
                $message['title'],
                $message['body'],
                $message['url']
            );
            $success = $result['success'] === 1 && $result['failed'] === 0;
            $error = $success ? null : (string) ($result['errors'][0]['message'] ?? 'Error de envío Web Push');
        } catch (Throwable $exception) {
            $success = false;
            $error = 'Error de envío Web Push (' . get_debug_type($exception) . ')';
        }
        if (!astronomyMoonrisePushComplete($connection, $event['event_utc'], $claim['attempted_at'], $success, $error)) {
            moonriseReminderOutput('Fallo: el intento perdió su reclamación antes de guardar el resultado.');
            continue;
        }
        moonriseReminderOutput($success ? 'Envío exitoso; estado sent.' : 'Envío fallido; estado failed y disponible para reintento dentro de la ventana.');
    }
} catch (Throwable $exception) {
    moonriseReminderOutput('ERROR: ' . astronomyMoonrisePushSanitizeError($exception->getMessage()));
    exit(1);
}
