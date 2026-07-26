<?php

final class MercadoPagoTransportException extends RuntimeException
{
    public function __construct(
        private readonly int $transportErrno,
        private readonly string $transportDescription
    ) {
        parent::__construct('No se pudo completar la conexión con el proveedor de pagos.');
    }

    public function transportErrno(): int
    {
        return $this->transportErrno;
    }

    public function transportDescription(): string
    {
        return $this->transportDescription;
    }
}

final class MercadoPagoApiException extends RuntimeException
{
    public function __construct(
        private readonly int $httpStatus,
        private readonly string $operation
    ) {
        parent::__construct($operation === 'payment_lookup' ? 'No se pudo verificar el pago.' : 'No se pudo iniciar el pago.');
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function operation(): string
    {
        return $this->operation;
    }
}

function truncateMercadoPagoDiagnosticText(
    string $text,
    int $maximumLength,
    ?bool $mbstringAvailable = null
): string {
    $maximumLength = max(0, $maximumLength);
    $useMbstring = $mbstringAvailable ?? function_exists('mb_substr');
    if ($useMbstring && function_exists('mb_substr')) {
        return mb_substr($text, 0, $maximumLength, 'UTF-8');
    }
    $truncated = substr($text, 0, $maximumLength);
    while ($truncated !== '' && preg_match('//u', $truncated) !== 1) {
        $truncated = substr($truncated, 0, -1);
    }
    return $truncated;
}

function sanitizeMercadoPagoDiagnosticValue(mixed $value, int $maximumLength = 240, array $secrets = []): ?string
{
    if (!is_scalar($value)) {
        return null;
    }
    $sanitized = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '');
    $sanitized = preg_replace('/\bBearer\s+[^\s,;]+/i', 'Bearer [redacted]', $sanitized) ?? '';
    $sanitized = preg_replace('/([?&](?:access_token|token|client_secret|secret)=)[^&\s]+/i', '$1[redacted]', $sanitized) ?? '';
    foreach ($secrets as $secret) {
        if (is_string($secret) && $secret !== '') {
            $sanitized = str_replace($secret, '[redacted]', $sanitized);
        }
    }
    if ($sanitized === '') {
        return null;
    }
    return truncateMercadoPagoDiagnosticText($sanitized, $maximumLength);
}

function mercadoPagoErrorCause(array $response): array
{
    $cause = $response['cause'] ?? null;
    if (is_array($cause) && array_is_list($cause)) {
        $cause = $cause[0] ?? null;
    }
    return is_array($cause) ? $cause : [];
}

function logMercadoPagoApiError(int $status, ?array $response, array $secrets = [], string $operation = 'preference'): void
{
    $cause = is_array($response) ? mercadoPagoErrorCause($response) : [];
    $diagnostic = ['http_status' => $status];
    foreach ([
        'error' => $response['error'] ?? null,
        'message' => $response['message'] ?? null,
        'cause_code' => $cause['code'] ?? null,
        'cause_description' => $cause['description'] ?? null,
    ] as $key => $value) {
        $sanitized = sanitizeMercadoPagoDiagnosticValue($value, 240, $secrets);
        if ($sanitized !== null) {
            $diagnostic[$key] = $sanitized;
        }
    }
    $safeOperation = in_array($operation, ['preference', 'payment_lookup'], true) ? $operation : 'request';
    error_log('Mercado Pago ' . $safeOperation . ' API error ' . json_encode($diagnostic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '.');
}

function mercadoPagoPaymentCurlTransport(array $request): array
{
    $handle = curl_init($request['url']);
    if ($handle === false) {
        throw new MercadoPagoTransportException(0, 'No se pudo inicializar cURL.');
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => $request['headers'],
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlCode = curl_errno($handle);
    $curlDescription = curl_error($handle);
    if (!is_string($body) || $curlCode !== 0) {
        throw new MercadoPagoTransportException($curlCode, $curlDescription);
    }
    return ['status' => $status, 'body' => $body];
}

function getMercadoPagoPayment(
    array $config,
    string $paymentId,
    ?callable $transport = null,
    ?callable $diagnostic = null
): array
{
    if (preg_match('/^[0-9]{1,32}$/', $paymentId) !== 1) {
        throw new InvalidArgumentException('La notificación de pago no es válida.');
    }
    $request = [
        'url' => 'https://api.mercadopago.com/v1/payments/' . $paymentId,
        'headers' => [
            'Authorization: Bearer ' . $config['access_token'],
            'Accept: application/json',
        ],
    ];
    if ($diagnostic !== null) {
        $diagnostic('payment_lookup_started');
    }
    try {
        $response = ($transport ?? 'mercadoPagoPaymentCurlTransport')($request);
    } catch (MercadoPagoTransportException $exception) {
        $description = sanitizeMercadoPagoDiagnosticValue($exception->transportDescription(), 240, [$config['access_token'] ?? '']) ?? 'Sin descripción disponible.';
        if ($diagnostic !== null) {
            $diagnostic('payment_lookup_curl_error', [
                'curl_errno' => $exception->transportErrno(),
                'description' => $description,
            ]);
        } else {
            error_log('Mercado Pago payment_lookup cURL error [errno=' . $exception->transportErrno() . ' description=' . json_encode($description, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '].');
        }
        throw new RuntimeException('No se pudo verificar el pago.');
    }
    $status = (int) ($response['status'] ?? 0);
    $decoded = isset($response['body']) && is_string($response['body'])
        ? json_decode($response['body'], true)
        : null;
    if ($diagnostic !== null) {
        $cause = is_array($decoded) ? mercadoPagoErrorCause($decoded) : [];
        $diagnostic('payment_lookup_http_status', [
            'http_status' => $status,
            'error' => sanitizeMercadoPagoDiagnosticValue($decoded['error'] ?? null, 120, [$config['access_token'] ?? '']),
            'message' => sanitizeMercadoPagoDiagnosticValue($decoded['message'] ?? null, 240, [$config['access_token'] ?? '']),
            'cause_code' => sanitizeMercadoPagoDiagnosticValue($cause['code'] ?? null, 120, [$config['access_token'] ?? '']),
            'cause_description' => sanitizeMercadoPagoDiagnosticValue($cause['description'] ?? null, 240, [$config['access_token'] ?? '']),
        ]);
    }
    if ($status !== 200) {
        if ($diagnostic === null) {
            logMercadoPagoApiError($status, is_array($decoded) ? $decoded : null, [$config['access_token'] ?? ''], 'payment_lookup');
        }
        throw new MercadoPagoApiException($status, 'payment_lookup');
    }
    if (!is_array($decoded)) {
        if ($diagnostic === null) {
            error_log('Mercado Pago payment_lookup invalid response [http_status=' . $status . ' reason=invalid_json].');
        }
        throw new RuntimeException('No se pudo verificar el pago.');
    }
    if ((string) ($decoded['id'] ?? '') !== $paymentId) {
        if ($diagnostic === null) {
            error_log('Mercado Pago payment_lookup invalid response [http_status=' . $status . ' reason=payment_id_mismatch].');
        }
        throw new RuntimeException('No se pudo verificar el pago.');
    }
    return $decoded;
}

function buildMercadoPagoPreferencePayload(array $order, array $config): array
{
    $items = [];
    foreach ($order['photos'] as $photo) {
        $title = is_string($photo['titulo'] ?? null) ? trim($photo['titulo']) : '';
        $items[] = [
            'id' => $photo['foto_id'],
            'title' => $title !== '' ? $title : 'Fotografía de la Luna',
            'quantity' => 1,
            'currency_id' => $order['currency'],
            'unit_price' => (float) $photo['precio'],
        ];
    }
    return [
        'items' => $items,
        'external_reference' => $order['public_code'],
        'back_urls' => [
            'success' => $config['success_url'],
            'pending' => $config['pending_url'],
            'failure' => $config['failure_url'],
        ],
        'auto_return' => 'approved',
    ];
}

function mercadoPagoCurlTransport(array $request): array
{
    $handle = curl_init($request['url']);
    if ($handle === false) {
        throw new MercadoPagoTransportException(0, 'No se pudo inicializar cURL.');
    }
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $request['headers'],
        CURLOPT_POSTFIELDS => $request['body'],
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlCode = curl_errno($handle);
    $curlDescription = curl_error($handle);
    if (!is_string($body) || $curlCode !== 0) {
        throw new MercadoPagoTransportException($curlCode, $curlDescription);
    }
    return ['status' => $status, 'body' => $body];
}

function createMercadoPagoPreference(array $config, array $payload, string $idempotencyKey, ?callable $transport = null): array
{
    if (preg_match('/^[a-f0-9-]{36}$/', $idempotencyKey) !== 1) {
        throw new InvalidArgumentException('La solicitud de pago no es válida.');
    }
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $request = [
        'url' => 'https://api.mercadopago.com/checkout/preferences',
        'headers' => [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . $idempotencyKey,
        ],
        'body' => $body,
    ];
    try {
        $response = ($transport ?? 'mercadoPagoCurlTransport')($request);
    } catch (MercadoPagoTransportException $exception) {
        $description = sanitizeMercadoPagoDiagnosticValue($exception->transportDescription(), 240, [$config['access_token'] ?? '']) ?? 'Sin descripción disponible.';
        error_log('Mercado Pago preference cURL error [errno=' . $exception->transportErrno() . ' description=' . json_encode($description, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '].');
        throw new RuntimeException('No se pudo iniciar el pago.');
    }
    $status = (int) ($response['status'] ?? 0);
    $decoded = isset($response['body']) && is_string($response['body'])
        ? json_decode($response['body'], true)
        : null;
    if (!in_array($status, [200, 201], true)) {
        logMercadoPagoApiError($status, is_array($decoded) ? $decoded : null, [$config['access_token'] ?? '']);
        throw new RuntimeException('No se pudo iniciar el pago.');
    }
    if (!is_array($decoded)) {
        error_log('Mercado Pago preference invalid response [http_status=' . $status . ' reason=invalid_json].');
        throw new RuntimeException('No se pudo iniciar el pago.');
    }
    $preferenceId = $decoded['id'] ?? null;
    $checkoutKey = $config['mode'] === 'test' ? 'sandbox_init_point' : 'init_point';
    $checkoutUrl = $decoded[$checkoutKey] ?? null;
    $host = is_string($checkoutUrl) ? parse_url($checkoutUrl, PHP_URL_HOST) : null;
    if (
        !is_string($preferenceId) || $preferenceId === '' || strlen($preferenceId) > 100
        || !is_string($checkoutUrl) || filter_var($checkoutUrl, FILTER_VALIDATE_URL) === false
        || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
        || !is_string($host) || preg_match('/(^|\.)mercadopago\.com(?:\.ar)?$/i', $host) !== 1
    ) {
        error_log('Mercado Pago preference invalid response [http_status=' . $status . ' reason=missing_or_invalid_fields].');
        throw new RuntimeException('No se pudo iniciar el pago.');
    }
    return [
        'id' => $preferenceId,
        'checkout_url' => $checkoutUrl,
        'mode' => $config['mode'],
        'date_created' => is_string($decoded['date_created'] ?? null) ? $decoded['date_created'] : null,
    ];
}
