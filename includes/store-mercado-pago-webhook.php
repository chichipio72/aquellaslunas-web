<?php

function parseMercadoPagoSignatureHeader(string $signature): ?array
{
    $parts = [];
    $rawParts = explode(',', $signature);
    foreach ($rawParts as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) !== 2 || isset($parts[$pair[0]])) {
            return null;
        }
        $parts[$pair[0]] = trim($pair[1]);
    }
    if (
        preg_match('/^[0-9]{10,16}$/', $parts['ts'] ?? '') !== 1
        || preg_match('/^[a-f0-9]{64}$/i', $parts['v1'] ?? '') !== 1
    ) {
        return null;
    }
    return ['ts' => $parts['ts'], 'v1' => strtolower($parts['v1']), 'component_count' => count($rawParts)];
}

function validateMercadoPagoWebhookSignature(
    string $signature,
    string $requestId,
    ?string $dataId,
    string $secret,
    ?callable $diagnostic = null
): bool {
    $parsed = parseMercadoPagoSignatureHeader($signature);
    if (
        $parsed === null
        || $secret === ''
        || ($requestId !== '' && preg_match('/^[A-Za-z0-9-]{1,100}$/', $requestId) !== 1)
        || ($dataId !== null && preg_match('/^[A-Za-z0-9]{1,100}$/', $dataId) !== 1)
    ) {
        return false;
    }
    $manifest = '';
    if ($dataId !== null) {
        $manifest .= 'id:' . $dataId . ';';
    }
    if ($requestId !== '') {
        $manifest .= 'request-id:' . $requestId . ';';
    }
    $manifest .= 'ts:' . $parsed['ts'] . ';';
    $calculated = hash_hmac('sha256', $manifest, $secret);
    if ($diagnostic !== null) {
        $manifestDiagnostic = '';
        if ($dataId !== null) {
            $manifestDiagnostic .= 'id:[length=' . strlen($dataId) . ',sha256=' . substr(hash('sha256', $dataId), 0, 12) . '];';
        }
        if ($requestId !== '') {
            $manifestDiagnostic .= 'request-id:[length=' . strlen($requestId) . ',sha256=' . substr(hash('sha256', $requestId), 0, 12) . '];';
        }
        $manifestDiagnostic .= 'ts:' . $parsed['ts'] . ';';
        $diagnostic('signature_diagnostic', [
            'data_id_length' => $dataId === null ? 0 : strlen($dataId),
            'data_id_php_type' => get_debug_type($dataId),
            'data_id_has_spaces' => $dataId !== null && preg_match('/\s/', $dataId) === 1,
            'request_id_length' => strlen($requestId),
            'request_id_has_spaces' => preg_match('/\s/', $requestId) === 1,
            'signature_ts' => $parsed['ts'],
            'signature_component_count' => $parsed['component_count'],
            'signature_v1_length' => strlen($parsed['v1']),
            'manifest_safe' => $manifestDiagnostic,
            'calculated_hmac_prefix' => substr($calculated, 0, 8),
            'received_v1_prefix' => substr($parsed['v1'], 0, 8),
        ]);
    }
    return hash_equals($calculated, $parsed['v1']);
}

function isStoreWebhookLocalAddress(?string $remoteAddress): bool
{
    return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
}

function authorizeMercadoPagoWebhook(
    array $config,
    string $signature,
    string $requestId,
    ?string $dataId,
    ?string $remoteAddress,
    ?callable $diagnostic = null
): bool {
    $secret = is_string($config['webhook_secret'] ?? null) ? $config['webhook_secret'] : '';
    if ($secret !== '') {
        return validateMercadoPagoWebhookSignature($signature, $requestId, $dataId, $secret, $diagnostic);
    }
    return ($config['mode'] ?? null) === 'test' && isStoreWebhookLocalAddress($remoteAddress);
}

function mercadoPagoWebhookHeader(array $server, string $headerName): string
{
    $wanted = strtolower(str_replace('_', '-', $headerName));
    foreach ($server as $key => $value) {
        if (!is_string($key) || !is_scalar($value)) {
            continue;
        }
        $normalized = strtolower(str_replace('_', '-', $key));
        if (str_starts_with($normalized, 'http-')) {
            $normalized = substr($normalized, 5);
        }
        if ($normalized === $wanted) {
            return (string) $value;
        }
    }
    return '';
}

function normalizeMercadoPagoWebhookNotification(array $query, mixed $body): array
{
    $queryIdValue = $query['data.id'] ?? $query['data_id'] ?? null;
    $queryId = is_scalar($queryIdValue) ? (string) $queryIdValue : null;
    $bodyIdValue = is_array($body) && is_array($body['data'] ?? null) ? ($body['data']['id'] ?? null) : null;
    $bodyId = is_scalar($bodyIdValue) ? (string) $bodyIdValue : null;
    $queryType = isset($query['type']) && is_scalar($query['type']) ? trim((string) $query['type']) : '';
    $bodyType = is_array($body) && is_scalar($body['type'] ?? null) ? trim((string) $body['type']) : '';
    $type = $queryType !== '' ? $queryType : $bodyType;
    $lookupId = $queryId !== null && $queryId !== '' ? $queryId : ($bodyId ?? '');
    return [
        'query_id' => $queryId !== '' ? $queryId : null,
        'body_id' => $bodyId !== '' ? $bodyId : null,
        'lookup_id' => $lookupId,
        'signature_data_id' => $queryId !== '' ? $queryId : null,
        'type' => $type,
        'ids_match' => $queryId === null || $queryId === '' || $bodyId === null || $bodyId === '' || $queryId === $bodyId,
    ];
}

function extractMercadoPagoWebhookPaymentId(array $query, mixed $body): string
{
    $notification = normalizeMercadoPagoWebhookNotification($query, $body);
    if ($notification['type'] !== 'payment') {
        throw new InvalidArgumentException('La notificación no es válida.');
    }
    $candidate = $notification['lookup_id'];
    if (preg_match('/^[0-9]{1,32}$/', $candidate) !== 1) {
        throw new InvalidArgumentException('La notificación no es válida.');
    }
    return $candidate;
}

function mercadoPagoPaymentAmountToDecimal(mixed $amount): ?string
{
    if (is_int($amount)) {
        $amount = (string) $amount;
    } elseif (is_float($amount)) {
        if (!is_finite($amount) || abs($amount - round($amount, 2)) > 0.0000001) {
            return null;
        }
        $amount = number_format($amount, 2, '.', '');
    }
    if (!is_string($amount) || preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $amount) !== 1) {
        return null;
    }
    [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '');
    return $whole . '.' . str_pad($decimal, 2, '0');
}

function normalizeMercadoPagoPayment(array $payment): array
{
    $id = is_scalar($payment['id'] ?? null) ? (string) $payment['id'] : '';
    $status = is_string($payment['status'] ?? null) ? trim($payment['status']) : '';
    $statusDetail = is_string($payment['status_detail'] ?? null) ? trim($payment['status_detail']) : '';
    $externalReference = is_string($payment['external_reference'] ?? null) ? trim($payment['external_reference']) : '';
    $currency = is_string($payment['currency_id'] ?? null) ? strtoupper(trim($payment['currency_id'])) : '';
    $amount = mercadoPagoPaymentAmountToDecimal($payment['transaction_amount'] ?? null);
    if (
        preg_match('/^[0-9]{1,32}$/', $id) !== 1
        || preg_match('/^[a-z][a-z0-9_]{0,29}$/', $status) !== 1
        || strlen($statusDetail) > 100
        || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $externalReference) !== 1
        || preg_match('/^[A-Z]{3}$/', $currency) !== 1
        || $amount === null
    ) {
        throw new RuntimeException('La respuesta del pago no es válida.');
    }
    $approvedAt = null;
    if ($status === 'approved') {
        $rawApprovedAt = $payment['date_approved'] ?? null;
        try {
            $date = is_string($rawApprovedAt) && $rawApprovedAt !== '' ? new DateTimeImmutable($rawApprovedAt) : null;
        } catch (Throwable) {
            $date = null;
        }
        if (!$date instanceof DateTimeImmutable) {
            throw new RuntimeException('La respuesta del pago no es válida.');
        }
        $approvedAt = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    return [
        'id' => $id,
        'status' => $status,
        'status_detail' => $statusDetail !== '' ? $statusDetail : null,
        'external_reference' => $externalReference,
        'amount' => $amount,
        'currency' => $currency,
        'approved_at' => $approvedAt,
        'response_json' => json_encode([
            'id' => $id,
            'status' => $status,
            'status_detail' => $statusDetail !== '' ? $statusDetail : null,
            'external_reference' => $externalReference,
            'transaction_amount' => $amount,
            'currency_id' => $currency,
            'date_approved' => $approvedAt,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ];
}

function logStorePaymentValidationFailure(string $reason, array $payment, ?callable $diagnostic = null): void
{
    $allowedReasons = [
        'external_reference_not_found', 'amount_mismatch', 'currency_mismatch',
        'different_payment_already_approved', 'payment_record_not_found',
    ];
    $safeReason = in_array($reason, $allowedReasons, true) ? $reason : 'invalid_payment';
    if ($diagnostic !== null) {
        $diagnostic('finished', ['result' => 'ignored', 'reason' => $safeReason]);
        return;
    }
    error_log('Store payment validation failed ' . json_encode([
        'reason' => $safeReason,
        'payment_id' => $payment['id'] ?? null,
        'status' => $payment['status'] ?? null,
        'external_reference' => $payment['external_reference'] ?? null,
    ], JSON_UNESCAPED_SLASHES) . '.');
}

function confirmStoreMercadoPagoPayment(
    PDO $connection,
    array $rawPayment,
    int $downloadExpiryHours,
    int $downloadMaxCount,
    ?callable $beforeCommit = null,
    ?callable $diagnostic = null
): array {
    if ($downloadExpiryHours < 1 || $downloadExpiryHours > 8760 || $downloadMaxCount < 1 || $downloadMaxCount > 100) {
        throw new InvalidArgumentException('La configuración de descarga no es válida.');
    }
    $payment = normalizeMercadoPagoPayment($rawPayment);
    if ($diagnostic !== null) {
        $diagnostic('payment_payload_valid');
    }
    $ownsTransaction = !$connection->inTransaction();
    $savepoint = 'store_payment_webhook';
    $ownsTransaction ? $connection->beginTransaction() : $connection->exec("SAVEPOINT {$savepoint}");
    try {
        if ($diagnostic !== null) {
            $diagnostic('order_lookup_started');
        }
        $orderStatement = $connection->prepare(
            'SELECT id, estado, monto_total, moneda, pagado_en FROM pedidos WHERE codigo_publico = :codigo FOR UPDATE'
        );
        $orderStatement->execute(['codigo' => $payment['external_reference']]);
        $order = $orderStatement->fetch();
        if (!is_array($order)) {
            logStorePaymentValidationFailure('external_reference_not_found', $payment, $diagnostic);
            $ownsTransaction ? $connection->commit() : $connection->exec("RELEASE SAVEPOINT {$savepoint}");
            return ['result' => 'ignored', 'reason' => 'external_reference_not_found'];
        }
        $paymentStatement = $connection->prepare(
            'SELECT id, pago_proveedor_id, estado FROM pagos WHERE pedido_id = :pedido_id AND proveedor = :proveedor FOR UPDATE'
        );
        $paymentStatement->execute(['pedido_id' => $order['id'], 'proveedor' => 'mercado_pago']);
        $storedPayment = $paymentStatement->fetch();
        if (!is_array($storedPayment)) {
            logStorePaymentValidationFailure('payment_record_not_found', $payment, $diagnostic);
            $ownsTransaction ? $connection->commit() : $connection->exec("RELEASE SAVEPOINT {$savepoint}");
            return ['result' => 'ignored', 'reason' => 'payment_record_not_found'];
        }
        if ($payment['amount'] !== (string) $order['monto_total']) {
            logStorePaymentValidationFailure('amount_mismatch', $payment, $diagnostic);
            $ownsTransaction ? $connection->commit() : $connection->exec("RELEASE SAVEPOINT {$savepoint}");
            return ['result' => 'ignored', 'reason' => 'amount_mismatch'];
        }
        if ($payment['currency'] !== strtoupper((string) $order['moneda'])) {
            logStorePaymentValidationFailure('currency_mismatch', $payment, $diagnostic);
            $ownsTransaction ? $connection->commit() : $connection->exec("RELEASE SAVEPOINT {$savepoint}");
            return ['result' => 'ignored', 'reason' => 'currency_mismatch'];
        }
        $storedProviderId = is_string($storedPayment['pago_proveedor_id']) ? $storedPayment['pago_proveedor_id'] : '';
        if ($order['estado'] === 'pagado' && $storedProviderId !== '' && $storedProviderId !== $payment['id']) {
            logStorePaymentValidationFailure('different_payment_already_approved', $payment, $diagnostic);
            $ownsTransaction ? $connection->commit() : $connection->exec("RELEASE SAVEPOINT {$savepoint}");
            return ['result' => 'ignored', 'reason' => 'different_payment_already_approved'];
        }

        $updatePayment = $connection->prepare(
            'UPDATE pagos SET pago_proveedor_id = :pago_id, estado = :estado, estado_detalle = :detalle, '
            . 'monto = :monto, moneda = :moneda, aprobado_en = :aprobado_en, respuesta_json = :respuesta WHERE id = :id'
        );
        $updatePayment->execute([
            'pago_id' => $payment['id'],
            'estado' => $payment['status'],
            'detalle' => $payment['status_detail'],
            'monto' => $payment['amount'],
            'moneda' => $payment['currency'],
            'aprobado_en' => $payment['approved_at'],
            'respuesta' => $payment['response_json'],
            'id' => $storedPayment['id'],
        ]);

        $downloadCreated = false;
        if ($payment['status'] === 'approved') {
            $updateOrder = $connection->prepare(
                'UPDATE pedidos SET estado = :estado, pagado_en = :pagado_en WHERE id = :id'
            );
            $updateOrder->execute(['estado' => 'pagado', 'pagado_en' => $payment['approved_at'], 'id' => $order['id']]);
            $download = $connection->prepare('SELECT id FROM descargas WHERE pedido_id = :pedido_id LIMIT 1 FOR UPDATE');
            $download->execute(['pedido_id' => $order['id']]);
            if ($download->fetchColumn() === false) {
                $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                    ->modify('+' . $downloadExpiryHours . ' hours')
                    ->format('Y-m-d H:i:s');
                $insertDownload = $connection->prepare(
                    'INSERT INTO descargas (pedido_id, token, vence_en, max_descargas) '
                    . 'VALUES (:pedido_id, :token, :vence_en, :max_descargas)'
                );
                $insertDownload->execute([
                    'pedido_id' => $order['id'],
                    'token' => bin2hex(random_bytes(32)),
                    'vence_en' => $expiresAt,
                    'max_descargas' => $downloadMaxCount,
                ]);
                $downloadCreated = true;
            }
        }
        if ($beforeCommit !== null) {
            $beforeCommit($payment, $order);
        }
        $ownsTransaction ? $connection->commit() : $connection->exec("RELEASE SAVEPOINT {$savepoint}");
        return [
            'result' => $payment['status'] === 'approved' ? 'approved' : 'updated',
            'download_created' => $downloadCreated,
        ];
    } catch (Throwable $exception) {
        if ($ownsTransaction && $connection->inTransaction()) {
            $connection->rollBack();
        } elseif (!$ownsTransaction) {
            $connection->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
        }
        throw $exception;
    }
}

function processMercadoPagoWebhookPayment(
    PDO $connection,
    array $config,
    string $paymentId,
    int $downloadExpiryHours,
    int $downloadMaxCount,
    ?callable $transport = null,
    ?callable $beforeCommit = null,
    ?callable $diagnostic = null
): array {
    $payment = getMercadoPagoPayment($config, $paymentId, $transport, $diagnostic);
    return confirmStoreMercadoPagoPayment(
        $connection,
        $payment,
        $downloadExpiryHours,
        $downloadMaxCount,
        $beforeCommit,
        $diagnostic
    );
}

function handleMercadoPagoWebhookPayment(
    PDO $connection,
    array $config,
    string $paymentId,
    int $downloadExpiryHours,
    int $downloadMaxCount,
    ?callable $transport = null,
    ?callable $beforeCommit = null,
    ?callable $diagnostic = null
): array {
    try {
        return processMercadoPagoWebhookPayment(
            $connection,
            $config,
            $paymentId,
            $downloadExpiryHours,
            $downloadMaxCount,
            $transport,
            $beforeCommit,
            $diagnostic
        );
    } catch (MercadoPagoApiException $exception) {
        if ($exception->operation() === 'payment_lookup' && $exception->httpStatus() === 404) {
            if ($diagnostic !== null) {
                $diagnostic('finished', ['result' => 'ignored', 'reason' => 'payment_not_found']);
            } else {
                error_log('Mercado Pago webhook ignored [reason=payment_not_found http_status=404].');
            }
            return ['result' => 'ignored', 'reason' => 'payment_not_found'];
        }
        throw $exception;
    }
}
