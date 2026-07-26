<?php

require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-checkout.php';
require_once __DIR__ . '/../includes/mercado-pago-client.php';

function storeCheckoutAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$connection = getStoreDatabaseConnection();
$tables = ['fotos', 'pedidos', 'pedido_fotos', 'pagos', 'descargas'];
$before = [];
foreach ($tables as $table) {
    $before[$table] = (int) $connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
$suffix = bin2hex(random_bytes(6));
$firstPublicId = hash('sha256', 'checkout-first-' . $suffix);
$secondPublicId = hash('sha256', 'checkout-second-' . $suffix);
$hiddenPublicId = hash('sha256', 'checkout-hidden-' . $suffix);

try {
    $connection->beginTransaction();
    $insert = $connection->prepare(
        'INSERT INTO fotos (foto_id, nombre_archivo, archivo_original, archivo_preview_tienda, titulo, precio, moneda, disponible) '
        . 'VALUES (:foto_id, :nombre, :original, :preview, :titulo, :precio, :moneda, :disponible)'
    );
    foreach ([
        [$firstPublicId, 'first-' . $suffix . '.jpg', 'Primera foto', '125.50', 1],
        [$secondPublicId, 'second-' . $suffix . '.jpg', null, '274.50', 1],
        [$hiddenPublicId, 'hidden-' . $suffix . '.jpg', 'Oculta', '99.00', 0],
    ] as [$publicId, $filename, $title, $price, $available]) {
        $insert->execute([
            'foto_id' => $publicId,
            'nombre' => $filename,
            'original' => 'checkout-' . $filename,
            'preview' => 'tienda/' . $publicId . '.jpg',
            'titulo' => $title,
            'precio' => $price,
            'moneda' => 'ARS',
            'disponible' => $available,
        ]);
    }

    foreach ([[], [str_repeat('f', 64)], [$hiddenPublicId]] as $invalidSelection) {
        $rejected = false;
        try {
            createStorePendingOrder($connection, $invalidSelection);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $rejected = true;
        }
        storeCheckoutAssert($rejected, 'Se aceptó una selección inválida o no disponible.');
    }

    $ordersBeforeFailure = (int) $connection->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
    try {
        createStorePendingOrder($connection, [$firstPublicId], static function (): void {
            throw new RuntimeException('Falla simulada de inserción.');
        });
        throw new RuntimeException('La falla simulada no interrumpió el pedido.');
    } catch (RuntimeException $exception) {
        storeCheckoutAssert((int) $connection->query('SELECT COUNT(*) FROM pedidos')->fetchColumn() === $ordersBeforeFailure, 'La inserción fallida no hizo rollback.');
    }

    $order = createStorePendingOrder($connection, [$firstPublicId, $secondPublicId]);
    storeCheckoutAssert($order['total'] === '400.00' && $order['currency'] === 'ARS', 'El total no se recalculó desde MySQL.');
    storeCheckoutAssert(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $order['public_code']) === 1, 'El código público no es UUID v4.');
    $savedOrder = $connection->query('SELECT estado, monto_total, moneda FROM pedidos WHERE id = ' . $order['id'])->fetch();
    storeCheckoutAssert($savedOrder === ['estado' => 'pendiente', 'monto_total' => '400.00', 'moneda' => 'ARS'], 'El pedido pendiente es incorrecto.');
    $items = $connection->query('SELECT foto_registro_id, foto_id, nombre_archivo, precio_unitario FROM pedido_fotos WHERE pedido_id = ' . $order['id'] . ' ORDER BY id')->fetchAll();
    storeCheckoutAssert(count($items) === 2 && $items[0]['foto_id'] === $firstPublicId && $items[0]['precio_unitario'] === '125.50' && $items[1]['precio_unitario'] === '274.50', 'Los valores históricos del pedido son incorrectos.');

    $config = [
        'mode' => 'test', 'access_token' => 'mock-access-token',
        'webhook_secret' => '',
        'success_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-exitoso.php',
        'pending_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-pendiente.php',
        'failure_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-fallido.php',
        'notification_url' => 'https://aquellaslunas.com.ar/astro/webhooks/mercado-pago.php',
    ];
    $payload = buildMercadoPagoPreferencePayload($order, $config);
    storeCheckoutAssert($payload['external_reference'] === $order['public_code'], 'external_reference no coincide con codigo_publico.');
    storeCheckoutAssert(!array_key_exists('notification_url', $payload), 'La preferencia todavía contiene notification_url.');
    storeCheckoutAssert(array_sum(array_map(static fn (array $item): float => $item['unit_price'], $payload['items'])) === 400.0, 'La preferencia no conserva el total.');
    $mockTransport = static function (array $request) use ($order): array {
        storeCheckoutAssert($request['url'] === 'https://api.mercadopago.com/checkout/preferences', 'Endpoint de preferencia incorrecto.');
        storeCheckoutAssert(in_array('Authorization: Bearer mock-access-token', $request['headers'], true), 'Falta Authorization Bearer.');
        storeCheckoutAssert(in_array('X-Idempotency-Key: ' . $order['public_code'], $request['headers'], true), 'Falta la idempotencia del pedido.');
        storeCheckoutAssert(!str_contains($request['body'], 'webhook_secret'), 'El secreto webhook intervino en la preferencia.');
        $sentPayload = json_decode($request['body'], true, 32, JSON_THROW_ON_ERROR);
        storeCheckoutAssert(!array_key_exists('notification_url', $sentPayload), 'El cuerpo enviado a /checkout/preferences contiene notification_url.');
        storeCheckoutAssert(isset($sentPayload['back_urls']) && ($sentPayload['auto_return'] ?? null) === 'approved', 'Se alteraron back_urls o auto_return.');
        return ['status' => 201, 'body' => json_encode([
            'id' => 'mock-preference-123',
            'date_created' => '2026-07-22T12:00:00Z',
            'init_point' => 'https://www.mercadopago.com.ar/checkout/start?pref_id=mock-preference-123',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/pay?pref_id=mock-preference-123',
        ], JSON_THROW_ON_ERROR)];
    };
    $preference = createMercadoPagoPreference($config, $payload, $order['public_code'], $mockTransport);
    recordStoreMercadoPagoPreference($connection, $order, $preference);
    $payment = $connection->query('SELECT referencia_externa, preferencia_proveedor_id, pago_proveedor_id, estado, monto, moneda, respuesta_json FROM pagos WHERE id = ' . $order['payment_id'])->fetch();
    storeCheckoutAssert($payment['referencia_externa'] === $order['public_code'] && $payment['preferencia_proveedor_id'] === 'mock-preference-123' && $payment['pago_proveedor_id'] === null, 'Los identificadores de pago se mezclaron.');
    storeCheckoutAssert($payment['estado'] === 'pendiente' && $payment['monto'] === '400.00' && $payment['moneda'] === 'ARS', 'El pago pendiente es incorrecto.');
    storeCheckoutAssert(!str_contains($payment['respuesta_json'], 'mock-access-token'), 'El token llegó a respuesta_json.');

    $failedOrder = createStorePendingOrder($connection, [$firstPublicId]);
    $mercadoPagoFailed = false;
    try {
        createMercadoPagoPreference($config, buildMercadoPagoPreferencePayload($failedOrder, $config), $failedOrder['public_code'], static fn (): array => ['status' => 500, 'body' => '{"message":"mock"}']);
    } catch (RuntimeException $exception) {
        $mercadoPagoFailed = true;
        $failedPayment = $connection->query('SELECT estado, preferencia_proveedor_id FROM pagos WHERE id = ' . $failedOrder['payment_id'])->fetch();
        storeCheckoutAssert($failedPayment['estado'] === 'pendiente' && $failedPayment['preferencia_proveedor_id'] === null, 'La falla de Mercado Pago habilitó o perdió el pedido.');
        storeCheckoutAssert((int) $connection->query('SELECT COUNT(*) FROM descargas WHERE pedido_id = ' . $failedOrder['id'])->fetchColumn() === 0, 'Se creó una descarga sin pago confirmado.');
    }
    storeCheckoutAssert($mercadoPagoFailed, 'Se aceptó una falla simulada de Mercado Pago.');

    $connection->rollBack();
    foreach ($tables as $table) {
        storeCheckoutAssert((int) $connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === $before[$table], "La prueba dejó cambios en {$table}.");
    }
    fwrite(STDOUT, "store checkout tests: ok\n");
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
