<?php

require_once __DIR__ . '/../includes/api-config.php';
require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/mercado-pago-client.php';
require_once __DIR__ . '/../includes/mercado-pago-sdk-signature-validator.php';
require_once __DIR__ . '/../includes/store-mercado-pago-webhook.php';

// Diagnóstico temporal: retirar cuando se confirme el recorrido del webhook en el hosting.
function sanitizeMercadoPagoWebhookExceptionMessage(Throwable $exception, array $config = []): string
{
    $secrets = [];
    foreach (['access_token', 'webhook_secret', 'public_key'] as $key) {
        if (isset($config[$key]) && is_string($config[$key]) && $config[$key] !== '') {
            $secrets[] = $config[$key];
        }
    }
    $message = sanitizeMercadoPagoDiagnosticValue($exception->getMessage(), 300, $secrets) ?? 'Sin mensaje disponible.';
    $message = preg_replace('~\b(?:mysql|pgsql):[^\s]+~i', '[dsn redacted]', $message) ?? '[mensaje redacted]';
    $message = preg_replace('/\b(password|passwd|pwd|dsn)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? '[mensaje redacted]';
    $message = preg_replace('~\b(https?://[^\s?]+)\?[^\s]+~i', '$1?[redacted]', $message) ?? '[mensaje redacted]';
    $message = preg_replace('~\b(https?://)[^/\s@]+@~i', '$1[redacted]@', $message) ?? '[mensaje redacted]';
    return truncateMercadoPagoDiagnosticText($message, 300);
}

function logMercadoPagoWebhookTrace(string $traceId, string $stage, array $context = [], array $secrets = []): void
{
    $allowedStages = [
        'notification_received', 'config_loaded', 'secret_diagnostic', 'signature_diagnostic', 'signature_comparison', 'signature_valid', 'payment_lookup_started',
        'payment_lookup_http_status', 'payment_lookup_curl_error',
        'payment_payload_valid', 'order_lookup_started', 'finished', 'exception_type',
    ];
    if (preg_match('/^[a-f0-9]{32}$/', $traceId) !== 1 || !in_array($stage, $allowedStages, true)) {
        return;
    }
    $entry = ['request_trace_id' => $traceId, 'stage' => $stage];
    foreach (['http_status', 'curl_errno', 'exception_line', 'data_id_length', 'request_id_length', 'signature_component_count', 'signature_v1_length', 'secret_length', 'secret_trimmed_length'] as $numericKey) {
        if (isset($context[$numericKey]) && is_int($context[$numericKey])) {
            $entry[$numericKey] = $context[$numericKey];
        }
    }
    foreach (['query_data_id_present', 'body_data_id_present', 'signature_present', 'request_id_present', 'query_body_ids_match', 'data_id_has_spaces', 'request_id_has_spaces', 'secret_leading_boundary_character', 'secret_trailing_boundary_character', 'custom_valid', 'official_sdk_valid'] as $booleanKey) {
        if (isset($context[$booleanKey]) && is_bool($context[$booleanKey])) {
            $entry[$booleanKey] = $context[$booleanKey];
        }
    }
    if (isset($context['received_type']) && is_string($context['received_type']) && preg_match('/^[A-Za-z0-9_.-]{0,60}$/', $context['received_type']) === 1) {
        $entry['received_type'] = $context['received_type'];
    }
    if (isset($context['data_id_php_type']) && in_array($context['data_id_php_type'], ['string', 'null'], true)) {
        $entry['data_id_php_type'] = $context['data_id_php_type'];
    }
    foreach (['signature_ts', 'manifest_safe', 'calculated_hmac_prefix', 'received_v1_prefix', 'secret_sha256_prefix', 'secret_source', 'notification_source'] as $signatureKey) {
        if (isset($context[$signatureKey]) && is_string($context[$signatureKey])) {
            $value = sanitizeMercadoPagoDiagnosticValue($context[$signatureKey], $signatureKey === 'manifest_safe' ? 300 : 32, $secrets);
            if ($value !== null) {
                $entry[$signatureKey] = $value;
            }
        }
    }
    foreach (['description', 'error', 'message', 'cause_code', 'cause_description', 'exception_message'] as $textKey) {
        $value = sanitizeMercadoPagoDiagnosticValue($context[$textKey] ?? null, $textKey === 'exception_message' ? 300 : 240, $secrets);
        if ($value !== null) {
            $entry[$textKey] = $value;
        }
    }
    if (isset($context['exception_type']) && is_string($context['exception_type']) && preg_match('/^[A-Za-z0-9_\\\\]{1,120}$/', $context['exception_type']) === 1) {
        $entry['exception_type'] = $context['exception_type'];
    }
    if (isset($context['exception_file']) && is_string($context['exception_file'])) {
        $baseFile = basename($context['exception_file']);
        if (preg_match('/^[A-Za-z0-9._-]{1,160}$/', $baseFile) === 1) {
            $entry['exception_file'] = $baseFile;
        }
    }
    if (isset($context['result']) && in_array($context['result'], ['ok', 'ignored', 'rejected', 'error'], true)) {
        $entry['result'] = $context['result'];
    }
    if (isset($context['reason']) && is_string($context['reason']) && preg_match('/^[a-z0-9_]{1,80}$/', $context['reason']) === 1) {
        $entry['reason'] = $context['reason'];
    }
    error_log('Mercado Pago webhook trace ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '.');
}

$requestTraceId = bin2hex(random_bytes(16));
$config = [];
$trace = static function (string $stage, array $context = []) use ($requestTraceId, &$config): void {
    $secrets = [];
    foreach (['access_token', 'webhook_secret', 'public_key'] as $key) {
        if (isset($config[$key]) && is_string($config[$key]) && $config[$key] !== '') {
            $secrets[] = $config[$key];
        }
    }
    logMercadoPagoWebhookTrace($requestTraceId, $stage, $context, $secrets);
};

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $trace('finished', ['result' => 'rejected', 'reason' => 'method_not_allowed']);
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if (is_int($contentLength) && $contentLength > 65536) {
    $trace('finished', ['result' => 'rejected', 'reason' => 'payload_too_large']);
    http_response_code(400);
    echo json_encode(['error' => 'Notificación no válida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $rawBody = file_get_contents('php://input');
    $body = null;
    if (is_string($rawBody) && trim($rawBody) !== '') {
        try {
            $decodedBody = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
            $body = is_array($decodedBody) ? $decodedBody : null;
        } catch (JsonException) {
            $body = null;
        }
    }
    $notification = normalizeMercadoPagoWebhookNotification($_GET, $body);
    $signature = mercadoPagoWebhookHeader($_SERVER, 'x-signature');
    $requestId = mercadoPagoWebhookHeader($_SERVER, 'x-request-id');
    $trace('notification_received', [
        'query_data_id_present' => $notification['query_id'] !== null,
        'body_data_id_present' => $notification['body_id'] !== null,
        'received_type' => $notification['type'],
        'signature_present' => $signature !== '',
        'request_id_present' => $requestId !== '',
        'query_body_ids_match' => $notification['ids_match'],
    ]);
    if ($notification['type'] === '' && $notification['query_id'] === null && $notification['body_id'] === null) {
        $trace('finished', ['result' => 'ignored', 'reason' => 'empty_notification']);
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        exit;
    }
    if ($notification['type'] !== 'payment') {
        $trace('finished', ['result' => 'ignored', 'reason' => 'unsupported_notification']);
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        exit;
    }
    $paymentId = extractMercadoPagoWebhookPaymentId($_GET, $body);
    $config = loadMercadoPagoConfig();
    $trace('config_loaded');
    $secret = is_string($config['webhook_secret'] ?? null) ? $config['webhook_secret'] : '';
    $trimmedSecret = trim($secret);
    $trace('secret_diagnostic', [
        'secret_length' => strlen($secret),
        'secret_trimmed_length' => strlen($trimmedSecret),
        'secret_leading_boundary_character' => $secret !== '' && preg_match('/^[\s\x00-\x1F\x7F]/', $secret) === 1,
        'secret_trailing_boundary_character' => $secret !== '' && preg_match('/[\s\x00-\x1F\x7F]$/', $secret) === 1,
        'secret_sha256_prefix' => substr(hash('sha256', $secret), 0, 12),
        'secret_source' => $config['_sources']['webhook_secret'] ?? 'unknown',
        'notification_source' => isset($_GET['source_news']) && $_GET['source_news'] === 'webhooks'
            ? 'webhooks'
            : 'unknown',
    ]);
    $remoteAddress = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : null;
    $customValid = authorizeMercadoPagoWebhook($config, $signature, $requestId, $notification['signature_data_id'], $remoteAddress, $trace);
    $officialSdkValid = validateMercadoPagoWebhookSignatureWithOfficialSdk(
        $signature,
        $requestId,
        $notification['signature_data_id'],
        $secret
    );
    $trace('signature_comparison', [
        'custom_valid' => $customValid,
        'official_sdk_valid' => $officialSdkValid,
    ]);
    if (!$customValid || !$officialSdkValid) {
        $trace('exception_type', ['exception_type' => 'WebhookSignatureRejected']);
        $trace('finished', ['result' => 'rejected', 'reason' => 'invalid_or_missing_signature']);
        http_response_code(401);
        echo json_encode(['error' => 'Notificación no autorizada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $trace('signature_valid');
    $result = handleMercadoPagoWebhookPayment(
        getStoreDatabaseConnection(),
        $config,
        $paymentId,
        loadStoreDownloadExpiryHours(),
        loadStoreDownloadMaxCount(),
        null,
        null,
        $trace
    );
    $trace('finished', ['result' => ($result['result'] ?? null) === 'ignored' ? 'ignored' : 'ok']);
    http_response_code(200);
    echo json_encode(['status' => ($result['result'] ?? null) === 'ignored' ? 'ignored' : 'ok']);
} catch (JsonException|InvalidArgumentException $exception) {
    $trace('exception_type', ['exception_type' => get_debug_type($exception)]);
    $trace('finished', ['result' => 'rejected', 'reason' => 'invalid_notification']);
    http_response_code(400);
    echo json_encode(['error' => 'Notificación no válida.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    $trace('exception_type', [
        'exception_type' => get_debug_type($exception),
        'exception_message' => sanitizeMercadoPagoWebhookExceptionMessage($exception, $config),
        'exception_file' => basename($exception->getFile()),
        'exception_line' => $exception->getLine(),
    ]);
    $trace('finished', ['result' => 'error']);
    http_response_code(503);
    echo json_encode([
        'error' => 'Servicio temporalmente no disponible.',
        'trace_id' => $requestTraceId,
    ], JSON_UNESCAPED_UNICODE);
}
