<?php

require_once __DIR__ . '/../includes/web-push.php';

function webPushAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$previousPublic = getenv('WEB_PUSH_VAPID_PUBLIC_KEY');
$previousPrivate = getenv('WEB_PUSH_VAPID_PRIVATE_KEY');
$previousSubject = getenv('WEB_PUSH_VAPID_SUBJECT');

try {
    $publicKey = 'B' . str_repeat('A', 86);
    $privateKey = rtrim(strtr(base64_encode(str_repeat("\0", 31) . "\1"), '+/', '-_'), '=');
    putenv('WEB_PUSH_VAPID_PUBLIC_KEY=' . $publicKey);
    putenv('WEB_PUSH_VAPID_PRIVATE_KEY=' . $privateKey);
    putenv('WEB_PUSH_VAPID_SUBJECT=mailto:pruebas@example.com');
    $config = loadWebPushPublicConfig('/ruta/inexistente');
    webPushAssert($config['public_key'] === $publicKey, 'No se cargó la clave pública VAPID.');
    webPushAssert($config['subject'] === 'mailto:pruebas@example.com', 'No se cargó el subject VAPID.');
    $serverConfig = loadWebPushServerConfig('/ruta/inexistente');
    webPushAssert($serverConfig['private_key'] === $privateKey, 'No se cargó la clave privada VAPID server-side.');

    webPushAssert(astronomyWebPushNormalizeMessage(' Título ', 120, 'El título') === 'Título', 'No se normalizó el título.');
    webPushAssert(astronomyWebPushNormalizeTargetUrl('./') === './', 'No se aceptó la portada relativa al scope.');
    webPushAssert(astronomyWebPushNormalizeTargetUrl('/astro/') === '/astro/', 'No se aceptó una ruta interna absoluta.');
    $expiredReport = new class {
        public function getResponse(): object { return new class { public function getStatusCode(): int { return 410; } public function getReasonPhrase(): string { return 'Gone'; } }; }
        public function isSubscriptionExpired(): bool { return false; }
    };
    $expiredFailure = astronomyWebPushSanitizedFailure($expiredReport);
    webPushAssert($expiredFailure === ['message' => 'HTTP 410 Gone', 'expired' => true], 'No se reconoció una suscripción expirada.');

    $valid = astronomyWebPushValidateSubscription([
        'endpoint' => 'https://push.example.com/subscription/123',
        'keys' => ['p256dh' => 'B' . str_repeat('A', 86), 'auth' => str_repeat('b', 22)],
    ]);
    webPushAssert($valid['endpoint'] === 'https://push.example.com/subscription/123', 'El endpoint válido cambió.');

    foreach ([
        [],
        ['endpoint' => 'http://push.example.com/123', 'keys' => ['p256dh' => 'abc', 'auth' => 'def']],
        ['endpoint' => 'https://push.example.com/123', 'keys' => ['p256dh' => 'clave inválida', 'auth' => 'def']],
    ] as $invalid) {
        try {
            astronomyWebPushValidateSubscription($invalid);
            throw new RuntimeException('Una suscripción inválida fue aceptada.');
        } catch (InvalidArgumentException $exception) {
        }
    }

    $clientScript = file_get_contents(__DIR__ . '/../assets/js/push-notifications.js');
    $workerScript = file_get_contents(__DIR__ . '/../service-worker.js');
    webPushAssert(is_string($clientScript) && str_contains($clientScript, 'Notification.requestPermission()'), 'Falta la solicitud explícita de permiso.');
    webPushAssert(str_contains($clientScript, 'pushManager.getSubscription()'), 'No se reutiliza la suscripción existente.');
    webPushAssert(str_contains($clientScript, 'pushManager.subscribe('), 'Falta el alta PushManager.');
    webPushAssert(str_contains($clientScript, 'applicationServerKey'), 'Falta la clave VAPID pública en la suscripción.');
    webPushAssert(str_contains($clientScript, 'subscription.unsubscribe()'), 'Falta la desactivación del navegador.');
    webPushAssert(!str_contains($clientScript, 'WEB_PUSH_VAPID_PRIVATE_KEY'), 'El frontend referencia la clave privada.');
    webPushAssert(is_string($workerScript) && str_contains($workerScript, "addEventListener('push'"), 'El service worker no atiende push.');
    webPushAssert(str_contains($workerScript, "addEventListener('notificationclick'"), 'El service worker no atiende el clic.');
    webPushAssert(str_contains($workerScript, 'new URL(path, self.registration.scope).href'),
        'Los recursos de notificación no se resuelven desde el scope local o /astro/.');
    webPushAssert(str_contains($workerScript, "icon: notificationAssetUrl('assets/images/favicon/icon-192.png')"),
        'La notificación no declara el icono principal de Aquellas Lunas.');
    webPushAssert(str_contains($workerScript, "badge: notificationAssetUrl('assets/images/favicon/badge-96.png')"),
        'La notificación no declara el badge monocromo.');
    $badge = file_get_contents(__DIR__ . '/../assets/images/favicon/badge-96.png');
    webPushAssert(is_string($badge) && substr($badge, 1, 3) === 'PNG'
        && unpack('Nwidth/Nheight', substr($badge, 16, 8)) === ['width' => 96, 'height' => 96],
        'El badge no es un PNG válido de 96×96.');
    $manifest = json_decode((string) file_get_contents(__DIR__ . '/../manifest.webmanifest'), true);
    webPushAssert(is_array($manifest) && in_array([
        'src' => 'assets/images/favicon/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png',
    ], $manifest['icons'] ?? [], true), 'El manifiesto no conserva el icono 192×192 adecuado.');
    webPushAssert(!str_contains($workerScript, 'caches.'), 'El service worker introdujo caché fuera del alcance.');
    $homeSource = file_get_contents(__DIR__ . '/../index.php');
    webPushAssert(is_string($homeSource) && !str_contains($homeSource, 'data-push-notifications'), 'La portada todavía contiene el bloque de suscripción.');
} finally {
    is_string($previousPublic) ? putenv('WEB_PUSH_VAPID_PUBLIC_KEY=' . $previousPublic) : putenv('WEB_PUSH_VAPID_PUBLIC_KEY');
    is_string($previousPrivate) ? putenv('WEB_PUSH_VAPID_PRIVATE_KEY=' . $previousPrivate) : putenv('WEB_PUSH_VAPID_PRIVATE_KEY');
    is_string($previousSubject) ? putenv('WEB_PUSH_VAPID_SUBJECT=' . $previousSubject) : putenv('WEB_PUSH_VAPID_SUBJECT');
}

echo "OK web push\n";
