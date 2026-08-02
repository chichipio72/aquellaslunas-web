<?php

require_once dirname(__DIR__) . '/includes/web-push.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function webPushSubscriptionResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    webPushSubscriptionResponse(405, ['ok' => false, 'message' => 'Método no permitido.']);
}

$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0] ?? ''));
if ($contentType !== 'application/json') {
    webPushSubscriptionResponse(415, ['ok' => false, 'message' => 'El contenido debe enviarse como JSON.']);
}

$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
$fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true)) {
    webPushSubscriptionResponse(403, ['ok' => false, 'message' => 'Origen no permitido.']);
}
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $originPort = parse_url($origin, PHP_URL_PORT);
    $expectedHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $receivedHost = is_string($originHost) ? strtolower($originHost) . ($originPort !== null ? ':' . $originPort : '') : '';
    if ($receivedHost === '' || !hash_equals($expectedHost, $receivedHost)) {
        webPushSubscriptionResponse(403, ['ok' => false, 'message' => 'Origen no permitido.']);
    }
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 16384) {
    webPushSubscriptionResponse(400, ['ok' => false, 'message' => 'La suscripción enviada no es válida.']);
}
$payload = json_decode($rawBody, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    webPushSubscriptionResponse(400, ['ok' => false, 'message' => 'La suscripción enviada no es válida.']);
}

try {
    $subscription = astronomyWebPushValidateSubscription($payload);
    astronomyWebPushSaveSubscription(
        getWebDatabaseConnection(),
        $subscription,
        isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null
    );
    webPushSubscriptionResponse(200, ['ok' => true, 'message' => 'Notificaciones activadas.']);
} catch (InvalidArgumentException $exception) {
    webPushSubscriptionResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Web Push subscription failed [type=' . get_debug_type($exception) . '].');
    webPushSubscriptionResponse(503, ['ok' => false, 'message' => 'No pudimos guardar la suscripción en este momento.']);
}
