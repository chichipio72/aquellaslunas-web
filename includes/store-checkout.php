<?php

function generateStoreOrderPublicCode(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function normalizeStoreCheckoutPhotoIds(mixed $photoIds): array
{
    if (!is_array($photoIds) || $photoIds === [] || count($photoIds) > 50) {
        throw new InvalidArgumentException('La selección no es válida.');
    }
    $normalized = [];
    foreach ($photoIds as $photoId) {
        if (!is_string($photoId) || preg_match('/^[a-f0-9]{64}$/', $photoId) !== 1) {
            throw new InvalidArgumentException('La selección no es válida.');
        }
        $normalized[$photoId] = $photoId;
    }
    return array_values($normalized);
}

function loadStoreCheckoutPhotos(PDO $connection, array $publicPhotoIds): array
{
    $ids = normalizeStoreCheckoutPhotoIds($publicPhotoIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $connection->prepare(
        "SELECT id, foto_id, nombre_archivo, titulo, precio, moneda FROM fotos "
        . "WHERE disponible = 1 AND foto_id IN ({$placeholders}) FOR UPDATE"
    );
    $statement->execute($ids);
    $byPublicId = [];
    foreach ($statement->fetchAll() as $photo) {
        $byPublicId[$photo['foto_id']] = $photo;
    }
    if (count($byPublicId) !== count($ids)) {
        throw new RuntimeException('La selección no está disponible.');
    }
    $photos = [];
    foreach ($ids as $id) {
        $photos[] = $byPublicId[$id];
    }
    return $photos;
}

function createStorePendingOrder(PDO $connection, array $publicPhotoIds, ?callable $afterOrderInsert = null): array
{
    $ownsTransaction = !$connection->inTransaction();
    $savepoint = 'store_checkout_order';
    $ownsTransaction ? $connection->beginTransaction() : $connection->exec("SAVEPOINT {$savepoint}");
    try {
        $photos = loadStoreCheckoutPhotos($connection, $publicPhotoIds);
        $currency = null;
        $totalCents = 0;
        foreach ($photos as $photo) {
            $photoCurrency = strtoupper(trim((string) $photo['moneda']));
            $price = (string) $photo['precio'];
            if (preg_match('/^[A-Z]{3}$/', $photoCurrency) !== 1 || preg_match('/^(?:0|[1-9]\d{0,7})\.\d{2}$/', $price) !== 1 || (float) $price <= 0) {
                throw new RuntimeException('La selección no está disponible.');
            }
            $currency ??= $photoCurrency;
            if ($currency !== $photoCurrency) {
                throw new RuntimeException('Las fotos seleccionadas deben usar la misma moneda.');
            }
            [$whole, $decimal] = explode('.', $price, 2);
            $totalCents += ((int) $whole * 100) + (int) $decimal;
        }
        if ($totalCents < 1 || $currency === null) {
            throw new RuntimeException('La selección no está disponible.');
        }
        $total = intdiv($totalCents, 100) . '.' . str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT);
        $publicCode = generateStoreOrderPublicCode();
        $order = $connection->prepare('INSERT INTO pedidos (codigo_publico, estado, monto_total, moneda) VALUES (:codigo, :estado, :total, :moneda)');
        $order->execute(['codigo' => $publicCode, 'estado' => 'pendiente', 'total' => $total, 'moneda' => $currency]);
        $orderId = (int) $connection->lastInsertId();
        if ($afterOrderInsert !== null) {
            $afterOrderInsert($orderId);
        }
        $item = $connection->prepare(
            'INSERT INTO pedido_fotos (pedido_id, foto_registro_id, foto_id, nombre_archivo, precio_unitario) '
            . 'VALUES (:pedido_id, :foto_registro_id, :foto_id, :nombre_archivo, :precio)'
        );
        foreach ($photos as $photo) {
            $item->execute([
                'pedido_id' => $orderId,
                'foto_registro_id' => $photo['id'],
                'foto_id' => $photo['foto_id'],
                'nombre_archivo' => $photo['nombre_archivo'],
                'precio' => $photo['precio'],
            ]);
        }
        $payment = $connection->prepare(
            'INSERT INTO pagos (pedido_id, proveedor, referencia_externa, estado, monto, moneda) '
            . 'VALUES (:pedido_id, :proveedor, :referencia, :estado, :monto, :moneda)'
        );
        $payment->execute([
            'pedido_id' => $orderId,
            'proveedor' => 'mercado_pago',
            'referencia' => $publicCode,
            'estado' => 'pendiente',
            'monto' => $total,
            'moneda' => $currency,
        ]);
        $paymentId = (int) $connection->lastInsertId();
        $ownsTransaction ? $connection->commit() : $connection->exec("RELEASE SAVEPOINT {$savepoint}");
        return [
            'id' => $orderId,
            'public_code' => $publicCode,
            'total' => $total,
            'currency' => $currency,
            'photos' => $photos,
            'payment_id' => $paymentId,
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

function recordStoreMercadoPagoPreference(PDO $connection, array $order, array $preference): void
{
    $sanitized = json_encode([
        'preference_id' => $preference['id'],
        'mode' => $preference['mode'],
        'created_at' => $preference['date_created'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $statement = $connection->prepare(
        'UPDATE pagos SET preferencia_proveedor_id = :preferencia, respuesta_json = :respuesta '
        . 'WHERE id = :id AND pedido_id = :pedido_id AND proveedor = :proveedor AND estado = :estado'
    );
    $statement->execute([
        'preferencia' => $preference['id'],
        'respuesta' => $sanitized,
        'id' => $order['payment_id'],
        'pedido_id' => $order['id'],
        'proveedor' => 'mercado_pago',
        'estado' => 'pendiente',
    ]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('No se pudo registrar la preferencia de pago.');
    }
}
