<?php

require_once __DIR__ . '/../includes/mercado-pago-client.php';

function mercadoPagoDiagnosticsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$logPath = tempnam(sys_get_temp_dir(), 'mp-diagnostics-');
if (!is_string($logPath)) {
    throw new RuntimeException('No se pudo preparar el log de prueba.');
}
$previousLog = ini_get('error_log');
ini_set('error_log', $logPath);
$config = ['mode' => 'test', 'access_token' => 'TEST-SECRET-TOKEN'];
$payload = ['items' => []];
$idempotencyKey = '12345678-1234-4123-8123-123456789abc';

mercadoPagoDiagnosticsAssert(
    truncateMercadoPagoDiagnosticText('abcdef', 3, false) === 'abc',
    'El fallback sin mbstring no truncó texto ASCII.'
);
$fallbackUtf8 = truncateMercadoPagoDiagnosticText('áéí', 3, false);
mercadoPagoDiagnosticsAssert(
    $fallbackUtf8 === 'á' && preg_match('//u', $fallbackUtf8) === 1,
    'El fallback sin mbstring dejó UTF-8 inválido.'
);
mercadoPagoDiagnosticsAssert(
    sanitizeMercadoPagoDiagnosticValue('Bearer TEST-SECRET-TOKEN', 240, ['TEST-SECRET-TOKEN']) === 'Bearer [redacted]',
    'El helper dejó de sanear secretos.'
);

try {
    try {
        createMercadoPagoPreference($config, $payload, $idempotencyKey, static function (): array {
            throw new MercadoPagoTransportException(28, "Timeout\nAuthorization: Bearer TEST-SECRET-TOKEN");
        });
    } catch (RuntimeException $exception) {
        mercadoPagoDiagnosticsAssert($exception->getMessage() === 'No se pudo iniciar el pago.', 'El error cURL dejó de ser genérico.');
    }

    try {
        createMercadoPagoPreference($config, $payload, $idempotencyKey, static fn (): array => [
            'status' => 400,
            'body' => json_encode([
                'error' => 'bad_request',
                'message' => "Invalid token\nTEST-SECRET-TOKEN",
                'cause' => [[
                    'code' => 'invalid_item',
                    'description' => 'El ítem no es válido.',
                    'sensitive' => 'no debe registrarse',
                ]],
                'access_token' => 'TEST-SECRET-TOKEN',
            ], JSON_THROW_ON_ERROR),
        ]);
    } catch (RuntimeException $exception) {
        mercadoPagoDiagnosticsAssert($exception->getMessage() === 'No se pudo iniciar el pago.', 'El error HTTP dejó de ser genérico.');
    }

    foreach ([
        ['status' => 201, 'body' => '<html>respuesta inesperada</html>'],
        ['status' => 201, 'body' => '{"id":"sin-url"}'],
    ] as $invalidResponse) {
        try {
            createMercadoPagoPreference($config, $payload, $idempotencyKey, static fn (): array => $invalidResponse);
        } catch (RuntimeException $exception) {
            mercadoPagoDiagnosticsAssert($exception->getMessage() === 'No se pudo iniciar el pago.', 'La respuesta inválida dejó de ser genérica.');
        }
    }

    try {
        getMercadoPagoPayment($config, '123456', static function (): array {
            throw new MercadoPagoTransportException(7, 'Connection failed for Bearer TEST-SECRET-TOKEN');
        });
    } catch (RuntimeException $exception) {
        mercadoPagoDiagnosticsAssert($exception->getMessage() === 'No se pudo verificar el pago.', 'El error de consulta cURL dejó de ser genérico.');
    }
    try {
        getMercadoPagoPayment($config, '123456', static fn (): array => [
            'status' => 401,
            'body' => json_encode([
                'error' => 'unauthorized',
                'message' => 'Invalid access token TEST-SECRET-TOKEN',
                'cause' => ['code' => 'AUTH', 'description' => 'Credencial rechazada'],
                'raw' => 'no debe registrarse payment lookup',
            ], JSON_THROW_ON_ERROR),
        ]);
    } catch (RuntimeException $exception) {
        mercadoPagoDiagnosticsAssert($exception->getMessage() === 'No se pudo verificar el pago.', 'El error HTTP de consulta dejó de ser genérico.');
    }
    try {
        getMercadoPagoPayment($config, '123456', static fn (): array => [
            'status' => 200,
            'body' => '{"id":654321}',
        ]);
    } catch (RuntimeException $exception) {
        mercadoPagoDiagnosticsAssert($exception->getMessage() === 'No se pudo verificar el pago.', 'La respuesta de pago inválida dejó de ser genérica.');
    }

    $log = file_get_contents($logPath);
    mercadoPagoDiagnosticsAssert(is_string($log), 'No se pudo leer el log de prueba.');
    mercadoPagoDiagnosticsAssert(str_contains($log, 'cURL error [errno=28'), 'No se registró errno de cURL.');
    mercadoPagoDiagnosticsAssert(str_contains($log, 'Bearer [redacted]'), 'No se saneó Authorization en la descripción cURL.');
    mercadoPagoDiagnosticsAssert(str_contains($log, '"http_status":400'), 'No se registró el HTTP status.');
    mercadoPagoDiagnosticsAssert(str_contains($log, '"error":"bad_request"'), 'No se registró error de la API.');
    mercadoPagoDiagnosticsAssert(str_contains($log, '"cause_code":"invalid_item"'), 'No se registró cause.code.');
    mercadoPagoDiagnosticsAssert(str_contains($log, '"cause_description":"El ítem no es válido."'), 'No se registró cause.description.');
    mercadoPagoDiagnosticsAssert(str_contains($log, 'reason=invalid_json'), 'No se distinguió JSON inválido.');
    mercadoPagoDiagnosticsAssert(str_contains($log, 'reason=missing_or_invalid_fields'), 'No se distinguieron campos inválidos.');
    mercadoPagoDiagnosticsAssert(str_contains($log, 'payment_lookup cURL error [errno=7'), 'No se diagnosticó el cURL de consulta del pago.');
    mercadoPagoDiagnosticsAssert(str_contains($log, 'Mercado Pago payment_lookup API error {"http_status":401'), 'No se diagnosticó el HTTP de consulta del pago.');
    mercadoPagoDiagnosticsAssert(str_contains($log, 'reason=payment_id_mismatch'), 'No se diagnosticó la respuesta de pago inválida.');
    mercadoPagoDiagnosticsAssert(!str_contains($log, 'no debe registrarse'), 'Se registró un campo no permitido del cuerpo.');
    mercadoPagoDiagnosticsAssert(!str_contains($log, 'no debe registrarse payment lookup'), 'Se registró el cuerpo completo de la consulta del pago.');
    mercadoPagoDiagnosticsAssert(!str_contains($log, 'TEST-SECRET-TOKEN'), 'Se filtró el access token en el diagnóstico.');

    fwrite(STDOUT, "mercado pago diagnostics tests: ok\n");
} finally {
    ini_set('error_log', is_string($previousLog) ? $previousLog : '');
    unlink($logPath);
}
