<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/web-push-device-config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function astronomyPushDeviceResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    astronomyPushDeviceResponse(405, ['ok' => false, 'message' => 'Método no permitido.']);
}
if (strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0] ?? '')) !== 'application/json') {
    astronomyPushDeviceResponse(415, ['ok' => false, 'message' => 'El contenido debe enviarse como JSON.']);
}
if (!astronomyPushDeviceRequestIsSameOrigin($_SERVER)) {
    astronomyPushDeviceResponse(403, ['ok' => false, 'message' => 'Origen no permitido.']);
}
$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 32768) {
    astronomyPushDeviceResponse(400, ['ok' => false, 'message' => 'La solicitud no es válida.']);
}
$payload = json_decode($rawBody, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    astronomyPushDeviceResponse(400, ['ok' => false, 'message' => 'La solicitud no es válida.']);
}

try {
    astronomyPushDeviceStartSession();
    if (!astronomyPushDeviceCsrfIsValid($payload['csrf_token'] ?? null)) {
        astronomyPushDeviceResponse(403, ['ok' => false, 'message' => 'La sesión expiró. Recargá la página.']);
    }
    $subscription = $payload['subscription'] ?? null;
    if (!is_array($subscription)) throw new InvalidArgumentException('La suscripción actual no es válida.');
    $action = (string) ($payload['action'] ?? 'read');
    $connection = getWebDatabaseConnection();
    if ($action === 'read') {
        $state = astronomyPushCurrentDeviceState($connection, $subscription);
    } elseif ($action === 'save') {
        $config = $payload['config'] ?? null;
        if (!is_array($config)) throw new InvalidArgumentException('La configuración enviada no es válida.');
        $state = astronomyPushSaveCurrentDevice($connection, $subscription, $config);
    } else {
        throw new InvalidArgumentException('La acción solicitada no es válida.');
    }
    astronomyPushDeviceResponse(200, ['ok' => true, 'state' => $state]);
} catch (InvalidArgumentException $exception) {
    astronomyPushDeviceResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (AstronomyPushSubscriptionAccessException $exception) {
    astronomyPushDeviceResponse(409, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Public Web Push device configuration failed [type=' . get_debug_type($exception) . '].');
    astronomyPushDeviceResponse(503, ['ok' => false, 'message' => 'No pudimos cargar o guardar la configuración en este momento.']);
}
