<?php

require_once dirname(__DIR__) . '/includes/web-push.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function webPushUnsubscribeResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    webPushUnsubscribeResponse(405, ['ok' => false, 'message' => 'Método no permitido.']);
}
if (strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0] ?? '')) !== 'application/json') {
    webPushUnsubscribeResponse(415, ['ok' => false, 'message' => 'El contenido debe enviarse como JSON.']);
}
$fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true)) {
    webPushUnsubscribeResponse(403, ['ok' => false, 'message' => 'Origen no permitido.']);
}
$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $originPort = parse_url($origin, PHP_URL_PORT);
    $receivedHost = is_string($originHost) ? strtolower($originHost) . ($originPort !== null ? ':' . $originPort : '') : '';
    if ($receivedHost === '' || !hash_equals(strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')), $receivedHost)) {
        webPushUnsubscribeResponse(403, ['ok' => false, 'message' => 'Origen no permitido.']);
    }
}
$rawBody = file_get_contents('php://input');
$payload = is_string($rawBody) && strlen($rawBody) <= 4096 ? json_decode($rawBody, true) : null;
$endpoint = is_array($payload) ? trim((string) ($payload['endpoint'] ?? '')) : '';
if ($endpoint === '' || strlen($endpoint) > WEB_PUSH_ENDPOINT_MAX_LENGTH || filter_var($endpoint, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
    webPushUnsubscribeResponse(422, ['ok' => false, 'message' => 'La suscripción no es válida.']);
}
try {
    astronomyWebPushDeactivateSubscription(getWebDatabaseConnection(), $endpoint);
    webPushUnsubscribeResponse(200, ['ok' => true, 'message' => 'Notificaciones desactivadas.']);
} catch (Throwable $exception) {
    error_log('Web Push unsubscribe failed [type=' . get_debug_type($exception) . '].');
    webPushUnsubscribeResponse(503, ['ok' => false, 'message' => 'No pudimos desactivar la suscripción en este momento.']);
}
