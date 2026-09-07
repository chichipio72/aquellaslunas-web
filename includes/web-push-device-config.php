<?php

declare(strict_types=1);

require_once __DIR__ . '/web-push-astronomy.php';

const ASTRONOMY_PUSH_DEVICE_CSRF_KEY = 'astronomy_push_device_csrf';

final class AstronomyPushSubscriptionAccessException extends RuntimeException
{
}

function astronomyPushDeviceStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $localSession = $_COOKIE['aquellas_lunas_local'] ?? null;
    $sessionName = is_string($localSession) && preg_match('/^[A-Za-z0-9,-]{1,128}$/', $localSession) === 1
        ? 'aquellas_lunas_local' : 'aquellas_lunas_notifications';
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) throw new RuntimeException('No se pudo iniciar la sesión de notificaciones.');
}

function astronomyPushDeviceCsrfToken(): string
{
    $token = $_SESSION[ASTRONOMY_PUSH_DEVICE_CSRF_KEY] ?? null;
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[ASTRONOMY_PUSH_DEVICE_CSRF_KEY] = $token;
    }
    return $token;
}

function astronomyPushDeviceCsrfIsValid(mixed $token): bool
{
    $stored = $_SESSION[ASTRONOMY_PUSH_DEVICE_CSRF_KEY] ?? null;
    return is_string($token) && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
}

function astronomyPushDeviceRequestIsSameOrigin(array $server): bool
{
    $fetchSite = strtolower(trim((string) ($server['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true)) return false;
    $origin = trim((string) ($server['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') return true;
    $originHost = parse_url($origin, PHP_URL_HOST);
    $originPort = parse_url($origin, PHP_URL_PORT);
    $received = is_string($originHost)
        ? strtolower($originHost) . ($originPort !== null ? ':' . $originPort : '') : '';
    return $received !== '' && hash_equals(strtolower((string) ($server['HTTP_HOST'] ?? '')), $received);
}

/** @return array<string,mixed> */
function astronomyPushIdentifyCurrentSubscription(PDO $connection, array $payload): array
{
    $subscription = astronomyWebPushValidateSubscription($payload);
    $statement = $connection->prepare(
        'SELECT id, active, p256dh, auth, user_agent FROM web_push_subscriptions '
        . 'WHERE endpoint_hash = :endpoint_hash AND endpoint = :endpoint LIMIT 1'
    );
    $statement->execute([
        'endpoint_hash' => hash('sha256', $subscription['endpoint'], true),
        'endpoint' => $subscription['endpoint'],
    ]);
    $row = $statement->fetch();
    if (!is_array($row) || (int) $row['active'] !== 1
        || !hash_equals((string) $row['p256dh'], $subscription['p256dh'])
        || !hash_equals((string) $row['auth'], $subscription['auth'])) {
        throw new AstronomyPushSubscriptionAccessException('La suscripción actual no está registrada o ya no está activa.');
    }
    return $row;
}

function astronomyPushSuggestedDeviceName(?string $userAgent): string
{
    $agent = (string) $userAgent;
    if (stripos($agent, 'iPhone') !== false || stripos($agent, 'iPad') !== false) return 'iPhone';
    if (stripos($agent, 'Android') !== false) return 'Celular Android';
    if (stripos($agent, 'Edg/') !== false) return 'Windows Edge';
    if (stripos($agent, 'Firefox') !== false) return 'Firefox escritorio';
    if (stripos($agent, 'Chrome') !== false) return 'Chrome escritorio';
    return 'Mi dispositivo';
}

/** @return list<array<string,mixed>> */
function astronomyPushPublicNotificationTypes(PDO $connection, int $subscriptionId): array
{
    $statement = $connection->prepare(
        'SELECT t.notification_type, t.display_name, t.description, t.default_schedule_mode, '
        . 't.default_lead_minutes, t.default_delivery_time, t.default_delivery_day_offset, '
        . 'p.enabled, p.schedule_mode, p.lead_minutes, p.delivery_local_time, p.delivery_day_offset '
        . 'FROM web_push_notification_types t LEFT JOIN web_push_notification_preferences p '
        . 'ON p.notification_type = t.notification_type AND p.subscription_id = :subscription_id '
        . 'WHERE t.available = 1 AND t.admin_only = 0 ORDER BY t.sort_order, t.display_name'
    );
    $statement->execute(['subscription_id' => $subscriptionId]);
    return $statement->fetchAll();
}

/** @return array<string,mixed> */
function astronomyPushCurrentDeviceState(PDO $connection, array $subscriptionPayload): array
{
    $subscription = astronomyPushIdentifyCurrentSubscription($connection, $subscriptionPayload);
    $subscriptionId = (int) $subscription['id'];
    $statement = $connection->prepare(
        'SELECT support_id, device_name, notifications_enabled, location_name, latitude, longitude, timezone, '
        . 'quiet_hours_enabled, quiet_start_local, quiet_end_local, updated_at '
        . 'FROM web_push_device_config WHERE subscription_id = :subscription_id'
    );
    $statement->execute(['subscription_id' => $subscriptionId]);
    $device = $statement->fetch();
    $types = astronomyPushPublicNotificationTypes($connection, $subscriptionId);
    return [
        'configured' => is_array($device),
        'suggested_device_name' => astronomyPushSuggestedDeviceName($subscription['user_agent'] ?? null),
        'device' => is_array($device) ? [
            'support_id' => (string) $device['support_id'],
            'device_name' => (string) $device['device_name'],
            'notifications_enabled' => (int) $device['notifications_enabled'] === 1,
            'location_name' => (string) $device['location_name'],
            'latitude' => (float) $device['latitude'],
            'longitude' => (float) $device['longitude'],
            'timezone' => (string) $device['timezone'],
            'quiet_hours_enabled' => (int) $device['quiet_hours_enabled'] === 1,
            'quiet_start_local' => $device['quiet_start_local'] !== null ? substr((string) $device['quiet_start_local'], 0, 5) : '23:00',
            'quiet_end_local' => $device['quiet_end_local'] !== null ? substr((string) $device['quiet_end_local'], 0, 5) : '08:00',
            'updated_at' => (string) $device['updated_at'],
        ] : null,
        'notification_types' => array_map(static function (array $type): array {
            $lead = $type['lead_minutes'] !== null ? (int) $type['lead_minutes']
                : ($type['default_lead_minutes'] !== null ? (int) $type['default_lead_minutes'] : null);
            return [
                'notification_type' => (string) $type['notification_type'],
                'display_name' => (string) $type['display_name'],
                'description' => (string) ($type['description'] ?? ''),
                'enabled' => (int) ($type['enabled'] ?? 0) === 1,
                'schedule_mode' => (string) ($type['schedule_mode'] ?? $type['default_schedule_mode']),
                'lead_minutes' => $lead,
                'delivery_time' => $type['delivery_local_time'] !== null
                    ? substr((string) $type['delivery_local_time'], 0, 5)
                    : ($type['default_delivery_time'] !== null ? substr((string) $type['default_delivery_time'], 0, 5) : null),
                'delivery_day_offset' => (int) ($type['delivery_day_offset'] ?? $type['default_delivery_day_offset']),
            ];
        }, $types),
    ];
}

/** @return array<string,mixed> */
function astronomyPushSaveCurrentDevice(PDO $connection, array $subscriptionPayload, array $input): array
{
    $subscription = astronomyPushIdentifyCurrentSubscription($connection, $subscriptionPayload);
    $allowedTypes = astronomyPushPublicNotificationTypes($connection, (int) $subscription['id']);
    $allowedCodes = array_column($allowedTypes, 'notification_type');
    $requested = $input['notification_preferences'] ?? [];
    if (!is_array($requested)) throw new InvalidArgumentException('Los tipos de aviso no son válidos.');
    $normalizedPreferences = [];
    foreach ($requested as $typeCode => $enabled) {
        if (!is_string($typeCode) || !in_array($typeCode, $allowedCodes, true)) {
            throw new InvalidArgumentException('Uno de los tipos de aviso no está disponible.');
        }
        if (filter_var($enabled, FILTER_VALIDATE_BOOL)) $normalizedPreferences[$typeCode] = true;
    }
    $input['notification_preferences'] = $normalizedPreferences;
    astronomyPushSaveDevice($connection, (int) $subscription['id'], $input);
    return astronomyPushCurrentDeviceState($connection, $subscriptionPayload);
}

/** @return array<string,mixed> */
function astronomyPushUpdateCurrentDeviceLocation(PDO $connection, array $subscriptionPayload, array $input): array
{
    $subscription = astronomyPushIdentifyCurrentSubscription($connection, $subscriptionPayload);
    $subscriptionId = (int) $subscription['id'];
    $locationName = trim((string) ($input['location_name'] ?? ''));
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $timezoneName = trim((string) ($input['timezone'] ?? ''));
    if ($locationName === '' || strlen($locationName) > 150 || preg_match('/[\x00-\x1F\x7F]/', $locationName)) {
        throw new InvalidArgumentException('El nombre de la ubicación no es válido.');
    }
    if (!is_numeric($latitude) || !is_numeric($longitude)
        || !is_finite((float) $latitude) || (float) $latitude < -90 || (float) $latitude > 90
        || !is_finite((float) $longitude) || (float) $longitude < -180 || (float) $longitude > 180) {
        throw new InvalidArgumentException('Las coordenadas de la ubicación no son válidas.');
    }
    try {
        $timezone = new DateTimeZone($timezoneName);
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('La zona horaria no es válida.', 0, $exception);
    }
    $configured = $connection->prepare(
        'SELECT 1 FROM web_push_device_config WHERE subscription_id = :subscription_id'
    );
    $configured->execute(['subscription_id' => $subscriptionId]);
    if ($configured->fetchColumn() === false) {
        throw new AstronomyPushSubscriptionAccessException('Este dispositivo todavía no tiene notificaciones configuradas.');
    }
    $statement = $connection->prepare(
        'UPDATE web_push_device_config SET location_name = :location_name, latitude = :latitude, '
        . 'longitude = :longitude, timezone = :timezone WHERE subscription_id = :subscription_id'
    );
    $statement->execute([
        'location_name' => $locationName,
        'latitude' => (float) $latitude,
        'longitude' => (float) $longitude,
        'timezone' => $timezone->getName(),
        'subscription_id' => $subscriptionId,
    ]);
    return astronomyPushCurrentDeviceState($connection, $subscriptionPayload);
}
