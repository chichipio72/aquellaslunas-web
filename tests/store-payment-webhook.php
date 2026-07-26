<?php

require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-checkout.php';
require_once __DIR__ . '/../includes/mercado-pago-client.php';
require_once __DIR__ . '/../includes/store-mercado-pago-webhook.php';
require_once __DIR__ . '/../includes/mercado-pago-sdk-signature-validator.php';

function storeWebhookAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function storeWebhookPayment(string $id, array $order, string $status = 'approved', array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'status' => $status,
        'status_detail' => $status === 'approved' ? 'accredited' : $status . '_detail',
        'external_reference' => $order['public_code'],
        'transaction_amount' => (float) $order['total'],
        'currency_id' => $order['currency'],
        'date_approved' => $status === 'approved' ? '2026-07-22T15:30:00-03:00' : null,
        'payer' => ['email' => 'no-debe-persistirse@example.com'],
    ], $overrides);
}

function storeWebhookMockTransport(array $payment, string $expectedToken = 'mock-access-token'): callable
{
    return static function (array $request) use ($payment, $expectedToken): array {
        storeWebhookAssert(
            $request['url'] === 'https://api.mercadopago.com/v1/payments/' . $payment['id'],
            'La consulta usó un endpoint de pago incorrecto.'
        );
        storeWebhookAssert(
            in_array('Authorization: Bearer ' . $expectedToken, $request['headers'], true),
            'La consulta no autenticó con Bearer.'
        );
        return ['status' => 200, 'body' => json_encode($payment, JSON_THROW_ON_ERROR)];
    };
}

$secret = 'webhook-test-secret';
$dataId = '123456789';
$requestId = 'request-test-123';
$timestamp = '1753200000';
$manifest = 'id:' . $dataId . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
$signature = 'ts=' . $timestamp . ',v1=' . hash_hmac('sha256', $manifest, $secret);
storeWebhookAssert(validateMercadoPagoWebhookSignature($signature, $requestId, $dataId, $secret), 'La firma válida fue rechazada.');
storeWebhookAssert(validateMercadoPagoWebhookSignatureWithOfficialSdk($signature, $requestId, $dataId, $secret), 'El comparador del SDK rechazó la firma válida.');
storeWebhookAssert(!validateMercadoPagoWebhookSignature($signature . '0', $requestId, $dataId, $secret), 'La firma inválida fue aceptada.');
$twelveDigitId = '123456789012';
$uuidRequestId = '12345678-1234-4123-8123-123456789abc';
$spacedHeaderManifest = 'id:' . $twelveDigitId . ';request-id:' . $uuidRequestId . ';ts:' . $timestamp . ';';
$spacedHeaderHash = hash_hmac('sha256', $spacedHeaderManifest, $secret);
$signatureEvents = [];
$signatureDiagnostic = static function (string $stage, array $context = []) use (&$signatureEvents): void {
    $signatureEvents[] = ['stage' => $stage, 'context' => $context];
};
storeWebhookAssert(
    validateMercadoPagoWebhookSignature(
        'ts=' . $timestamp . ', v1=' . $spacedHeaderHash,
        $uuidRequestId,
        $twelveDigitId,
        $secret,
        $signatureDiagnostic
    ),
    'La firma con ID de 12 dígitos y espacio tras la coma fue rechazada.'
);
storeWebhookAssert($signatureEvents[0]['stage'] === 'signature_diagnostic', 'No se emitió el diagnóstico de firma.');
$signatureContext = $signatureEvents[0]['context'];
storeWebhookAssert(
    $signatureContext['data_id_length'] === 12
    && $signatureContext['data_id_php_type'] === 'string'
    && $signatureContext['data_id_has_spaces'] === false
    && $signatureContext['request_id_length'] === 36
    && $signatureContext['request_id_has_spaces'] === false
    && $signatureContext['signature_ts'] === $timestamp
    && $signatureContext['signature_component_count'] === 2
    && $signatureContext['signature_v1_length'] === 64
    && $signatureContext['calculated_hmac_prefix'] === substr($spacedHeaderHash, 0, 8)
    && $signatureContext['received_v1_prefix'] === substr($spacedHeaderHash, 0, 8),
    'El diagnóstico de firma alteró tipos, longitudes o hashes.'
);
storeWebhookAssert(
    !str_contains($signatureContext['manifest_safe'], $twelveDigitId)
    && !str_contains($signatureContext['manifest_safe'], $uuidRequestId),
    'El manifiesto diagnóstico expuso valores completos.'
);
$timestampOnlyManifest = 'ts:' . $timestamp . ';';
$timestampOnlySignature = 'ts=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestampOnlyManifest, $secret);
storeWebhookAssert(
    validateMercadoPagoWebhookSignature($timestampOnlySignature, '', null, $secret),
    'No se omitieron del manifiesto los componentes ausentes.'
);
storeWebhookAssert(authorizeMercadoPagoWebhook(['mode' => 'test', 'webhook_secret' => ''], '', '', $dataId, '127.0.0.1'), 'El mock local sin secreto fue rechazado.');
storeWebhookAssert(!authorizeMercadoPagoWebhook(['mode' => 'test', 'webhook_secret' => ''], '', '', $dataId, '203.0.113.10'), 'Se aceptó una notificación externa sin secreto.');
storeWebhookAssert(!authorizeMercadoPagoWebhook(['mode' => 'production', 'webhook_secret' => ''], '', '', $dataId, '127.0.0.1'), 'Producción aceptó una notificación sin secreto.');
storeWebhookAssert(
    extractMercadoPagoWebhookPaymentId(['data_id' => $dataId], ['type' => 'payment', 'data' => ['id' => $dataId]]) === $dataId,
    'No se extrajo data.id.'
);
storeWebhookAssert(
    normalizeMercadoPagoWebhookNotification(['data.id' => '111', 'type' => 'payment'], null)['lookup_id'] === '111',
    'No se aceptó data.id literal en query.'
);
storeWebhookAssert(
    normalizeMercadoPagoWebhookNotification(['data_id' => '222', 'type' => 'payment'], null)['lookup_id'] === '222',
    'No se aceptó data_id transformado por PHP.'
);
$bodyOnlyNotification = normalizeMercadoPagoWebhookNotification([], ['type' => 'payment', 'data' => ['id' => '333']]);
storeWebhookAssert(
    $bodyOnlyNotification['lookup_id'] === '333' && $bodyOnlyNotification['signature_data_id'] === null,
    'El body no funcionó como respaldo o contaminó el manifiesto.'
);
$matchingNotification = normalizeMercadoPagoWebhookNotification(
    ['data_id' => '444', 'type' => 'payment'],
    ['type' => 'payment', 'data' => ['id' => '444']]
);
storeWebhookAssert($matchingNotification['lookup_id'] === '444' && $matchingNotification['ids_match'], 'No se aceptaron IDs coincidentes.');
$differentNotification = normalizeMercadoPagoWebhookNotification(
    ['data_id' => '555', 'type' => 'payment'],
    ['type' => 'payment', 'data' => ['id' => '999']]
);
storeWebhookAssert(
    $differentNotification['lookup_id'] === '555'
    && $differentNotification['signature_data_id'] === '555'
    && !$differentNotification['ids_match'],
    'La query no tuvo prioridad sobre un body diferente.'
);
storeWebhookAssert(
    mercadoPagoWebhookHeader(['HTTP_X_SIGNATURE' => 'one'], 'x-signature') === 'one'
    && mercadoPagoWebhookHeader(['Http_X_Request_Id' => 'two'], 'X-Request-ID') === 'two'
    && mercadoPagoWebhookHeader(['x-signature' => 'three'], 'X-SIGNATURE') === 'three',
    'La lectura de headers dependió de mayúsculas o del formato SAPI.'
);
storeWebhookAssert(
    mercadoPagoWebhookHeader(['HTTP_X_REQUEST_ID' => ' value-with-spaces '], 'x-request-id') === ' value-with-spaces ',
    'x-request-id fue recortado.'
);
$realisticNotification = normalizeMercadoPagoWebhookNotification(
    ['data_id' => '777', 'type' => 'payment'],
    [
        'action' => 'payment.updated',
        'api_version' => 'v1',
        'data' => ['id' => '777'],
        'live_mode' => true,
        'type' => 'payment',
    ]
);
storeWebhookAssert(
    $realisticNotification['lookup_id'] === '777' && $realisticNotification['type'] === 'payment',
    'La notificación realista payment.updated fue rechazada.'
);

$connection = getStoreDatabaseConnection();
$tables = ['fotos', 'pedidos', 'pedido_fotos', 'pagos', 'descargas'];
$before = [];
foreach ($tables as $table) {
    $before[$table] = (int) $connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
$suffix = bin2hex(random_bytes(6));
$publicId = hash('sha256', 'webhook-photo-' . $suffix);
$paymentSequence = (string) random_int(100000000, 900000000);
$logPath = tempnam(sys_get_temp_dir(), 'store-webhook-log-');
$previousLog = ini_get('error_log');

try {
    storeWebhookAssert(is_string($logPath), 'No se pudo crear el log temporal.');
    ini_set('error_log', $logPath);
    $connection->beginTransaction();
    $photo = $connection->prepare(
        'INSERT INTO fotos (foto_id, nombre_archivo, archivo_original, archivo_preview_tienda, titulo, precio, moneda, disponible) '
        . 'VALUES (:foto_id, :nombre, :original, :preview, :titulo, :precio, :moneda, 1)'
    );
    $photo->execute([
        'foto_id' => $publicId,
        'nombre' => 'webhook-' . $suffix . '.jpg',
        'original' => 'webhook-original-' . $suffix . '.jpg',
        'preview' => 'tienda/' . $publicId . '.jpg',
        'titulo' => 'Prueba webhook',
        'precio' => '321.45',
        'moneda' => 'ARS',
    ]);
    $config = ['mode' => 'test', 'access_token' => 'mock-access-token', 'webhook_secret' => ''];

    $countsBeforeMissingPayment = [];
    foreach ($tables as $table) {
        $countsBeforeMissingPayment[$table] = (int) $connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
    $missingTrace = [];
    $missingDiagnostic = static function (string $stage, array $context = []) use (&$missingTrace): void {
        $missingTrace[] = ['stage' => $stage, 'context' => $context];
    };
    $missingResult = handleMercadoPagoWebhookPayment(
        $connection,
        $config,
        $paymentSequence . '0',
        72,
        5,
        static fn (): array => [
            'status' => 404,
            'body' => '{"error":"not_found","message":"Payment not found"}',
        ],
        null,
        $missingDiagnostic
    );
    storeWebhookAssert($missingResult === ['result' => 'ignored', 'reason' => 'payment_not_found'], 'El pago inexistente no fue ignorado.');
    storeWebhookAssert(
        array_column($missingTrace, 'stage') === ['payment_lookup_started', 'payment_lookup_http_status', 'finished'],
        'El diagnóstico 404 no registró las etapas esperadas.'
    );
    storeWebhookAssert($missingTrace[1]['context']['http_status'] === 404, 'El diagnóstico perdió el HTTP 404.');
    foreach ($tables as $table) {
        storeWebhookAssert(
            (int) $connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === $countsBeforeMissingPayment[$table],
            "El pago inexistente modificó {$table}."
        );
    }
    foreach ([401, 403, 429, 500] as $httpStatus) {
        try {
            handleMercadoPagoWebhookPayment(
                $connection,
                $config,
                $paymentSequence . '0',
                72,
                5,
                static fn (): array => [
                    'status' => $httpStatus,
                    'body' => '{"error":"mock_error","message":"Controlled error"}',
                ]
            );
            throw new RuntimeException('Se aceptó un error HTTP que requiere reintento.');
        } catch (MercadoPagoApiException $exception) {
            storeWebhookAssert($exception->httpStatus() === $httpStatus, 'Se perdió el HTTP status estructurado.');
        }
    }
    try {
        handleMercadoPagoWebhookPayment(
            $connection,
            $config,
            $paymentSequence . '0',
            72,
            5,
            static function (): array {
                throw new MercadoPagoTransportException(28, 'Operation timed out');
            }
        );
        throw new RuntimeException('Se aceptó un timeout de Mercado Pago.');
    } catch (RuntimeException $exception) {
        storeWebhookAssert($exception->getMessage() === 'No se pudo verificar el pago.', 'El timeout no conservó el error genérico.');
    }

    $statusOrder = createStorePendingOrder($connection, [$publicId]);
    $pendingId = $paymentSequence . '1';
    $pending = storeWebhookPayment($pendingId, $statusOrder, 'pending');
    $pendingResult = processMercadoPagoWebhookPayment($connection, $config, $pendingId, 72, 5, storeWebhookMockTransport($pending));
    storeWebhookAssert($pendingResult['result'] === 'updated', 'El pago pendiente no se actualizó.');
    $statusRow = $connection->query('SELECT estado, pago_proveedor_id FROM pagos WHERE id = ' . $statusOrder['payment_id'])->fetch();
    storeWebhookAssert($statusRow['estado'] === 'pending' && $statusRow['pago_proveedor_id'] === $pendingId, 'El estado pendiente se guardó mal.');
    storeWebhookAssert((int) $connection->query('SELECT COUNT(*) FROM descargas WHERE pedido_id = ' . $statusOrder['id'])->fetchColumn() === 0, 'Pendiente generó descarga.');
    $rejectedId = $paymentSequence . '2';
    $rejected = storeWebhookPayment($rejectedId, $statusOrder, 'rejected');
    processMercadoPagoWebhookPayment($connection, $config, $rejectedId, 72, 5, storeWebhookMockTransport($rejected));
    storeWebhookAssert($connection->query('SELECT estado FROM pagos WHERE id = ' . $statusOrder['payment_id'])->fetchColumn() === 'rejected', 'El rechazo no se registró.');

    foreach ([
        ['overrides' => ['transaction_amount' => 1.00], 'reason' => 'amount_mismatch'],
        ['overrides' => ['currency_id' => 'USD'], 'reason' => 'currency_mismatch'],
        ['overrides' => ['external_reference' => '00000000-0000-4000-8000-000000000000'], 'reason' => 'external_reference_not_found'],
    ] as $index => $case) {
        $order = createStorePendingOrder($connection, [$publicId]);
        $id = $paymentSequence . (string) (3 + $index);
        $payment = storeWebhookPayment($id, $order, 'approved', $case['overrides']);
        $result = handleMercadoPagoWebhookPayment($connection, $config, $id, 72, 5, storeWebhookMockTransport($payment));
        storeWebhookAssert($result === ['result' => 'ignored', 'reason' => $case['reason']], 'La inconsistencia no fue ignorada.');
        storeWebhookAssert($connection->query('SELECT estado FROM pedidos WHERE id = ' . $order['id'])->fetchColumn() === 'pendiente', 'La inconsistencia aprobó el pedido.');
        storeWebhookAssert((int) $connection->query('SELECT COUNT(*) FROM descargas WHERE pedido_id = ' . $order['id'])->fetchColumn() === 0, 'La inconsistencia generó descarga.');
    }

    $approvedOrder = createStorePendingOrder($connection, [$publicId]);
    $approvedItemsBefore = json_encode($connection->query(
        'SELECT foto_registro_id, foto_id, nombre_archivo, precio_unitario FROM pedido_fotos WHERE pedido_id = ' . $approvedOrder['id'] . ' ORDER BY id'
    )->fetchAll(), JSON_THROW_ON_ERROR);
    $approvedId = $paymentSequence . '6';
    $approved = storeWebhookPayment($approvedId, $approvedOrder);
    $approvedTrace = [];
    $approvedDiagnostic = static function (string $stage, array $context = []) use (&$approvedTrace): void {
        $approvedTrace[] = $stage;
    };
    $approvedResult = handleMercadoPagoWebhookPayment(
        $connection,
        $config,
        $approvedId,
        72,
        5,
        storeWebhookMockTransport($approved),
        null,
        $approvedDiagnostic
    );
    storeWebhookAssert($approvedResult === ['result' => 'approved', 'download_created' => true], 'El pago válido no fue aprobado.');
    storeWebhookAssert(
        $approvedTrace === ['payment_lookup_started', 'payment_lookup_http_status', 'payment_payload_valid', 'order_lookup_started'],
        'El pago aprobado no recorrió las etapas diagnosticadas.'
    );
    $savedOrder = $connection->query('SELECT estado, pagado_en FROM pedidos WHERE id = ' . $approvedOrder['id'])->fetch();
    storeWebhookAssert($savedOrder['estado'] === 'pagado' && $savedOrder['pagado_en'] === '2026-07-22 18:30:00', 'El pedido pagado quedó incorrecto.');
    $savedPayment = $connection->query('SELECT pago_proveedor_id, estado, estado_detalle, monto, moneda, aprobado_en, respuesta_json FROM pagos WHERE id = ' . $approvedOrder['payment_id'])->fetch();
    storeWebhookAssert($savedPayment['pago_proveedor_id'] === $approvedId && $savedPayment['estado'] === 'approved' && $savedPayment['monto'] === '321.45' && $savedPayment['moneda'] === 'ARS', 'El pago aprobado quedó incorrecto.');
    storeWebhookAssert(!str_contains($savedPayment['respuesta_json'], 'payer') && !str_contains($savedPayment['respuesta_json'], 'mock-access-token'), 'respuesta_json guardó datos no permitidos.');
    $download = $connection->query('SELECT token, max_descargas, cantidad_descargas FROM descargas WHERE pedido_id = ' . $approvedOrder['id'])->fetch();
    storeWebhookAssert(preg_match('/^[a-f0-9]{64}$/', $download['token']) === 1 && (int) $download['max_descargas'] === 5 && (int) $download['cantidad_descargas'] === 0, 'El permiso de descarga no es seguro.');

    $repeatResult = handleMercadoPagoWebhookPayment($connection, $config, $approvedId, 72, 5, storeWebhookMockTransport($approved));
    storeWebhookAssert($repeatResult === ['result' => 'approved', 'download_created' => false], 'El webhook repetido no fue idempotente.');
    storeWebhookAssert((int) $connection->query('SELECT COUNT(*) FROM descargas WHERE pedido_id = ' . $approvedOrder['id'])->fetchColumn() === 1, 'El webhook repetido duplicó la descarga.');

    $rollbackOrder = createStorePendingOrder($connection, [$publicId]);
    $rollbackId = $paymentSequence . '7';
    $rollbackPayment = storeWebhookPayment($rollbackId, $rollbackOrder);
    try {
        handleMercadoPagoWebhookPayment(
            $connection,
            $config,
            $rollbackId,
            72,
            5,
            storeWebhookMockTransport($rollbackPayment),
            static function (): void { throw new RuntimeException('Rollback simulado.'); }
        );
        throw new RuntimeException('La falla simulada no interrumpió la confirmación.');
    } catch (RuntimeException $exception) {
        storeWebhookAssert($exception->getMessage() === 'Rollback simulado.', 'La prueba capturó un error inesperado.');
    }
    storeWebhookAssert($connection->query('SELECT estado FROM pedidos WHERE id = ' . $rollbackOrder['id'])->fetchColumn() === 'pendiente', 'El rollback dejó el pedido pagado.');
    $rollbackStoredPayment = $connection->query('SELECT estado, pago_proveedor_id FROM pagos WHERE id = ' . $rollbackOrder['payment_id'])->fetch();
    storeWebhookAssert($rollbackStoredPayment['estado'] === 'pendiente' && $rollbackStoredPayment['pago_proveedor_id'] === null, 'El rollback dejó el pago modificado.');
    storeWebhookAssert((int) $connection->query('SELECT COUNT(*) FROM descargas WHERE pedido_id = ' . $rollbackOrder['id'])->fetchColumn() === 0, 'El rollback dejó una descarga.');

    $approvedItemsAfter = json_encode($connection->query(
        'SELECT foto_registro_id, foto_id, nombre_archivo, precio_unitario FROM pedido_fotos WHERE pedido_id = ' . $approvedOrder['id'] . ' ORDER BY id'
    )->fetchAll(), JSON_THROW_ON_ERROR);
    storeWebhookAssert($approvedItemsAfter === $approvedItemsBefore, 'El webhook modificó pedido_fotos.');
    $log = file_get_contents($logPath);
    storeWebhookAssert(is_string($log) && str_contains($log, 'amount_mismatch') && str_contains($log, 'currency_mismatch'), 'Falta diagnóstico de inconsistencias.');
    storeWebhookAssert(!str_contains($log, 'mock-access-token') && !str_contains($log, 'webhook-test-secret'), 'El log expuso secretos.');

    $connection->rollBack();
    foreach ($tables as $table) {
        storeWebhookAssert((int) $connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === $before[$table], "La prueba dejó cambios en {$table}.");
    }
    fwrite(STDOUT, "store payment webhook tests: ok\n");
} finally {
    ini_set('error_log', is_string($previousLog) ? $previousLog : '');
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    if (is_string($logPath) && is_file($logPath)) {
        unlink($logPath);
    }
}
