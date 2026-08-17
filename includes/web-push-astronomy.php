<?php

declare(strict_types=1);

require_once __DIR__ . '/api-client.php';
require_once __DIR__ . '/web-push.php';
require_once __DIR__ . '/web-push-support-id.php';
require_once __DIR__ . '/web-push-astronomy-providers.php';

const ASTRONOMY_PUSH_MOONRISE_TYPE = 'moonrise';
const ASTRONOMY_PUSH_MOONRISE_EVENT_KEY = 'moonrise';
const ASTRONOMY_PUSH_ECLIPSE_TYPE = 'eclipse';
const ASTRONOMY_PUSH_LUNAR_CONJUNCTION_TYPE = 'lunar_conjunction';
const ASTRONOMY_PUSH_SATELLITE_TRANSIT_TYPE = 'satellite_transit';
const ASTRONOMY_PUSH_TEST_TYPE = 'test';
const ASTRONOMY_PUSH_LOOKBACK_MINUTES = 6;
const ASTRONOMY_PUSH_TEST_LOOKBACK_MINUTES = 10;
const ASTRONOMY_PUSH_STALE_MINUTES = 10;

function astronomyPushUtc(DateTimeImmutable $date): DateTimeImmutable
{
    return $date->setTimezone(new DateTimeZone('UTC'));
}

function astronomyPushNormalizeTime(mixed $value, string $field): ?string
{
    if ($value === null || trim((string) $value) === '') return null;
    $value = trim((string) $value);
    if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $value, $matches) !== 1
        || (int) $matches[1] > 23 || (int) $matches[2] > 59) {
        throw new InvalidArgumentException($field . ' debe usar HH:MM.');
    }
    return $matches[1] . ':' . $matches[2];
}

/** @return array<string,mixed> */
function astronomyPushValidateDeviceConfig(array $input): array
{
    $deviceName = trim((string) ($input['device_name'] ?? ''));
    $locationName = trim((string) ($input['location_name'] ?? ''));
    if ($deviceName === '' || strlen($deviceName) > 100 || preg_match('/[\x00-\x1F\x7F]/', $deviceName)) {
        throw new InvalidArgumentException('El nombre del dispositivo no es válido.');
    }
    if ($locationName === '' || strlen($locationName) > 150 || preg_match('/[\x00-\x1F\x7F]/', $locationName)) {
        throw new InvalidArgumentException('El nombre de la ubicación no es válido.');
    }
    if (!is_numeric($input['latitude'] ?? null) || !is_numeric($input['longitude'] ?? null)) {
        throw new InvalidArgumentException('Latitud y longitud deben ser numéricas.');
    }
    $latitude = (float) $input['latitude'];
    $longitude = (float) $input['longitude'];
    if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
        throw new InvalidArgumentException('La latitud debe estar entre -90 y 90.');
    }
    if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
        throw new InvalidArgumentException('La longitud debe estar entre -180 y 180.');
    }
    $timezoneName = trim((string) ($input['timezone'] ?? ''));
    try {
        $timezone = new DateTimeZone($timezoneName);
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('La zona horaria no es válida.', 0, $exception);
    }
    $quietEnabled = filter_var($input['quiet_hours_enabled'] ?? false, FILTER_VALIDATE_BOOL);
    $quietStart = astronomyPushNormalizeTime($input['quiet_start_local'] ?? null, 'La hora desde');
    $quietEnd = astronomyPushNormalizeTime($input['quiet_end_local'] ?? null, 'La hora hasta');
    if ($quietEnabled && ($quietStart === null || $quietEnd === null)) {
        throw new InvalidArgumentException('Completá ambas horas de No molestar.');
    }
    if ($quietEnabled && $quietStart === $quietEnd) {
        throw new InvalidArgumentException('El inicio y el fin de No molestar deben ser diferentes.');
    }
    return [
        'device_name' => $deviceName,
        'notifications_enabled' => filter_var($input['notifications_enabled'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0,
        'location_name' => $locationName,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timezone' => $timezone->getName(),
        'quiet_hours_enabled' => $quietEnabled ? 1 : 0,
        'quiet_start_local' => $quietEnabled ? $quietStart : null,
        'quiet_end_local' => $quietEnabled ? $quietEnd : null,
    ];
}

function astronomyPushValidateParametersJson(mixed $value): ?array
{
    if ($value === null || trim((string) $value) === '') return null;
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded) || array_is_list($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('Los parámetros específicos no contienen un objeto JSON válido.');
    }
    return $decoded;
}

/** @return list<string> */
function astronomyPushAllowedPlaceholders(string $notificationType): array
{
    return match ($notificationType) {
        'moonrise' => ['event_time', 'event_date', 'location_name', 'lead_minutes', 'device_name'],
        'eclipse' => ['event_time', 'event_date', 'location_name', 'eclipse_type', 'eclipse_kind', 'device_name'],
        'lunar_conjunction' => ['event_time', 'event_date', 'location_name', 'object_name', 'separation', 'device_name'],
        'satellite_transit' => ['event_time', 'event_date', 'location_name', 'satellite_name', 'target_name',
            'classification', 'separation', 'device_name'],
        'test' => ['event_time', 'event_date', 'location_name', 'device_name'],
        default => [],
    };
}

function astronomyPushValidateTemplate(string $template, int $maximumLength, string $field,
    array $allowedPlaceholders): string
{
    $template = astronomyWebPushNormalizeMessage($template, $maximumLength, $field);
    if (str_contains($template, '<') || str_contains($template, '>')) {
        throw new InvalidArgumentException($field . ' no admite HTML.');
    }
    preg_match_all('/\{([a-z_]+)\}/', $template, $matches);
    $recognizedSyntax = preg_replace('/\{[a-z_]+\}/', '', $template) ?? '';
    if (str_contains($recognizedSyntax, '{') || str_contains($recognizedSyntax, '}')) {
        throw new InvalidArgumentException($field . ' contiene un marcador con formato inválido.');
    }
    foreach (array_unique($matches[1] ?? []) as $placeholder) {
        if (!in_array($placeholder, $allowedPlaceholders, true)) {
            throw new InvalidArgumentException($field . ' contiene el marcador no permitido {' . $placeholder . '}.');
        }
    }
    return $template;
}

/** @return array<string,mixed> */
function astronomyPushValidateNotificationType(array $input): array
{
    $notificationType = trim((string) ($input['notification_type'] ?? ''));
    if (preg_match('/^[a-z][a-z0-9_]{0,49}$/', $notificationType) !== 1) {
        throw new InvalidArgumentException('El código interno del tipo no es válido.');
    }
    $allowed = astronomyPushAllowedPlaceholders($notificationType);
    $displayName = astronomyWebPushNormalizeMessage($input['display_name'] ?? '', 100, 'El nombre visible');
    $description = trim((string) ($input['description'] ?? ''));
    if (strlen($description) > 255 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $description) === 1) {
        throw new InvalidArgumentException('La descripción no es válida.');
    }
    $title = astronomyPushValidateTemplate((string) ($input['title_template'] ?? ''), 180,
        'El título', $allowed);
    $body = astronomyPushValidateTemplate((string) ($input['body_template'] ?? ''), 500,
        'El mensaje', $allowed);
    $targetUrl = astronomyWebPushNormalizeTargetUrl($input['target_url'] ?? '');
    if (strlen($targetUrl) > 500) throw new InvalidArgumentException('La URL no puede superar 500 caracteres.');
    $scheduleMode = (string) ($input['default_schedule_mode'] ?? '');
    if (!in_array($scheduleMode, ['before_event', 'fixed_time', 'fixed_local_time'], true)) {
        throw new InvalidArgumentException('El modo de programación predeterminado no es válido.');
    }
    $lead = $input['default_lead_minutes'] ?? null;
    if ($lead === '') $lead = null;
    if ($lead !== null) {
        $lead = filter_var($lead, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1440]]);
        if ($lead === false) throw new InvalidArgumentException('La anticipación predeterminada no es válida.');
    }
    $deliveryTime = astronomyPushNormalizeTime($input['default_delivery_time'] ?? null, 'La hora predeterminada');
    $dayOffset = filter_var($input['default_delivery_day_offset'] ?? 0, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => -366, 'max_range' => 366]]);
    if ($dayOffset === false) throw new InvalidArgumentException('El desplazamiento de día no es válido.');
    $quietPolicy = (string) ($input['default_quiet_policy'] ?? '');
    if (!in_array($quietPolicy, ['omit', 'postpone', 'ignore'], true)) {
        throw new InvalidArgumentException('La política predeterminada de No molestar no es válida.');
    }
    $sortOrder = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    if ($sortOrder === false) throw new InvalidArgumentException('El orden no es válido.');
    return [
        'notification_type' => $notificationType,
        'display_name' => $displayName,
        'description' => $description === '' ? null : $description,
        'available' => filter_var($input['available'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0,
        'admin_only' => filter_var($input['admin_only'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0,
        'title_template' => $title,
        'body_template' => $body,
        'target_url' => $targetUrl,
        'default_schedule_mode' => $scheduleMode,
        'default_lead_minutes' => $lead === null ? null : (int) $lead,
        'default_delivery_time' => $deliveryTime,
        'default_delivery_day_offset' => (int) $dayOffset,
        'default_quiet_policy' => $quietPolicy,
        'sort_order' => (int) $sortOrder,
    ];
}

/** @return list<array<string,mixed>> */
function astronomyPushNotificationTypes(PDO $connection): array
{
    $rows = $connection->query('SELECT * FROM web_push_notification_types ORDER BY sort_order, display_name')->fetchAll();
    return array_map('astronomyPushValidateNotificationType', $rows);
}

function astronomyPushNotificationType(PDO $connection, string $notificationType): ?array
{
    $statement = $connection->prepare('SELECT * FROM web_push_notification_types WHERE notification_type = :type');
    $statement->execute(['type' => $notificationType]);
    $row = $statement->fetch();
    return is_array($row) ? astronomyPushValidateNotificationType($row) : null;
}

function astronomyPushSaveNotificationType(PDO $connection, string $notificationType, array $input): void
{
    $input['notification_type'] = $notificationType;
    $type = astronomyPushValidateNotificationType($input);
    $exists = $connection->prepare('SELECT 1 FROM web_push_notification_types WHERE notification_type = :type');
    $exists->execute(['type' => $notificationType]);
    if ($exists->fetchColumn() === false) throw new InvalidArgumentException('El tipo de notificación no existe.');
    $statement = $connection->prepare(
        'UPDATE web_push_notification_types SET display_name = :display_name, description = :description, '
        . 'available = :available, admin_only = :admin_only, title_template = :title_template, '
        . 'body_template = :body_template, target_url = :target_url, '
        . 'default_schedule_mode = :default_schedule_mode, default_lead_minutes = :default_lead_minutes, '
        . 'default_delivery_time = :default_delivery_time, '
        . 'default_delivery_day_offset = :default_delivery_day_offset, '
        . 'default_quiet_policy = :default_quiet_policy, sort_order = :sort_order '
        . 'WHERE notification_type = :notification_type'
    );
    $statement->execute($type);
}

function astronomyPushSpanishDate(DateTimeImmutable $date): string
{
    $months = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')] . ' de ' . $date->format('Y');
}

/** @return array{title:string,body:string,url:string} */
function astronomyPushRenderNotification(array $type, array $event, array $device, array $preference = []): array
{
    $type = astronomyPushValidateNotificationType($type);
    $timezone = new DateTimeZone((string) ($device['timezone'] ?? 'UTC'));
    $eventDate = $event['event_time_local'] ?? $event['event_time_utc'] ?? null;
    if (!$eventDate instanceof DateTimeImmutable) throw new InvalidArgumentException('El evento no tiene una fecha válida.');
    $eventLocal = $eventDate->setTimezone($timezone);
    $lead = array_key_exists('lead_minutes', $preference) && $preference['lead_minutes'] !== null
        ? (int) $preference['lead_minutes'] : (int) ($type['default_lead_minutes'] ?? 0);
    $values = [
        '{event_time}' => $eventLocal->format('H:i'),
        '{event_date}' => astronomyPushSpanishDate($eventLocal),
        '{location_name}' => (string) ($device['location_name'] ?? ''),
        '{lead_minutes}' => (string) $lead,
        '{device_name}' => (string) ($device['device_name'] ?? ''),
        '{eclipse_type}' => (string) ($event['eclipse_type'] ?? ''),
        '{eclipse_kind}' => (string) ($event['eclipse_kind'] ?? ''),
        '{object_name}' => (string) ($event['object_name'] ?? ''),
        '{separation}' => (string) ($event['separation'] ?? ''),
        '{satellite_name}' => (string) ($event['satellite_name'] ?? ''),
        '{target_name}' => (string) ($event['target_name'] ?? ''),
        '{classification}' => (string) ($event['classification'] ?? ''),
    ];
    $title = strtr((string) $type['title_template'], $values);
    $body = strtr((string) $type['body_template'], $values);
    return [
        'title' => astronomyWebPushNormalizeMessage($title, 180, 'El título renderizado'),
        'body' => astronomyWebPushNormalizeMessage($body, 500, 'El mensaje renderizado'),
        'url' => astronomyWebPushNormalizeTargetUrl($type['target_url']),
    ];
}

/** @return array{title:string,body:string,url:string} */
function astronomyPushNotificationPreview(array $type): array
{
    return astronomyPushRenderNotification($type, [
        'event_time_local' => new DateTimeImmutable('2026-08-04 20:42:00', new DateTimeZone('America/Argentina/Buenos_Aires')),
        'eclipse_type' => 'eclipse lunar total', 'eclipse_kind' => 'lunar',
        'object_name' => 'Júpiter', 'separation' => '2,1°',
        'satellite_name' => 'ISS', 'target_name' => 'Luna', 'classification' => 'tránsito',
    ], [
        'timezone' => 'America/Argentina/Buenos_Aires', 'location_name' => 'Vicente López',
        'device_name' => 'Celular Android',
    ], ['lead_minutes' => 15]);
}

function astronomyPushIsQuietAt(DateTimeImmutable $instantUtc, array $device): bool
{
    if ((int) ($device['quiet_hours_enabled'] ?? 0) !== 1) return false;
    $start = astronomyPushNormalizeTime($device['quiet_start_local'] ?? null, 'La hora desde');
    $end = astronomyPushNormalizeTime($device['quiet_end_local'] ?? null, 'La hora hasta');
    if ($start === null || $end === null || $start === $end) {
        throw new InvalidArgumentException('El horario de No molestar guardado no es válido.');
    }
    $timezone = new DateTimeZone((string) $device['timezone']);
    $time = astronomyPushUtc($instantUtc)->setTimezone($timezone)->format('H:i');
    return $start < $end ? $time >= $start && $time < $end : $time >= $start || $time < $end;
}

function astronomyPushNotificationTime(DateTimeImmutable $eventTimeUtc, int $leadMinutes): DateTimeImmutable
{
    if ($leadMinutes < 1 || $leadMinutes > 1440) {
        throw new InvalidArgumentException('La anticipación debe estar entre 1 y 1440 minutos.');
    }
    return astronomyPushUtc($eventTimeUtc)->modify('-' . $leadMinutes . ' minutes');
}

function astronomyPushScheduledNotificationTime(DateTimeImmutable $eventTimeUtc, array $preference,
    array $device): DateTimeImmutable
{
    $mode = (string) ($preference['schedule_mode'] ?? 'before_event');
    if ($mode === 'before_event') {
        return astronomyPushNotificationTime($eventTimeUtc, (int) ($preference['lead_minutes'] ?? 0));
    }
    if (!in_array($mode, ['fixed_local_time', 'fixed_time'], true)) {
        throw new InvalidArgumentException('El modo de programación guardado no es válido.');
    }
    $time = astronomyPushNormalizeTime($preference['delivery_local_time'] ?? null, 'La hora de entrega');
    if ($time === null) throw new InvalidArgumentException('Falta la hora local de entrega.');
    $offset = filter_var($preference['delivery_day_offset'] ?? 0, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => -366, 'max_range' => 366]]);
    if ($offset === false) throw new InvalidArgumentException('El desplazamiento de entrega no es válido.');
    $timezone = new DateTimeZone((string) $device['timezone']);
    $eventLocal = astronomyPushUtc($eventTimeUtc)->setTimezone($timezone);
    $date = new DateTimeImmutable($eventLocal->format('Y-m-d') . ' ' . $time . ':00', $timezone);
    if ((int) $offset !== 0) $date = $date->modify(((int) $offset > 0 ? '+' : '') . (int) $offset . ' days');
    return astronomyPushUtc($date);
}

function astronomyPushQuietEnd(DateTimeImmutable $instantUtc, array $device): DateTimeImmutable
{
    if (!astronomyPushIsQuietAt($instantUtc, $device)) return astronomyPushUtc($instantUtc);
    $start = astronomyPushNormalizeTime($device['quiet_start_local'] ?? null, 'La hora desde');
    $end = astronomyPushNormalizeTime($device['quiet_end_local'] ?? null, 'La hora hasta');
    if ($start === null || $end === null) throw new InvalidArgumentException('No molestar no es válido.');
    $timezone = new DateTimeZone((string) $device['timezone']);
    $local = astronomyPushUtc($instantUtc)->setTimezone($timezone);
    $endLocal = new DateTimeImmutable($local->format('Y-m-d') . ' ' . $end . ':00', $timezone);
    if ($start > $end && $local->format('H:i') >= $start) $endLocal = $endLocal->modify('+1 day');
    return astronomyPushUtc($endLocal);
}

function astronomyPushIsDue(DateTimeImmutable $notificationTimeUtc, DateTimeImmutable $nowUtc,
    int $lookbackMinutes = ASTRONOMY_PUSH_LOOKBACK_MINUTES): bool
{
    $notification = astronomyPushUtc($notificationTimeUtc)->getTimestamp();
    $now = astronomyPushUtc($nowUtc)->getTimestamp();
    return $notification <= $now && $notification >= $now - $lookbackMinutes * 60;
}

function astronomyPushLookbackMinutes(string $notificationType): int
{
    return $notificationType === ASTRONOMY_PUSH_ECLIPSE_TYPE ? 16 : ASTRONOMY_PUSH_LOOKBACK_MINUTES;
}

function astronomyPushProviderCadenceDue(string $notificationType, DateTimeImmutable $nowUtc): bool
{
    $minutes = match ($notificationType) {
        ASTRONOMY_PUSH_ECLIPSE_TYPE => 15,
        ASTRONOMY_PUSH_LUNAR_CONJUNCTION_TYPE, ASTRONOMY_PUSH_SATELLITE_TRANSIT_TYPE => 5,
        default => 1,
    };
    return ((int) astronomyPushUtc($nowUtc)->format('i')) % $minutes === 0;
}

/** @return list<array<string,mixed>> */
function astronomyPushMoonriseEvents(array $device, DateTimeImmutable $nowUtc): array
{
    $timezone = new DateTimeZone((string) $device['timezone']);
    $nowLocal = astronomyPushUtc($nowUtc)->setTimezone($timezone);
    $today = new DateTimeImmutable($nowLocal->format('Y-m-d') . ' 00:00:00', $timezone);
    $calculator = new AstronomyEngine\LunarDayCalculator(new AstronomyEngine\MeeusLunarCalculator());
    $events = [];
    foreach ([$today, $today->modify('+1 day')] as $date) {
        $moonrise = $calculator->calculate($date, (float) $device['latitude'], (float) $device['longitude'], 0.0)->moonrise;
        if (!$moonrise instanceof DateTimeImmutable) continue;
        $eventUtc = astronomyPushUtc($moonrise);
        $events[] = [
            'event_key' => ASTRONOMY_PUSH_MOONRISE_EVENT_KEY,
            'event_time_utc' => $eventUtc,
            'event_time_local' => $moonrise->setTimezone($timezone),
        ];
    }
    return $events;
}

/** @return list<array<string,mixed>> */
function astronomyPushConfiguredDevices(PDO $connection, ?int $subscriptionId = null): array
{
    $sql = 'SELECT s.id AS subscription_id, s.active, s.user_agent, s.last_success_at, s.last_error_at, '
        . 's.last_error_message, d.device_name, d.notifications_enabled, d.location_name, d.latitude, '
        . 'd.longitude, d.timezone, d.quiet_hours_enabled, d.quiet_start_local, d.quiet_end_local, '
        . 'p.notification_type, p.enabled, p.schedule_mode, p.lead_minutes, p.delivery_local_time, '
        . 'p.delivery_day_offset, p.quiet_policy, p.parameters_json, t.* '
        . 'FROM web_push_subscriptions s JOIN web_push_device_config d ON d.subscription_id = s.id '
        . 'JOIN web_push_notification_preferences p ON p.subscription_id = s.id LEFT JOIN web_push_notification_types t '
        . "ON t.notification_type = p.notification_type WHERE s.active = 1 AND d.notifications_enabled = 1 AND p.enabled = 1";
    $parameters = [];
    if ($subscriptionId !== null) {
        $sql .= ' AND s.id = :subscription_id';
        $parameters['subscription_id'] = $subscriptionId;
    }
    $sql .= ' ORDER BY s.id';
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $devices = [];
    foreach ($statement->fetchAll() as $row) {
        try {
            $validated = astronomyPushValidateDeviceConfig($row + ['notifications_enabled' => true]);
            $notificationType = (string) $row['notification_type'];
            if (!in_array($notificationType, [ASTRONOMY_PUSH_MOONRISE_TYPE, ASTRONOMY_PUSH_ECLIPSE_TYPE,
                ASTRONOMY_PUSH_LUNAR_CONJUNCTION_TYPE, ASTRONOMY_PUSH_SATELLITE_TRANSIT_TYPE], true)) {
                throw new RuntimeException('El tipo astronómico guardado no es compatible.');
            }
            $schedule = (string) $row['schedule_mode'];
            if (!in_array($schedule, ['before_event', 'fixed_local_time', 'fixed_time'], true)) {
                throw new RuntimeException('La programación guardada no es compatible.');
            }
            $lead = $row['lead_minutes'] === null ? null : filter_var($row['lead_minutes'], FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 1440]]);
            if ($schedule === 'before_event' && $lead === false) throw new RuntimeException('La anticipación guardada no es válida.');
            if ($schedule !== 'before_event' && astronomyPushNormalizeTime($row['delivery_local_time'], 'La hora') === null) {
                throw new RuntimeException('La hora fija guardada no es válida.');
            }
            if (!in_array((string) $row['quiet_policy'], ['omit', 'postpone', 'ignore'], true)) {
                throw new RuntimeException('La política de silencio guardada no es válida.');
            }
            astronomyPushEventParameters($row);
        } catch (Throwable $exception) {
            $devices[] = array_replace($row, ['_configuration_error' => 'Configuración astronómica no válida.']);
            continue;
        }
        $device = array_replace($row, $validated, ['lead_minutes' => $lead === null ? null : (int) $lead]);
        try { $device['notification_type_config'] = astronomyPushValidateNotificationType($row); }
        catch (Throwable $exception) { $device['_notification_type_error'] = 'Catálogo astronómico no válido.'; }
        $devices[] = $device;
    }
    return $devices;
}

/** @return list<array<string,mixed>> */
function astronomyPushAdminSubscriptions(PDO $connection): array
{
    return $connection->query(
        'SELECT s.id AS subscription_id, s.active, s.user_agent, s.created_at, s.updated_at, '
        . 's.last_success_at, s.last_error_at, s.last_error_message, d.support_id, d.device_name, '
        . 'd.location_name, d.timezone, d.notifications_enabled, d.quiet_hours_enabled, '
        . 'd.quiet_start_local, d.quiet_end_local '
        . 'FROM web_push_subscriptions s LEFT JOIN web_push_device_config d ON d.subscription_id = s.id '
        . 'ORDER BY s.id DESC'
    )->fetchAll();
}

function astronomyPushAdminDevice(PDO $connection, int $subscriptionId): ?array
{
    $statement = $connection->prepare(
        'SELECT s.id AS subscription_id, s.active, s.user_agent, s.last_success_at, s.last_error_at, '
        . 's.last_error_message, d.device_name, d.notifications_enabled, d.location_name, d.latitude, '
        . 'd.longitude, d.timezone, d.quiet_hours_enabled, d.quiet_start_local, d.quiet_end_local, '
        . 'p.enabled AS moonrise_enabled, p.lead_minutes, p.schedule_mode, p.quiet_policy '
        . 'FROM web_push_subscriptions s LEFT JOIN web_push_device_config d ON d.subscription_id = s.id '
        . 'LEFT JOIN web_push_notification_preferences p ON p.subscription_id = s.id '
        . "AND p.notification_type = 'moonrise' WHERE s.id = :subscription_id"
    );
    $statement->execute(['subscription_id' => $subscriptionId]);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

/** @return list<array<string,mixed>> */
function astronomyPushDeviceNotificationPreferences(PDO $connection, int $subscriptionId): array
{
    $statement = $connection->prepare(
        'SELECT t.*, p.enabled, p.schedule_mode, p.lead_minutes, p.delivery_local_time, '
        . 'p.delivery_day_offset, p.quiet_policy, p.parameters_json '
        . 'FROM web_push_notification_types t LEFT JOIN web_push_notification_preferences p '
        . 'ON p.notification_type = t.notification_type AND p.subscription_id = :subscription_id '
        . 'WHERE t.admin_only = 0 AND (t.available = 1 OR p.subscription_id IS NOT NULL) '
        . 'ORDER BY t.sort_order, t.display_name'
    );
    $statement->execute(['subscription_id' => $subscriptionId]);
    return $statement->fetchAll();
}

/** @return list<array<string,mixed>> */
function astronomyPushAdminUpcomingEvents(array $device, array $preferences, DateTimeImmutable $nowUtc): array
{
    $results = [];
    foreach ($preferences as $preference) {
        if ((int) ($preference['available'] ?? 0) !== 1) continue;
        $type = (string) $preference['notification_type'];
        if (!in_array($type, [ASTRONOMY_PUSH_MOONRISE_TYPE, ASTRONOMY_PUSH_ECLIPSE_TYPE,
            ASTRONOMY_PUSH_LUNAR_CONJUNCTION_TYPE, ASTRONOMY_PUSH_SATELLITE_TRANSIT_TYPE], true)) continue;
        $configured = array_replace($device, $preference, ['notification_type' => $type]);
        if ((int) ($preference['enabled'] ?? 0) !== 1 && $type === ASTRONOMY_PUSH_SATELLITE_TRANSIT_TYPE) {
            $results[] = ['notification_type' => $type, 'display_name' => $preference['display_name'],
                'enabled' => false, 'event' => null, 'error' => null, 'not_calculated' => true];
            continue;
        }
        try {
            $events = astronomyPushProviderEvents($configured, $nowUtc);
            usort($events, static fn(array $a, array $b): int => $a['event_time_utc'] <=> $b['event_time_utc']);
            $event = null;
            foreach ($events as $candidate) {
                if ($candidate['event_time_utc'] > $nowUtc) { $event = $candidate; break; }
            }
            $schedule = null;
            $quiet = false;
            $effective = null;
            if (is_array($event)) {
                $schedule = astronomyPushScheduledNotificationTime($event['event_time_utc'], $configured, $configured);
                $quiet = astronomyPushIsQuietAt($schedule, $configured);
                $effective = (string) ($configured['quiet_policy'] ?? 'omit') === 'postpone' && $quiet
                    ? astronomyPushQuietEnd($schedule, $configured) : $schedule;
            }
            $results[] = ['notification_type' => $type, 'display_name' => $preference['display_name'],
                'enabled' => (int) ($preference['enabled'] ?? 0) === 1, 'event' => $event,
                'notification_time_utc' => $schedule, 'effective_time_utc' => $effective,
                'quiet' => $quiet, 'quiet_policy' => $configured['quiet_policy'], 'error' => null];
        } catch (Throwable $exception) {
            $results[] = ['notification_type' => $type, 'display_name' => $preference['display_name'],
                'enabled' => (int) ($preference['enabled'] ?? 0) === 1, 'event' => null,
                'error' => astronomyWebPushSanitizeError($exception->getMessage())];
        }
    }
    return $results;
}

function astronomyPushSaveDevice(PDO $connection, int $subscriptionId, array $input): void
{
    $device = astronomyPushValidateDeviceConfig($input);
    $exists = $connection->prepare('SELECT 1 FROM web_push_subscriptions WHERE id = :id');
    $exists->execute(['id' => $subscriptionId]);
    if ($exists->fetchColumn() === false) throw new InvalidArgumentException('La suscripción elegida no existe.');
    $ownsTransaction = !$connection->inTransaction();
    if ($ownsTransaction) $connection->beginTransaction();
    try {
        $supportIdStatement = $connection->prepare(
            'SELECT support_id FROM web_push_device_config WHERE subscription_id = :subscription_id'
        );
        $supportIdStatement->execute(['subscription_id' => $subscriptionId]);
        $supportId = $supportIdStatement->fetchColumn();
        if (!is_string($supportId) || astronomyPushNormalizeSupportId($supportId) === null) {
            $supportId = astronomyPushAllocateSupportId($connection);
        }
        $statement = $connection->prepare(
            'INSERT INTO web_push_device_config (subscription_id, support_id, device_name, notifications_enabled, location_name, '
            . 'latitude, longitude, timezone, quiet_hours_enabled, quiet_start_local, quiet_end_local) '
            . 'VALUES (:subscription_id, :support_id, :device_name, :notifications_enabled, :location_name, :latitude, :longitude, '
            . ':timezone, :quiet_hours_enabled, :quiet_start_local, :quiet_end_local) '
            . 'ON DUPLICATE KEY UPDATE device_name = VALUES(device_name), notifications_enabled = VALUES(notifications_enabled), '
            . 'location_name = VALUES(location_name), latitude = VALUES(latitude), longitude = VALUES(longitude), '
            . 'timezone = VALUES(timezone), quiet_hours_enabled = VALUES(quiet_hours_enabled), '
            . 'quiet_start_local = VALUES(quiet_start_local), quiet_end_local = VALUES(quiet_end_local)'
        );
        $statement->execute(['subscription_id' => $subscriptionId, 'support_id' => $supportId] + $device);
        $preference = $connection->prepare(
            'INSERT INTO web_push_notification_preferences (subscription_id, notification_type, enabled, schedule_mode, '
            . 'lead_minutes, delivery_local_time, delivery_day_offset, quiet_policy, parameters_json) '
            . 'VALUES (:subscription_id, :notification_type, :enabled, :schedule_mode, :lead_minutes, '
            . ':delivery_local_time, :delivery_day_offset, :quiet_policy, :parameters_json) '
            . 'ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
        );
        foreach (astronomyPushNotificationTypes($connection) as $notificationType) {
            if ((int) $notificationType['available'] !== 1 || (int) $notificationType['admin_only'] === 1) continue;
            $typeCode = (string) $notificationType['notification_type'];
            $enabled = isset($input['notification_preferences'][$typeCode])
                || ($typeCode === 'moonrise' && filter_var($input['moonrise_enabled'] ?? false, FILTER_VALIDATE_BOOL));
            $preference->execute([
                'subscription_id' => $subscriptionId, 'notification_type' => $typeCode,
                'enabled' => $enabled ? 1 : 0,
                'schedule_mode' => $notificationType['default_schedule_mode'],
                'lead_minutes' => $notificationType['default_lead_minutes'],
                'delivery_local_time' => $notificationType['default_delivery_time'],
                'delivery_day_offset' => $notificationType['default_delivery_day_offset'],
                'quiet_policy' => $notificationType['default_quiet_policy'],
                'parameters_json' => ($defaults = astronomyPushDefaultParameters($typeCode)) === []
                    ? null : json_encode($defaults, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        }
        if ($ownsTransaction) $connection->commit();
    } catch (Throwable $exception) {
        if ($ownsTransaction && $connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

/** @return list<array<string,mixed>> */
function astronomyPushRecentLog(PDO $connection, int $subscriptionId, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $statement = $connection->prepare(
        'SELECT notification_type, event_key, event_time_utc, notification_time_utc, status, decision_reason, '
        . 'attempted_at, sent_at, error_message, title_sent, body_sent, target_url_sent FROM web_push_notification_log '
        . 'WHERE subscription_id = :subscription_id ORDER BY attempted_at DESC, id DESC LIMIT ' . $limit
    );
    $statement->execute(['subscription_id' => $subscriptionId]);
    return $statement->fetchAll();
}

/** @return array{claimed:bool,reason:string,attempted_at:string} */
function astronomyPushClaim(PDO $connection, int $subscriptionId, string $notificationType, string $eventKey,
    DateTimeImmutable $eventTimeUtc, DateTimeImmutable $notificationTimeUtc, DateTimeImmutable $attemptedAtUtc,
    int $staleMinutes = ASTRONOMY_PUSH_STALE_MINUTES): array
{
    $values = [
        'subscription_id' => $subscriptionId, 'notification_type' => $notificationType, 'event_key' => $eventKey,
        'event_time_utc' => astronomyPushUtc($eventTimeUtc)->format('Y-m-d H:i:s'),
        'notification_time_utc' => astronomyPushUtc($notificationTimeUtc)->format('Y-m-d H:i:s'),
        'attempted_at' => astronomyPushUtc($attemptedAtUtc)->format('Y-m-d H:i:s'),
    ];
    $insert = $connection->prepare(
        'INSERT IGNORE INTO web_push_notification_log (subscription_id, notification_type, event_key, event_time_utc, '
        . 'notification_time_utc, status, decision_reason, attempted_at) VALUES (:subscription_id, :notification_type, '
        . ":event_key, :event_time_utc, :notification_time_utc, 'processing', NULL, :attempted_at)"
    );
    $insert->execute($values);
    if ($insert->rowCount() === 1) return ['claimed' => true, 'reason' => 'new', 'attempted_at' => $values['attempted_at']];
    $retry = $connection->prepare(
        "UPDATE web_push_notification_log SET status = 'processing', decision_reason = NULL, attempted_at = :attempted_at, "
        . 'notification_time_utc = :notification_time_utc, sent_at = NULL, error_message = NULL '
        . 'WHERE subscription_id = :subscription_id AND notification_type = :notification_type AND event_key = :event_key '
        . "AND event_time_utc = :event_time_utc AND (status = 'failed' OR (status = 'processing' AND attempted_at <= :stale_before))"
    );
    $retry->execute($values + [
        'stale_before' => astronomyPushUtc($attemptedAtUtc)->modify('-' . $staleMinutes . ' minutes')->format('Y-m-d H:i:s'),
    ]);
    if ($retry->rowCount() === 1) return ['claimed' => true, 'reason' => 'retry', 'attempted_at' => $values['attempted_at']];
    return ['claimed' => false, 'reason' => 'deduplicated', 'attempted_at' => $values['attempted_at']];
}

function astronomyPushComplete(PDO $connection, int $subscriptionId, string $notificationType, string $eventKey,
    DateTimeImmutable $eventTimeUtc, string $attemptedAt, string $status, ?string $errorMessage = null,
    ?string $decisionReason = null, ?array $content = null): bool
{
    if (!in_array($status, ['sent', 'failed', 'skipped_quiet_hours', 'skipped_unavailable'], true)) {
        throw new InvalidArgumentException('Estado final de notificación no válido.');
    }
    $error = $status === 'failed' ? astronomyWebPushSanitizeError($errorMessage) : null;
    $statement = $connection->prepare(
        'UPDATE web_push_notification_log SET status = :status, decision_reason = :decision_reason, sent_at = :sent_at, '
        . 'error_message = :error_message, title_sent = :title_sent, body_sent = :body_sent, '
        . 'target_url_sent = :target_url_sent WHERE subscription_id = :subscription_id '
        . 'AND notification_type = :notification_type AND event_key = :event_key AND event_time_utc = :event_time_utc '
        . "AND status = 'processing' AND attempted_at = :attempted_at"
    );
    $statement->execute([
        'status' => $status, 'decision_reason' => $decisionReason,
        'sent_at' => $status === 'sent' ? gmdate('Y-m-d H:i:s') : null, 'error_message' => $error,
        'title_sent' => $content['title'] ?? null, 'body_sent' => $content['body'] ?? null,
        'target_url_sent' => $content['url'] ?? null,
        'subscription_id' => $subscriptionId, 'notification_type' => $notificationType, 'event_key' => $eventKey,
        'event_time_utc' => astronomyPushUtc($eventTimeUtc)->format('Y-m-d H:i:s'), 'attempted_at' => $attemptedAt,
    ]);
    return $statement->rowCount() === 1;
}

/**
 * @param callable(array<string,mixed>,DateTimeImmutable):list<array<string,mixed>>|null $eventResolver
 * @param callable(array<string,mixed>,array<string,string>):array<string,mixed>|null $sender
 * @param callable(string):void|null $output
 * @return array<string,int>
 */
function astronomyPushProcess(PDO $connection, array $devices, DateTimeImmutable $nowUtc, bool $dryRun,
    ?callable $eventResolver = null, ?callable $sender = null, ?callable $output = null): array
{
    $eventResolver ??= static fn(array $device, DateTimeImmutable $now): array => astronomyPushProviderEvents($device, $now);
    $sender ??= static function (array $device, array $message) use ($connection): array {
        return astronomyWebPushSend($connection, loadWebPushServerConfig(), (int) $device['subscription_id'],
            $message['title'], $message['body'], $message['url']);
    };
    $output ??= static function (string $message): void {};
    $counts = ['devices' => count($devices), 'evaluated' => 0, 'due' => 0, 'sent' => 0, 'failed' => 0,
        'skipped' => 0, 'deduplicated' => 0];
    $eventCache = [];
    foreach ($devices as $device) {
        $notificationType = (string) ($device['notification_type'] ?? ASTRONOMY_PUSH_MOONRISE_TYPE);
        $device['notification_type'] = $notificationType;
        $identity = 'Dispositivo ' . $device['device_name'] . ' (ID ' . (int) $device['subscription_id'] . ')';
        if (isset($device['_configuration_error'])) {
            $counts['failed']++;
            $output($identity . ': ' . $device['_configuration_error']);
            continue;
        }
        $cacheKey = implode('|', [number_format((float) $device['latitude'], 6, '.', ''),
            number_format((float) $device['longitude'], 6, '.', ''), (string) $device['timezone'], '0',
            $notificationType, json_encode(astronomyPushEventParameters($device), JSON_UNESCAPED_SLASHES)]);
        if (!array_key_exists($cacheKey, $eventCache)) {
            try { $eventCache[$cacheKey] = $eventResolver($device, $nowUtc); }
            catch (Throwable $exception) {
                $counts['failed']++;
                $output($identity . ': error del proveedor ' . $notificationType . '; se continúa con los demás tipos.');
                continue;
            }
        }
        $output($identity . '; ubicación ' . $device['location_name'] . '.');
        foreach ($eventCache[$cacheKey] as $event) {
            $counts['evaluated']++;
            if (!isset($event['event_key']) || !($event['event_time_utc'] ?? null) instanceof DateTimeImmutable) {
                $counts['failed']++;
                $output('Evento inválido devuelto por ' . $notificationType . '.');
                continue;
            }
            $nominalUtc = astronomyPushScheduledNotificationTime($event['event_time_utc'], $device, $device);
            $quietPolicy = (string) ($device['quiet_policy'] ?? 'omit');
            $notificationUtc = $nominalUtc;
            if ($quietPolicy === 'postpone' && astronomyPushIsQuietAt($nominalUtc, $device)) {
                $notificationUtc = astronomyPushQuietEnd($nominalUtc, $device);
                if ($notificationUtc->getTimestamp() - $nominalUtc->getTimestamp() > 12 * 3600
                    || $notificationUtc >= $event['event_time_utc']) {
                    $output('Decisión: la postergación superaría 12 horas o el instante del evento.');
                    continue;
                }
            }
            if (in_array($notificationType, [ASTRONOMY_PUSH_ECLIPSE_TYPE, ASTRONOMY_PUSH_LUNAR_CONJUNCTION_TYPE], true)
                && $notificationUtc >= $event['event_time_utc']) {
                $output('Decisión: el aviso fijo ya no tendría sentido temporal.');
                continue;
            }
            $output('Evento ' . $event['event_time_local']->format('Y-m-d H:i:s T') . '; aviso nominal '
                . $nominalUtc->setTimezone(new DateTimeZone((string) $device['timezone']))->format('Y-m-d H:i:s T')
                . ($notificationUtc != $nominalUtc ? '; entrega pospuesta '
                    . $notificationUtc->setTimezone(new DateTimeZone((string) $device['timezone']))->format('Y-m-d H:i:s T') : '') . '.');
            if (!astronomyPushIsDue($notificationUtc, $nowUtc, astronomyPushLookbackMinutes($notificationType))) {
                $output('Decisión: fuera de la ventana retrospectiva.');
                continue;
            }
            $counts['due']++;
            $quiet = $quietPolicy === 'omit' && astronomyPushIsQuietAt($notificationUtc, $device);
            $typeUnavailable = !isset($device['notification_type_config'])
                || (int) $device['notification_type_config']['available'] !== 1
                || (int) $device['notification_type_config']['admin_only'] !== 0;
            if ($dryRun) {
                $output($typeUnavailable ? 'Decisión: omitiría por tipo global no disponible; dry-run sin escritura.'
                    : ($quiet ? 'Decisión: omitiría por No molestar; dry-run sin escritura.'
                        : 'Decisión: enviaría; dry-run sin escritura ni envío.'));
                continue;
            }
            $claim = astronomyPushClaim($connection, (int) $device['subscription_id'], $notificationType,
                (string) $event['event_key'], $event['event_time_utc'], $notificationUtc, $nowUtc);
            if (!$claim['claimed']) {
                $counts['deduplicated']++;
                $output('Decisión: omitida por deduplicación.');
                continue;
            }
            if ($typeUnavailable) {
                $typeError = isset($device['_notification_type_error']) ? 'Catálogo astronómico no válido.' : null;
                if ($typeError !== null) {
                    astronomyPushComplete($connection, (int) $device['subscription_id'], $notificationType,
                        (string) $event['event_key'], $event['event_time_utc'], $claim['attempted_at'],
                        'failed', $typeError);
                    $counts['failed']++;
                    $output('Decisión: error de catálogo astronómico.');
                } else {
                    astronomyPushComplete($connection, (int) $device['subscription_id'], $notificationType,
                        (string) $event['event_key'], $event['event_time_utc'], $claim['attempted_at'],
                        'skipped_unavailable', null, 'notification_type_unavailable');
                    $counts['skipped']++;
                    $output('Decisión: tipo global no disponible.');
                }
                continue;
            }
            try {
                $content = astronomyPushRenderNotification($device['notification_type_config'], $event, $device, $device);
            } catch (Throwable $exception) {
                astronomyPushComplete($connection, (int) $device['subscription_id'], $notificationType,
                    (string) $event['event_key'], $event['event_time_utc'], $claim['attempted_at'],
                    'failed', 'No se pudo renderizar la notificación astronómica.');
                $counts['failed']++;
                $output('Decisión: plantilla astronómica inválida.');
                continue;
            }
            if ($quiet) {
                astronomyPushComplete($connection, (int) $device['subscription_id'], $notificationType,
                    (string) $event['event_key'], $event['event_time_utc'], $claim['attempted_at'],
                    'skipped_quiet_hours', null, 'quiet_hours', $content);
                $counts['skipped']++;
                $output('Decisión: omitida por No molestar; estado skipped_quiet_hours.');
                continue;
            }
            try {
                $result = $sender($device, $content);
                $success = (int) ($result['success'] ?? 0) === 1 && (int) ($result['failed'] ?? 0) === 0;
                $error = $success ? null : (string) ($result['errors'][0]['message'] ?? 'Error de envío Web Push');
            } catch (Throwable $exception) {
                $success = false;
                $error = 'Error de envío Web Push (' . get_debug_type($exception) . ')';
            }
            astronomyPushComplete($connection, (int) $device['subscription_id'], $notificationType,
                (string) $event['event_key'], $event['event_time_utc'], $claim['attempted_at'],
                $success ? 'sent' : 'failed', $error, null, $content);
            $counts[$success ? 'sent' : 'failed']++;
            $output($success ? 'Envío exitoso.' : 'Envío fallido; disponible para reintento dentro de la ventana.');
        }
    }
    return $counts;
}

/** @return array{id:int,scheduled_at_utc:DateTimeImmutable} */
function astronomyPushScheduleTest(PDO $connection, int $subscriptionId, int $delayMinutes,
    bool $respectQuietHours, ?DateTimeImmutable $nowUtc = null): array
{
    if (!in_array($delayMinutes, [1, 3, 5], true)) {
        throw new InvalidArgumentException('El plazo de la prueba debe ser de 1, 3 o 5 minutos.');
    }
    $exists = $connection->prepare('SELECT 1 FROM web_push_subscriptions WHERE id = :subscription_id');
    $exists->execute(['subscription_id' => $subscriptionId]);
    if ($exists->fetchColumn() === false) {
        throw new InvalidArgumentException('La suscripción elegida no existe.');
    }
    $testType = astronomyPushNotificationType($connection, ASTRONOMY_PUSH_TEST_TYPE);
    if ($testType === null || (int) $testType['available'] !== 1 || (int) $testType['admin_only'] !== 1) {
        throw new InvalidArgumentException('Las pruebas programadas no están disponibles globalmente.');
    }
    $scheduled = astronomyPushUtc($nowUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+' . $delayMinutes . ' minutes');
    $statement = $connection->prepare(
        "INSERT INTO web_push_scheduled_tests (subscription_id, scheduled_at_utc, respect_quiet_hours, status) "
        . "VALUES (:subscription_id, :scheduled_at_utc, :respect_quiet_hours, 'pending')"
    );
    $statement->execute([
        'subscription_id' => $subscriptionId,
        'scheduled_at_utc' => $scheduled->format('Y-m-d H:i:s'),
        'respect_quiet_hours' => $respectQuietHours ? 1 : 0,
    ]);
    return ['id' => (int) $connection->lastInsertId(), 'scheduled_at_utc' => $scheduled];
}

function astronomyPushCancelScheduledTest(PDO $connection, int $subscriptionId, int $testId,
    ?DateTimeImmutable $nowUtc = null): bool
{
    if ($testId < 1) throw new InvalidArgumentException('La prueba elegida no es válida.');
    $statement = $connection->prepare(
        "UPDATE web_push_scheduled_tests SET status = 'cancelled', processed_at = :processed_at, "
        . 'error_message = NULL WHERE id = :id AND subscription_id = :subscription_id AND status = \'pending\''
    );
    $statement->execute([
        'processed_at' => astronomyPushUtc($nowUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s'),
        'id' => $testId,
        'subscription_id' => $subscriptionId,
    ]);
    return $statement->rowCount() === 1;
}

/** @return list<array<string,mixed>> */
function astronomyPushScheduledTests(PDO $connection, int $subscriptionId, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $statement = $connection->prepare(
        'SELECT id, subscription_id, scheduled_at_utc, respect_quiet_hours, status, created_at, processed_at, '
        . 'notification_log_id, error_message FROM web_push_scheduled_tests WHERE subscription_id = :subscription_id '
        . 'ORDER BY created_at DESC, id DESC LIMIT ' . $limit
    );
    $statement->execute(['subscription_id' => $subscriptionId]);
    return $statement->fetchAll();
}

function astronomyPushScheduledTestDevice(PDO $connection, int $subscriptionId): ?array
{
    $statement = $connection->prepare(
        'SELECT s.id AS subscription_id, s.active, d.device_name, d.notifications_enabled, d.location_name, '
        . 'd.latitude, d.longitude, d.timezone, d.quiet_hours_enabled, d.quiet_start_local, d.quiet_end_local '
        . 'FROM web_push_subscriptions s LEFT JOIN web_push_device_config d ON d.subscription_id = s.id '
        . 'WHERE s.id = :subscription_id'
    );
    $statement->execute(['subscription_id' => $subscriptionId]);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function astronomyPushClaimScheduledTest(PDO $connection, int $testId): bool
{
    $statement = $connection->prepare(
        "UPDATE web_push_scheduled_tests SET status = 'processing', processed_at = NULL, error_message = NULL "
        . "WHERE id = :id AND status = 'pending'"
    );
    $statement->execute(['id' => $testId]);
    return $statement->rowCount() === 1;
}

function astronomyPushNotificationLogId(PDO $connection, int $subscriptionId, string $notificationType,
    string $eventKey, DateTimeImmutable $eventTimeUtc): ?int
{
    $statement = $connection->prepare(
        'SELECT id FROM web_push_notification_log WHERE subscription_id = :subscription_id '
        . 'AND notification_type = :notification_type AND event_key = :event_key AND event_time_utc = :event_time_utc'
    );
    $statement->execute([
        'subscription_id' => $subscriptionId,
        'notification_type' => $notificationType,
        'event_key' => $eventKey,
        'event_time_utc' => astronomyPushUtc($eventTimeUtc)->format('Y-m-d H:i:s'),
    ]);
    $id = $statement->fetchColumn();
    return $id === false ? null : (int) $id;
}

function astronomyPushFinishScheduledTest(PDO $connection, int $testId, string $status,
    DateTimeImmutable $processedAtUtc, ?int $notificationLogId = null, ?string $errorMessage = null): bool
{
    if (!in_array($status, ['sent', 'failed', 'skipped_quiet_hours', 'skipped_unavailable', 'expired'], true)) {
        throw new InvalidArgumentException('Estado final de prueba no válido.');
    }
    $statement = $connection->prepare(
        'UPDATE web_push_scheduled_tests SET status = :status, processed_at = :processed_at, '
        . 'notification_log_id = :notification_log_id, error_message = :error_message '
        . "WHERE id = :id AND status = 'processing'"
    );
    $statement->execute([
        'status' => $status,
        'processed_at' => astronomyPushUtc($processedAtUtc)->format('Y-m-d H:i:s'),
        'notification_log_id' => $notificationLogId,
        'error_message' => $status === 'failed' ? astronomyWebPushSanitizeError($errorMessage) : null,
        'id' => $testId,
    ]);
    return $statement->rowCount() === 1;
}

/**
 * @param callable(array<string,mixed>,array<string,string>):array<string,mixed>|null $sender
 * @param callable(string):void|null $output
 * @return array<string,int>
 */
function astronomyPushProcessScheduledTests(PDO $connection, DateTimeImmutable $nowUtc, bool $dryRun,
    ?int $subscriptionId = null, ?callable $sender = null, ?callable $output = null): array
{
    $nowUtc = astronomyPushUtc($nowUtc);
    $cutoff = $nowUtc->modify('-' . ASTRONOMY_PUSH_TEST_LOOKBACK_MINUTES . ' minutes');
    $sender ??= static function (array $device, array $message) use ($connection): array {
        return astronomyWebPushSend($connection, loadWebPushServerConfig(), (int) $device['subscription_id'],
            $message['title'], $message['body'], $message['url']);
    };
    $output ??= static function (string $message): void {};
    $testType = null;
    $testTypeError = null;
    try { $testType = astronomyPushNotificationType($connection, ASTRONOMY_PUSH_TEST_TYPE); }
    catch (Throwable $exception) { $testTypeError = 'El catálogo de pruebas no es válido.'; }
    $testTypeUnavailable = $testType === null || (int) $testType['available'] !== 1
        || (int) $testType['admin_only'] !== 1;
    $sql = "SELECT id, subscription_id, scheduled_at_utc, respect_quiet_hours FROM web_push_scheduled_tests "
        . "WHERE status = 'pending' AND scheduled_at_utc <= :now_utc";
    $parameters = ['now_utc' => $nowUtc->format('Y-m-d H:i:s')];
    if ($subscriptionId !== null) {
        $sql .= ' AND subscription_id = :subscription_id';
        $parameters['subscription_id'] = $subscriptionId;
    }
    $sql .= ' ORDER BY scheduled_at_utc, id';
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $tests = $statement->fetchAll();
    $counts = ['pending_due' => count($tests), 'sent' => 0, 'failed' => 0, 'skipped' => 0,
        'expired' => 0, 'deduplicated' => 0];
    foreach ($tests as $test) {
        $testId = (int) $test['id'];
        $scheduled = new DateTimeImmutable((string) $test['scheduled_at_utc'], new DateTimeZone('UTC'));
        $prefix = 'Prueba ' . $testId . ' para suscripción ' . (int) $test['subscription_id'] . ': ';
        if ($scheduled < $cutoff) {
            if ($dryRun) {
                $output($prefix . 'vencida; dry-run sin escritura.');
            } elseif (astronomyPushClaimScheduledTest($connection, $testId)) {
                astronomyPushFinishScheduledTest($connection, $testId, 'expired', $nowUtc);
                $counts['expired']++;
                $output($prefix . 'estado expired.');
            }
            continue;
        }
        $device = astronomyPushScheduledTestDevice($connection, (int) $test['subscription_id']);
        $deviceError = null;
        if ($device === null) $deviceError = 'La suscripción de la prueba ya no existe.';
        elseif ((int) $device['active'] !== 1) $deviceError = 'La suscripción de la prueba está técnicamente inactiva.';
        elseif ($device['device_name'] === null) $deviceError = 'La suscripción de la prueba no tiene configuración.';
        elseif ((int) $device['notifications_enabled'] !== 1) $deviceError = 'Las notificaciones generales están deshabilitadas.';
        else {
            try { $device = array_replace($device, astronomyPushValidateDeviceConfig($device)); }
            catch (Throwable $exception) { $deviceError = 'La configuración del dispositivo no es válida.'; }
        }
        $quiet = $deviceError === null && (int) $test['respect_quiet_hours'] === 1
            && astronomyPushIsQuietAt($scheduled, $device);
        if ($dryRun) {
            $output($prefix . ($testTypeError !== null ? 'fallaría: catálogo inválido.'
                : ($testTypeUnavailable ? 'se omitiría por tipo global no disponible.'
                    : ($deviceError !== null ? 'fallaría: ' . $deviceError
                        : ($quiet ? 'se omitiría por No molestar.' : 'se enviaría.'))))
                . ' Dry-run sin escritura ni envío.');
            continue;
        }
        if (!astronomyPushClaimScheduledTest($connection, $testId)) continue;
        $eventKey = 'test:' . $testId;
        $claim = astronomyPushClaim($connection, (int) $test['subscription_id'], ASTRONOMY_PUSH_TEST_TYPE,
            $eventKey, $scheduled, $scheduled, $nowUtc);
        $logId = astronomyPushNotificationLogId($connection, (int) $test['subscription_id'],
            ASTRONOMY_PUSH_TEST_TYPE, $eventKey, $scheduled);
        if (!$claim['claimed']) {
            astronomyPushFinishScheduledTest($connection, $testId, 'failed', $nowUtc, $logId,
                'La prueba ya tenía un registro de deduplicación.');
            $counts['deduplicated']++;
            $counts['failed']++;
            $output($prefix . 'omitida por deduplicación.');
            continue;
        }
        if ($testTypeError !== null) {
            astronomyPushComplete($connection, (int) $test['subscription_id'], ASTRONOMY_PUSH_TEST_TYPE,
                $eventKey, $scheduled, $claim['attempted_at'], 'failed', $testTypeError);
            astronomyPushFinishScheduledTest($connection, $testId, 'failed', $nowUtc, $logId, $testTypeError);
            $counts['failed']++;
            $output($prefix . 'catálogo inválido.');
            continue;
        }
        if ($testTypeUnavailable) {
            astronomyPushComplete($connection, (int) $test['subscription_id'], ASTRONOMY_PUSH_TEST_TYPE,
                $eventKey, $scheduled, $claim['attempted_at'], 'skipped_unavailable', null,
                'notification_type_unavailable');
            astronomyPushFinishScheduledTest($connection, $testId, 'skipped_unavailable', $nowUtc, $logId);
            $counts['skipped']++;
            $output($prefix . 'tipo global no disponible.');
            continue;
        }
        if ($deviceError !== null) {
            astronomyPushComplete($connection, (int) $test['subscription_id'], ASTRONOMY_PUSH_TEST_TYPE,
                $eventKey, $scheduled, $claim['attempted_at'], 'failed', $deviceError);
            astronomyPushFinishScheduledTest($connection, $testId, 'failed', $nowUtc, $logId, $deviceError);
            $counts['failed']++;
            $output($prefix . astronomyWebPushSanitizeError($deviceError));
            continue;
        }
        try {
            $content = astronomyPushRenderNotification($testType, ['event_time_utc' => $scheduled], $device);
        } catch (Throwable $exception) {
            $messageError = 'No se pudo renderizar la notificación de prueba.';
            astronomyPushComplete($connection, (int) $test['subscription_id'], ASTRONOMY_PUSH_TEST_TYPE,
                $eventKey, $scheduled, $claim['attempted_at'], 'failed', $messageError);
            astronomyPushFinishScheduledTest($connection, $testId, 'failed', $nowUtc, $logId, $messageError);
            $counts['failed']++;
            $output($prefix . 'plantilla inválida.');
            continue;
        }
        if ($quiet) {
            astronomyPushComplete($connection, (int) $test['subscription_id'], ASTRONOMY_PUSH_TEST_TYPE,
                $eventKey, $scheduled, $claim['attempted_at'], 'skipped_quiet_hours', null, 'quiet_hours', $content);
            astronomyPushFinishScheduledTest($connection, $testId, 'skipped_quiet_hours', $nowUtc, $logId);
            $counts['skipped']++;
            $output($prefix . 'omitida por No molestar.');
            continue;
        }
        try {
            $result = $sender($device, $content);
            $success = (int) ($result['success'] ?? 0) === 1 && (int) ($result['failed'] ?? 0) === 0;
            $error = $success ? null : (string) ($result['errors'][0]['message'] ?? 'Error de envío Web Push');
        } catch (Throwable $exception) {
            $success = false;
            $error = 'Error de envío Web Push (' . get_debug_type($exception) . ')';
        }
        $status = $success ? 'sent' : 'failed';
        astronomyPushComplete($connection, (int) $test['subscription_id'], ASTRONOMY_PUSH_TEST_TYPE,
            $eventKey, $scheduled, $claim['attempted_at'], $status, $error, null, $content);
        astronomyPushFinishScheduledTest($connection, $testId, $status, $nowUtc, $logId, $error);
        $counts[$status]++;
        $output($prefix . ($success ? 'envío exitoso.' : 'envío fallido.'));
    }
    return $counts;
}

/** @return array<string,mixed> */
function astronomyPushRunReminderCycle(PDO $connection, DateTimeImmutable $nowUtc, bool $dryRun = false,
    ?int $subscriptionId = null, ?callable $detailOutput = null): array
{
    $detailOutput ??= static function (string $message): void {};
    $devices = astronomyPushConfiguredDevices($connection, $subscriptionId);
    $byType = [];
    foreach ([ASTRONOMY_PUSH_MOONRISE_TYPE, ASTRONOMY_PUSH_ECLIPSE_TYPE,
        ASTRONOMY_PUSH_LUNAR_CONJUNCTION_TYPE, ASTRONOMY_PUSH_SATELLITE_TRANSIT_TYPE] as $type) {
        $typeDevices = array_values(array_filter($devices,
            static fn(array $device): bool => (string) ($device['notification_type'] ?? '') === $type));
        $byType[$type] = astronomyPushProviderCadenceDue($type, $nowUtc)
            ? astronomyPushProcess($connection, $typeDevices, $nowUtc, $dryRun, null, null, $detailOutput)
            : ['devices' => count($typeDevices), 'evaluated' => 0, 'due' => 0, 'sent' => 0, 'failed' => 0,
                'skipped' => 0, 'deduplicated' => 0];
    }
    $tests = astronomyPushProcessScheduledTests(
        $connection, $nowUtc, $dryRun, $subscriptionId, null, $detailOutput
    );
    $evaluated = $sent = $skipped = $errors = 0;
    foreach ($byType as $counts) {
        $evaluated += $counts['evaluated'];
        $sent += $counts['sent'];
        $skipped += $counts['skipped'] + $counts['deduplicated'];
        $errors += $counts['failed'];
    }
    return [
        'moonrise' => $byType[ASTRONOMY_PUSH_MOONRISE_TYPE],
        'eclipse' => $byType[ASTRONOMY_PUSH_ECLIPSE_TYPE],
        'lunar_conjunction' => $byType[ASTRONOMY_PUSH_LUNAR_CONJUNCTION_TYPE],
        'satellite_transit' => $byType[ASTRONOMY_PUSH_SATELLITE_TRANSIT_TYPE],
        'tests' => $tests,
        'summary' => [
            'tests_processed' => $tests['sent'] + $tests['failed'] + $tests['skipped'] + $tests['expired'],
            'evaluated' => $evaluated + $tests['pending_due'],
            'sent' => $sent + $tests['sent'],
            'skipped' => $skipped + $tests['skipped'] + $tests['expired'] + $tests['deduplicated'],
            'errors' => $errors + $tests['failed'],
        ],
    ];
}
