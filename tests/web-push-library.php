<?php

require_once __DIR__ . '/../vendor/autoload.php';

function webPushLibraryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$keys = Minishlink\WebPush\VAPID::createVapidKeys();
webPushLibraryAssert(isset($keys['publicKey'], $keys['privateKey']), 'La biblioteca no generó claves VAPID.');
$sender = new Minishlink\WebPush\WebPush([
    'VAPID' => [
        'subject' => 'mailto:pruebas@example.com',
        'publicKey' => $keys['publicKey'],
        'privateKey' => $keys['privateKey'],
    ],
]);
webPushLibraryAssert($sender instanceof Minishlink\WebPush\WebPush, 'No se pudo construir el emisor Web Push.');

echo "OK web push library\n";
