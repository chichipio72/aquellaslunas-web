<?php

require_once __DIR__ . '/../includes/web-push.php';
require_once __DIR__ . '/../vendor/autoload.php';

function webPushSendAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function webPushSendBase64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

$connection = getWebDatabaseConnection();
$endpoint = 'https://127.0.0.1:1/aquellas-lunas-web-push-test';
$delete = $connection->prepare('DELETE FROM web_push_subscriptions WHERE endpoint_hash = ?');
$delete->execute([hash('sha256', $endpoint, true)]);

try {
    $receiver = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $details = is_object($receiver) ? openssl_pkey_get_details($receiver) : false;
    if (!is_array($details) || !is_array($details['ec'] ?? null) || !is_string($details['ec']['x'] ?? null) || !is_string($details['ec']['y'] ?? null)) {
        throw new RuntimeException('No se pudo preparar la suscripción de prueba.');
    }
    $subscription = [
        'endpoint' => $endpoint,
        'p256dh' => webPushSendBase64Url("\x04" . $details['ec']['x'] . $details['ec']['y']),
        'auth' => webPushSendBase64Url(random_bytes(16)),
    ];
    astronomyWebPushSaveSubscription($connection, $subscription, 'Aquellas Lunas integration test');
    $testSubscription = $connection->prepare('SELECT id FROM web_push_subscriptions WHERE endpoint_hash = ?');
    $testSubscription->execute([hash('sha256', $endpoint, true)]);
    $testSubscriptionId = (int) $testSubscription->fetchColumn();
    $keys = Minishlink\WebPush\VAPID::createVapidKeys();
    $result = astronomyWebPushSend($connection, [
        'subject' => 'mailto:pruebas@example.com',
        'public_key' => $keys['publicKey'],
        'private_key' => $keys['privateKey'],
    ], $testSubscriptionId, 'Aquellas Lunas', 'Esta es una notificación de prueba.', './');

    webPushSendAssert($result['sent'] === 1, 'El emisor no procesó la suscripción de prueba.');
    webPushSendAssert($result['success'] === 0 && $result['failed'] === 1, 'El fallo controlado no se contabilizó correctamente.');
    $statement = $connection->prepare('SELECT active, last_error_at, last_error_message FROM web_push_subscriptions WHERE endpoint_hash = ?');
    $statement->execute([hash('sha256', $endpoint, true)]);
    $row = $statement->fetch();
    webPushSendAssert(is_array($row) && (int) $row['active'] === 1, 'Un error temporal desactivó la suscripción.');
    webPushSendAssert($row['last_error_at'] !== null && $row['last_error_message'] === 'Error de envío Web Push', 'El error no se guardó sanitizado.');
} finally {
    $delete->execute([hash('sha256', $endpoint, true)]);
}

echo "OK web push send\n";
