<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/web-push-astronomy.php';

sendStoreAdminHeaders();
startStoreAdminSession();
if (!storeAdminIsAuthenticated()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['error' => 'Autenticación administrativa requerida.']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

try {
    $rows = astronomyPushAdminSubscriptions(getWebDatabaseConnection());
    foreach ($rows as &$row) {
        $active = (int) $row['active'] === 1;
        $lastSuccess = (string) ($row['last_success_at'] ?? '');
        $lastError = (string) ($row['last_error_at'] ?? '');
        if (!$active) {
            $health = ['code' => 'inactive', 'label' => 'Inactiva'];
        } elseif ($lastError !== '' && ($lastSuccess === '' || $lastError > $lastSuccess)) {
            $health = ['code' => 'error', 'label' => 'Con errores'];
        } elseif ($lastSuccess !== '') {
            $health = ['code' => 'healthy', 'label' => 'Saludable'];
        } else {
            $health = ['code' => 'idle', 'label' => 'Sin actividad'];
        }
        $row['health'] = $health;
        $row['last_activity_at'] = max(array_filter([
            (string) ($row['updated_at'] ?? ''), $lastSuccess, $lastError,
        ]));
        $row['last_error_message'] = !empty($row['last_error_message'])
            ? astronomyWebPushSanitizeError((string) $row['last_error_message']) : null;
    }
    unset($row);
    $search = trim((string) ($_GET['q'] ?? ''));
    if ($search !== '') {
        $normalizedSupportId = astronomyPushNormalizeSupportId($search);
        $rows = array_values(array_filter($rows, static function (array $row) use ($search, $normalizedSupportId): bool {
            if ($normalizedSupportId !== null && hash_equals((string) ($row['support_id'] ?? ''), $normalizedSupportId)) return true;
            return stripos((string) ($row['device_name'] ?? ''), $search) !== false;
        }));
    }
    echo json_encode(['updated_at' => gmdate('c'), 'subscriptions' => $rows],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('Admin subscriptions status failed [type=' . get_debug_type($exception) . '].');
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo actualizar el estado de las suscripciones.']);
}
