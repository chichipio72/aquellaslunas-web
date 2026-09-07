<?php

require_once __DIR__ . '/web-database.php';

const WEB_PUSH_ENDPOINT_MAX_LENGTH = 2048;
const WEB_PUSH_KEY_MAX_LENGTH = 255;
const WEB_PUSH_USER_AGENT_MAX_LENGTH = 500;

function astronomyWebPushDecodeBase64Url(string $value): string|false
{
    $normalized = strtr(rtrim($value, '='), '-_', '+/');
    $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);
    return base64_decode($normalized, true);
}

function astronomyWebPushValidateSubscription(array $payload): array
{
    $endpoint = trim((string) ($payload['endpoint'] ?? ''));
    $keys = $payload['keys'] ?? null;
    $p256dh = is_array($keys) ? trim((string) ($keys['p256dh'] ?? '')) : '';
    $auth = is_array($keys) ? trim((string) ($keys['auth'] ?? '')) : '';

    if (
        $endpoint === ''
        || strlen($endpoint) > WEB_PUSH_ENDPOINT_MAX_LENGTH
        || filter_var($endpoint, FILTER_VALIDATE_URL) === false
        || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https'
        || !is_string(parse_url($endpoint, PHP_URL_HOST))
        || parse_url($endpoint, PHP_URL_USER) !== null
        || parse_url($endpoint, PHP_URL_PASS) !== null
        || parse_url($endpoint, PHP_URL_FRAGMENT) !== null
    ) {
        throw new InvalidArgumentException('La suscripción no contiene un endpoint válido.');
    }
    foreach ([$p256dh, $auth] as $key) {
        if ($key === '' || strlen($key) > WEB_PUSH_KEY_MAX_LENGTH || preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $key) !== 1) {
            throw new InvalidArgumentException('La suscripción no contiene claves válidas.');
        }
    }
    $decodedP256dh = astronomyWebPushDecodeBase64Url($p256dh);
    $decodedAuth = astronomyWebPushDecodeBase64Url($auth);
    if ($decodedP256dh === false || strlen($decodedP256dh) !== 65 || ord($decodedP256dh[0]) !== 4 || $decodedAuth === false || strlen($decodedAuth) !== 16) {
        throw new InvalidArgumentException('La suscripción no contiene claves válidas.');
    }

    return ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth];
}

function astronomyWebPushSaveSubscription(PDO $connection, array $subscription, ?string $userAgent): void
{
    $userAgent = is_string($userAgent) && trim($userAgent) !== ''
        ? substr(trim($userAgent), 0, WEB_PUSH_USER_AGENT_MAX_LENGTH)
        : null;
    $statement = $connection->prepare(
        'INSERT INTO web_push_subscriptions (endpoint, endpoint_hash, p256dh, auth, user_agent, active) '
        . 'VALUES (:endpoint, :endpoint_hash, :p256dh, :auth, :user_agent, 1) '
        . 'ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), '
        . 'user_agent = VALUES(user_agent), active = 1, updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'endpoint' => $subscription['endpoint'],
        'endpoint_hash' => hash('sha256', $subscription['endpoint'], true),
        'p256dh' => $subscription['p256dh'],
        'auth' => $subscription['auth'],
        'user_agent' => $userAgent,
    ]);
}

function astronomyWebPushDeactivateSubscription(PDO $connection, string $endpoint): bool
{
    $statement = $connection->prepare(
        'UPDATE web_push_subscriptions SET active = 0, updated_at = CURRENT_TIMESTAMP WHERE endpoint_hash = :endpoint_hash AND endpoint = :endpoint'
    );
    $statement->execute([
        'endpoint_hash' => hash('sha256', $endpoint, true),
        'endpoint' => $endpoint,
    ]);
    return $statement->rowCount() > 0;
}

function astronomyWebPushAdminRows(PDO $connection): array
{
    return $connection->query(
        'SELECT id, active, user_agent, created_at, updated_at, last_success_at, last_error_at, last_error_message '
        . 'FROM web_push_subscriptions ORDER BY id DESC'
    )->fetchAll();
}

function astronomyWebPushNormalizeMessage(mixed $value, int $maximumLength, string $field): string
{
    $value = trim((string) $value);
    if ($value === '' || strlen($value) > $maximumLength || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
        throw new InvalidArgumentException($field . ' no es válido.');
    }
    return $value;
}

function astronomyWebPushNormalizeTargetUrl(mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '' || strlen($value) > 2048 || preg_match('/\s/', $value) === 1) {
        throw new InvalidArgumentException('La URL de apertura no es válida.');
    }
    if (preg_match('#^(?:\./|/)(?!/)[^\s]*$#', $value) === 1) {
        return $value;
    }
    if (filter_var($value, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https') {
        return $value;
    }
    throw new InvalidArgumentException('La URL de apertura no es válida.');
}

function astronomyWebPushSanitizeError(?string $message): string
{
    $message = trim((string) $message);
    $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '';
    if ($message === '' || preg_match('/https?:\/\/|p256dh|auth|private.?key|vapid/i', $message) === 1) {
        return 'Error de envío Web Push';
    }
    return substr($message, 0, 500);
}

function astronomyWebPushSanitizedFailure(object $report): array
{
    $response = method_exists($report, 'getResponse') ? $report->getResponse() : null;
    $status = is_object($response) && method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : null;
    $reason = is_object($response) && method_exists($response, 'getReasonPhrase') ? trim((string) $response->getReasonPhrase()) : '';
    $message = $status !== null
        ? 'HTTP ' . $status . ($reason !== '' ? ' ' . substr($reason, 0, 120) : '')
        : 'Error de envío Web Push';
    $expired = method_exists($report, 'isSubscriptionExpired') && $report->isSubscriptionExpired();
    return ['message' => $message, 'expired' => $expired || in_array($status, [404, 410], true)];
}

function astronomyWebPushSend(
    PDO $connection,
    array $config,
    ?int $subscriptionId,
    string $title,
    string $body,
    string $url,
    array $transportOptions = []
): array {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('La dependencia de envío Web Push no está instalada.');
    }
    require_once $autoload;
    if (!class_exists(\Minishlink\WebPush\WebPush::class)) {
        throw new RuntimeException('La dependencia de envío Web Push no está disponible.');
    }

    $query = 'SELECT id, endpoint, p256dh, auth FROM web_push_subscriptions WHERE active = 1';
    $parameters = [];
    if ($subscriptionId !== null) {
        $query .= ' AND id = :id';
        $parameters['id'] = $subscriptionId;
    }
    $query .= ' ORDER BY id';
    $statement = $connection->prepare($query);
    $statement->execute($parameters);
    $rows = $statement->fetchAll();
    if ($rows === []) {
        throw new InvalidArgumentException('No hay suscripciones activas para el destino elegido.');
    }

    $ttl = filter_var($transportOptions['TTL'] ?? 300, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 2419200]]);
    $urgency = (string) ($transportOptions['urgency'] ?? 'normal');
    if ($ttl === false || !in_array($urgency, ['very-low', 'low', 'normal', 'high'], true)) {
        throw new InvalidArgumentException('Las opciones de transporte Web Push no son válidas.');
    }
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $sender = new \Minishlink\WebPush\WebPush([
        'VAPID' => [
            'subject' => $config['subject'],
            'publicKey' => $config['public_key'],
            'privateKey' => $config['private_key'],
        ],
    ], ['TTL' => $ttl, 'urgency' => $urgency, 'batchSize' => 50, 'contentType' => 'application/json'], 20, [
        \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => false,
    ]);
    $sender->setReuseVAPIDHeaders(true);

    $idsByEndpointHash = [];
    foreach ($rows as $row) {
        $subscription = \Minishlink\WebPush\Subscription::create([
            'endpoint' => $row['endpoint'],
            'keys' => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']],
            'contentEncoding' => 'aes128gcm',
        ]);
        $sender->queueNotification($subscription, $payload);
        $idsByEndpointHash[hash('sha256', (string) $row['endpoint'])] = (int) $row['id'];
    }

    $result = ['sent' => count($rows), 'success' => 0, 'failed' => 0, 'errors' => []];
    foreach ($sender->flush() as $report) {
        $endpoint = (string) $report->getEndpoint();
        $id = $idsByEndpointHash[hash('sha256', $endpoint)] ?? null;
        if (!is_int($id)) {
            continue;
        }
        if ($report->isSuccess()) {
            $update = $connection->prepare(
                'UPDATE web_push_subscriptions SET last_success_at = CURRENT_TIMESTAMP, last_error_at = NULL, last_error_message = NULL WHERE id = :id'
            );
            $update->execute(['id' => $id]);
            $result['success']++;
            continue;
        }
        $failure = astronomyWebPushSanitizedFailure($report);
        $update = $connection->prepare(
            'UPDATE web_push_subscriptions SET active = :active, last_error_at = CURRENT_TIMESTAMP, last_error_message = :message WHERE id = :id'
        );
        $update->execute(['active' => $failure['expired'] ? 0 : 1, 'message' => $failure['message'], 'id' => $id]);
        $result['failed']++;
        $result['errors'][] = ['id' => $id, 'message' => $failure['message']];
    }
    return $result;
}
